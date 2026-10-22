// No-fault only: idle companion plus bounded cache-aware monitor. No locking command.
import { createOwnedStdioLines } from './owned-stdio-lines.mjs';
import { boundedSettlement } from './selected-native-lifetime.mjs';
import { startCacheWitnessHelper } from './cache-witness-native-process.mjs';
import { cacheCommand,parseCacheHelper } from './cache-witness-native-protocol.mjs';
import { cacheWitnessDigest } from './cache-witness-native-identity.mjs';
import { copyWaitContext,nativeBase } from './wait-witness-contract.mjs';
import { replayCacheExpiry } from './cache-witness-native-replay.mjs';
export function startCacheWitnessHelpers(php,args) {
    if(arguments.length!==2||!Array.isArray(args)||args.length!==4)throw Error('cache_scope');
    const rows=[];let context=null,started=false,starting=false,bound=false,watch=null,work=null,creditOwner=null,failed=false,verified=false,traffic=0,queries=0,firstFailure=null;
    const failure=label=>{failed=true;firstFailure??=label;};
    async function open(role) {
        const proc=startCacheWitnessHelper(php,role,args),messages=[];let waiter=null,bad=false,count=0,closed=false;
        const timer=setTimeout(()=>{bad=true;proc.child.stdin.destroy();proc.child.kill();},28000);
        const parser=createOwnedStdioLines(line=>{const m=parseCacheHelper(line,role,context,cacheWitnessDigest);if(!m||++count>4){bad=true;return false;}messages.push(m);if(waiter){waiter();waiter=null;}return true;});
        const row={role,proc,timer,parser,thread:null,state:null,get bad(){return bad;},async next(kind,ms=1000){
            if(!messages.length&&!closed){const joined=await boundedSettlement(new Promise(resolve=>{waiter=resolve;}),ms);if(!joined.settled){waiter=null;throw Error('cache_ack');}}
            const m=messages.shift();if(bad||!m||m.kind!==kind)throw Error('cache_frame');return m;
        },async send(kind,statement){const line=cacheCommand(kind,context,statement);if((traffic+=line.length)>32768)throw Error('cache_traffic');const r=await boundedSettlement(new Promise((resolve,reject)=>proc.child.stdin.write(line,'ascii',e=>e?reject(Error('cache_write')):resolve())),500);if(!r.settled||r.rejected)throw Error('cache_write');}};
        rows.push(row); // Retain ownership before waiting for startup.
        proc.child.once('close',()=>{closed=true;if(waiter){waiter();waiter=null;}});
        proc.child.stdout.on('data',bytes=>{if((traffic+=bytes.length)>32768||!parser.feed(bytes)){bad=true;proc.child.stdin.destroy();}});
        const hello=await row.next('hello',8000);row.thread=hello.thread;return row;
    }
    let idle,monitor;
    return Object.freeze({
        async start(){if(starting)throw Error('cache_rebind');starting=true;try{idle=await open('idle');monitor=await open('monitor');if(idle.thread===monitor.thread)throw Error('cache_collision');started=true;}catch(e){failure('startup');throw e;}},
        async bind(ids,attempt,root,registry){
            if(!started||bound||context||Object.keys(ids).join(',')!=='auth,capture,writer,barrier')throw Error('cache_rebind');
            context=copyWaitContext({attempt,root,source:nativeBase,roles:{...ids,blocker:idle.thread,monitor:monitor.thread},table:args[2]+'options'});bound=true;
            await idle.send('bind');await idle.next('ready');await monitor.send('arm');await monitor.next('armed');
            if(!registry.bindContext(context))throw Error('cache_bind_credit');
            creditOwner=registry;
            // Retained before bound_barrier ACK. Original relay accounts; this job never drives it.
            watch=(async()=>{try{
                work=(async()=>{const signal=await registry.execution();if(!signal)throw Error('cache_execute');await monitor.send('execute',signal.statement);const m=await monitor.next('refused',3000);if(!replayCacheExpiry(context,signal,m.metrics))throw Error('cache_schedule');queries=m.metrics.queries.length;verified=true;})();
                const r=await boundedSettlement(work,3000);if(!r.settled||r.rejected)throw Error('cache_watch');
            }catch{failure('monitor_expiry');}})();
        },
        async finish(){
            if(creditOwner)creditOwner.cancelCredit(); // Settles only the private unissued credit, never native state.
            const joined=watch?await boundedSettlement(watch,3250):{settled:true,rejected:false};
            if(bound){try{await idle.send('finish');await idle.next('closed');}catch{failure('idle_finish');}}
            for(const r of rows)r.proc.child.stdin.end();
            await Promise.all(rows.map(async r=>{r.state=await r.proc.join(3000);clearTimeout(r.timer);}));
            const workJoin=work?await boundedSettlement(work,100):{settled:true};
            const resolved=workJoin.settled&&joined.settled&&rows.every(r=>r.state.closed&&r.state.exited&&r.state.cleanup_resolved&&r.state.pipes_destroyed&&!r.state.timed_out);
            const passed=started&&bound&&verified&&!failed&&joined.settled&&!joined.rejected&&rows.every(r=>!r.bad&&r.parser.end()&&r.state.code===(r.role==='monitor'?1:0)&&r.state.stderr_bytes===0&&!r.state.overflow);
            return {passed,cleanup_resolved:resolved,threads:rows.map(r=>r.thread).filter(Number.isInteger),startup_completed:started,children_created:rows.length,actual_wait:false,no_match_refusal:passed,queries,zero_dml:passed,private_pipes_closed:resolved,control_bytes:traffic,first_failure:firstFailure,processes:rows.map(r=>({role:r.role,closed:r.state.closed,exited:r.state.exited,code:r.state.code,timed_out:r.state.timed_out,stderr_bytes:r.state.stderr_bytes,pipes_destroyed:r.state.pipes_destroyed,invalid_frame:r.bad}))};
        },
    });
}
