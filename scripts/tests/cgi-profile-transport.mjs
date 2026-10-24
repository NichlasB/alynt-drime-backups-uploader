import {createConnection} from 'node:net';import {CgiResponse} from './cgi-profile-codec.mjs';import {boundedSettlement} from './selected-native-lifetime.mjs';
// Caller has just verified the owned listening PID. Destination is never an URL or client site.
export function openCgiRequest(port,bytes,remaining=null){
    if(!Number.isInteger(port)||port<1024||port>65535||[10002,10003,10004,10005].includes(port)||!Buffer.isBuffer(bytes)||bytes.length>18000)throw Error('cgi_transport');
    if(remaining!==null&&typeof remaining!=='function')throw Error('cgi_transport_budget');
    const budget=(phase,cap)=>{const ms=remaining===null?cap:remaining(phase);if(!Number.isInteger(ms)||ms<1)throw Error('cgi_transport_budget');return Math.min(cap,ms);};
    const workMilliseconds=budget('work',4000);
    const parser=new CgiResponse(),socket=createConnection({host:'127.0.0.1',port});let done=false,closed=false,error=false,timer,pending=null;
    const closure=new Promise(resolve=>socket.once('close',()=>{closed=true;resolve(true);}));
    const completion=new Promise(resolve=>{
        const settle=value=>{if(!done){done=true;clearTimeout(timer);resolve(value);}};
        timer=setTimeout(()=>{socket.destroy();settle({kind:'deadline'});},workMilliseconds);
        socket.once('connect',()=>{try{budget('work',4000);socket.write(bytes);}catch{socket.destroy();settle({kind:'deadline'});}});
        socket.on('data',chunk=>{try{if(parser.push(chunk)){pending=parser.finish();socket.end();}}catch(e){error=true;socket.destroy();settle({kind:'invalid',reason:e.message});}});
        socket.on('error',()=>{error=true;settle({kind:'transport_error'});});
        socket.once('close',()=>{closed=true;settle(pending&&!error?{kind:'complete',...pending}:{kind:'incomplete'});});
    });
    return {socket,completion,async close(){clearTimeout(timer);socket.destroy();if(!closed){const r=await boundedSettlement(Promise.all([completion,closure]),budget('cleanup',1000));if(!closed||!r.settled)throw Error('cgi_socket_cleanup');}return {closed:true,error};}};
}
