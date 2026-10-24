// Explicit disposable-proof support only. Never imported by plugin/runtime entry points.
import {createServer} from 'node:http';
import {performance} from 'node:perf_hooks';
import {FixtureCgiHandoff} from './http-cgi-handoff.mjs';
import {openCgiRequest} from './cgi-profile-transport.mjs';
import {boundedSettlement} from './selected-native-lifetime.mjs';

export function createFixtureHttpServer(script,cgiPort){
    let origin=null,timer=null,done=false,requestCount=0,transport=null,handoff=null;
    const sockets=new Set(),closures=[],receipt={accepted_connections:0,requests:0,forwarded:0};
    let resolveDone;const completion=new Promise(resolve=>{resolveDone=resolve;});
    const remaining=limit=>origin===null?0:Math.max(0,Math.floor(limit-(performance.now()-origin)));
    const finish=(reason,response=null)=>{
        if(done)return;done=true;clearTimeout(timer);receipt.reason=reason;receipt.response=response;
        receipt.elapsed_ms=origin===null?null:Math.ceil(performance.now()-origin);
        if(handoff)receipt.erased=handoff.cleanup(performance.now());
        for(const socket of sockets)socket.destroy();resolveDone({...receipt});
    };
    const arm=limit=>{clearTimeout(timer);const ms=remaining(limit);if(ms<1){finish('deadline');return false;}timer=setTimeout(()=>finish('deadline'),ms);return true;};
    const server=createServer({maxHeaderSize:4096,insecureHTTPParser:false,requireHostHeader:true,joinDuplicateHeaders:false,
        requestTimeout:2000,headersTimeout:1000,connectionsCheckingInterval:100,keepAliveTimeout:1,highWaterMark:4096});
    // Do not truncate rawHeaders: semantic policy must see every field before rejecting >16.
    server.maxHeadersCount=0;
    const listenerClosed=new Promise(resolve=>server.once('close',()=>resolve(true)));
    server.on('error',()=>finish('server_error'));
    server.on('connection',socket=>{
        receipt.accepted_connections++;sockets.add(socket);
        closures.push(new Promise(resolve=>socket.once('close',()=>{sockets.delete(socket);resolve(true);})));socket.on('error',()=>finish('client_error'));
        if(origin!==null||done){socket.destroy();finish('second_connection');return;}
        origin=performance.now();server.close();socket.once('close',()=>{if(!done)finish('client_closed');});arm(1000);
    });
    for(const event of ['checkContinue','checkExpectation','upgrade','connect'])server.on(event,()=>finish('unsupported_ingress'));
    server.on('clientError',()=>finish('parser_error'));
    server.on('request',(request,response)=>{
        request.on('error',()=>finish('request_error'));request.on('aborted',()=>finish('request_aborted'));
        response.on('error',()=>finish('response_error'));
        receipt.requests=++requestCount;
        if(done||requestCount!==1||origin===null||remaining(1000)<1){finish('request_refused');return;}
        try{
            handoff=new FixtureCgiHandoff({method:request.method,target:request.url,version:request.httpVersion,headers:request.rawHeaders},origin,performance.now(),script);
            receipt.headers_accepted=true;if(!arm(2000))return;
        }catch{finish('headers_refused');return;}
        request.on('data',chunk=>{if(done)return;try{handoff.push(chunk,performance.now());}catch{finish('body_refused');}});
        request.once('end',async()=>{
            if(done)return;
            try{
                handoff.end({complete:request.complete,aborted:request.aborted,error:false},performance.now());
                receipt.parser_complete=request.complete;handoff.prepare(performance.now());const wire=handoff.take(performance.now());
                if(!arm(4000))return;
                transport=openCgiRequest(cgiPort,wire,phase=>remaining(phase==='work'?4000:10000));receipt.forwarded++;
                const result=await transport.completion;if(done)return;
                if(result.kind!=='complete'||result.application_status!==0||result.stderr!==''){
                    receipt.cgi_fatal_observed=typeof result.stderr==='string'&&/Maximum execution time of 1 seconds? exceeded/.test(result.stderr);finish('cgi_refused');return;
                }
                // No general response proxy: only bounded synthetic fixture output is observed.
                finish('complete',result.stdout);
            }catch{finish('handoff_refused');}
        });
        // No HTTP response body is required; completion is the private parent observation.
    });
    return {server,completion,origin:()=>origin,remainingCleanup:()=>remaining(10000),async close(){
        clearTimeout(timer);for(const socket of sockets)socket.destroy();
        if(server.listening)server.close();
        if(transport)await transport.close();
        if(handoff&&!handoff.cleanup(performance.now()))throw Error('http_cleanup_budget');
        const ms=remaining(10000);if(ms<1)throw Error('http_cleanup_budget');
        const joined=await boundedSettlement(Promise.all([listenerClosed,...closures]),Math.min(1000,ms));
        if(!joined.settled||joined.rejected||server.listening||sockets.size!==0)throw Error('http_cleanup');
        if(!done)finish('closed');return {listener_closed:true,sockets_closed:true,remaining_ms:remaining(10000)};
    }};
}
