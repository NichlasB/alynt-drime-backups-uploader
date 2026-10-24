import test from 'node:test';import assert from 'node:assert/strict';import {execFileSync} from 'node:child_process';import {fileURLToPath} from 'node:url';
test('explicit native operator refuses absent/wrong flags before fixture or socket operations',()=>{
    const script=fileURLToPath(new URL('../../scripts/tests/http-cgi-owned-proof.mjs',import.meta.url));
    for(const args of [[],['--wrong'],['--run-owned-http-cgi','--extra']]){let caught;try{execFileSync(process.execPath,[script,...args],{encoding:'utf8',timeout:5000,windowsHide:true,stdio:['ignore','pipe','pipe']});}catch(error){caught=error;}
    assert.equal(caught.status,1);assert.equal(caught.stderr,'');assert.deepEqual(JSON.parse(caught.stdout),{passed:false,refused_before_operations:true});}
});
