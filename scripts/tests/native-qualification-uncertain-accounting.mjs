// Eight virtual byte streams only; a matching model is never physical credit/cleanup proof.
import { canonicalOk, exactCommit } from './native-witness-profile.mjs';
export function createUncertainBarrierAccounting() {
	if(arguments.length) return false;
	const rows=new Map(); for(const r of ['auth','capture','writer','barrier']) for(const d of ['client','server']) rows.set(r+':'+d,{read:0,written:0,pending:null,closed:false});
	const counts=new Map(['auth','capture','writer','barrier'].map((k)=>[k,0]));
	const used=new Set(); let failed=false, spent=false, cut=false, request=false, delivered=false, total=0;
	const fail=()=>{failed=true; return false;};
	return Object.freeze({
		receive(role,direction,frame,credit,tail=0) {
			const r=rows.get(role+':'+direction);
			if(failed||spent||!r||r.closed||r.pending||cut&&role==='writer'||counts.get(role)>=4096||typeof credit!=='symbol'||used.has(credit)||used.size>=16384||!Buffer.isBuffer(frame)||frame.length<5||frame.length>2097152||frame.readUIntLE(0,3)!==frame.length-4||tail!==0||(total+=frame.length)>67108864||[...rows.values()].reduce((n,v)=>n+(v.pending?.bytes??0),frame.length)>8388608) return fail();
			counts.set(role,counts.get(role)+1);
			used.add(credit);
			r.read+=frame.length; r.pending={credit,bytes:frame.length,commit:role==='writer'&&direction==='client'&&frame[3]===0&&exactCommit(frame.subarray(4)),ok:role==='writer'&&direction==='server'&&frame[3]===1&&frame.length===11&&canonicalOk(frame.subarray(4))}; return true;
		},
		account(role,direction,credit) {
			const r=rows.get(role+':'+direction); if(failed||spent||!r?.pending||r.pending.credit!==credit) return fail();
			if(r.pending.commit) { if(request) return fail(); request=true; }
			if(r.pending.ok) delivered=true;
			r.written+=r.pending.bytes; r.pending=null; return true;
		},
		discardWriterResponse(credit) {
			const r=rows.get('writer:server');
			if(failed||spent||cut||!request||delivered||!r.pending?.ok||r.pending.credit!==credit||[...rows.values()].some((v)=>v!==r&&v.pending)||r.closed) return fail();
			r.pending=null; cut=true; return true; // Consumed before virtual downstream accounting.
		},
		close(role,direction) { const r=rows.get(role+':'+direction); if(failed||spent||!r||r.closed||r.pending) return fail(); r.closed=true; return true; },
		finish() {
			if(spent) return false; spent=true;
			const matched=arguments.length===0&&!failed&&request&&cut&&[...rows].every(([k,r])=>r.closed&&!r.pending&&r.read>0&&r.read-r.written===(k==='writer:server'?11:0));
			return Object.freeze({synthetic_match:matched,eight_streams:8,native_proven:false,response_loss_proven:false,cleanup_proven:false});
		}
	});
}
