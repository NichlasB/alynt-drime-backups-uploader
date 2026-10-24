// Explicit operator only. Standalone FastCGI, not an HTTP/WordPress/profile-admission endpoint.
import {writeFileSync,readFileSync,existsSync} from 'node:fs';
import path from 'node:path';import {createHash} from 'node:crypto';import {performance} from 'node:perf_hooks';
import {setTimeout as delay} from 'node:timers/promises';
import {cgiRequest} from './cgi-profile-codec.mjs';import {openCgiRequest} from './cgi-profile-transport.mjs';
import {resolveCgi,reserveCgiPort,startCgi,inspectCgi} from './cgi-profile-process.mjs';
import {createCgiFixture} from './cgi-profile-fixture.mjs';
const canonical=v=>JSON.stringify(v,(_key,x)=>x&&typeof x==='object'&&!Array.isArray(x)?Object.fromEntries(Object.keys(x).sort().map(k=>[k,x[k]])):x);
const sha=v=>createHash('sha256').update(v).digest('hex');
function check(value,label){if(!value)throw Error(label);}
function stage(root){const file=path.join(root,'stage.json');return existsSync(file)?JSON.parse(readFileSync(file,'utf8')).stage:null;}
async function awaitStage(root,name){const end=performance.now()+1600;while(performance.now()<end){if(stage(root)===name)return;await delay(20);}throw Error('stage_missing');}
async function runCase(mode,cgi){
    const fixture=createCgiFixture(),{marker,root}=fixture;
    let owner=null,request=null,clean=false,phase='fixture',epoch=null;const receipt={mode,passed:false};
    try{
        phase='spawn';const port=await reserveCgiPort();owner=startCgi(cgi,path.join(root,'php.ini'),port,marker,mode);writeFileSync(path.join(root,'process.json'),JSON.stringify({pid:owner.child.pid,owner:process.pid,port,marker}),{flag:'wx'});await delay(350);
        phase='identity';await owner.verify();check(owner.alive(),'startup');
        phase='request';const body=Buffer.from(mode==='stalled_input'?'x':'x=owned-fixture');epoch=performance.now();
        request=openCgiRequest(port,cgiRequest(path.join(root,'probe.php').replaceAll('\\','/'),body,mode==='refusal'?'GET':'POST',mode!=='stalled_input'));
        if(mode==='disconnect'){
            phase='disconnect_entry';await awaitStage(root,'entered');await request.close();await delay(100);check(owner.alive(),'disconnect_is_not_exit');
            phase='disconnect_completion';await awaitStage(root,'completed_after_disconnect');check(owner.alive(),'worker_persists_after_request');
            receipt.client_disconnected=true;receipt.script_continued=true;receipt.worker_still_alive=true;
        }else if(mode==='stalled_input'){
            await delay(700);check(owner.alive()&&stage(root)===null,'input_not_stalled');receipt.pre_execution_stall=true;receipt.external_termination_required=true;
        }else{
            phase='response';const response=await request.completion;receipt.response_kind=response.kind;receipt.response_status=response.application_status??null;
            if(mode==='native_timeout'){
                const fatal=/Maximum execution time of 1 seconds? exceeded/;
                check(['complete','incomplete'].includes(response.kind)&&fatal.test((response.stderr??'')+owner.privateStderr())&&performance.now()-epoch>=500,'native_timeout_not_observed');
                receipt.native_fatal_observed=true;receipt.application_status=response.application_status??null;receipt.worker_still_alive=owner.alive();receipt.response_complete=response.kind==='complete';
                receipt.shutdown_ran=existsSync(path.join(root,'shutdown.json'));if(receipt.shutdown_ran)check(JSON.parse(readFileSync(path.join(root,'shutdown.json'),'utf8')).fatal===true,'fatal_shutdown');
            }else{
                if(response.kind!=='complete'||response.application_status!==0||response.stderr!==''){receipt.response_diagnostic={reason:response.reason,stdout_bytes:response.stdout?.length,stderr_bytes:response.stderr?.length};}check(response.kind==='complete'&&response.application_status===0&&response.stderr==='','normal_response');
                const split=response.stdout.indexOf('\r\n\r\n');check(split>=0,'cgi_headers');const result=JSON.parse(response.stdout.slice(split+4));
                if(mode==='refusal'){check(/^Status: 405\b/m.test(response.stdout)&&result.refused===true&&result.body_read===false&&stage(root)===null,'refusal_boundary');receipt.refusal_before_fixture_read=true;}
                else{check(result.fixture===true&&result.wordpress===false&&result.mysqli===false&&result.sapi==='cgi-fcgi'&&result.bytes===body.length&&result.synthetic_sha256===sha(body)&&result.reads<=17&&result.eof===true&&result.closed===true&&result.seekable===true&&result.execution_limit==='1'&&result.input_limit==='5'&&result.post_limit==='16K'&&result.prepend===''&&!result.scanned_ini,'no_fault');receipt.body_bytes=result.bytes;receipt.body_conserved=true;receipt.effective_owned_ini=true;}
            }
        }
        phase='cleanup';
    }catch(error){receipt.failure_stage=phase;receipt.reason=/^[a-z_]+$/.test(error.message)?error.message:'private_failure';if(owner){const d=owner.diagnostics();receipt.startup_exit=d.exit;receipt.startup_signal=d.signal;receipt.startup_error=d.start_error;receipt.private_diagnostic_bytes=Buffer.byteLength(d.stdout)+Buffer.byteLength(d.stderr);writeFileSync(path.join(root,'diagnostic.json'),JSON.stringify(d),{flag:'wx'});}}
    finally{
        try{
            // Kill only this verified child; do it before releasing a deliberately stalled input socket.
            if(owner){const result=await owner.close();receipt.process_cleanup=result;if(result.error||result.overflow||result.stdout_bytes!==0||(result.stderr_bytes!==0&&!(mode==='native_timeout'&&/Maximum execution time of 1 seconds? exceeded/.test(owner.privateStderr())))){receipt.failure_stage??='child_output';receipt.reason='unexpected_child_output';}}
            if(request)check((await request.close()).closed,'socket_cleanup');
            if(mode==='stalled_input'&&stage(root)!==null){receipt.failure_stage??='stall_cleanup';receipt.reason='stall_released_before_termination';}
            receipt.request_and_cleanup_ms=epoch===null?null:Math.ceil(performance.now()-epoch);
            if(epoch!==null&&receipt.request_and_cleanup_ms>=9500){receipt.failure_stage??='cleanup_budget';receipt.reason='original_cleanup_budget';}
            fixture.remove();clean=true;
        }catch{if(request){try{await request.close();}catch{receipt.socket_cleanup_unresolved=true;}}receipt.cleanup_unresolved=true;receipt.retained_root=root;if(owner?.child){owner.child.unref();owner.child.stdin.destroy();owner.child.stdout.destroy();owner.child.stderr.destroy();}}
    }
    receipt.fixtures_removed=clean;receipt.passed=clean&&!receipt.failure_stage&&!receipt.cleanup_unresolved;
    return receipt;
}
async function main(){
    const receipts=[];let before=null,preserved=false,phase='preflight',failureReason=null;
    try{
        const cgi=resolveCgi();before=canonical(await inspectCgi('snapshot'));
        for(const mode of ['no_fault','refusal','native_timeout','disconnect','stalled_input']){
            phase=mode;const result=await runCase(mode,cgi);receipts.push(result);process.stdout.write(JSON.stringify({event:'owned_case',...result})+'\n');check(result.passed,'owned_case_failed');
        }
    }catch(error){failureReason=/^[a-z_]+$/.test(error.message)?error.message:'operator_preflight_or_case_failure';process.exitCode=1;}
    finally{if(before!==null){try{preserved=before===canonical(await inspectCgi('snapshot'));}catch{preserved=false;}}}
    const passed=receipts.length===5&&receipts.every(r=>r.passed)&&preserved;
    process.stdout.write(JSON.stringify({passed,phase,failure_reason:failureReason,existing_preservation_observed:before!==null,existing_service_and_config_identity_preserved:preserved,cases:receipts.length,wordpress:false,database_access:false,http_front_door_proof:false,production_profile_qualified:false,production_wiring:false})+'\n');if(!passed)process.exitCode=1;
}
if(process.argv.length!==3||process.argv[2]!=='--run-owned-cgi-profile'||process.platform!=='win32'){process.stdout.write('{"passed":false,"refused_before_operations":true}\n');process.exitCode=1;}else await main();
