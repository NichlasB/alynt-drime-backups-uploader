import test from 'node:test';import assert from 'node:assert/strict';import {execFileSync} from 'node:child_process';import {fileURLToPath} from 'node:url';
import {pipelineRejected} from '../../scripts/tests/http-cgi-owned-proof.mjs';
test('explicit native operator refuses absent/wrong flags before fixture or socket operations',()=>{
    const script=fileURLToPath(new URL('../../scripts/tests/http-cgi-owned-proof.mjs',import.meta.url));
    for(const args of [[],['--wrong'],['--run-owned-http-cgi','--extra']]){let caught;try{execFileSync(process.execPath,[script,...args],{encoding:'utf8',timeout:5000,windowsHide:true,stdio:['ignore','pipe','pipe']});}catch(error){caught=error;}
    assert.equal(caught.status,1);assert.equal(caught.stderr,'');assert.deepEqual(JSON.parse(caught.stdout),{passed:false,refused_before_operations:true});}
});
test('pipeline observation accepts only exact parser refusal or guarded second-event refusal',()=>{
    assert.equal(pipelineRejected({reason:'parser_error',requests:1,forwarded:0}),true);
    for(const forwarded of [0,1])assert.equal(pipelineRejected({reason:'request_refused',requests:2,forwarded}),true);
    for(const reason of ['complete','client_closed','request_error','deadline','parser_error','request_refused'])for(const requests of [0,1,2,3])for(const forwarded of [-1,0,1,2]){
        const exact=(reason==='parser_error'&&requests===1&&forwarded===0)||(reason==='request_refused'&&requests===2&&[0,1].includes(forwarded));
        if(!exact)assert.equal(pipelineRejected({reason,requests,forwarded}),false,JSON.stringify({reason,requests,forwarded}));
    }
    for(const value of [null,undefined,0,{},'parser_error'])assert.equal(pipelineRejected(value),false);
});
