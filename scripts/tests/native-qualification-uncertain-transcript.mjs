// Virtual-clock/private-message model only. No process, pipe, networking or native APIs.
import { parseUncertainBarrierLine, token } from './native-qualification-uncertain-grammar.mjs';
const kinds=['bind','ready','waiting','disposition','terminal','cleanup'];
const directions=['parent','blocker','monitor','parent','worker','parent'];
const tickOk=(n)=>typeof n==='bigint'&&n>=0n&&n<=9223372016854775807n;
export function createUncertainBarrierTranscript(source) {
	if (arguments.length!==1 || !token(source)) return false;
	let stage=0, bytes=0, line='', last=null, first=null, deadline=null, rootEnd=null, readEnd=null, attempt=null, root=null, roles=null, disposition=null, spent=false, failed=false;
	const fail=()=>{ failed=true; line=''; roles=attempt=root=disposition=null; return false; };
	return Object.freeze({
		feed(direction,chunk,tick) {
			if (arguments.length!==3 || failed || spent || stage>=6 || direction!==directions[stage] || !tickOk(tick) || last!==null&&tick<last || typeof chunk!=='string'||!chunk.length||chunk.length>32768-bytes) return fail();
			if (first===null) first=tick;
			if (stage===0&&deadline===null) deadline=tick+1000000000n;
			if (tick-first>=20000000000n || rootEnd!==null&&tick>=rootEnd || stage<5&&readEnd!==null&&tick>=readEnd || stage===1&&tick>deadline) return fail();
			bytes+=chunk.length; last=tick;
			for(let i=0;i<chunk.length;i++) {
				const c=chunk.charCodeAt(i);
				if(c!==10) { if(c<32||c>126||c===92||line.length>=4095) return fail(); line+=chunk[i]; continue; }
				if(i!==chunk.length-1) return fail();
				const m=parseUncertainBarrierLine(line); line='';
				if(!m||m.kind!==kinds[stage]||m.source!==source||stage&& (m.attempt!==attempt||m.root!==root)) return fail();
				if(stage===0) { roles=m.roles; attempt=m.attempt; root=m.root; rootEnd=first+BigInt(m.root_remaining_ns); readEnd=first+BigInt(m.read_remaining_ns); }
				if(stage===1&&(m.blocker!==roles.blocker||m.barrier!==roles.barrier)) return fail();
				if(stage===2&&(m.requester!==roles.barrier||m.blocker!==roles.blocker||m.monitor!==roles.monitor)) return fail();
				if(stage===3) disposition=m.case;
				if(stage===4&&(m.data.qualification.result.historical_outcome!=='uncertain'|| (disposition==='held' ? m.data.terminal.outcome!=='refused'||m.data.terminal.phase!=='rolled_back' : m.data.terminal.outcome!=='observed'))) return fail();
				stage++;
			}
			return true;
		},
		finish() {
			if(spent) return false; spent=true;
			const matched=arguments.length===0&&!failed&&stage===6&&line===''; fail();
			return Object.freeze({synthetic_match:matched,native_proven:false,timeout_proven:false,authentication_proven:false,cleanup_proven:false,execution_supported:false});
		}
	});
}
