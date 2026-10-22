// Synthetic fixtures only. No supervisor import or native calls.
import { qualificationFixture } from './support/native-qualification-fixture.mjs';
import { terminal, failureDrain } from './uncertain-barrier-fixtures.mjs';
import { domain } from '../../scripts/tests/uncertain-barrier-native-grammar.mjs';
import { uncertainNativeIdentities } from '../../scripts/tests/uncertain-barrier-native-identity.mjs';
export function nativeTerminalFixture(profile) {
    const f=qualificationFixture(profile==='no_fault'),source=uncertainNativeIdentities();
    for(const m of f.frames){m.domain=domain;if(Object.hasOwn(m,'profile'))m.profile=profile;if(Object.hasOwn(m,'source'))m.source=source;}
    const final=f.frames[7],data=terminal(profile==='held');
    final.terminal=data.terminal;final.genuine_denials=10;final.checks=100;
    if(profile==='held'){final.native_result=null;final.post_guards=12;final.guard_order=final.guard_order.slice(0,22);final.barrier_reads=1;final.metadata_checks=1;final.zero_dml_checks=1;final.barrier_closed_before_finish=false;}
    return {source,frames:f.frames,lines:f.frames.map(m=>JSON.stringify(m)+'\n')};
}
const text=s=>Buffer.concat([Buffer.from([s.length]),Buffer.from(s,'ascii')]);
export function nativeTimeoutFixture() {
    let physical=0;
    return failureDrain().map(([d,n,b])=>{
        if(d==='server'&&n===13)b=Buffer.concat(['104','local','fixture-host','10004','1','10.6.23-MariaDB','1',physical++===0?'1':'0','0','0'].map(text));
        return [d,n,b];
    });
}
