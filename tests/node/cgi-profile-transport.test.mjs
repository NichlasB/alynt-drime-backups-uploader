// Actual transport source with an EventEmitter socket, never opens a connection.
import test from 'node:test';import assert from 'node:assert/strict';import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../scripts/tests/cgi-profile-transport.mjs',import.meta.url),'utf8');
const data=text=>`data:text/javascript;base64,${Buffer.from(text).toString('base64')}`;
async function fixture(){const key=`cgi_socket_${Math.random()}`;globalThis[key]={};const state=globalThis[key];
    const mock=`import {EventEmitter} from 'node:events';export function createConnection(options){const s=new EventEmitter();const state=globalThis[${JSON.stringify(key)}];state.socket=s;state.writes=0;s.write=()=>{state.writes++};s.end=()=>{};s.destroy=()=>{state.destroyed=true;queueMicrotask(()=>s.emit('close'))};return s;}`;
    const text=source.replace("from 'node:net'",`from '${data(mock)}'`).replace("from './cgi-profile-codec.mjs'",`from '${new URL('../../scripts/tests/cgi-profile-codec.mjs',import.meta.url).href}'`).replace("from './selected-native-lifetime.mjs'",`from '${new URL('../../scripts/tests/selected-native-lifetime.mjs',import.meta.url).href}'`);
    return {module:await import(data(text)),state,clear:()=>delete globalThis[key]};
}
const end=Buffer.from([1,3,0,1,0,8,0,0,0,0,0,0,0,0,0,0]);
test('END is not success until socket closure; subsequent record fragments poison',async()=>{const f=await fixture();try{const r=f.module.openCgiRequest(25000,Buffer.alloc(0));let settled=false;r.completion.then(()=>{settled=true});f.state.socket.emit('data',end);await Promise.resolve();assert.equal(settled,false);f.state.socket.emit('data',Buffer.from([1]));assert.equal((await r.completion).kind,'invalid');assert.equal((await r.close()).closed,true);}finally{f.clear();}});
test('complete response plus actual closure succeeds, incomplete closure refuses',async()=>{for(const complete of [true,false]){const f=await fixture();try{const r=f.module.openCgiRequest(25000,Buffer.alloc(0));if(complete)f.state.socket.emit('data',end);f.state.socket.emit('close');assert.equal((await r.completion).kind,complete?'complete':'incomplete');assert.equal((await r.close()).closed,true);}finally{f.clear();}}});
test('reserved/oversized port or body refuses before socket creation',async()=>{const f=await fixture();try{for(const p of [1,65536,10002,10003,10004,10005])assert.throws(()=>f.module.openCgiRequest(p,Buffer.alloc(0)));assert.throws(()=>f.module.openCgiRequest(25000,Buffer.alloc(18001)));assert.equal(f.state.socket,undefined);}finally{f.clear();}});

test('invalid original work credit refuses before socket creation',async()=>{const f=await fixture();try{
    for(const value of [0,-1,NaN,Infinity,1.5,'100'])assert.throws(()=>f.module.openCgiRequest(25000,Buffer.alloc(1),()=>value),/cgi_transport_budget/);
    assert.throws(()=>f.module.openCgiRequest(25000,Buffer.alloc(1),1),/cgi_transport_budget/);assert.equal(f.state.socket,undefined);
}finally{f.clear();}});
test('expiry while connecting cannot forward bytes or renew the original timer',async()=>{const f=await fixture();try{
    let now=0;const phases=[];const r=f.module.openCgiRequest(25000,Buffer.alloc(1),phase=>{phases.push(phase);return phase==='work'?100-now:1000;});
    now=100;f.state.socket.emit('connect');assert.equal((await r.completion).kind,'deadline');assert.equal(f.state.writes,0);assert.equal(f.state.destroyed,true);await r.close();assert.deepEqual(phases,['work','work']);
}finally{f.clear();}});
test('valid credit forwards once and cleanup samples separate remaining epoch',async()=>{const f=await fixture();try{
    const phases=[];const r=f.module.openCgiRequest(25000,Buffer.alloc(1),phase=>{phases.push(phase);return 1000;});
    f.state.socket.emit('connect');assert.equal(f.state.writes,1);assert.equal((await r.close()).closed,true);assert.deepEqual(phases,['work','work','cleanup']);
}finally{f.clear();}});
test('expired cleanup destroys owned socket but never reports an unresolved join as success',async()=>{const f=await fixture();try{
    const r=f.module.openCgiRequest(25000,Buffer.alloc(1),phase=>phase==='work'?1000:0);await assert.rejects(r.close(),/cgi_transport_budget/);assert.equal(f.state.destroyed,true);assert.equal((await r.completion).kind,'incomplete');
}finally{f.clear();}});
