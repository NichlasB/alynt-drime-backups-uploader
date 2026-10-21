import { domain } from '../../scripts/tests/native-qualification-uncertain-grammar.mjs';
import { guardOrder } from '../../scripts/tests/native-qualification-grammar.mjs';
import { drainQueries } from '../../scripts/tests/fixed-drain-grammar.mjs';
export const source='c'.repeat(64);
export function terminal(negative=false) {
	const result={historical_outcome:'uncertain',client_release_claimed:true,current_acceptance_claimed:false,request_lifecycle_closed_claimed:true,diagnostic_candidate_only:false,candidate_only:true,authenticated:false,barrier_verified:false,closure_proven:false,retry_supported:false,storage_supported:false,dispatch_supported:false,execution_supported:false};
	return {terminal:{outcome:negative?'refused':'observed',root_kind:negative?'false':'array',phase:negative?'rolled_back':'barrier_released',barrier_receipt:!negative,admission_reads:40,barrier_reads:negative?1:10,writes:1,metadata_checks:negative?1:2,zero_dml_checks:negative?1:2,root_result:negative?null:{candidate_only:false,storage_supported:false,dispatch_supported:false,execution_supported:false,retry_supported:false,authenticated:false,classification:'registered_closed',barrier_verified:true,closure_proven:false}},guard_order:guardOrder.slice(0,negative?22:24),qualification:{valid:true,entries:1,returns:1,kind:'closed_array',result,sealed:true,empty_slots:true,capture_unchanged:true,io_unchanged:true},native_proven:false,timeout_proven:false,cleanup_proven:false,execution_supported:false};
}
export function messages(negative=false) {
	const roles={auth:11,capture:12,writer:13,barrier:14,blocker:15,monitor:16};
	return [
		{kind:'bind',roles,root_remaining_ns:'15000000000',read_remaining_ns:'9000000000'},
		{kind:'ready',blocker:15,barrier:14,zero_dml:true},
		{kind:'waiting',requester:14,blocker:15,monitor:16,owned_table:true,role:'safety'},
		{kind:'disposition',case:negative?'held':'released'},
		{kind:'terminal',data:terminal(negative)},
		{kind:'cleanup',sessions_absent:true,locks_available:true,processes_closed:true,pipes_closed:true,fixtures_removed:true,application_unchanged:true,streams_accounted:true,blocker_zero_dml:true},
	].map((m,i)=>({domain,v:1,kind:m.kind,seq:i+1,attempt:'a'.repeat(64),root:'b'.repeat(64),source,...Object.fromEntries(Object.entries(m).filter(([k])=>k!=='kind'))}));
}
export const frame=(bytes,seq=0)=>{const b=Buffer.alloc(bytes.length+4);b.writeUIntLE(bytes.length,0,3);b[3]=seq;bytes.copy(b,4);return b;};
export const commit=()=>frame(Buffer.from('\x03COMMIT AND NO CHAIN NO RELEASE','ascii'));
export const ok=()=>frame(Buffer.from([0,0,0,2,0,0,0]),1);
const text=(v)=>v===null?Buffer.from([251]):Buffer.concat([Buffer.from([v.length]),Buffer.from(v,'ascii')]);
export function failureDrain() {
	const close=Buffer.from([25,7,0,0,0]);
	const rows=[['server',1,Buffer.concat([Buffer.from([255,181,4,35]),Buffer.from('HY000Lock wait timeout exceeded; try restarting transaction','ascii')])],['client',0,close]];
	const queries=[...drainQueries,'ROLLBACK AND NO CHAIN NO RELEASE',...drainQueries];
	for(let q=0;q<queries.length;q++) {
		rows.push(['client',0,Buffer.from('\x03'+queries[q],'ascii')]);
		if(q===2) {rows.push(['server',1,ok().subarray(4)]);continue;}
		const policy=q===1||q===4, count=policy?5:10, tx=q<2;
		const eof=Buffer.from([254,0,0,tx?3:2,0]); let seq=1;
		rows.push(['server',seq++,Buffer.from([count])]);
		for(let i=0;i<count;i++) rows.push(['server',seq++,Buffer.concat([text('def'),text(''),text(''),text(''),text(''),text(''),Buffer.from([12]),Buffer.alloc(12)])]);
		rows.push(['server',seq++,eof]);
		const values=policy?['READ-COMMITTED','2','3',null,'1']:['14','synthetic','host','10004','1','10.6.23','1',tx?'1':'0','0','0'];
		rows.push(['server',seq++,Buffer.concat(values.map(text))],['server',seq++,eof]);
	}
	rows.push(['client',0,Buffer.from([1])]);return rows;
}
