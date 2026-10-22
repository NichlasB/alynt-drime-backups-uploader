// Retained one-shot tap around the ORIGINAL no-fault registry. No transport mutation.
import { createNativeQualificationRegistry } from './native-qualification-no-fault-registry.mjs';
import { createWaitExecuteCredit } from './wait-witness-credit.mjs';
import { copyWaitContext } from './wait-witness-contract.mjs';
export function createCacheWitnessRegistry() {
    if(arguments.length)throw Error('cache_registry');
    const base=createNativeQualificationRegistry(),pairs=new Map(),history=[];
    let context=null,model=null,failed=false,signalled=false,total=0,resolve,boundIds=null;
    const execution=new Promise(r=>{resolve=r;});
    const fail=()=>{failed=true;resolve(false);return false;};
    const forwarded=event=>{
        if(!model.frame('barrier',event.direction,event.packet,event.credit)||!model.forwarded(event.direction,event.credit,event.packet.length))return fail();
        const receipt=model.account(event.direction,event.credit);
        if(receipt===false)return fail();
        if(receipt!==true){if(signalled)return fail();signalled=true;resolve(Object.freeze({receipt,statement:event.packet.readUInt32LE(5)}));}
        return true;
    };
    return Object.freeze({
        ...Object.fromEntries(['boundary','root','header','readyWriter','bindWriter','readyBarrier','claim','close'].map(k=>[k,(...args)=>base[k](...args)])),
        unavailable(){fail();return base.unavailable();},
        open(id){const result=base.open(id);if(result!==false)pairs.set(id,{role:['auth','capture','writer','barrier'][pairs.size],frames:0,prepare:false,pending:{client:null,server:null}});return result;},
        frame(id,direction,packet,credit){
            const result=base.frame(id,direction,packet,credit),p=pairs.get(id);
            if(result===false){fail();return result;}
            if(!p||p.role!=='barrier'||++p.frames<=3||failed)return result;
            const body=packet.subarray(4),selected=direction==='client'&&[22,23,25].includes(body[0])||direction==='server'&&p.prepare&&packet[3]===1;
            if(!selected)return result;
            if(p.pending[direction]||history.length>=64||(total+=packet.length)>16384){fail();return result;}
            if(direction==='client'&&body[0]===22)p.prepare=true;
            else if(direction==='server')p.prepare=false;
            const event={direction,packet:Buffer.from(packet),credit,accounted:false};p.pending[direction]=event;
            if(!context)history.push(event);
            return result;
        },
        account(id,direction,credit){
            // Only the unchanged relay's successful target.write callback calls this original account.
            const result=base.account(id,direction,credit),p=pairs.get(id),event=p?.pending[direction];
            if(result===false){fail();return result;}
            if(event){if(event.credit!==credit||event.accounted){fail();return result;}event.accounted=true;p.pending[direction]=null;if(model)forwarded(event);}
            return result;
        },
        bindBarrier(ids){const result=base.bindBarrier(ids);if(result)boundIds={...ids};return result;},
        bindContext(c){
            if(arguments.length!==1||failed||context||!boundIds||['auth','capture','writer','barrier'].some(k=>c?.roles?.[k]!==boundIds[k])||history.length!==20||history.some(e=>!e.accounted))return fail();
            context=copyWaitContext(c);model=createWaitExecuteCredit(context);
            for(const event of history)if(!forwarded(event))return false;
            history.length=0;return model.bind()||fail();
        },
        execution(){if(arguments.length)return false;return execution;},
        cancelCredit(){if(arguments.length)return false;if(!signalled)fail();return true;},
        finish(transport){const original=base.finish(transport);return Object.freeze({...original,cache_credit_verified:!failed&&signalled&&context!==null,source_projection_only:true});},
    });
}
