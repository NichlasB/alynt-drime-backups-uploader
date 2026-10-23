// Fixed repository-source instrumentation in memory only; no disk mutation, native IO or injected production seam.
import test from 'node:test';import assert from 'node:assert/strict';import {readFileSync} from 'node:fs';import {createHash} from 'node:crypto';
const root=new URL('../../scripts/tests/',import.meta.url),names=['http-cgi-budget.mjs','http-cgi-headers.mjs','http-cgi-body.mjs','http-cgi-handoff.mjs'];
const sources=Object.fromEntries(names.map(n=>[n,readFileSync(new URL(n,root),'utf8')]));let serial=0;
const data=text=>`data:text/javascript;base64,${Buffer.from(text).toString('base64')}`;
const absolute=text=>text.replace(/from '(\.\/[^']+)'/g,(_m,p)=>`from '${new URL(p,root).href}'`);
const load=text=>import(data(absolute(text)));
function change(text,from,to){assert.equal(text.split(from).length,2,'one exact semantic target');return text.replace(from,to);}
const head=(n=3)=>({method:'POST',target:'/probe.php',version:'1.1',headers:['Host','owned.invalid','Connection','close','Content-Length',String(n),'Content-Type','application/x-www-form-urlencoded']});
const eof={complete:true,aborted:false,error:false},script='C:/owned/alynt-cgi-profile-'+ 'b'.repeat(32)+'/probe.php';
const killed=fn=>assert.throws(fn,e=>e instanceof assert.AssertionError,'must fail an outcome assertion after successful import');
function positiveBody(m){const b=new m.FixtureHttpBody(head(),0,0);assert.equal(b.push(Buffer.from('abc'),1),true);assert.equal(b.end(eof,2),true);assert.equal(b.take(3).body.toString(),'abc');b.cleanup(4);}
async function instrumentBody(text){
    const key=`alynt_http_body_${++serial}`,state={buffers:[],copies:0};globalThis[key]=state;
    const prefix=`const testState=globalThis[${JSON.stringify(key)}];function testAlloc(n){const b=Buffer.alloc(n),set=b.set.bind(b);testState.buffers.push(b);b.set=(...args)=>{testState.copies++;return set(...args)};return b;}\n`;
    text=change(text,'this.#buffer=Buffer.alloc(this.#head.length)','this.#buffer=testAlloc(this.#head.length)');
    return {module:await load(prefix+text),state,clear:()=>delete globalThis[key]};
}
async function bodyControl(from,to,exercise){
    const original=sources['http-cgi-body.mjs'],mutant=change(original,from,to);
    for(const [text,isMutant]of [[original,false],[mutant,true]]){const f=await instrumentBody(text);try{positiveBody(f.module);const run=()=>exercise(f.module,f.state);if(isMutant)killed(run);else run();}finally{f.clear();}}
}
test('original absolute cutoff control is assertion-killed, not an import error',async()=>{const source=sources['http-cgi-budget.mjs'];const exercise=m=>{const b=new m.HttpCgiBudget(100);assert.equal(b.check('headers',101),true);assert.equal(b.check('headers',1100),false);};exercise(await load(source));const m=await load(change(source,'now-this.#origin>=limits[index]','false'));killed(()=>exercise(m));});
for(const [name,from,to,negative]of [
    ['duplicate fields','||fields.has(lower)','',()=>{const h=head();h.headers.push('HOST','owned.invalid');return h;}],
    ['body ceiling','||Number(length)>16384','',()=>head(16385)]
])test(`${name} control is assertion-killed with valid positive header case`,async()=>{const source=sources['http-cgi-headers.mjs'];const exercise=m=>{assert.deepEqual(m.acceptFixtureHeaders(head()),{method:'POST',length:3});assert.throws(()=>m.acceptFixtureHeaders(negative()));};exercise(await load(source));const m=await load(change(source,from,to));killed(()=>exercise(m));});
test('body completeness control cannot substitute a closed/truncated parser message',async()=>bodyControl('d.complete.value!==true||','',(m)=>{const b=new m.FixtureHttpBody(head(),0,0);b.push(Buffer.from('abc'),1);assert.throws(()=>b.end({...eof,complete:false},2));}));
test('refusal/cleanup erasure control inspects actual retained allocation',async()=>bodyControl('this.#taken=true;this.#buffer.fill(0);','this.#taken=true;',(m,state)=>{const b=new m.FixtureHttpBody(head(),0,0);b.push(Buffer.from('abc'),1);b.cleanup(2);assert.ok(state.buffers.at(-1).every(v=>v===0));}));
test('take erasure control inspects retained allocation before cleanup',async()=>bodyControl('const body=Buffer.from(this.#buffer);this.#buffer.fill(0);','const body=Buffer.from(this.#buffer);',(m,state)=>{const b=new m.FixtureHttpBody(head(),0,0);b.push(Buffer.from('abc'),1);b.end(eof,2);assert.equal(b.take(3).body.toString(),'abc');assert.ok(state.buffers.at(-1).every(v=>v===0));}));
test('size guard must refuse before copying, not merely rely on typed-array RangeError',async()=>bodyControl('chunk.length>this.#buffer.length-this.#offset','false',(m,state)=>{const b=new m.FixtureHttpBody(head(),0,0);b.push(Buffer.from('a'),1);const before=state.copies;assert.throws(()=>b.push(Buffer.from('abcd'),2));assert.equal(state.copies,before);}));
test('post-encoding remaining-budget control cannot forward after original cutoff',async()=>{const source=sources['http-cgi-handoff.mjs'];const exercise=m=>{const h=new m.FixtureCgiHandoff(head(),0,0,script);h.push(Buffer.from('abc'),1);h.end(eof,2);assert.equal(h.prepare(3),true);assert.throws(()=>h.take(4000));};exercise(await load(source));const m=await load(change(source,'||this.#body.remainingHandoff(now)===0',''));killed(()=>exercise(m));});
async function builderFixture(source,mode){const key=`alynt_http_builder_${++serial}`,state={raw:null};globalThis[key]=state;
    const stub=`export function cgiRequest(_script,body){globalThis[${JSON.stringify(key)}].raw=body;${mode==='throw'?"throw Error('fixed_builder_failure')":"return 'not bytes'"}}`;
    const text=change(source,"from './cgi-profile-codec.mjs'",`from '${data(stub)}'`);
    return {module:await load(text),state,clear:()=>delete globalThis[key]};
}
for(const mode of ['throw','type'])test(`fixed builder ${mode} fails closed and erases its actual raw-byte argument`,async()=>{
    const source=sources['http-cgi-handoff.mjs'];const target=mode==='throw'?'raw?.body.fill(0);':"if(!Buffer.isBuffer(wire))throw Error('http_cgi_wire');";
    const mutant=change(source,target,'');
    for(const [text,isMutant]of [[source,false],[mutant,true]]){const f=await builderFixture(text,mode);try{
        const h=new f.module.FixtureCgiHandoff(head(),0,0,script);h.push(Buffer.from('abc'),1);h.end(eof,2);
        const run=()=>{assert.throws(()=>h.prepare(3),/^Error: http_cgi_handoff$/);assert.ok(f.state.raw.every(v=>v===0));assert.throws(()=>h.take(4));};if(isMutant)killed(run);else run();
    }finally{f.clear();}}
});
test('socket-free source imports/operations and disk SHA256s remain unchanged by controls',()=>{
    for(const name of names){const text=sources[name];assert.doesNotMatch(text,/from ['"]node:(?:net|http|https|fs|child_process)|\b(?:setTimeout|spawn|createServer|createConnection|fetch)\s*\(/);
        const digest=v=>createHash('sha256').update(v).digest('hex');assert.equal(digest(readFileSync(new URL(name,root),'utf8')),digest(text));}
});
