import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { copyWaitContext,parseWaitLine,queryGap,windowLength,waitDomain } from '../../scripts/tests/wait-witness-contract.mjs';
import { createWaitWitnessWindow } from '../../scripts/tests/wait-witness-window.mjs';
import { createWaitExecuteCredit } from '../../scripts/tests/wait-witness-credit.mjs';
import { rowQuery } from '../../scripts/tests/uncertain-barrier-native-statements.mjs';
import { context,line,tuple,ms,armed,triggered,complete,creditFixture,packet,command } from './wait-witness-fixture.mjs';

test('fixed limits, detached context and no authority or write-capable interface',()=>{
    assert.equal(queryGap,125000000n);assert.equal(windowLength,2500000000n);
    const c=context(),copy=copyWaitContext(c);c.roles.monitor=999;assert.equal(copy.roles.monitor,106);assert.equal(Object.isFrozen(copy.roles),true);
    assert.deepEqual(Object.keys(createWaitWitnessWindow(context(),0n)),['feed','beginQuery','endQuery','finish']);
    assert.deepEqual(Object.keys(createWaitExecuteCredit(context())),['frame','bind','forwarded','account']);
    for(const name of ['contract','credit','window']){const raw=readFileSync(new URL(`../../scripts/tests/wait-witness-${name}.mjs`,import.meta.url),'utf8');assert.doesNotMatch(raw,/node:|\b(?:process|setTimeout|setInterval|Date|performance|fetch|eval)\s*[.(]/);}
});
test('wrong source/root, colliding roles, application tables, getters and extra handles refuse construction',()=>{
    const bad=[];for(const [key,value] of [['source','c'.repeat(64)],['root','a'.repeat(64)],['table','wp_options']])bad.push({...context(),[key]:value});
    const dup=context();dup.roles.monitor=dup.roles.blocker;bad.push(dup,{...context(),handle:()=>true});
    const getter=context();Object.defineProperty(getter,'root',{get(){throw Error('protected_read');},enumerable:true});bad.push(getter);
    for(const c of bad){assert.throws(()=>createWaitWitnessWindow(c,0n),/fixed_wait_context/);assert.throws(()=>createWaitExecuteCredit(c),/fixed_wait_context/);}
    for(const tick of [-1n,0,Infinity,null])assert.throws(()=>createWaitWitnessWindow(context(),tick),/fixed_wait_window_arguments/);
});
test('canonical successor bytes reject old domain, malformed/stale/extra/deep/duplicate intent and invalid query bounds',()=>{
    const c=context(),good=line('hello',c,{thread:106});assert.equal(parseWaitLine(good,c).domain,waitDomain);
    const bad=[Buffer.from(good.toString().replace(waitDomain,'alynt.uncertain-barrier.direct.v1')),Buffer.from(good.toString().replace('"v":2','"v":1')),
        Buffer.from(good.toString().replace('"v":2','"v":2,"v":2')),Buffer.from(good.toString().replace('"v":2','"v":2.0')),
        Buffer.from(good.toString().replace('"hello"','"he\\u006clo"')),Buffer.from(' '.repeat(4096)+'\n'),Buffer.from('{}\n{}\n'),good.subarray(0,-1),
        line('hello',{...c,root:'d'.repeat(64)},{thread:106}),line('hello',c,{thread:106,handle:{}}),Buffer.from('[ '.repeat(9)+' ]'.repeat(9)+'\n'),Buffer.from([255,10])];
    for(const b of bad)assert.equal(parseWaitLine(b,c),false);
    for(const queries of [-1,0,126,1.5,'1'])assert.equal(parseWaitLine(line('waiting',c,{...tuple(c),queries}),c),false);
    for(const queries of [1,125])assert.notEqual(parseWaitLine(line('waiting',c,{...tuple(c),queries}),c),false);
});
for(const width of [1,3,4096])test(`fragmented HELLO/ARM/ARMED and terminal transcript width${width}`,()=>{
    const c=context(),w=createWaitWitnessWindow(c,0n);
    for(const [d,b] of [['worker',line('hello',c,{thread:106})],['parent',line('arm',c)],['worker',line('armed',c,{zero_dml:true})]])for(let n=0;n<b.length;n+=width)assert.equal(w.feed(d,b.subarray(n,n+width),0n),true);
    const r=creditFixture().receipt(),b=line('execute',c,{statement:r.statement});
    for(let n=0;n<b.length;n+=width){const chunk=b.subarray(n,n+width);assert.equal(n+width>=b.length?w.feed('parent',chunk,0n,r.receipt):w.feed('parent',chunk,0n),true);}
    complete({window:w,c},ms(125),width);
});
test('unfinished/oversize/cross-direction/trailing frames, duplicate arm and early execute poison',()=>{
    const c=context(),w=createWaitWitnessWindow(c,0n),b=line('hello',c,{thread:106});assert.equal(w.feed('worker',b.subarray(0,1),0n),true);assert.equal(w.feed('parent',b.subarray(1),0n),false);
    for(const bytes of [Buffer.alloc(4097,32),Buffer.concat([b,b]),Buffer.from('x\n')]){const p=createWaitWitnessWindow(c,0n);assert.equal(p.feed('worker',bytes,0n),false);assert.equal(p.finish().synthetic_match,false);}
    const a=armed();assert.equal(a.window.feed('parent',line('arm',a.c),0n),false);
    const e=createWaitWitnessWindow(c,0n),r=creditFixture().receipt();assert.equal(e.feed('parent',line('execute',c,{statement:r.statement}),0n,r.receipt),false);
});
test('foreign owned table and every unsafe credit direction refuse without touching prototypes',()=>{
    const f=creditFixture();f.pre();const foreign=rowQuery('wp_alynt_core_probe_abcdef012345_options',0);
    assert.equal(f.owner.frame('barrier','client',packet(Buffer.concat([Buffer.from([22]),Buffer.from(foreign)])),Symbol()),false);
    for(const direction of ['__proto__','constructor','toString','worker',null]){
        const owner=createWaitExecuteCredit(context());assert.equal(owner.forwarded(direction,undefined,undefined),false);assert.equal(Object.hasOwn(Object.prototype,'forwarded'),false);
        assert.equal(createWaitExecuteCredit(context()).account(direction,undefined),false);
    }
});
test('source-backed metadata/safety requests remain exact; no new native imports or changed source closure',async()=>{
    const base=new URL('../../',import.meta.url),metadata=readFileSync(new URL('includes/trait-native-options-metadata.php',base),'utf8');
    const sql=[...metadata.matchAll(/^\s*'(SELECT [^']+)' =>/gm)].map(m=>m[1]);
    sql.push(metadata.match(/->prepare\( '(SELECT CAST\(COUNT\(\*\) AS CHAR\) FROM information_schema.KEY_COLUMN_USAGE[^']+)' \)/)[1]);
    const {metadataQueries}=await import('../../scripts/tests/uncertain-barrier-native-statements.mjs');assert.deepEqual(sql,metadataQueries);
    const native=readFileSync(new URL('includes/trait-registration-observation-barrier-rows.php',base),'utf8').match(/->prepare\( "([^"\n]+)" \)/)[1];
    assert.equal(rowQuery(context().table,0),native.replaceAll('{$table}',context().table).replaceAll('{$cap}','131072'));
    // Read-only closure verification is not native execution or a successor reseal.
    const {verifyUncertainNativeIdentity}=await import('../../scripts/tests/uncertain-barrier-native-identity.mjs');assert.equal(verifyUncertainNativeIdentity(),true);
});
test('required guard removals are assertion-killed in memory, not merely import failures',async()=>{
    const cases=[
        ['window','tick<notBefore','false',factory=>{const f=triggered(factory);assert.equal(f.window.beginQuery(ms(20)),false);} ],
        ['window','tick>=deadline','false',factory=>{const f=triggered(factory);assert.equal(f.window.beginQuery(ms(2500)),false);} ],
        ['window','tick<last','false',factory=>{const f=triggered(factory);assert.equal(f.window.beginQuery(ms(125)),true);assert.equal(f.window.endQuery(null,ms(124)),false);} ],
        ['window',"phase!=='quiet'",'false',factory=>{assert.equal(factory(context(),0n).beginQuery(ms(125)),false);} ],
        ['window','credited!==m.statement','false',factory=>{const f=armed(factory);assert.equal(f.window.feed('parent',line('execute',f.c,{statement:11}),0n,{}),false);} ],
        ['window','!exactWaitTuple(tuple,c)','false',factory=>{const f=triggered(factory);assert.equal(f.window.beginQuery(ms(125)),true);assert.equal(f.window.endQuery({...tuple(f.c),index:'foreign'},ms(125)),false);} ],
        ['credit','!p.forwarded','false',factory=>{const f=creditFixture(factory);f.pre();const id=f.prepare(rowQuery(f.c.table,0)),key=Symbol();assert.equal(f.owner.frame('barrier','client',command(23,id,10),key),true);assert.equal(f.owner.account('client',key),false);} ],
        ['credit','bytes!==p.size','false',factory=>{const f=creditFixture(factory);f.pre();const id=f.prepare(rowQuery(f.c.table,0)),key=Symbol(),p=command(23,id,10);assert.equal(f.owner.frame('barrier','client',p,key),true);assert.equal(f.owner.forwarded('client',key,p.length-1),false);} ],
        ['credit','used.has(credit)','false',factory=>{const f=creditFixture(factory);f.pre();const id=f.prepare(rowQuery(f.c.table,0)),key=Symbol(),p=command(23,id,10);assert.equal(f.owner.frame('barrier','client',p,key),true);assert.equal(f.owner.forwarded('client',key,p.length),true);assert.equal(typeof f.owner.account('client',key),'object');assert.equal(f.owner.frame('barrier','client',command(25,id,5),key),false);} ],
        ['credit','p.credit!==credit || p.forwarded','false || p.forwarded',factory=>{const f=creditFixture(factory);f.pre();const id=f.prepare(rowQuery(f.c.table,0)),p=command(23,id,10);assert.equal(f.owner.frame('barrier','client',p,Symbol()),true);assert.equal(f.owner.forwarded('client',Symbol(),p.length),false);} ],
        ['credit',"bytes.subarray(1).toString('ascii')!==rowQuery(c.table,0)",'false',factory=>{const f=creditFixture(factory);f.pre();const sql=rowQuery('wp_alynt_core_probe_abcdef012345_options',0);assert.equal(f.owner.frame('barrier','client',packet(Buffer.concat([Buffer.from([22]),Buffer.from(sql)])),Symbol()),false);} ],
    ];
    for(const [name,from,to,check] of cases){
        const url=new URL(`../../scripts/tests/wait-witness-${name}.mjs`,import.meta.url),raw=readFileSync(url,'utf8');assert.equal(raw.split(from).length,2);
        const code=raw.replace(from,to).replace(/from '(\.\/[^']+)'/g,(_,p)=>`from '${new URL(p,url).href}'`);
        const mod=await import('data:text/javascript;base64,'+Buffer.from(code).toString('base64'));
        assert.throws(()=>check(name==='window'?mod.createWaitWitnessWindow:mod.createWaitExecuteCredit),assert.AssertionError,`${name}: ${from}`);assert.equal(readFileSync(url,'utf8'),raw);
    }
});
