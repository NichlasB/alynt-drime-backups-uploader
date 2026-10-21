// Fixed four-role relay. Only one eligible writer OK can be withheld, before write.
import net from 'node:net';
import { once } from 'node:events';
import { selectedNativeFrames } from './selected-native-frames.mjs';
import { handshakeGate } from './relay-no-fault-frames.mjs';
import { closeSelectedTransport } from './selected-native-lifetime.mjs';
export async function openNativeSelectedRelay(backend, registry) {
	if (arguments.length !== 2 || backend !== 10004 || !registry) throw Error('selected_fixed_backend');
	const live=new Set(), records=[], jobs=[];
	let stopping=false, failed=false, observerFailed=false, accepted=0, queued=0, total=0, closed=null;
	const observe=(method,...args)=>{ if(observerFailed) return false; try { const value=registry[method](...args); if(value!==false) return value; } catch {} observerFailed=true; try{registry.unavailable();}catch{} return false; };
	const server=net.createServer({allowHalfOpen:true},client=>{
		if(stopping || ++accepted>8 || live.size>=4){failed=true;client.destroy();return;}
		const upstream=net.createConnection({host:'127.0.0.1',port:backend,allowHalfOpen:true}), id=Symbol('native_selected_pair'), pair={client,upstream};
		const gate=handshakeGate(), parsers={}, row={thread:null,streams:[],cut:false,quit:false,attempts:0,completions:0}; records.push(row);live.add(pair); observe('open',id);
		const fail=()=>{failed=true;observe('unavailable');client.destroy();upstream.destroy();};
		const quitError=e=>row.quit && ['ECONNRESET','ERR_STREAM_PREMATURE_CLOSE'].includes(e.code);
		for(const socket of [client,upstream]){socket.setNoDelay(true);socket.setTimeout(6000,fail);socket.on('error',e=>{if(!row.cut&&!quitError(e))fail();});}
		const pump=async(source,target,direction)=>{
			const parser=selectedNativeFrames(async(frame,tail)=>{
				gate.inspect(direction,frame);if((total+=frame.length)>67108864)throw Error('selected_transport_cap');
				const credit=Symbol('native_selected_credit'), candidate=observe('frame',id,direction,frame,credit,tail);
				if(candidate==='response' && observe('cut',id,credit)===true){row.cut=true;client.destroy();upstream.destroy();return 'withheld';}
				if(row.cut)throw Error('selected_post_cut_write');
				if(candidate==='response')row.attempts++;
				await new Promise((resolve,reject)=>target.write(frame,e=>e?reject(Error('selected_write')):resolve()));
				if(candidate==='response')row.completions++;
				observe('account',id,direction,credit);
				if(direction==='client'&&frame.length===5&&frame[3]===0&&frame[4]===1)row.quit=true;
				return 'forwarded';
			},n=>{if(queued+n>8388608)throw Error('selected_queue');queued+=n;},n=>{queued-=n;},n=>{
				// Ineligibility is transparent, never an instruction to drop a malformed OK.
				observe('cap',id,direction,n);return 2097152;
			});
			parsers[direction]=parser;
			try{for await(const bytes of source.iterator({destroyOnReturn:false}))await parser.feed(bytes);if(!row.cut&&!target.destroyed&&!target.writableEnded)target.end();}
			catch(e){if((!row.cut&&!quitError(e))||!parser.boundary())fail();}
			finally{row.streams.push({...parser.finish(),direction});}
		};
		jobs.push((async()=>{try{await once(upstream,'connect');await Promise.all([pump(client,upstream,'client'),pump(upstream,client,'server')]);}catch{fail();}
			finally{row.thread=gate.cleanupIdentity();observe('close',id);client.destroy();upstream.destroy();live.delete(pair);}})());
		observe('boundary',id,d=>parsers[d]?.boundary()??false);
	});
	server.on('error',()=>{failed=true;});server.listen({host:'127.0.0.1',port:0,exclusive:true});await once(server,'listening');
	return Object.freeze({port:server.address().port,close(){if(closed)return closed;closed=(async()=>{
		stopping=true;const join=await closeSelectedTransport(server,live,jobs), streams=records.flatMap(r=>r.streams), cuts=records.filter(r=>r.cut), withheld=streams.filter(s=>s.withheld!==0);
		const passed=join.resolved&&!failed&&!observerFailed&&live.size===0&&queued===0&&records.length===4&&records.every(r=>Number.isInteger(r.thread)&&r.streams.length===2)&&new Set(records.map(r=>r.thread)).size===4&&streams.length===8&&streams.every(s=>s.complete&&s.conserved&&s.bytes>0)&&cuts.length===1&&withheld.length===1&&withheld[0].direction==='server'&&withheld[0].withheld===11&&cuts[0].attempts===0&&cuts[0].completions===0;
		return {passed,cleanup_resolved:join.resolved,graceful_pumps:join.graceful,listener_closed:join.listener_closed,live_pairs:live.size,queued_bytes:queued,pairs:records.length,streams:streams.length,conserved_streams:streams.filter(s=>s.complete&&s.conserved).length,equal_streams:streams.filter(s=>s.complete&&s.conserved&&s.withheld===0).length,withheld_bytes:streams.reduce((n,s)=>n+s.withheld,0),target_write_attempts:records.reduce((n,r)=>n+r.attempts,0),target_write_completions:records.reduce((n,r)=>n+r.completions,0),bytes:total,threads:records.map(r=>r.thread),observation_failed:observerFailed};
	})();return closed;}});
}
