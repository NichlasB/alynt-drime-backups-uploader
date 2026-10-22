// Successor no-fault control; original eight-frame grammar and clocks remain sealed.
import { randomBytes } from 'node:crypto';
import { createOwnedStdioLines } from './owned-stdio-lines.mjs';
import { boundedSettlement } from './selected-native-lifetime.mjs';
import { domain, parseNativeQualificationLine } from './uncertain-barrier-native-grammar.mjs';
import { createNativeQualificationNativeTranscript } from './uncertain-barrier-native-transcript.mjs';
export function ownCacheWitnessControl(child, registry, source, profile, helpers) {
	const attempt = randomBytes(32).toString('hex'), root = randomBytes(32).toString('hex'), transcript = createNativeQualificationNativeTranscript(source,profile);
	if (arguments.length!==5 || profile !== 'no_fault' || !helpers || attempt === root || !transcript) throw Error('native_qualification_context');
	let failed = false, reason = '', stage = 0, total = 0, pending = Promise.resolve(), first = null, rootAt = null, final = null;
	const fail = (label = 'channel_refused') => { failed = true; reason ||= typeof label === 'string' ? label : 'channel_io'; child.stdin.destroy(); return false; };
	const send = async (kind, seq, more) => {
		const m = { domain, v: 1, kind, seq, attempt, ...more }, line = JSON.stringify(m) + '\n';
		if (failed || !parseNativeQualificationLine(line.slice(0,-1),source) || !transcript.feed('parent',line,process.hrtime.bigint()) || (total += line.length) > 32768) throw Error('native_qualification_outbound');
		for (let i=0; i<line.length; i+=3) { const r = await boundedSettlement(new Promise((resolve,reject) => child.stdin.write(line.slice(i,i+3),'ascii',(e) => e ? reject(Error('write')) : resolve(true))),500); if (!r.settled || r.rejected || failed) throw Error('native_qualification_write'); }
	};
	const queue = (f) => { pending = pending.then(f).catch((e) => fail(['root','writer_bind','barrier_bind','native_qualification_ack','native_qualification_outbound','native_qualification_write'].includes(e?.message) ? e.message : 'channel_operation')); };
	const ready = async (method, ids, received) => { while (!registry[method](ids) && process.hrtime.bigint() - received < 900000000n && !failed) await new Promise((resolve) => setTimeout(resolve,2)); if (failed || process.hrtime.bigint() - received > 1000000000n) throw Error('native_qualification_ack'); };
	const parser = createOwnedStdioLines((line) => {
		const m = parseNativeQualificationLine(line,source), received = first; first = null;
		if (!m || m.attempt !== attempt || Object.hasOwn(m,'profile') && m.profile !== profile) return fail();
		if (stage === 0 && m.kind === 'root_request') { stage=1; queue(async () => { rootAt=process.hrtime.bigint(); if (!registry.root(rootAt)) throw Error('root'); await send('root',2,{profile,source,root}); }); return true; }
		if (stage === 1 && m.kind === 'bind_writer' && m.root === root) {
			stage=2; const ids={auth:m.auth,capture:m.capture,writer:m.writer};
			queue(async () => { await ready('readyWriter',ids,received); if (!registry.bindWriter(ids)) throw Error('writer_bind'); const {domain:d,v,kind,seq,attempt:a,...more}=m; await send('bound_writer',3,more); if (process.hrtime.bigint()-received>1000000000n || process.hrtime.bigint()-rootAt>4000000000n) fail(); }); return true;
		}
		if (stage === 2 && m.kind === 'bind_barrier' && m.root === root) {
			stage=3; const ids={auth:m.auth,capture:m.capture,writer:m.writer,barrier:m.barrier};
			queue(async () => { await ready('readyBarrier',ids,received); if (!registry.bindBarrier(ids)) throw Error('barrier_bind'); if(helpers) await helpers.bind(ids,attempt,root,registry); const {domain:d,v,kind,seq,attempt:a,...more}=m; await send('bound_barrier',4,more); if (process.hrtime.bigint()-received>1000000000n) fail(); }); return true;
		}
		if (stage === 3 && m.kind === 'final' && m.root === root) { if (!registry.claim({auth:m.auth,capture:m.capture,writer:m.writer,barrier:m.barrier})) return fail('final_registry'); stage=4; final=m; queue(async () => child.stdin.end()); return true; }
		return fail();
	});
	child.stdin.on('error',fail); child.stdout.on('error',fail);
	child.stdout.on('data',(chunk) => { const tick=process.hrtime.bigint(); first ??= tick; total+=chunk.length; if (failed || total>32768 || !chunk.every((b)=>b===10 || b>=32 && b<=126 && b!==92) || !transcript.feed('worker',chunk.toString('ascii'),tick) || !parser.feed(chunk)) fail(); });
	queue(()=>send('boot',1,{profile,source}));
	return Object.freeze({ abort:fail, async finish() {
		const joined=await boundedSettlement(pending,750), eof=parser.end(), receipt=transcript.finish();
		const result={ passed:joined.settled && !joined.rejected && !failed && stage===4 && eof && receipt?.synthetic_match===true, reason, protocol_stage:stage, channel_bytes:total, pending_settled:joined.settled, guard_count:final ? final.pre_guards+final.post_guards : 0, historical_outcome:final?.observation.result.historical_outcome ?? null, original_slots_empty:final?.original_slots_empty===true, terminal_observed:final?.terminal.outcome==='observed', genuine_denials:final?.genuine_denials ?? 0, checks:final?.checks ?? 0, private_tokens_exported:false };
		final=null; return result;
	} });
}
