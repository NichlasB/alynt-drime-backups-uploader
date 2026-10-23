import {createConnection} from 'node:net';import {CgiResponse} from './cgi-profile-codec.mjs';import {boundedSettlement} from './selected-native-lifetime.mjs';
// Caller has just verified the owned listening PID. Destination is never an URL or client site.
export function openCgiRequest(port,bytes){
    if(!Number.isInteger(port)||port<1024||port>65535||[10002,10003,10004,10005].includes(port)||!Buffer.isBuffer(bytes)||bytes.length>18000)throw Error('cgi_transport');
    const parser=new CgiResponse(),socket=createConnection({host:'127.0.0.1',port});let done=false,closed=false,error=false,timer,pending=null;
    const closure=new Promise(resolve=>socket.once('close',()=>{closed=true;resolve(true);}));
    const completion=new Promise(resolve=>{
        const settle=value=>{if(!done){done=true;clearTimeout(timer);resolve(value);}};
        timer=setTimeout(()=>{socket.destroy();settle({kind:'deadline'});},4000);
        socket.once('connect',()=>socket.write(bytes));
        socket.on('data',chunk=>{try{if(parser.push(chunk)){pending=parser.finish();socket.end();}}catch(e){error=true;socket.destroy();settle({kind:'invalid',reason:e.message});}});
        socket.on('error',()=>{error=true;settle({kind:'transport_error'});});
        socket.once('close',()=>{closed=true;settle(pending&&!error?{kind:'complete',...pending}:{kind:'incomplete'});});
    });
    return {socket,completion,async close(){clearTimeout(timer);socket.destroy();const r=await boundedSettlement(Promise.all([completion,closure]),1000);if(!closed||!r.settled)throw Error('cgi_socket_cleanup');return {closed:true,error};}};
}
