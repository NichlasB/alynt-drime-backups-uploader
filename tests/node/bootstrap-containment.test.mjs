import test from 'node:test';import assert from 'node:assert/strict';import {spawnSync} from 'node:child_process';import {fileURLToPath} from 'node:url';
import {fixtureTable,validateReceipt} from '../../scripts/tests/bootstrap-containment-owned-proof.mjs';
test('operator refuses missing/wrong flags before SQL or child execution',()=>{
    const script=fileURLToPath(new URL('../../scripts/tests/bootstrap-containment-owned-proof.mjs',import.meta.url));
    for(const args of [[],['--wrong'],['--run-owned-bootstrap','extra']]){const r=spawnSync(process.execPath,[script,...args],{encoding:'utf8',timeout:5000,windowsHide:true});assert.equal(r.status,1);assert.equal(r.stderr,'');assert.deepEqual(JSON.parse(r.stdout),{passed:false,refused_before_operations:true});}
});
test('fixture identifier cannot select application or foreign tables',()=>{assert.equal(fixtureTable('wp_alynt_bootstrap_probe_123456abcdef_'),'wp_alynt_bootstrap_probe_123456abcdef_options');for(const p of ['wp_','wp_alynt_bootstrap_probe_123456ABCDEF_','wp_alynt_bootstrap_probe_123456abcdef_;DROP','',null])assert.throws(()=>fixtureTable(p),/prefix_scope/);});
test('receipt requires real positive and killed controls independently of blocked route',()=>{
    const r={passed:true,profile:'fallback',route_status:'blocked_before_installation_check',route_reached:false,authentication_reached:false,owner_reached:false,request_completed_data_queries:0,request_cached_protected_values:0,request_attempts:['bulk_autoload'],cold_alloptions:true,dispatch_before_execute:true,safe_read_completed:true,prevented_control_queries:5,semantic_control_assertion_killed:true,control_only_completed_canary_reads:2,control_cache_hydrated:true,guarded_option_branch:['bulk_autoload','bulk_all'],native_execution:false,admission:false,production_wiring:false};
    assert.equal(validateReceipt(r,'fallback'),true);
    for(const key of Object.keys(r)){const changed={...r};delete changed[key];assert.equal(validateReceipt(changed,'fallback'),false,key);}
    for(const change of [{safe_read_completed:false},{semantic_control_assertion_killed:false},{control_only_completed_canary_reads:0},{cold_alloptions:false},{request_completed_data_queries:1},{request_cached_protected_values:1},{route_reached:true},{request_attempts:[]},{guarded_option_branch:['bulk_all']},{production_wiring:true}])assert.equal(validateReceipt({...r,...change},'fallback'),false);
});
