// Virtual-time DATA window only. Does not sleep, query, authenticate or run helpers.
import { copyWaitContext, exactWaitTuple, parseWaitLine, queryGap, windowLength } from './wait-witness-contract.mjs';
import { consumeWaitExecuteReceipt } from './wait-witness-credit.mjs';
export function createWaitWitnessWindow(context,prepared) {
    if(arguments.length!==2 || typeof prepared!=='bigint' || prepared<0n || prepared>1000000000000000000n)throw Error('fixed_wait_window_arguments');
    const c=copyWaitContext(context),deadline=prepared+windowLength;
    let last=prepared,phase='hello',notBefore=null,queries=0,total=0,helperFrames=0,buffer=Buffer.alloc(0),fragmentDirection=null,failed=false,finished=false;
    const fail=()=>{failed=true;buffer=Buffer.alloc(0);return false;};
    const clock=tick=>{
        if(failed || finished || typeof tick!=='bigint' || tick<last || tick>1000000000000000000n)return fail();
        last=tick;
        if(!['finish','closed','done'].includes(phase) && tick>=deadline)return fail();
        return true;
    };
    return Object.freeze({
        feed(direction,bytes,tick,receipt) {
            // Burn supplied evidence even when shape, phase or time is wrong; never re-arm it elsewhere.
            const credited=receipt===undefined ? false:consumeWaitExecuteReceipt(receipt,c);
            if(arguments.length<3 || arguments.length>4 || !clock(tick) || !['worker','parent'].includes(direction) || !Buffer.isBuffer(bytes) || !bytes.length ||
                (total+=bytes.length)>32768 || buffer.length+bytes.length>4096 || fragmentDirection && fragmentDirection!==direction)return fail();
            fragmentDirection=direction;buffer=Buffer.concat([buffer,bytes]);
            if(buffer.at(-1)!==10)return receipt===undefined ? true:fail();
            const m=parseWaitLine(buffer,c);buffer=Buffer.alloc(0);fragmentDirection=null;
            if(!m || direction==='worker' && ++helperFrames>4 || m.kind==='refused')return fail();
            if(phase==='hello' && direction==='worker' && m.kind==='hello')phase='arm';
            else if(phase==='arm' && direction==='parent' && m.kind==='arm')phase='armed';
            else if(phase==='armed' && direction==='worker' && m.kind==='armed')phase='execute';
            else if(phase==='execute' && direction==='parent' && m.kind==='execute') {
                if(credited!==m.statement)return fail();
                notBefore=tick+queryGap;phase='quiet';return true;
            } else if(phase==='waiting' && direction==='worker' && m.kind==='waiting' && m.queries===queries)phase='finish';
            else if(phase==='finish' && direction==='parent' && m.kind==='finish')phase='closed';
            else if(phase==='closed' && direction==='worker' && m.kind==='closed')phase='done';
            else return fail();
            return receipt===undefined ? true:fail();
        },
        beginQuery(tick) {
            if(arguments.length!==1 || !clock(tick) || buffer.length || phase!=='quiet' || tick<notBefore || queries>=125)return fail();
            queries++;phase='query';return true;
        },
        endQuery(tuple,tick) {
            if(arguments.length!==2 || !clock(tick) || phase!=='query' || buffer.length)return fail();
            if(tuple===null){notBefore=tick+queryGap;phase='quiet';return true;}
            if(!exactWaitTuple(tuple,c))return fail();
            phase='waiting';return true;
        },
        finish() {
            if(arguments.length || finished)return false;
            const synthetic_match=!failed && phase==='done' && !buffer.length && helperFrames===4 && queries>=1 && queries<=125;
            finished=true;buffer=Buffer.alloc(0);
            return Object.freeze({synthetic_match,native_verified:false,storage_supported:false,dispatch_supported:false,execution_supported:false,wiring_supported:false});
        },
    });
}
