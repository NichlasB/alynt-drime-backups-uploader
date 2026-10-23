// Actual owner source with fixed process/inspection doubles. No sockets, PHP or OS processes.
import test from 'node:test';import assert from 'node:assert/strict';import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../../scripts/tests/cgi-profile-process.mjs',import.meta.url),'utf8');
const data=text=>`data:text/javascript;base64,${Buffer.from(text).toString('base64')}`;
async function fixture(kind='valid'){
    const key=`cgi_owner_${kind}_${Math.random()}`,marker='1'.repeat(32),ini=`C:/Temp/alynt-cgi-profile-${marker}/php.ini`,cgi='C:/fixed/php-cgi.exe';
    globalThis[key]={calls:[],kills:0};const state=globalThis[key];
    const inspection={owned:kind!=='foreign',console_valid:kind!=='console',console_pid:789,children:1,listener:kind!=='listener'};
    const shim=`import {EventEmitter} from 'node:events';import {promisify} from 'node:util';
      const state=globalThis[${JSON.stringify(key)}];
      export function spawn(exe,args,options){state.spawn={exe,args,options};const c=new EventEmitter();c.pid=456;c.exitCode=null;c.signalCode=null;
      for(const name of ['stdin','stdout','stderr']){c[name]=new EventEmitter();c[name].destroyed=false;c[name].destroy=()=>{c[name].destroyed=true};c[name].resume=()=>{};c[name].end=()=>{}}
      c.kill=()=>{state.kills++;c.signalCode='SIGTERM';queueMicrotask(()=>{c.emit('exit');c.emit('close',null)});};state.child=c;return c;}
      export function execFile(){};execFile[promisify.custom]=async(exe,args,options)=>{state.calls.push({exe,args,modulePath:options.env.PSModulePath,timeout:options.timeout});
      const mode=args[args.indexOf('-Mode')+1];return {stdout:JSON.stringify(mode==='owned'?${JSON.stringify(inspection)}:{closed:${kind!=='leak'}}),stderr:''}};`;
    let text=source.replace("from 'node:child_process'",`from '${data(shim)}'`)
      .replace("import {realpathSync} from 'node:fs';","const realpathSync=p=>p;")
      .replace("import {resolveOwnedStdioRuntime} from './owned-stdio-runtime.mjs';","const resolveOwnedStdioRuntime=()=> 'C:/fixed/php.exe';")
      .replace("from './selected-native-lifetime.mjs'",`from '${new URL('../../scripts/tests/selected-native-lifetime.mjs',import.meta.url).href}'`)
      .replace("fileURLToPath(new URL('./cgi-profile-inspect.ps1',import.meta.url))",JSON.stringify('C:/fixed/inspect.ps1'));
    assert.notEqual(text,source);const module=await import(data(text));
    return {state,marker,ini,cgi,module,clear:()=>delete globalThis[key]};
}
test('valid actual owner checks identity before killing and separately joins console/listener cleanup',async()=>{
    const f=await fixture();try{const owner=f.module.startCgi(f.cgi,f.ini,25000,f.marker,'no_fault');await owner.verify();const r=await owner.close();assert.equal(r.listener_closed,true);assert.equal(r.pipes_destroyed,true);assert.equal(f.state.kills,1);assert.equal(f.state.calls.length,3);
    assert.deepEqual(f.state.calls.map(c=>c.args[c.args.indexOf('-Mode')+1]),['owned','owned','closed']);assert.ok(f.state.calls[2].args.includes('789'));
    for(const call of f.state.calls){assert.match(call.modulePath,/Windows.*System32.*WindowsPowerShell.*Modules/);assert.equal(call.timeout,5000);}
    assert.equal(f.state.spawn.options.shell,false);assert.equal(f.state.spawn.options.env.PHP_FCGI_CHILDREN,'0');assert.equal(f.state.spawn.options.env.PHP_INI_SCAN_DIR,'');assert.equal(f.state.spawn.options.env.ALYNT_CGI_CASE,'no_fault');assert.equal(f.state.spawn.options.env.MYSQL_PWD,undefined);
    }finally{f.clear();}
});
for(const kind of ['foreign','console','listener'])test(`actual owner refuses ${kind} inspection without killing`,async()=>{
    const f=await fixture(kind);try{const owner=f.module.startCgi(f.cgi,f.ini,25000,f.marker,'no_fault');await assert.rejects(owner.verify(),/cgi_identity/);assert.equal(f.state.kills,0);}finally{f.clear();}
});
test('console/listener residue cannot count as cleanup success',async()=>{const f=await fixture('leak');try{const owner=f.module.startCgi(f.cgi,f.ini,25000,f.marker,'no_fault');await owner.verify();await assert.rejects(owner.close(),/cgi_cleanup/);assert.equal(f.state.kills,1);}finally{f.clear();}});
test('unowned path, marker, case and port refuse before spawn',async()=>{const f=await fixture();try{for(const args of [...[1,65536,10002,10003,10004,10005].map(p=>[f.cgi,f.ini,p,f.marker,'no_fault']),[f.cgi,f.ini,25000,'bad','no_fault'],[f.cgi,'C:/foreign/php.ini',25000,f.marker,'no_fault'],[f.cgi,f.ini,25000,f.marker,'activate']])assert.throws(()=>f.module.startCgi(...args));assert.equal(f.state.spawn,undefined);assert.equal(f.state.calls.length,0);}finally{f.clear();}});
