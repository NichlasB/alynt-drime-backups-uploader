// New fixed-case terminal owner registry; historical selected registry remains immutable. No transport handles or configurable fault selector.
import { nativeGreeting, nativeHello, canonicalOk, exactCommit, validThread } from './native-witness-profile.mjs';
import { createFixedDrain } from './fixed-drain-grammar.mjs';
import { createNativeTimeoutDrain } from './uncertain-barrier-native-drain.mjs';
import { createNativeBarrierDrain } from './native-qualification-no-fault-barrier-drain.mjs';
export function createUncertainNativeRegistry(profile) {
	if (arguments.length !== 1 || !['selected','released','held'].includes(profile)) throw Error('fixed_native_qualification_registry');
	const rows = new Map(), names = ['auth','capture','writer','barrier'];
	let started = null, writerBound = false, barrierBound = false, roles = null, claimed = false, finished = false, failed = false, reason = '', total = 0, eligible = true, cut = false;
	const now = () => process.hrtime.bigint();
	const fail = (label) => { failed = true; eligible = false; reason ||= label; return false; };
	const budget = () => started !== null && now() >= started && now() - started < (claimed ? 28000000000n : 20000000000n);
	const empty = (r) => !r.pending.client && !r.pending.server;
	const clean = () => [...rows.values()].every((r) => empty(r) && r.boundary('client') && r.boundary('server'));
	const pick = (ids, fourth) => {
		if (!ids || Object.keys(ids).join(',') !== names.slice(0, fourth ? 4 : 3).join(',') || !Object.values(ids).every(validThread) || new Set(Object.values(ids)).size !== Object.values(ids).length || rows.size !== (fourth ? 4 : 3) || !clean()) return false;
		const selected = {};
		for (const role of Object.keys(ids)) { const r = [...rows.values()].find((x) => x.thread === ids[role]); if (!r || r.role !== role || r.stage !== 3 || r.live !== (role === 'auth' || role === (fourth ? 'barrier' : 'writer'))) return false; selected[role] = r; }
		if (selected.writer.profile !== selected.capture.profile || fourth && selected.barrier.profile !== selected.writer.profile) return false;
		return selected;
	};
	return Object.freeze({
		unavailable() { return fail('observer_exception'); },
		open(id) { if (failed || finished || typeof id !== 'symbol' || rows.has(id) || rows.size >= 4 || rows.size === 3 && (!writerBound || roles.writer.live || roles.capture.live)) return fail('pair_set'); const role = names[rows.size]; rows.set(id, { role, stage: 0, thread: null, profile: null, live: true, quit: false, count: 0, pending: { client: null, server: null }, boundary: () => false, preparing: false, statement: null, executing: false, timeout: false, commit: false, requestDone: false, response: false, responseDone: false, drain: role === 'writer' ? createFixedDrain() : role === 'barrier' ? createNativeBarrierDrain() : null }); return true; },
		boundary(id, read) { const r = rows.get(id); if (!r || typeof read !== 'function') return fail('boundary'); r.boundary = read; return true; },
		root(tick) { if (started !== null || typeof tick !== 'bigint' || tick < 0n || tick > now()) return fail('root'); started = tick; return true; },
		cap(id, direction, length) { const r = rows.get(id); if (r?.role === 'writer' && r.commit && direction === 'server' && length !== 7) eligible = false; return 2097152; },
		header(id, direction, length) { const r = rows.get(id); return !failed && r?.live && ['client','server'].includes(direction) && Number.isInteger(length) && length > 0 && length <= 2097152 || fail('header'); },
		frame(id, direction, frame, credit, tail) {
			const r = rows.get(id); if (failed) return false;
			if (cut && r?.role === 'writer') return fail('post_cut');
			if (finished || !r?.live || r.quit || !['client','server'].includes(direction) || typeof credit !== 'symbol' || r.pending[direction] || !Buffer.isBuffer(frame) || frame.length < 5 || frame.readUIntLE(0,3) !== frame.length - 4 || ++r.count > 4096 || (total += frame.length) > 67108864) return fail('frame');
			const seq = frame[3], bytes = frame.subarray(4); let selected = '';
			if (r.stage < 3) {
				if (seq !== r.stage || direction !== (r.stage === 1 ? 'client' : 'server')) return fail('profile_order');
				if (r.stage === 0) { r.thread = nativeGreeting(bytes); if (!r.thread || [...rows.values()].some((x) => x !== r && x.thread === r.thread)) return fail('greeting'); }
				if (r.stage === 1) { r.profile = nativeHello(bytes,'root','local',r.role !== 'auth'); if (!r.profile) return fail('hello'); }
				if (r.stage === 2 && !canonicalOk(bytes)) return fail('auth_ok'); r.stage++;
			} else {
				if (r.role === 'writer' && r.commit && direction === 'client') eligible = false;
				if (direction === 'server' && bytes[0] === 251) return fail('local_file');
				const quit = direction === 'client' && seq === 0 && bytes.length === 1 && bytes[0] === 1;
				if (claimed && !(r.role === 'auth' && quit)) return fail('post_final');
				if (r.role==='barrier' && profile==='held' && direction==='server' && seq===1 && bytes[0]===255) {
                    if (!barrierBound || r.commit || !r.executing || r.timeout || !r.statement) return fail('timeout_without_execute');
                    r.drain=createNativeTimeoutDrain(r.statement,r.thread); r.timeout=true;
                }
                if (r.timeout || r.drain && r.response) { if (!r.drain.accept(direction,seq,bytes)) return fail('post_commit_drain'); }
				else if (direction === 'client') {
					if (seq !== 0 || ![1,2,3,14,22,23,24,25,26].includes(bytes[0]) || !r.boundary('server')) return fail('command');
					if (r.role==='barrier' && bytes[0]===22) r.preparing=bytes.subarray(1).toString('ascii').startsWith('SELECT CASE WHEN ') && bytes.subarray(1).toString('ascii').endsWith('LIMIT 2 FOR UPDATE');
                    if (r.role==='barrier' && bytes[0]===23) { if (!r.statement || bytes.length<10 || bytes.readUInt32LE(1)!==r.statement) return fail('barrier_execute'); r.executing=true; }
                    // Capture/barrier prepared statements are SELECT-only; the fixed writer alone owns its one CAS.
					if (bytes[0] === 22 && r.role !== 'writer' && !bytes.subarray(1).toString('ascii').startsWith('SELECT ')) return fail('read_prepare');
					if (r.drain && exactCommit(bytes)) { if (r.commit || !budget() || !empty(r) || r.role === 'writer' && !writerBound || r.role === 'barrier' && !barrierBound) return fail('commit_before_bind'); r.commit = true; selected = 'commit';
						if (r.role === 'writer' && (now() - started > 4000000000n || tail !== 0 || ![...rows.values()].every((x) => empty(x) && ['client','server'].every((d) => x === r && d === 'client' || x.boundary(d))))) eligible = false; }
				} else if (r.role==='barrier' && r.preparing && seq===1 && bytes[0]===0 && bytes.length===12) { if (profile==='held' && r.statement!==null) return fail('second_barrier_prepare'); r.statement=bytes.readUInt32LE(1); r.preparing=false;
                } else if (r.drain && r.commit) { if (!r.requestDone || seq !== 1 || !canonicalOk(bytes)) return fail('commit_ack'); r.response = true; selected = 'ok';
					if (r.role === 'writer' && (now() - started > 4000000000n || tail !== 0 || ![...rows.values()].every((x) => empty(x) && ['client','server'].every((d) => x === r && d === 'server' || x.boundary(d))))) eligible = false; }
				if (quit) r.quit = true;
			}
			r.pending[direction] = { credit, selected }; return r.role === 'writer' && selected === 'ok' ? 'response' : true;
		},
		cut(id, credit) {
            const r=rows.get(id), p=r?.pending.server;
            if (failed || !eligible || cut || !writerBound || r !== roles.writer || !r.live || !roles.auth.live || !r.requestDone || !r.response || p?.credit !== credit || p.selected !== 'ok' || !budget() || now()-started>4000000000n) return false;
            cut=true; r.pending.server=null; return true; // Consumed before the sole downstream write site.
        },
        account(id, direction, credit) { const r = rows.get(id), p = r?.pending[direction]; if (failed || !p || p.credit !== credit || started !== null && !budget()) return fail('credit'); if (p.selected === 'commit') r.requestDone = true; if (p.selected === 'ok') { r.responseDone = true; if (r.role === 'writer') eligible = false; } r.pending[direction] = null; return true; },
		readyWriter(ids) { return !failed && !writerBound && budget() && now() - started <= 4000000000n && Boolean(pick(ids,false)); },
		bindWriter(ids) { if (!this.readyWriter(ids)) return fail('writer_bind'); roles = pick(ids,false); writerBound = true; return true; },
		readyBarrier(ids) { return !failed && writerBound && !barrierBound && budget() && Boolean(pick(ids,true)) && cut && !roles.writer.responseDone; },
		bindBarrier(ids) { if (!this.readyBarrier(ids)) return fail('barrier_bind'); const all = pick(ids,true); if (['auth','capture','writer'].some((k) => all[k] !== roles[k])) return fail('role_swap'); roles = all; barrierBound = true; return true; },
		claim(ids) { if (failed || claimed || !barrierBound || !budget() || !clean() || !roles.auth.live || ['capture','writer','barrier'].some((k) => roles[k].live) || !roles.barrier.drain.done() || (profile==='held' ? !roles.barrier.timeout || roles.barrier.commit || roles.barrier.responseDone : !roles.barrier.responseDone) || Object.keys(ids).join(',') !== names.join(',') || names.some((k) => ids[k] !== roles[k].thread)) return fail('final'); claimed = true; return true; },
		close(id) { const r = rows.get(id); if (!r?.live || !empty(r)) return fail('close'); r.live = false; if (r.stage !== 3 || (r.role === 'writer' && cut ? !r.requestDone || !r.response || r.responseDone : !r.quit || (r.role==='barrier' && profile==='held' ? !r.timeout || !r.drain.done() || r.commit : r.drain && (!r.drain.done() || !r.requestDone || !r.responseDone))) || r.role === 'auth' && (!claimed || !barrierBound)) return fail('role_close'); return !failed; },
		finish(transport) { const passed = !finished && !failed && eligible && cut && claimed && barrierBound && budget() && transport === true && rows.size === 4 && [...rows.values()].every((r) => !r.live && empty(r)); finished = true; return Object.freeze({ passed, reason, four_roles_bound: barrierBound, selected_response_discarded: cut, original_request_completed: roles?.writer?.requestDone === true, writer_commit_delivered: roles?.writer?.responseDone === true, native_timeout_suffix: roles?.barrier?.timeout===true && roles?.barrier?.drain.done()===true, barrier_commit_delivered: roles?.barrier?.responseDone === true, exact_writer_drain: roles?.writer?.drain.done() === true, exact_barrier_drain: roles?.barrier?.drain.done() === true, barrier_drain: roles?.barrier?.drain.diagnostic() ?? null, private_qualification_proven: false, suppression_supported: true }); },
	});
}
