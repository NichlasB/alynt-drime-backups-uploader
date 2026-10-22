// Synthetic packets and one virtual epoch only; never boots a native worker.
import assert from 'node:assert/strict';
import { waitDomain, nativeBase } from '../../scripts/tests/wait-witness-contract.mjs';
import { createWaitExecuteCredit } from '../../scripts/tests/wait-witness-credit.mjs';
import { createWaitWitnessWindow } from '../../scripts/tests/wait-witness-window.mjs';
import { metadataQueries, rowQuery } from '../../scripts/tests/uncertain-barrier-native-statements.mjs';
export const ms = n => BigInt(n) * 1000000n;
export const context = () => ({attempt:'a'.repeat(64),root:'b'.repeat(64),source:nativeBase,roles:{auth:101,capture:102,writer:103,barrier:104,blocker:105,monitor:106},table:'wp_alynt_core_probe_0123456789ab_options'});
export const tuple = c => ({requester:c.roles.barrier,blocker:c.roles.blocker,monitor:c.roles.monitor,table:c.table,index:'option_name',role:'safety'});
export const line = (kind,c,more={}) => Buffer.from(JSON.stringify({domain:waitDomain,v:2,kind,context:c,...more})+'\n');
export function packet(body,seq=0) { const h=Buffer.alloc(4);h.writeUIntLE(body.length,0,3);h[3]=seq;return Buffer.concat([h,body]); }
export const command = (code,id,size) => { const b=Buffer.alloc(size);b[0]=code;b.writeUInt32LE(id,1);return packet(b); };
export function creditFixture(factory=createWaitExecuteCredit,c=context()) {
    const owner=factory(c);let id=10;
    const send=(p,d='client')=>{const credit=Symbol('original-synthetic-credit');assert.equal(owner.frame('barrier',d,p,credit),true);assert.equal(owner.forwarded(d,credit,p.length),true);return owner.account(d,credit);};
    const prepare=(sql,n=++id)=>{assert.equal(send(packet(Buffer.concat([Buffer.from([22]),Buffer.from(sql)]))),true);const b=Buffer.alloc(12);b.writeUInt32LE(n,1);assert.equal(send(packet(b,1),'server'),true);return n;};
    const cycle=sql=>{const n=prepare(sql);assert.equal(send(command(23,n,10)),true);assert.equal(send(command(25,n,5)),true);};
    const pre=()=>{for(const sql of metadataQueries)cycle(sql);assert.equal(owner.bind(),true);};
    const receipt=()=>{pre();const n=prepare(rowQuery(c.table,0));return {receipt:send(command(23,n,10)),statement:n};};
    return {owner,send,prepare,cycle,pre,receipt,c};
}
export function armed(factory=createWaitWitnessWindow,c=context(),prepared=0n) {
    const window=factory(c,prepared);
    assert.equal(window.feed('worker',line('hello',c,{thread:c.roles.monitor}),prepared),true);
    assert.equal(window.feed('parent',line('arm',c),prepared),true);
    assert.equal(window.feed('worker',line('armed',c,{zero_dml:true}),prepared),true);
    return {window,c};
}
export function triggered(factory=createWaitWitnessWindow,c=context(),tick=0n,prepared=0n) {
    const f=armed(factory,c,prepared),r=creditFixture(createWaitExecuteCredit,c).receipt();
    assert.equal(f.window.feed('parent',line('execute',c,{statement:r.statement}),tick,r.receipt),true);
    return f;
}
export function complete(f,tick=ms(125),width=4096) {
    assert.equal(f.window.beginQuery(tick),true);assert.equal(f.window.endQuery(tuple(f.c),tick),true);
    for(const [d,b] of [['worker',line('waiting',f.c,{...tuple(f.c),queries:1})],['parent',line('finish',f.c)],['worker',line('closed',f.c,{zero_dml:true})]])for(let n=0;n<b.length;n+=width)assert.equal(f.window.feed(d,b.subarray(n,n+width),tick),true);
    const r=f.window.finish();assert.equal(r.synthetic_match,true);
    for(const [key,value] of Object.entries(r))if(key!=='synthetic_match')assert.equal(value,false);
    return r;
}
