import test from 'node:test';import assert from 'node:assert/strict';
import {HttpCgiBudget} from '../../scripts/tests/http-cgi-budget.mjs';
test('one original epoch spans ordered headers/body/handoff/cleanup without renewed budgets',()=>{
    const b=new HttpCgiBudget(100.25);assert.equal(b.remaining('headers',200.25),900);
    assert.equal(b.remaining('body',1600.25),500);assert.equal(b.remaining('handoff',3100.25),1000);
    assert.equal(b.remaining('cleanup',5100.25),5000);assert.equal(b.remaining('cleanup',10100.25),0);
});
for(const [phase,offset,steps]of [['headers',1000,[]],['body',2000,['headers']],['handoff',4000,['headers','body']]]){
    test(`${phase} refuses at original cutoff and cannot retry; cleanup remains separately bounded`,()=>{
        const b=new HttpCgiBudget(123);for(const step of steps)assert.equal(b.check(step,123),true);
        assert.equal(b.check(phase,123+offset),false);assert.equal(b.check(phase,123+offset),false);
        assert.equal(b.check('cleanup',123+offset),true);assert.equal(b.remaining('cleanup',10123),0);
    });
}
test('phase skip/regression and cleanup-to-work attempts poison work',()=>{for(const flow of [['body'],['headers','handoff'],['headers','body','headers'],['cleanup','headers']]){
    const b=new HttpCgiBudget(0);for(const phase of flow.slice(0,-1))assert.equal(b.check(phase,0),true);
    assert.equal(b.check(flow.at(-1),0),false);assert.equal(b.check('headers',0),false);
}});
test('clock regression, nonfinite and coercion inputs refuse without running hooks',()=>{let calls=0;const hostile={valueOf(){calls++;throw Error('coercion')},toString(){calls++;throw Error('coercion')}};
    for(const value of [-1,NaN,Infinity,'0',null,hostile,Number.MAX_SAFE_INTEGER])assert.throws(()=>new HttpCgiBudget(value));
    for(const value of [-1,NaN,Infinity,'0',null,hostile]){const b=new HttpCgiBudget(10);assert.equal(b.check('headers',value),false);assert.equal(b.check('headers',10),false);assert.equal(b.check('cleanup',11),true);}
    const b=new HttpCgiBudget(10);assert.equal(b.check('headers',20),true);assert.equal(b.check('body',19),false);assert.equal(b.check('body',21),false);assert.equal(calls,0);
});
test('unknown/hostile phase cannot invoke coercion or enable an operation',()=>{const b=new HttpCgiBudget(0);assert.equal(b.check({toString(){throw Error('hook')}},0),false);assert.equal(b.check('headers',0),false);assert.equal(b.check('cleanup',0),true);});
