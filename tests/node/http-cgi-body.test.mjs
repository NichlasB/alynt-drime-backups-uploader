import test from 'node:test';import assert from 'node:assert/strict';
import {FixtureHttpBody} from '../../scripts/tests/http-cgi-body.mjs';
const head=(length,method='POST')=>({method,target:'/probe.php',version:'1.1',headers:['Host','owned.invalid','Connection','close','Content-Length',String(length),...(method==='POST'?['Content-Type','application/x-www-form-urlencoded']:[])]});
const eof=()=>({complete:true,aborted:false,error:false});
for(const width of [1,7,1024,16384])test(`positive max body conserved at fragment width ${width}, with original epoch`,()=>{
    const source=Buffer.alloc(16384,97),b=new FixtureHttpBody(head(source.length),100,101);
    for(let i=0;i<source.length;i+=width)assert.equal(b.push(source.subarray(i,i+width),102),true);
    assert.equal(b.end(eof(),2099),true);const r=b.take(4099);assert.equal(r.method,'POST');assert.deepEqual(r.body,source);assert.ok(Object.isFrozen(r));assert.equal(b.cleanup(10099),true);assert.throws(()=>b.take(10099));
});
test('zero POST and GET complete without a fabricated data event',()=>{for(const method of ['POST','GET']){const b=new FixtureHttpBody(head(0,method),0,0);assert.equal(b.end(eof(),1),true);const r=b.take(2);assert.equal(r.body.length,0);assert.equal(r.method,method);assert.equal(b.cleanup(3),true);}});
test('input mutation after push cannot change the copied body; take is one-shot',()=>{const chunk=Buffer.from('x=1'),b=new FixtureHttpBody(head(3),0,0);b.push(chunk,1);chunk.fill(0);b.end(eof(),2);assert.equal(b.take(3).body.toString(),'x=1');assert.throws(()=>b.take(3));});
test('overrun, empty/type-confused chunk or pre-end take permanently consumes the attempt',()=>{
    for(const chunk of [Buffer.alloc(4),Buffer.alloc(0),'x=1',null]){const b=new FixtureHttpBody(head(3),0,0);assert.throws(()=>b.push(chunk,1));assert.throws(()=>b.push(Buffer.from('x=1'),1));assert.throws(()=>b.end(eof(),2));assert.equal(b.cleanup(3),true);}
    const b=new FixtureHttpBody(head(3),0,0);assert.throws(()=>b.take(0));assert.throws(()=>b.push(Buffer.from('x=1'),1));
});
test('truncated body, false/missing/truthy/extra/accessor parser facts refuse and erase',()=>{
    const cases=[{complete:false,aborted:false,error:false},{complete:true,aborted:true,error:false},{complete:true,aborted:false,error:true},{complete:1,aborted:false,error:false},{complete:true,aborted:false}, {...eof(),extra:1},null];
    let calls=0;const accessor=eof();Object.defineProperty(accessor,'complete',{get(){calls++;return true}});cases.push(accessor);
    for(const facts of cases){const b=new FixtureHttpBody(head(3),0,0);b.push(Buffer.from('x=1'),1);assert.throws(()=>b.end(facts,2));assert.throws(()=>b.take(3));assert.equal(b.cleanup(4),true);}
    const b=new FixtureHttpBody(head(3),0,0);b.push(Buffer.from('x='),1);assert.throws(()=>b.end(eof(),2));assert.throws(()=>b.take(3));assert.equal(calls,0);
});
test('exact body/header/handoff cutoffs and clock regression cannot reset original origin',()=>{
    assert.throws(()=>new FixtureHttpBody(head(3),100,1100));
    for(const stage of ['push','end','take']){const b=new FixtureHttpBody(head(3),100,101);if(stage!=='push')b.push(Buffer.from('x=1'),102);if(stage==='take')b.end(eof(),103);
        assert.throws(()=>stage==='push'?b.push(Buffer.from('x=1'),2100):stage==='end'?b.end(eof(),2100):b.take(4100));assert.throws(()=>b.take(4101));assert.equal(b.cleanup(10100),false);}
    const b=new FixtureHttpBody(head(3),100,110);assert.throws(()=>b.push(Buffer.from('x=1'),109));assert.throws(()=>b.push(Buffer.from('x=1'),111));assert.equal(b.cleanup(120),true);
});
test('end then extra bytes, repeated end and post-cleanup data never hand off',()=>{for(const after of ['push','end','cleanup']){const b=new FixtureHttpBody(head(3),0,0);b.push(Buffer.from('x=1'),1);b.end(eof(),2);
    if(after==='cleanup')assert.equal(b.cleanup(3),true);else assert.throws(()=>after==='push'?b.push(Buffer.from('x'),3):b.end(eof(),3));assert.throws(()=>b.take(4));}});
