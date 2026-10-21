// Invented no-fault native-profile packets only. Never connects or invokes a root.
import { createNativeQualificationRegistry } from '../../../scripts/tests/native-qualification-no-fault-registry.mjs';
import { greeting, frame, commit, ok } from './native-witness-fixtures.mjs';
import { drainPackets, result, column, text, eof } from './fixed-drain-fixtures.mjs';
export { frame, commit, ok, drainPackets };
export const counters=['Com_insert','Com_insert_select','Com_update','Com_update_multi','Com_delete','Com_delete_multi','Com_replace','Com_replace_select','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Handler_write','Handler_update','Handler_delete'];
export const zeroQuery="SHOW SESSION STATUS WHERE Variable_name IN ('Com_insert','Com_insert_select','Com_update','Com_update_multi','Com_delete','Com_delete_multi','Com_replace','Com_replace_select','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Handler_write','Handler_update','Handler_delete')";
export function barrierPackets() {
	// Independently reproduced with bundled MariaDB 10.6.23: SHOW metadata EOF=34,
	// final EOF=2, both warning counts zero. Do not copy the parser's expected bytes.
	const earlier=drainPackets().slice(0,-1), bodies=[Buffer.from([2]),column(),column(),Buffer.from([254,0,0,34,0]),...counters.map((s)=>Buffer.concat([text(s),text('0')])),eof()];
	return [...earlier,frame(Buffer.from('\x03'+zeroQuery)),...bodies.map((b,i)=>frame(b,i+1)),frame(Buffer.from([1]))];
}
function hello(role) {
	const flags=role==='auth'?0x8a200:0x8a208, fixed=Buffer.alloc(32); fixed.writeUInt32LE(flags); fixed.writeUInt32LE(2097152,4); fixed[8]=45;
	return Buffer.concat([fixed,Buffer.from('root\0'),Buffer.from([20]),Buffer.alloc(20,67),flags&8?Buffer.from('local\0'):Buffer.alloc(0),Buffer.from('mysql_native_password\0')]);
}
export function nativeNoFaultFixture(factory=createNativeQualificationRegistry) {
	const api=factory(), ids=Object.fromEntries(['auth','capture','writer','barrier'].map((s)=>[s,Symbol(s)])), roles={auth:101,capture:102,writer:103,barrier:104};
	const send=(role,direction,bytes,complete=true)=>{ const key=Symbol('credit'); const accepted=api.frame(ids[role],direction,bytes,key); return accepted && (!complete || api.account(ids[role],direction,key)); };
	const handshake=(role)=> api.open(ids[role]) && api.boundary(ids[role],()=>true) && send(role,'server',frame(greeting(roles[role]),0)) && send(role,'client',frame(hello(role),1)) && send(role,'server',frame(ok(),2));
	const quit=(role)=>send(role,'client',frame(Buffer.from([1]))) && api.close(ids[role]);
	const ready=()=>handshake('auth') && api.root(process.hrtime.bigint()) && handshake('capture') && quit('capture') && handshake('writer') && api.bindWriter({auth:101,capture:102,writer:103});
	const sendPackets=(role,packets)=>packets.every((p)=>send(role,p[3]===0?'client':'server',p));
	const committed=(role)=>send(role,'client',frame(commit())) && send(role,'server',frame(ok(),1));
	const writer=()=>committed('writer') && sendPackets('writer',drainPackets()) && api.close(ids.writer);
	const barrier=()=>handshake('barrier') && api.bindBarrier(roles) && committed('barrier') && sendPackets('barrier',barrierPackets()) && api.close(ids.barrier);
	const finish=()=>api.claim(roles) && quit('auth') && api.finish(true);
	return {api,ids,roles,send,handshake,quit,ready,committed,sendPackets,writer,barrier,finish};
}
