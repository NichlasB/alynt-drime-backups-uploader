// Two DIRECT owned sessions. No relay topology expansion, SQL input, KILL or retries.
import { createOwnedStdioLines } from './owned-stdio-lines.mjs';
import { boundedSettlement } from './selected-native-lifetime.mjs';
import { startNativeQualificationPhp } from './uncertain-barrier-native-process.mjs';
import { nativeSourceDigest } from './uncertain-barrier-native-identity.mjs';
import { validDirectFrame } from './uncertain-barrier-native-direct.mjs';
export async function startUncertainNativeHelpers(php,args,profile) {
    if(arguments.length!==3||!['released','held'].includes(profile)||!Array.isArray(args)||args.length!==4)throw Error('direct_scope');
    let context=null, bound=false, released=false, waiting=false, watch=null, failed=false, traffic=0;
    const rows=[];
    async function open(role) {
        const proc=startNativeQualificationPhp(php,`uncertain-barrier-native-${role}.php`,args), messages=[];let waiter=null, bad=false, count=0;
        const timer=setTimeout(()=>{bad=true;proc.child.stdin.destroy();proc.child.kill();},28000);
        const parser=createOwnedStdioLines(line=>{let m;try{m=JSON.parse(line);}catch{return false;}if(JSON.stringify(m)!==line||!validDirectFrame(m,role,context)||++count>4){bad=true;return false;}messages.push(m);if(waiter){waiter();waiter=null;}return true;});
        proc.child.stdout.on('data',bytes=>{if((traffic+=bytes.length)>32768||!parser.feed(bytes)){bad=true;proc.child.stdin.destroy();}});
        const row={role,proc,timer,thread:null,state:null,parser,get bad(){return bad;},async next(kind,ms=1000){
            if(!messages.length){const joined=await boundedSettlement(new Promise(resolve=>{waiter=resolve;}),ms);if(!joined.settled){waiter=null;throw Error('direct_ack');}}
            const m=messages.shift();if(bad||!m||m.kind!==kind)throw Error('direct_frame');return m;
        },async send(command){const line=JSON.stringify({domain:'alynt.uncertain-barrier.direct.v1',command,context})+'\n';if(!context||line.length>4096||(traffic+=line.length)>32768)throw Error('direct_context');const r=await boundedSettlement(new Promise((resolve,reject)=>proc.child.stdin.write(line,'ascii',e=>e?reject(Error('direct_write')):resolve())),500);if(!r.settled||r.rejected)throw Error('direct_write');}};
        rows.push(row);const hello=await row.next('hello',8000);row.thread=hello.thread;return row;
    }
    let blocker,monitor;
    try {blocker=await open('blocker');monitor=await open('monitor');if(blocker.thread===monitor.thread)throw Error('direct_collision');}
    catch(e){await Promise.all(rows.map(async r=>{r.proc.child.stdin.end();r.state=await r.proc.join(3000);clearTimeout(r.timer);}));throw e;}
    return Object.freeze({
        async bind(ids,attempt,root){
            if(bound||context||Object.keys(ids).join(',')!=='auth,capture,writer,barrier')throw Error('direct_rebind');
            const roles={...ids,blocker:blocker.thread,monitor:monitor.thread};if(new Set(Object.values(roles)).size!==6)throw Error('direct_roles');
            context={attempt,root,source:nativeSourceDigest,roles};bound=true;
            await blocker.send('lock');const ready=await blocker.next('ready');if(ready.blocker!==blocker.thread||ready.barrier!==ids.barrier)throw Error('direct_ready');
            await monitor.send('watch');
            watch=(async()=>{try{const m=await monitor.next('waiting',3000);if(m.requester!==ids.barrier||m.blocker!==blocker.thread||m.monitor!==monitor.thread)throw Error('direct_wait');waiting=true;if(profile==='released'){await blocker.send('rollback');await blocker.next('released');released=true;}}catch{failed=true;}})();
        },
        async finish(){
            const watchJoin=watch?await boundedSettlement(watch,3250):{settled:true,rejected:false};
            if(bound){
                try{if(!released){await blocker.send('rollback');await blocker.next('released');released=true;}await blocker.next('closed');blocker.proc.child.stdin.end();}catch{failed=true;blocker.proc.child.stdin.end();}
                try{await monitor.send('finish');await monitor.next('closed');monitor.proc.child.stdin.end();}catch{failed=true;monitor.proc.child.stdin.end();}
            }else for(const r of rows)r.proc.child.stdin.end();
            await Promise.all(rows.map(async r=>{r.state=await r.proc.join(3000);clearTimeout(r.timer);}));
            const resolved=rows.every(r=>r.state.closed&&r.state.exited&&r.state.cleanup_resolved&&r.state.pipes_destroyed&&!r.state.timed_out);
            const passed=bound&&waiting&&released&&!failed&&watchJoin.settled&&!watchJoin.rejected&&rows.every(r=>!r.bad&&r.parser.end()&&r.state.code===0&&r.state.stderr_bytes===0&&!r.state.overflow);
            return {passed,cleanup_resolved:resolved,threads:rows.map(r=>r.thread),actual_wait:waiting,rollback_acknowledged:released,blocker_zero_dml:passed,monitor_zero_dml:passed,direct_sessions:2,relay_roles_added:0,control_bytes:traffic};
        }
    });
}
