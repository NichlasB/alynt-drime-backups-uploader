// Actual event-boundary source with HTTP/socket/clock doubles. No listening sockets or PHP.
import test from 'node:test';import assert from 'node:assert/strict';import {EventEmitter} from 'node:events';import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../scripts/tests/http-cgi-server.mjs',import.meta.url),'utf8');
const data=s=>`data:text/javascript;base64,${Buffer.from(s).toString('base64')}`;
async function fixture(change=s=>s){
    const key=`http_boundary_${Math.random()}`;globalThis[key]={now:100,forwarded:0,timers:new Map(),serial:0};const state=globalThis[key];
    const http=`import {EventEmitter} from 'node:events';export function createServer(options){const state=globalThis[${JSON.stringify(key)}],s=new EventEmitter();state.options=options;s.listening=true;s.close=()=>{s.listening=false;queueMicrotask(()=>s.emit('close'))};return s;}`;
    const clock=`export const performance={now:()=>globalThis[${JSON.stringify(key)}].now};`;
    const transport=`export function openCgiRequest(port,wire,credit){const s=globalThis[${JSON.stringify(key)}];s.forwarded++;s.borrowed=wire;s.wire=Buffer.from(wire);s.credit=credit;return {completion:Promise.resolve({kind:'complete',application_status:0,stderr:'',stdout:'fixture'}),close:async()=>{s.transportClosed=true}};}`;
    const timers=`const state=globalThis[${JSON.stringify(key)}];const setTimeout=(fn,ms)=>{const id=++state.serial;state.timers.set(id,{fn,ms});return id};const clearTimeout=id=>state.timers.delete(id);`;
    let text=timers+change(source).replace("from 'node:http'",`from '${data(http)}'`).replace("from 'node:perf_hooks'",`from '${data(clock)}'`)
      .replace("from './cgi-profile-transport.mjs'",`from '${data(transport)}'`);
    for(const file of ['http-cgi-handoff.mjs','selected-native-lifetime.mjs'])text=text.replace(`from './${file}'`,`from '${new URL(`../../scripts/tests/${file}`,import.meta.url).href}'`);
    const module=await import(data(text));const owner=module.createFixtureHttpServer(`C:/Temp/alynt-cgi-profile-${'1'.repeat(32)}/probe.php`,25000);
    const socket=()=>{const s=new EventEmitter();s.closed=false;s.destroy=()=>{if(!s.closed){s.closed=true;s.emit('close')}};return s;};
    const client=socket();owner.server.emit('connection',client);
    const request=(overrides={})=>Object.assign(new EventEmitter(),{method:'POST',url:'/probe.php',httpVersion:'1.1',rawHeaders:['Host','owned.invalid','Connection','close','Content-Length','3','Content-Type','application/x-www-form-urlencoded'],complete:true,aborted:false},overrides);
    return {state,owner,client,socket,request,send:r=>owner.server.emit('request',r,new EventEmitter()),clear:()=>delete globalThis[key]};
}
test('fixed standard parser settings preserve full raw headers and refuse reuse',async()=>{const f=await fixture();try{
    assert.equal(f.state.options.maxHeaderSize,4096);assert.equal(f.state.options.insecureHTTPParser,false);assert.equal(f.owner.server.maxHeadersCount,0);assert.equal(f.owner.server.listening,false);
    const other=f.socket();f.owner.server.emit('connection',other);assert.equal((await f.owner.completion).reason,'second_connection');assert.equal(other.closed,true);assert.equal(f.state.forwarded,0);await f.owner.close();
}finally{f.clear();}});
test('valid actual-source composition reaches one handoff and original credits; cleanup joins all owners',async()=>{const f=await fixture();try{
    const r=f.request();f.state.now=1099;f.send(r);r.emit('data',Buffer.from('x=1'));f.state.now=2099;r.emit('end');
    const result=await f.owner.completion;assert.equal(result.reason,'complete');assert.equal(result.forwarded,1);assert.equal(result.parser_complete,true);assert.equal(result.erased,true);assert.equal(f.state.credit('work'),2001);assert.equal(f.state.credit('cleanup'),8001);
    assert.ok(f.state.wire.includes(Buffer.from('x=1')));assert.ok(f.state.borrowed.every(b=>b===0));r.emit('end');assert.equal(f.state.forwarded,1);assert.equal((await f.owner.close()).listener_closed,true);assert.equal(f.state.transportClosed,true);assert.equal(f.client.closed,true);assert.equal(f.state.timers.size,0);
}finally{f.clear();}});
for(const [name,changes] of [['duplicate',{rawHeaders:['Host','owned.invalid','host','owned.invalid']}],['target',{url:'/probe.php?x=1'}],['incomplete',{complete:false}],['aborted',{aborted:true}]])test(`${name} never forwards`,async()=>{const f=await fixture();try{
    const r=f.request(changes);f.send(r);r.emit('data',Buffer.from('x=1'));r.emit('end');assert.notEqual((await f.owner.completion).reason,'complete');assert.equal(f.state.forwarded,0);await f.owner.close();
}finally{f.clear();}});
for(const event of ['checkContinue','checkExpectation','upgrade','connect','clientError'])test(`${event} refuses without handoff`,async()=>{const f=await fixture();try{
    f.owner.server.emit(event);assert.notEqual((await f.owner.completion).reason,'complete');assert.equal(f.state.forwarded,0);await f.owner.close();
}finally{f.clear();}});
test('original header/body cutoffs and disconnect cannot re-arm work',async()=>{for(const kind of ['headers','body','disconnect']){const f=await fixture();try{
    const r=f.request();if(kind==='headers')f.state.now=1100;f.send(r);
    if(kind==='body')f.state.now=2100;if(kind==='disconnect')f.client.destroy();r.emit('data',Buffer.from('x=1'));r.emit('end');
    assert.notEqual((await f.owner.completion).reason,'complete');assert.equal(f.state.forwarded,0);await f.owner.close();
}finally{f.clear();}}});
test('absolute timer and pipelined second request cancel unfinished work',async()=>{for(const kind of ['timer','pipeline']){const f=await fixture();try{
    f.send(f.request());if(kind==='timer'){f.state.now=2100;[...f.state.timers.values()][0].fn();}else f.send(f.request());
    assert.notEqual((await f.owner.completion).reason,'complete');assert.equal(f.state.forwarded,0);await f.owner.close();
}finally{f.clear();}}});
test('expired cleanup cannot report success',async()=>{const f=await fixture();try{
    f.owner.server.emit('clientError');await f.owner.completion;f.state.now=10100;await assert.rejects(f.owner.close(),/http_cleanup_budget/);
}finally{f.clear();}});
test('request/response errors after early header refusal are consumed without rearming',async()=>{const f=await fixture();try{
    const r=f.request({url:'/wrong'}),response=new EventEmitter();f.owner.server.emit('request',r,response);await f.owner.completion;
    assert.doesNotThrow(()=>r.emit('error',Error('owned late error')));assert.doesNotThrow(()=>response.emit('error',Error('owned late response')));assert.equal(f.state.forwarded,0);await f.owner.close();
}finally{f.clear();}});
for(const [name,before,after] of [
    ['parser fact','complete:request.complete','complete:true'],
    ['request reuse','requestCount!==1','false'],
    ['immediate erasure','receipt.erased=handoff.cleanup(performance.now())','receipt.erased=true']
])test(`assertion-killed semantic control: ${name}`,async()=>{
    assert.ok(source.includes(before));const f=await fixture(s=>s.replace(before,after));try{
        let r=f.request(name==='parser fact'?{complete:false}:{});f.send(r);if(name==='request reuse'){r=f.request();f.send(r);}r.emit('data',Buffer.from('x=1'));r.emit('end');
        const result=await f.owner.completion;
        assert.throws(()=>{if(name==='immediate erasure')assert.ok(f.state.borrowed.every(b=>b===0));else assert.notEqual(result.reason,'complete');},{code:'ERR_ASSERTION'});
        await f.owner.close();
    }finally{f.clear();}
});
