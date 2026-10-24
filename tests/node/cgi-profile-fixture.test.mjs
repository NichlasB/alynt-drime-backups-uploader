import test from 'node:test';import assert from 'node:assert/strict';import {readFileSync,writeFileSync,existsSync,unlinkSync,mkdirSync,rmdirSync} from 'node:fs';import path from 'node:path';
import {createCgiFixture} from '../../scripts/tests/cgi-profile-fixture.mjs';
// Owned temporary filesystem only; no PHP/process/network/WordPress operation.
test('owned fixture removes exactly its bounded files and verifies absence',()=>{const f=createCgiFixture();assert.match(f.marker,/^[a-f0-9]{32}$/);assert.equal(Object.isFrozen(f),true);assert.equal(f.remove(),true);assert.equal(existsSync(f.root),false);});
for(const name of ['owner.json','php.ini','probe.php'])test(`tampered ${name} cannot partially erase the fixture`,()=>{const f=createCgiFixture(),target=path.join(f.root,name),before=readFileSync(target);try{
    writeFileSync(target,'tampered');assert.throws(()=>f.remove());assert.equal(existsSync(f.script),true);assert.equal(existsSync(path.join(f.root,'owner.json')),true);
}finally{writeFileSync(target,before);f.remove();}});
test('unknown files and directories block all deletion until owned test restoration',()=>{const f=createCgiFixture(),extra=path.join(f.root,'unexpected'),dir=path.join(f.root,'stage.json');try{
    writeFileSync(extra,'owned-test');assert.throws(()=>f.remove(),/cleanup_inventory/);assert.equal(existsSync(f.script),true);unlinkSync(extra);
    mkdirSync(dir);assert.throws(()=>f.remove(),/cleanup_file/);assert.equal(existsSync(f.script),true);rmdirSync(dir);
}finally{if(existsSync(extra))unlinkSync(extra);if(existsSync(dir))rmdirSync(dir);f.remove();}});
test('oversized allowed-name diagnostic blocks all deletion',()=>{const f=createCgiFixture(),target=path.join(f.root,'diagnostic.json');try{writeFileSync(target,Buffer.alloc(16384));assert.throws(()=>f.remove(),/cleanup_file/);assert.equal(existsSync(f.script),true);}finally{unlinkSync(target);f.remove();}});
