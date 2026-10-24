// Explicit standalone Windows experiment. No WordPress, SQL or existing service connection.
import {createConnection} from 'node:net';import {readFileSync,writeFileSync,existsSync} from 'node:fs';import path from 'node:path';
import {createHash} from 'node:crypto';import {performance} from 'node:perf_hooks';import {setTimeout as delay} from 'node:timers/promises';
import {createCgiFixture} from './cgi-profile-fixture.mjs';import {createFixtureHttpServer} from './http-cgi-server.mjs';
import {resolveCgi,reserveCgiPort,startCgi,inspectCgi} from './cgi-profile-process.mjs';import {boundedSettlement} from './selected-native-lifetime.mjs';
const sha=v=>createHash('sha256').update(v).digest('hex');
const canonical=v=>JSON.stringify(v,(_k,x)=>x&&typeof x==='object'&&!Array.isArray(x)?Object.fromEntries(Object.keys(x).sort().map(k=>[k,x[k]])):x);
const check=(v,reason)=>{if(!v)throw Error(reason);};
const headers=(length,extra='')=>`POST /probe.php HTTP/1.1\r\nHost: owned.invalid\r\nConnection: close\r\nContent-Length: ${length}\r\nContent-Type: application/x-www-form-urlencoded\r\n${extra}\r\n`;
const cases=['no_fault','refusal','max_body','oversized_body','duplicate_length','transfer_encoding','header_overflow','slow_headers','incomplete_body','disconnect','pipeline','native_fatal'];
async function runCase(mode,cgi){
    const fixture=createCgiFixture(),receipt={mode,passed:false};let owner=null,http=null,client=null,clientClosed=null,clean=false,phase='startup',startup=performance.now(),preflightMs=null;
    const remaining=()=>http?.origin()!==null&&http?.origin()!==undefined?http.remainingCleanup():Math.max(0,Math.floor(10000-(performance.now()-startup)));
    const timeLeft=()=>{const ms=remaining();if(ms<1)throw Error('original_cleanup_budget');return ms;};
    try{
        const port=await reserveCgiPort();owner=startCgi(cgi,fixture.ini,port,fixture.marker,mode==='native_fatal'?'native_timeout':'no_fault',remaining);
        writeFileSync(path.join(fixture.root,'process.json'),JSON.stringify({pid:owner.child.pid,owner:process.pid,port,marker:fixture.marker}),{flag:'wx'});
        await delay(350);phase='identity';await owner.verify();check(owner.alive(),'worker_startup');timeLeft();
        http=createFixtureHttpServer(fixture.script,port);await new Promise((resolve,reject)=>{http.server.once('error',reject);http.server.listen({host:'127.0.0.1',port:0},resolve);});
        const httpPort=http.server.address().port;check(httpPort!==port&&![10002,10003,10004,10005].includes(httpPort),'http_port_scope');
        preflightMs=Math.ceil(performance.now()-startup);check(preflightMs<10000,'startup_budget');phase='http';
        client=createConnection({host:'127.0.0.1',port:httpPort});let clientError=null;client.on('error',e=>{clientError=e.code??'client_error';});client.on('data',()=>{receipt.unexpected_http_output=true;});
        clientClosed=new Promise(resolve=>client.once('close',()=>resolve(true)));
        await new Promise((resolve,reject)=>{client.once('connect',resolve);client.once('error',reject);});
        let body=Buffer.from(mode==='max_body'?`x=${'a'.repeat(16382)}`:'x=1'),wire;
        if(mode==='refusal')wire=Buffer.from(headers(body.length,'X-Forbidden: yes\r\n')+body);
        else if(mode==='oversized_body')wire=Buffer.from(headers(16385));
        else if(mode==='duplicate_length')wire=Buffer.from(headers(3,'Content-Length: 3\r\n')+'x=1');
        else if(mode==='transfer_encoding')wire=Buffer.from(headers(3,'Transfer-Encoding: chunked\r\n')+'0\r\n\r\n');
        else if(mode==='header_overflow')wire=Buffer.from(headers(3,`X-Long: ${'a'.repeat(5000)}\r\n`)+'x=1');
        else if(mode==='slow_headers')wire=Buffer.from('POST /probe.php HTTP/1.1\r\nHost: owned.invalid\r\n');
        else if(mode==='incomplete_body'||mode==='disconnect')wire=Buffer.from(headers(3)+'x');
        else wire=Buffer.concat([Buffer.from(headers(body.length)),body,...(mode==='pipeline'?[Buffer.from('GET /probe.php HTTP/1.1\r\nHost: owned.invalid\r\nConnection: close\r\nContent-Length: 0\r\n\r\n')]:[])]);
        client.write(wire);if(mode==='disconnect'){await delay(25);client.destroy();}
        const settled=await boundedSettlement(http.completion,Math.min(4500,timeLeft()));check(settled.settled&&!settled.rejected,'http_observation_missing');
        const r=settled.value;receipt.observation={reason:r.reason,connections:r.accepted_connections,requests:r.requests,forwarded:r.forwarded,elapsed_ms:r.elapsed_ms,parser_complete:r.parser_complete??false};
        check(!receipt.unexpected_http_output&&r.accepted_connections===1&&r.forwarded<=1,'http_accounting');
        if(['no_fault','max_body'].includes(mode)){
            check(r.reason==='complete'&&r.forwarded===1&&r.parser_complete===true&&r.erased===true,'no_fault_handoff');
            const split=r.response.indexOf('\r\n\r\n');check(split>=0,'fixture_headers');const result=JSON.parse(r.response.slice(split+4));
            check(result.fixture===true&&result.wordpress===false&&result.mysqli===false&&result.sapi==='cgi-fcgi'&&result.bytes===body.length&&result.synthetic_sha256===sha(body)&&result.reads<=17&&result.eof===true&&result.closed===true&&result.seekable===true&&result.execution_limit==='1'&&result.input_limit==='5'&&result.post_limit==='16K'&&result.prepend===''&&!result.scanned_ini,'fixture_response');
            receipt.body_bytes=body.length;receipt.body_conserved=true;receipt.effective_owned_ini=true;
        }else if(mode==='native_fatal'){
            check(r.reason==='cgi_refused'&&r.forwarded===1&&owner.alive()&&/Maximum execution time of 1 seconds? exceeded/.test(owner.privateStderr()),'fatal_is_not_exit');receipt.worker_alive_after_fatal=true;
        }else if(mode==='pipeline')check(r.requests===2&&r.reason!=='complete'&&r.forwarded<=1,'pipeline_accounting');
        else{check(r.reason!=='complete'&&r.forwarded===0&&!existsSync(path.join(fixture.root,'stage.json')),'pre_handoff_refusal');receipt.refusal_before_php_entry=true;}
        if(mode==='slow_headers')check(r.elapsed_ms>=900&&r.elapsed_ms<1500,'header_deadline');
        if(mode==='incomplete_body')check(r.elapsed_ms>=1900&&r.elapsed_ms<2500,'body_deadline');
        if(clientError)receipt.client_error=clientError;phase='cleanup';
    }catch(error){receipt.failure_stage=phase;receipt.reason=/^[a-z_]+$/.test(error.message)?error.message:'private_failure';}
    finally{
        try{
            if(owner){const r=await owner.close();receipt.process_cleanup=r;check(!r.error&&!r.overflow&&r.stdout_bytes===0&&(r.stderr_bytes===0||(mode==='native_fatal'&&/Maximum execution time of 1 seconds? exceeded/.test(owner.privateStderr()))),'unexpected_child_output');}
            if(http)receipt.http_cleanup=await http.close();
            if(client){client.destroy();const r=await boundedSettlement(clientClosed,Math.min(1000,timeLeft()));check(r.settled&&!r.rejected,'client_cleanup');}
            timeLeft();fixture.remove();timeLeft();clean=true;
        }catch(error){receipt.cleanup_unresolved=true;receipt.cleanup_reason=/^[a-z_]+$/.test(error.message)?error.message:'private_cleanup_failure';receipt.retained_root=fixture.root;
            if(client)client.destroy();if(http)http.server.unref();if(owner?.child){owner.child.unref();owner.child.stdin.destroy();owner.child.stdout.destroy();owner.child.stderr.destroy();}}
    }
    receipt.preflight_ms=preflightMs;receipt.total_after_connection_ms=http?.origin()===null||!http?null:Math.ceil(performance.now()-http.origin());receipt.fixtures_removed=clean;
    receipt.passed=clean&&!receipt.failure_stage&&!receipt.cleanup_unresolved;return receipt;
}
async function main(){
    const receipts=[];let before=null,preserved=false,phase='preflight',reason=null;
    try{const cgi=resolveCgi();before=canonical(await inspectCgi('snapshot'));
        for(const mode of cases){phase=mode;const r=await runCase(mode,cgi);receipts.push(r);process.stdout.write(JSON.stringify({event:'owned_http_case',...r})+'\n');check(r.passed,'owned_http_case_failed');}
    }catch(error){reason=/^[a-z_]+$/.test(error.message)?error.message:'operator_preflight_failure';}
    finally{if(before!==null){try{preserved=before===canonical(await inspectCgi('snapshot'));}catch{preserved=false;}}}
    const passed=receipts.length===cases.length&&receipts.every(r=>r.passed)&&preserved;
    process.stdout.write(JSON.stringify({passed,phase,reason,cases:receipts.length,expected_cases:cases.length,existing_service_and_config_identity_preserved:preserved,wordpress:false,database_access:false,production_profile_qualified:false,production_wiring:false})+'\n');if(!passed)process.exitCode=1;
}
if(process.platform!=='win32'||process.argv.length!==3||process.argv[2]!=='--run-owned-http-cgi'){process.stdout.write('{"passed":false,"refused_before_operations":true}\n');process.exitCode=1;}else await main();
