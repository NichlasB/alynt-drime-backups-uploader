import test from 'node:test';
import assert from 'node:assert/strict';
import { createWaitWitnessWindow } from '../../scripts/tests/wait-witness-window.mjs';
import { createWaitExecuteCredit } from '../../scripts/tests/wait-witness-credit.mjs';
import { rowQuery,metadataQueries } from '../../scripts/tests/uncertain-barrier-native-statements.mjs';
import { context,line,tuple,ms,armed,triggered,complete,creditFixture,command } from './wait-witness-fixture.mjs';

test('non-vacuous credited execute, quiet gap, exact synthetic wait and closed transcript',()=>{
    const f=triggered();complete(f);assert.equal(f.window.finish(),false);
    assert.throws(()=>complete({window:{beginQuery:()=>false},c:context()}),assert.AssertionError);
});
for(const early of [0,20,100,124])test(`no query at ${early}ms, including legacy/cache boundary; refusal poisons`,()=>{
    const f=triggered();assert.equal(f.window.beginQuery(ms(early)),false);assert.equal(f.window.beginQuery(ms(125)),false);assert.equal(f.window.finish().synthetic_match,false);
});
test('no protected-query model before ARMED or consumed execute credit',()=>{
    for(const f of [{window:createWaitWitnessWindow(context(),0n)},armed()]){assert.equal(f.window.beginQuery(ms(125)),false);assert.equal(f.window.finish().synthetic_match,false);}
});
test('execute does not restart deadline; late trigger, late query/result and clock regression refuse',()=>{
    complete(triggered(createWaitWitnessWindow,context(),ms(2300)),ms(2425));
    const f=triggered(createWaitWitnessWindow,context(),ms(2400));assert.equal(f.window.beginQuery(ms(2525)),false);
    for(const tick of [ms(2500),ms(2501),-1n]){const a=armed(),r=creditFixture().receipt();assert.equal(a.window.feed('parent',line('execute',a.c,{statement:r.statement}),tick,r.receipt),false);}
    const a=triggered();assert.equal(a.window.beginQuery(ms(125)),true);assert.equal(a.window.endQuery(tuple(a.c),ms(2500)),false);
    const b=triggered();assert.equal(b.window.beginQuery(ms(125)),true);assert.equal(b.window.endQuery(null,ms(124)),false);
});
test('quiet gap starts at previous query END and queries cannot overlap',()=>{
    const f=triggered();assert.equal(f.window.beginQuery(ms(125)),true);assert.equal(f.window.endQuery(null,ms(200)),true);assert.equal(f.window.beginQuery(ms(324)),false);
    const g=triggered();assert.equal(g.window.beginQuery(ms(125)),true);assert.equal(g.window.endQuery(null,ms(200)),true);assert.equal(g.window.beginQuery(ms(325)),true);assert.equal(g.window.endQuery(tuple(g.c),ms(326)),true);
    assert.equal(g.window.feed('worker',line('waiting',g.c,{...tuple(g.c),queries:2}),ms(326)),true);
    assert.equal(g.window.feed('parent',line('finish',g.c),ms(327)),true);assert.equal(g.window.feed('worker',line('closed',g.c,{zero_dml:true}),ms(328)),true);assert.equal(g.window.finish().synthetic_match,true);
    const h=triggered();assert.equal(h.window.beginQuery(ms(125)),true);assert.equal(h.window.beginQuery(ms(250)),false);
});
test('synthetic shared-cache model: 20ms polling starves, quiet gap permits visibility; external readers still refuse',()=>{
    const cache=()=>{let read=0n,visible=false;return (at,external=false)=>{if(external)read=at-ms(20);if(at-read>ms(100))visible=true;read=at;return visible;};};
    const legacy=cache();for(let n=20;n<=2400;n+=20)assert.equal(legacy(ms(n)),false);
    const model=cache(),f=triggered();assert.equal(model(ms(100)),false);assert.equal(model(ms(225)),true);complete(f,ms(225));
    const starved=cache(),g=triggered();let count=0;
    for(let n=125;n<2500;n+=125){assert.equal(g.window.beginQuery(ms(n)),true);assert.equal(starved(ms(n),true),false);assert.equal(g.window.endQuery(null,ms(n)),true);count++;}
    assert.equal(count,19);assert.equal(g.window.beginQuery(ms(2500)),false);assert.equal(g.window.finish().synthetic_match,false);
});
for(const key of ['requester','blocker','monitor','table','index','role'])test('wait identity cannot be replaced: '+key,()=>{
    const f=triggered(),t=tuple(f.c);t[key]=typeof t[key]==='number'?999:'foreign';assert.equal(f.window.beginQuery(ms(125)),true);assert.equal(f.window.endQuery(t,ms(125)),false);assert.equal(f.window.finish().synthetic_match,false);
});
test('READY/1205/elapsed time/unqueried tuple, false closure and wrong query counts never suffice',()=>{
    for(const kind of ['ready','waiting','closed']){const f=triggered();assert.equal(f.window.feed('worker',line(kind,f.c,{...tuple(f.c),queries:1}),ms(125)),false);}
    for(const queries of [0,2,125,126]){const f=triggered();assert.equal(f.window.beginQuery(ms(125)),true);assert.equal(f.window.endQuery(tuple(f.c),ms(125)),true);assert.equal(f.window.feed('worker',line('waiting',f.c,{...tuple(f.c),queries}),ms(125)),false);}
    const f=triggered();assert.equal(f.window.beginQuery(ms(125)),true);assert.equal(f.window.endQuery({code:1205},ms(125)),false);
});
test('forged, wrong-root and replayed receipts refuse; one candidate consumes the receipt',()=>{
    const f=armed();assert.equal(f.window.feed('parent',line('execute',f.c,{statement:11}),0n,Object.freeze({})),false);
    const c=context(),r=creditFixture().receipt(),other={...c,root:'d'.repeat(64)},a=armed(createWaitWitnessWindow,other);
    assert.equal(a.window.feed('parent',line('execute',other,{statement:r.statement}),0n,r.receipt),false);
    const b=armed();assert.equal(b.window.feed('parent',line('execute',c,{statement:r.statement}),0n,r.receipt),false);
    const good=creditFixture().receipt(),x=armed(),y=armed();assert.equal(x.window.feed('parent',line('execute',c,{statement:good.statement}),0n,good.receipt),true);assert.equal(y.window.feed('parent',line('execute',c,{statement:good.statement}),0n,good.receipt),false);
});
test('metadata, observation alone, partial/foreign/missing forwarding credit and replays cannot mint receipts',()=>{
    for(const mode of ['observe','partial','foreign','replay','wrong-id','writer','reused']){
        const f=creditFixture();f.pre();const id=f.prepare(rowQuery(f.c.table,0)),p=command(23,mode==='wrong-id'?id+1:id,10),key=Symbol('original');
        if(['wrong-id','writer'].includes(mode)){assert.equal(f.owner.frame(mode==='writer'?'writer':'barrier','client',p,key),false);continue;}
        assert.equal(f.owner.frame('barrier','client',p,key),true);
        if(mode==='observe')assert.equal(f.owner.account('client',key),false);
        else if(mode==='partial')assert.equal(f.owner.forwarded('client',key,p.length-1),false);
        else if(mode==='foreign')assert.equal(f.owner.forwarded('client',Symbol('foreign'),p.length),false);
        else {assert.equal(f.owner.forwarded('client',key,p.length),true);
            if(mode==='replay')assert.equal(f.owner.forwarded('client',key,p.length),false);
            else {const receipt=f.owner.account('client',key);assert.equal(typeof receipt,'object');assert.equal(f.owner.frame('barrier','client',command(25,id,5),key),false);const a=armed();assert.equal(a.window.feed('parent',line('execute',a.c,{statement:id}),0n,receipt),false);}}
    }
});
test('early/expired/malformed/partial triggers burn their receipt and cannot re-arm another window',()=>{
    for(const mode of ['early','expired','malformed','partial']){
        const c=context(),r=creditFixture().receipt(),f=mode==='early'?{window:createWaitWitnessWindow(c,0n)}:armed();
        const b=line('execute',c,{statement:r.statement});
        assert.equal(f.window.feed('parent',mode==='malformed'?Buffer.from('{}\n'):mode==='partial'?b.subarray(0,1):b,mode==='expired'?ms(2500):0n,r.receipt),false);
        const next=armed();assert.equal(next.window.feed('parent',b,0n,r.receipt),false);
    }
});
test('duplicate/mismatched execute and false CLOSED never produce a synthetic success',()=>{
    const f=triggered(),r=creditFixture().receipt();assert.equal(f.window.feed('parent',line('execute',f.c,{statement:r.statement}),0n,r.receipt),false);
    const g=armed(),p=creditFixture().receipt();assert.equal(g.window.feed('parent',line('execute',g.c,{statement:p.statement+1}),0n,p.receipt),false);
    const h=triggered();assert.equal(h.window.beginQuery(ms(125)),true);assert.equal(h.window.endQuery(tuple(h.c),ms(125)),true);
    assert.equal(h.window.feed('worker',line('waiting',h.c,{...tuple(h.c),queries:1}),ms(125)),true);assert.equal(h.window.feed('parent',line('finish',h.c),ms(125)),true);
    assert.equal(h.window.feed('worker',line('closed',h.c,{zero_dml:false}),ms(125)),false);assert.equal(h.window.finish().synthetic_match,false);
});
test('credit projection rejects arbitrary commands and later safety rows cannot issue another receipt',()=>{
    const f=creditFixture();f.pre();assert.equal(f.owner.frame('barrier','client',Buffer.from([2,0,0,0,3,65]),Symbol()),false);
    const g=creditFixture(),r=g.receipt();assert.equal(typeof r.receipt,'object');assert.equal(g.send(command(25,r.statement,5)),true);
    // Follow the real post-safety metadata order; the next row is accepted but cannot issue a receipt.
    for(const sql of metadataQueries)g.cycle(sql);
    const later=g.prepare(rowQuery(g.c.table,1));assert.equal(g.send(command(23,later,10)),true);
});
