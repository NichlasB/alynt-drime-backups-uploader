// Exactly one ordered invocation of each approved case. Never retries a native failure.
import { runUncertainNativeCase } from './uncertain-barrier-native-case.mjs';
import { selectedCleanupProof } from './selected-native-cleanup-proof.mjs';
import { verifyUncertainNativeIdentity } from './uncertain-barrier-native-identity.mjs';
if(process.argv.length!==3||process.argv[2]!=='--confirmed-plugin-tester'||!process.env.MYSQL_PWD||!verifyUncertainNativeIdentity())throw Error('explicit_owned_scope');
const result={stage:'uncertain_barrier_native',passed:false,production_wiring:false,cases:[]};
try {
    result.cleanup_prerequisite=await selectedCleanupProof();
    if(result.cleanup_prerequisite.passed!==true)throw Error('cleanup_prerequisite');
    for(const profile of ['no_fault','selected','released','held']) {
        const receipt=await runUncertainNativeCase(profile);result.cases.push(receipt);
        if(receipt.passed!==true)throw Error('native_case_failed');
    }
    result.passed=result.cases.length===4&&result.cases.every(r=>r.passed===true);
}catch(e){result.failure=['cleanup_prerequisite','native_case_failed'].includes(e.message)?e.message:'owned_proof_refused';}
process.stdout.write(JSON.stringify(result)+'\n');process.exitCode=result.passed?0:1;
