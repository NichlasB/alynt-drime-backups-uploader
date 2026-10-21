// Synthetic candidate for the original first-row-timeout rollback suffix. Never executes SQL.
import { drainQueries } from './fixed-drain-grammar.mjs';
import { canonicalOk } from './native-witness-profile.mjs';
const queries=[...drainQueries,'ROLLBACK AND NO CHAIN NO RELEASE',...drainQueries];
const timeout=Buffer.concat([Buffer.from([255,181,4,35]),Buffer.from('HY000Lock wait timeout exceeded; try restarting transaction','ascii')]);
const eof=Buffer.from([254,0,0,3,0]), idleEof=Buffer.from([254,0,0,2,0]);
function fields(b,count,meta=false) {
	let at=0; const values=[];
	for(let i=0;i<count;i++) { if(at>=b.length) return false; const n=b[at++]; if(n===251) {values.push(null);continue;} if(n>250||n>1024||at+n>b.length) return false; values.push(b.subarray(at,at+n).toString('ascii')); at+=n; }
	return meta ? b.length-at===13&&b[at]===12&&values[0]==='def' : at===b.length?values:false;
}
export function createUncertainBarrierDrain(statement) {
	if(arguments.length!==1||!Number.isInteger(statement)||statement<1||statement>4294967295) return false;
	const close=Buffer.alloc(5);close[0]=25;close.writeUInt32LE(statement,1);
	let error=false, statementClosed=false, query=0, active=false, seq=0, failed=false, quit=false, bytes=0, frames=0;
	const fail=()=>{failed=true;return false;};
	return Object.freeze({
		accept(direction,sequence,b) {
			if(arguments.length!==3||failed||quit||!['client','server'].includes(direction)||!Buffer.isBuffer(b)||!b.length||b.length>4096||(bytes+=b.length+4)>32768||++frames>256) return fail();
			if(!error) { if(direction!=='server'||sequence!==1||!b.equals(timeout)) return fail(); error=true;return true; }
			if(!statementClosed) {if(direction!=='client'||sequence!==0||!b.equals(close)) return fail();statementClosed=true;return true;}
			if(direction==='client') {
				if(active||sequence!==0) return fail();
				if(query===5) {if(!b.equals(Buffer.from([1]))) return fail();quit=true;return true;}
				if(!b.equals(Buffer.from('\x03'+queries[query],'ascii'))) return fail();active=true;seq=0;return true;
			}
			if(!active||sequence!==++seq) return fail();
			if(query===2) { if(sequence!==1||!canonicalOk(b)) return fail();query++;active=false;return true; }
			const policy=query===1||query===4, count=policy?5:10, tx=query<2;
			if(seq===1) {if(b.length!==1||b[0]!==count) return fail();}
			else if(seq<=count+1) {if(!fields(b,6,true)) return fail();}
			else if(seq===count+2||seq===count+4) {if(!b.equals(tx?eof:idleEof)) return fail();}
			else if(seq===count+3) {
				const values=fields(b,count); if(!values) return fail();
				if(policy ? JSON.stringify(values)!==JSON.stringify(['READ-COMMITTED','2','3',null,'1']) : values.some((v)=>typeof v!=='string')||values[7]!== (tx?'1':'0')) return fail();
			} else return fail();
			if(seq===count+4) {query++;active=false;} return true;
		},
		done() {return !failed&&quit&&query===5&&error;}
	});
}
