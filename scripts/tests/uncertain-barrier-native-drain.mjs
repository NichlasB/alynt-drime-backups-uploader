// Exact candidate suffix plus same physical identity checks. No SQL/native execution.
import { createUncertainBarrierDrain } from './native-qualification-uncertain-drain.mjs';
function row(b,n) { const v=[];let i=0;for(let k=0;k<n;k++){if(i>=b.length)return false;const z=b[i++];if(z===251){v.push(null);continue;}if(z>250||i+z>b.length)return false;v.push(b.subarray(i,i+z).toString('ascii'));i+=z;}return i===b.length?v:false; }
export function createNativeTimeoutDrain(statement,thread) {
    if(arguments.length!==2||!Number.isInteger(thread)||thread<1||thread>4294967295)return false;
    const base=createUncertainBarrierDrain(statement); if(!base)return false;
    let physical=null, phase=0, sequence=0, active=false, failed=false, reason='';
    const fail=label=>{failed=true;reason ||= label;return false;};
    return Object.freeze({accept(direction,seq,b){
        if(failed||!base.accept(direction,seq,b))return fail('exact_timeout_suffix');
        if(direction==='client'&&b[0]===3){active=true;sequence=0;}
        if(direction==='server'&&active){
            sequence=seq;
            if([0,3].includes(phase)&&seq===13){
                const values=row(b,10);
                if(!values||values[0]!==String(thread)||values[1]!=='local'||values[3]!=='10004'||values[5]!=='10.6.23-MariaDB'||values[6]!=='1'||values[7]!==(phase===0?'1':'0')||values[8]!=='0'||values[9]!=='0'||!values.every(v=>typeof v==='string'&&v.length>0&&v.length<=255))return fail('physical_identity');
                if(physical&&values.some((v,i)=>i!==7&&v!==physical[i]))return fail('physical_changed');physical=values;
            }
            const end=phase===2?1:[0,3].includes(phase)?14:9;
            if(seq===end){phase++;active=false;}
        }
        return true;
    },done(){return !failed&&phase===5&&physical!==null&&base.done();},diagnostic(){return {reason,phase,sequence,active,physical_bound:physical!==null,exact_timeout:true};}});
}
