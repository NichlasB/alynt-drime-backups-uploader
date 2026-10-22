// Fixed bounded policy load; pure direct-frame validation has no native/process/clock calls.
import { exact, token, thread } from './uncertain-barrier-native-grammar.mjs';
import { readFileSync, statSync } from 'node:fs';
const policyUrl=new URL('./uncertain-barrier-helper-diagnostics.json',import.meta.url);
if(statSync(policyUrl).size>4096)throw Error('direct_failure_policy');
const failurePolicy=JSON.parse(readFileSync(policyUrl,'utf8'));
export function validHelperFailure(f,role) {
    return ['blocker','monitor'].includes(role)&&exact(f,['stage','reason','code'])&&[...failurePolicy.common,...failurePolicy[role]].includes(f.stage)&&failurePolicy.reasons.includes(f.reason)&&failurePolicy.codes.includes(f.code)&&(f.reason==='database'||f.code===0);
}
export function validDirectFrame(m,role,context) {
    if(arguments.length!==3||!['blocker','monitor'].includes(role)||!m||m.domain!=='alynt.uncertain-barrier.direct.v1')return false;
    if(m.kind==='hello')return context===null&&exact(m,['domain','kind','role','thread'])&&m.role===role&&thread(m.thread);
    if(m.kind==='refused'&&context===null)return exact(m,['domain','kind','role','failure'])&&m.role===role&&validHelperFailure(m.failure,role);
    if(!exact(context,['attempt','root','source','roles'])||!token(context.attempt)||!token(context.root)||!token(context.source)||context.attempt===context.root||!exact(context.roles,['auth','capture','writer','barrier','blocker','monitor'])||!Object.values(context.roles).every(thread)||new Set(Object.values(context.roles)).size!==6||JSON.stringify(m.context)!==JSON.stringify(context))return false;
    if(m.kind==='refused')return exact(m,['domain','kind','role','failure','context'])&&m.role===role&&validHelperFailure(m.failure,role);
    const fields=role==='blocker'?{ready:['blocker','barrier','zero_dml'],released:['rollback_acknowledged','zero_dml'],closed:['role','zero_dml']}:{waiting:['requester','blocker','monitor','owned_table','role','queries'],closed:['role','zero_dml']};
    if(!Object.hasOwn(fields,m.kind)||!exact(m,['domain','kind',...fields[m.kind],'context']))return false;
    if(m.kind==='closed')return m.role===role&&m.zero_dml===true;
    if(m.kind==='ready')return m.blocker===context.roles.blocker&&m.barrier===context.roles.barrier&&m.zero_dml===true;
    if(m.kind==='released')return m.rollback_acknowledged===true&&m.zero_dml===true;
    return m.requester===context.roles.barrier&&m.blocker===context.roles.blocker&&m.monitor===context.roles.monitor&&m.owned_table===true&&m.role==='safety'&&Number.isInteger(m.queries)&&m.queries>=1&&m.queries<=125;
}
