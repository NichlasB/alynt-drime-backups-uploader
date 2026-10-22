import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createBarrierProgress, validBarrierWorkerProgress } from '../../scripts/tests/uncertain-barrier-progress.mjs';
import { createBarrierStatementRoles, rowQuery } from '../../scripts/tests/uncertain-barrier-native-statements.mjs';
const query=s=>Buffer.concat([Buffer.from([3]),Buffer.from(s)]);
test('fixed passive stages distinguish no BEGIN, BEGIN and first safety execution without private payload',()=>{
    const p=createBarrierProgress();assert.equal(p.summary().begin_seen,false);
    p.frame('client',0,query('START TRANSACTION READ WRITE'),{});assert.equal(p.summary().begin_seen,true);
    const sql=rowQuery('wp_alynt_core_probe_aaaaaaaaaaaa_options',0);
    p.frame('client',0,Buffer.concat([Buffer.from([22]),Buffer.from(sql)]),{});
    p.frame('client',0,Buffer.from([23,1,0,0,0,0,1,0,0,0]),{live_kind:'row',rows_closed:0});
    p.frame('server',1,Buffer.from([255,181,4,...Buffer.from('PRIVATE_SQL_TOKEN')]),{});
    assert.deepEqual(p.summary(),{diagnostic_only:true,last_command:'safety_execute',frames_seen:4,begin_seen:true,safety_prepare_seen:true,safety_execute_seen:true,server_error_seen:true,server_error_code:1205,inconclusive:false});
    assert.equal(JSON.stringify(p.summary()).includes('PRIVATE'),false);assert.equal(JSON.stringify(p.summary()).includes('aaaaaaaa'),false);
});
test('unknown queries, foreign row prepares and metadata executes cannot invent safety or numeric error evidence',()=>{
    const p=createBarrierProgress();p.frame('client',0,query('PRIVATE_SQL_TOKEN'),{});
    p.frame('client',0,Buffer.concat([Buffer.from([22]),Buffer.from(rowQuery('wp_alynt_core_probe_aaaaaaaaaaaa_options',1))]),{});
    p.frame('client',0,Buffer.from([23]),{live_kind:'metadata',rows_closed:0});
    p.frame('server',1,Buffer.from([255,254,255]),{});
    assert.equal(p.summary().last_command,'none');assert.equal(p.summary().safety_execute_seen,false);assert.equal(p.summary().server_error_code,0);
    p.frame('other',0,Buffer.alloc(0),{});assert.equal(p.summary().inconclusive,true);assert.equal(JSON.stringify(p.summary()).includes('PRIVATE'),false);
});
test('diagnostic projection never changes original statement-role acceptance or exports statement IDs',()=>{
    const s=createBarrierStatementRoles(),before=s.diagnostic();assert.equal(s.ready(),false);assert.equal(before.live_kind,'none');
    const p=createBarrierProgress();p.frame('client',0,query('START TRANSACTION READ WRITE'),s.diagnostic());
    assert.deepEqual(s.diagnostic(),before);assert.equal(s.ready(),false);assert.equal(s.bind(),false);
    assert.deepEqual(Object.keys(s.diagnostic()),['bound','failed','metadata_closed','rows_closed','pending_kind','live_kind','live_executed']);
});
test('post-root worker projection has exact bounded primitive keys, not handles or arbitrary phase text',()=>{
    const v={phase:'idle',reads:0,metadata:1,zero_dml:0};assert.equal(validBarrierWorkerProgress(v),true);
    for(const bad of [null,{...v,phase:'private-SQL'},{...v,reads:11},{...v,reads:'0'},{...v,metadata:3},{...v,handle:{}},{...v,zero_dml:-1}])assert.equal(validBarrierWorkerProgress(bad),false);
});
test('diagnostic overflow is inconclusive without changing native policy or retaining input bytes',()=>{
    const p=createBarrierProgress();for(let n=0;n<4097;n++)p.frame('server',1,Buffer.from([0]),{});
    assert.equal(p.summary().inconclusive,true);assert.equal(p.summary().frames_seen,4097);assert.equal(p.summary().begin_seen,false);
});
test('actual registry wires diagnostics before statement validation and retains all original acceptance predicates',()=>{
    const s=readFileSync(new URL('../../scripts/tests/uncertain-barrier-native-registry.mjs',import.meta.url),'utf8');
    assert.ok(s.indexOf('r.progress.frame(')<s.indexOf('!r.statements.accept('));
    assert.match(s,/barrier_progress:barrier\?\.progress\?\.summary\(\)\?\?null/);
    const owner=readFileSync(new URL('../../scripts/tests/uncertain-barrier-native-case.mjs',import.meta.url),'utf8');
    assert.match(owner,/if \(!result\.native_passed/);assert.match(owner,/result\.native_passed = !watched && resolved\(state\) && channel\.passed && witness\.passed && transport\.passed/);
});
