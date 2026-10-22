// Two compiled direct helpers only. Preserves original owned exit/close/pipe budgets.
import { spawn } from 'node:child_process';
import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { resolveOwnedStdioRuntime } from './owned-stdio-runtime.mjs';
import { boundedSettlement,closeOwnedChild } from './selected-native-lifetime.mjs';
export function startContentionHelper(php,role,args) {
    if(arguments.length!==3||php!==resolveOwnedStdioRuntime('plugin-tester')||!['blocker','monitor'].includes(role)||!Array.isArray(args)||args.length!==5)throw Error('cache_child');
    if(typeof args[0]!=='string'||realpathSync(args[0])!==realpathSync('C:/Users/Captain/Local Sites/plugin-tester/app/public')||args[1]!=='10004'||!/^wp_alynt_core_probe_[a-f0-9]{12}_$/.test(args[2])||!/^[a-f0-9]{24}$/.test(args[3])||!['released','held'].includes(args[4]))throw Error('cache_child_scope');
    args=[realpathSync(args[0]),...args.slice(1)];
    const child=spawn(php,['-d','extension_dir='+php.slice(0,-7)+'ext','-d','extension=mysqli','-d','display_errors=0','-d','log_errors=0','-d','output_buffering=0','-d','implicit_flush=1','-d','disable_functions=exec,shell_exec,system,passthru,proc_open,popen',fileURLToPath(new URL('./cache-contention-native-'+role+'.php',import.meta.url)),...args],{shell:false,windowsHide:true,stdio:['pipe','pipe','pipe']});
    let closed=false,exited=false,stderr=0,overflow=false,output='';
    child.stdin.on('error',()=>{});child.stdout.on('error',()=>{overflow=true;});child.stderr.on('error',()=>{overflow=true;});
    child.stdout.on('data',bytes=>{if(Buffer.byteLength(output)+bytes.length>32768){overflow=true;child.kill();}else output+=bytes.toString('ascii');});
    child.stderr.on('data',bytes=>{stderr+=bytes.length;if(stderr>262144){overflow=true;child.kill();}});
    child.once('exit',()=>{exited=true;});
    const completion=new Promise(resolve=>{child.once('error',()=>{overflow=true;});child.once('close',code=>{closed=true;resolve({code,closed:true});});});
    return Object.freeze({child,async join(ms=3000){
        const joined=await boundedSettlement(completion,ms);let cleanup;
        if(!joined.settled)cleanup=await closeOwnedChild(child,completion,3000);
        else {child.stdin.destroy();child.stdout.destroy();child.stderr.destroy();cleanup={resolved:true,pipes_destroyed:child.stdin.destroyed&&child.stdout.destroyed&&child.stderr.destroyed};}
        return {closed,exited,code:joined.value?.code??null,timed_out:!joined.settled,stderr_bytes:stderr,overflow,cleanup_resolved:cleanup.resolved&&cleanup.pipes_destroyed,pipes_destroyed:cleanup.pipes_destroyed,private_output:output};
    }});
}
