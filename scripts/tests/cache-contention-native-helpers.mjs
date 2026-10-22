// Two owned DIRECT helpers; original relay retains exclusive writer-only suppression.
import { createOwnedStdioLines } from './owned-stdio-lines.mjs';
import { boundedSettlement } from './selected-native-lifetime.mjs';
import { startContentionHelper } from './cache-contention-native-process.mjs';
import { contentionCommand,parseContentionHelper,contentionProfiles } from './cache-contention-native-protocol.mjs';
import { cacheContentionDigest } from './cache-contention-native-identity.mjs';
import { copyWaitContext,nativeBase } from './wait-witness-contract.mjs';
import { createContentionReplay } from './cache-contention-native-replay.mjs';
export function startCacheContentionHelpers(php,args,profile,registry){
    if(arguments.length!==4||!contentionProfiles.includes(profile)||!Array.isArray(args)||args.length!==4||!registry||['bindContext','execution','cancelCredit'].some(k=>typeof registry[k]!=='function'))throw Error('contention_scope');
    const rows=[];let context=null,starting=false,started=false,bound=false,watch=null,work=null,creditBound=false,failed=false,waiting=false,released=false,monitorClosed=false,window=null,queries=0,traffic=0,firstFailure=null,closing=null;
    const failure=label=>{failed=true;firstFailure??=label;};
    async function open(role){
        const proc=startContentionHelper(php,role,[...args,profile]),messages=[];let waiter=null,bad=false,count=0,closed=false;
        const timer=setTimeout(()=>{bad=true;proc.child.stdin.destroy();proc.child.kill();},28000);
        const parser=createOwnedStdioLines(line=>{
            const m=parseContentionHelper(line,role,profile,context,cacheContentionDigest);
            if(!m||++count>4){bad=true;failure('invalid_helper_frame');return false;}
            if(m.kind==='refused'){failure(Object.freeze({role,...m.failure}));}
            messages.push(m);if(waiter){waiter();waiter=null;}return true;
        });
        const row={role,proc,timer,parser,thread:null,state:null,get bad(){return bad;},async next(kind,ms=1000){
            if(!messages.length&&!closed){const r=await boundedSettlement(new Promise(resolve=>{waiter=resolve;}),ms);if(!r.settled){waiter=null;throw Error('contention_ack');}}
            const m=messages.shift();if(bad||!m||m.kind!==kind)throw Error('contention_frame');return m;
        },async send(kind,statement){
            const line=contentionCommand(kind,profile,context,statement);if((traffic+=line.length)>32768)throw Error('contention_traffic');
            const r=await boundedSettlement(new Promise((resolve,reject)=>proc.child.stdin.write(line,'ascii',e=>e?reject(Error('contention_write')):resolve())),500);if(!r.settled||r.rejected)throw Error('contention_write');
        }};
        rows.push(row); // Retain every child before the first asynchronous wait.
        proc.child.once('close',()=>{closed=true;if(waiter){waiter();waiter=null;}});
        proc.child.stdout.on('data',bytes=>{if((traffic+=bytes.length)>32768||!parser.feed(bytes)){bad=true;failure('helper_transport');proc.child.stdin.destroy();}});
        const hello=await row.next('hello',8000);row.thread=hello.thread;return row;
    }
    let blocker,monitor;
    async function release(){
        if(released)return;
        await blocker.send('rollback');await blocker.next('released');await blocker.next('closed');released=true;blocker.proc.child.stdin.end();
    }
    return Object.freeze({
        async start(){if(starting||closing){failure('rebind');throw Error('contention_rebind');}starting=true;try{blocker=await open('blocker');monitor=await open('monitor');if(blocker.thread===monitor.thread)throw Error('contention_collision');started=true;}catch(e){failure('startup');throw e;}},
        async bind(ids,attempt,root){
            if(!started||bound||context||closing||Object.keys(ids).join(',')!=='auth,capture,writer,barrier'){failure('rebind');throw Error('contention_rebind');}
            context=copyWaitContext({attempt,root,source:nativeBase,roles:{...ids,blocker:blocker.thread,monitor:monitor.thread},table:args[2]+'options'});bound=true;
            await blocker.send('lock');await blocker.next('ready');await monitor.send('arm');await monitor.next('armed');
            if(!registry.bindContext(context))throw Error('contention_bind_credit');creditBound=true;
            // Retained before original bound_barrier ACK; nothing waits inside the relay callback.
            watch=(async()=>{try{
                work=(async()=>{const signal=await registry.execution();if(!signal)throw Error('contention_execute');await monitor.send('execute',signal.statement);const m=await monitor.next('waiting',3000);window=createContentionReplay(context,signal,m.metrics,m.wait);if(!window)throw Error('contention_schedule');queries=m.metrics.queries.length;waiting=true;if(profile==='released')await release();})();
                const r=await boundedSettlement(work,3000);if(!r.settled||r.rejected)throw Error('contention_watch');
            }catch{failure('exact_wait_unverified');}})();
        },
        finish(){if(closing)return closing;closing=(async()=>{
            if(creditBound)registry.cancelCredit();
            const watchJoin=watch?await boundedSettlement(watch,3250):{settled:true,rejected:false};
            if(bound){
                try{await release();}catch{failure('blocker_finish');}
                try{await monitor.send('finish');const m=await monitor.next('closed');monitorClosed=window?.finish(m.zero_dml,m.closed_ns)===true;}catch{failure('monitor_finish');}
            }
            for(const r of rows)r.proc.child.stdin.end();
            await Promise.all(rows.map(async r=>{try{r.state=await r.proc.join(3000);}catch{failure('helper_join');r.state={closed:false,exited:false,code:null,timed_out:false,stderr_bytes:0,overflow:false,cleanup_resolved:false,pipes_destroyed:false};}finally{clearTimeout(r.timer);}}));
            const workJoin=work?await boundedSettlement(work,100):{settled:true};
            const resolved=watchJoin.settled&&workJoin.settled&&rows.every(r=>r.state.closed&&r.state.exited&&r.state.cleanup_resolved&&r.state.pipes_destroyed&&!r.state.timed_out);
            const passed=started&&bound&&waiting&&released&&monitorClosed&&!failed&&watchJoin.settled&&!watchJoin.rejected&&rows.every(r=>!r.bad&&r.parser.end()&&r.state.code===0&&r.state.stderr_bytes===0&&!r.state.overflow);
            return {passed,cleanup_resolved:resolved,threads:rows.map(r=>r.thread).filter(Number.isInteger),startup_completed:started,children_created:rows.length,actual_wait:waiting,rollback_acknowledged:released,monitor_closed_verified:monitorClosed,queries,zero_dml:passed,private_pipes_closed:resolved,control_bytes:traffic,first_failure:firstFailure,processes:rows.map(r=>({role:r.role,closed:r.state.closed,exited:r.state.exited,code:r.state.code,timed_out:r.state.timed_out,stderr_bytes:r.state.stderr_bytes,pipes_destroyed:r.state.pipes_destroyed,invalid_frame:r.bad}))};
        })();return closing;},
    });
}
