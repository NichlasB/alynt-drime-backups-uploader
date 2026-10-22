// Test-only DATA successor. No filesystem, clock, process, IPC or networking imports.
import { exact, token } from './selected-qualification-grammar.mjs';
export const waitDomain = 'alynt.uncertain-barrier.wait-window.v2';
// Unchanged historical native base, not a seal or authorization for this successor.
export const nativeBase = '2ac6e5fb44961d21ff4f04996e1f011b8aaa4ed8534746e0711220713c8066f4';
export const queryGap = 125000000n, windowLength = 2500000000n;
const roleKeys = ['auth','capture','writer','barrier','blocker','monitor'];
const thread = n => Number.isInteger(n) && n > 0 && n <= 4294967295;
export function validWaitContext(c) {
    return exact(c,['attempt','root','source','roles','table']) && token(c.attempt) && token(c.root) && c.attempt !== c.root && c.source === nativeBase &&
        exact(c.roles,roleKeys) && Object.values(c.roles).every(thread) && new Set(Object.values(c.roles)).size === 6 &&
        typeof c.table === 'string' && /^wp_alynt_core_probe_[a-f0-9]{12}_options$/.test(c.table);
}
export function copyWaitContext(c) {
    if (!validWaitContext(c)) throw Error('fixed_wait_context');
    const clone = JSON.parse(JSON.stringify(c)); Object.freeze(clone.roles); return Object.freeze(clone);
}
export function exactWaitTuple(t,c) {
    return exact(t,['requester','blocker','monitor','table','index','role']) && t.requester === c.roles.barrier && t.blocker === c.roles.blocker &&
        t.monitor === c.roles.monitor && t.table === c.table && t.index === 'option_name' && t.role === 'safety';
}
const fields = {hello:['thread'],arm:[],armed:['zero_dml'],execute:['statement'],waiting:['requester','blocker','monitor','table','index','role','queries'],finish:[],closed:['zero_dml'],refused:['reason']};
export function parseWaitLine(bytes,c) {
    if (arguments.length!==2 || !validWaitContext(c) || !Buffer.isBuffer(bytes) || !bytes.length || bytes.length > 4096 || bytes.at(-1) !== 10) return false;
    const text = bytes.subarray(0,-1).toString('ascii');
    if (!bytes.subarray(0,-1).every(n => n >= 32 && n <= 126 && n !== 92)) return false;
    let depth=0,quoted=false;
    for (const char of text) { if(char==='"')quoted=!quoted;else if(!quoted && (char==='{'||char==='[') && ++depth>8)return false;else if(!quoted && (char==='}'||char===']'))depth--; }
    let m;try { m=JSON.parse(text); } catch { return false; }
    if (!m || !Object.hasOwn(fields,m.kind) || JSON.stringify(m)!==text || !exact(m,['domain','v','kind','context',...fields[m.kind]]) ||
        m.domain !== waitDomain || m.v !== 2 || !validWaitContext(m.context) || JSON.stringify(m.context)!==JSON.stringify(c)) return false;
    if(m.kind==='hello')return m.thread===c.roles.monitor ? m:false;
    if(['armed','closed'].includes(m.kind))return m.zero_dml===true ? m:false;
    if(m.kind==='execute')return thread(m.statement) ? m:false;
    if(m.kind==='waiting') {
        const {requester,blocker,monitor,table,index,role,queries}=m;
        return exactWaitTuple({requester,blocker,monitor,table,index,role},c) && Number.isInteger(queries) && queries>=1 && queries<=125 ? m:false;
    }
    if(m.kind==='refused')return ['expired','credit','query','transport','closed'].includes(m.reason) ? m:false;
    return m;
}
