// ONE ordered proof. Failure stops; no native retry, mutable profile/SQL/provider argument.
import { verifyCacheContentionIdentity,cacheContentionDigest } from './cache-contention-native-identity.mjs';
import { selectedCleanupProof } from './selected-native-cleanup-proof.mjs';
import { runUncertainNativeCase } from './uncertain-barrier-native-case.mjs';
import { runCacheWitnessCase } from './cache-witness-native-case.mjs';
import { runCacheContentionCase } from './cache-contention-native-case.mjs';
if(process.argv.length!==3||process.argv[2]!=='--confirmed-plugin-tester'||!process.env.MYSQL_PWD||!verifyCacheContentionIdentity())throw Error('contention_explicit_scope');
const started=process.hrtime.bigint(),deadline=started+90000000000n;
const report={passed:false,source:cacheContentionDigest,production_wiring:false,cases:[]};
const budget=()=>{if(process.hrtime.bigint()+28000000000n>=deadline)throw Error('ordered_outer_budget');};
try{
    report.cleanup_prerequisite=await selectedCleanupProof();if(!report.cleanup_prerequisite.passed)throw Error('cleanup_prerequisite');
    budget();report.original_no_fault=await runUncertainNativeCase('no_fault');if(!report.original_no_fault.passed)throw Error('guard_no_fault_prerequisite');
    budget();report.integrated_no_fault=await runCacheWitnessCase();if(!report.integrated_no_fault.passed)throw Error('integrated_no_fault_prerequisite');
    budget();report.selected_control=await runUncertainNativeCase('selected');if(!report.selected_control.passed)throw Error('selected_control');
    for(const profile of ['released','held']){budget();const result=await runCacheContentionCase(profile);report.cases.push(result);if(!result.passed)throw Error('ordered_native_case_failed');}
    report.passed=process.hrtime.bigint()<deadline&&report.cases.length===2&&report.cases.every(r=>r.passed===true);
}catch(e){report.failure=/^[a-z_]{1,48}$/.test(e.message)?e.message:'contention_owned_failure';}
report.elapsed_ms=Number((process.hrtime.bigint()-started)/1000000n);
console.log(JSON.stringify(report));process.exitCode=report.passed?0:1;
