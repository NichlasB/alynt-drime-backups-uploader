import test from 'node:test';import assert from 'node:assert/strict';import {spawnSync} from 'node:child_process';import {fileURLToPath} from 'node:url';
import {names,workerEnvironment,grantShape,value} from '../../scripts/tests/projection-owned-proof.mjs';
test('SQL value encoding keeps empty strings valid and quote/control/UTF8 bytes inert',()=>{
    assert.equal(value(''),"_utf8mb4 X''");assert.equal(value("';DROP\n"),"_utf8mb4 X'273b44524f500a'");assert.equal(value('é'),"_utf8mb4 X'c3a9'");for(const v of [null,{},1])assert.throws(()=>value(v),/sql_value_type/);
    assert.doesNotMatch(value('siteurl'),/CONVERT|COLLATE/); // Literal coercibility4: column collation wins, no session/schema mutation.
});
test('definer effective grants reject delegation,global writes,roles and foreign/extra tables',()=>{
    const usage="GRANT USAGE ON *.* TO 'owned'@'127.0.0.1'",select="GRANT SELECT ON `local`.`owned_options` TO 'owned'@'127.0.0.1'";
    assert.equal(grantShape(usage+'\n'+select,['owned_options']),true);
    for(const text of [usage+' WITH GRANT OPTION\n'+select,usage+'\n'+select+' WITH GRANT OPTION',usage+'\n'+select+'\nGRANT `role` TO `owned`',usage+'\n'+select.replace('owned_options','wp_options'),usage+'\n'+select.replace('SELECT','SELECT, UPDATE'),usage+'\n'+select+'\n'+select])assert.equal(grantShape(text,['owned_options']),false);
});
test('private worker environment excludes inherited root/database/cloud credentials',()=>{
    const env=workerEnvironment({SystemRoot:'C:/Windows',WINDIR:'C:/Windows',MYSQL_PWD:'root-secret',DB_PASSWORD:'other-secret',AWS_SECRET_ACCESS_KEY:'secret',PATH:'untrusted'});
    assert.deepEqual(env,{SystemRoot:'C:/Windows',WINDIR:'C:/Windows',PATH:'C:/Windows/System32'});assert.doesNotMatch(JSON.stringify(env),/secret|untrusted|MYSQL/);
});
test('owned names require canonical fixed-width seed and cannot select real accounts/tables',()=>{
    assert.deepEqual(names('123456abcdef'),{prefix:'wp_alynt_ro_123456abcdef_',base:'wp_alynt_ro_123456abcdef_src_',invoker:'alynt_ro_123456abcdef_i',definer:'alynt_ro_123456abcdef_d'});
    for(const seed of [null,{},'', 'root','123456ABCDEF','123456abcdef\n',"123456abcdef';DROP",'123456abcdef0'])assert.throws(()=>names(seed),/name_scope/);
});
test('operator refuses absent/wrong flag before site,SQL,account or process work',()=>{
    const script=fileURLToPath(new URL('../../scripts/tests/projection-owned-proof.mjs',import.meta.url));
    for(const args of [[],['--wrong'],['--run-owned-projection','extra']]){const r=spawnSync(process.execPath,[script,...args],{encoding:'utf8',timeout:5000,windowsHide:true});assert.equal(r.status,1);assert.equal(r.stderr,'');assert.deepEqual(JSON.parse(r.stdout),{passed:false,refused_before_operations:true});}
});
