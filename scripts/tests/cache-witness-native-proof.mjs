// ONE fresh no-fault integration, after original genuine guard / no-fault prerequisites.
import { verifyCacheWitnessIdentity,cacheWitnessDigest } from './cache-witness-native-identity.mjs';
import { selectedCleanupProof } from './selected-native-cleanup-proof.mjs';
import { runUncertainNativeCase } from './uncertain-barrier-native-case.mjs';
import { runCacheWitnessCase } from './cache-witness-native-case.mjs';
if(process.argv.length!==3||process.argv[2]!=='--confirmed-plugin-tester'||!verifyCacheWitnessIdentity())throw Error('cache_explicit_scope');
const report={passed:false,source:cacheWitnessDigest,response_suppression:false,contention:false,production_wiring:false,actual_wait:false};
try{
    report.cleanup_prerequisite=await selectedCleanupProof();
    if(!report.cleanup_prerequisite.passed)throw Error('cache_cleanup_prerequisite');
    report.no_fault_prerequisite=await runUncertainNativeCase('no_fault');
    if(!report.no_fault_prerequisite.passed)throw Error('cache_no_fault_prerequisite');
    report.integration=await runCacheWitnessCase();
    report.passed=report.integration.passed===true;
}catch(e){report.failure=/^[a-z_]{1,48}$/.test(e.message)?e.message:'cache_owned_failure';}
console.log(JSON.stringify(report));
process.exitCode=report.passed?0:1;
