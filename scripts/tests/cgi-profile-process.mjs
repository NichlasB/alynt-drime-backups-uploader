// Standalone owned CGI only. Never starts, stops or connects to an existing LocalWP service.
import {spawn,execFile} from 'node:child_process';import {promisify} from 'node:util';import {fileURLToPath} from 'node:url';
import {createServer} from 'node:net';import {realpathSync} from 'node:fs';import path from 'node:path';
import {resolveOwnedStdioRuntime} from './owned-stdio-runtime.mjs';
import {boundedSettlement,closeOwnedChild} from './selected-native-lifetime.mjs';
const exec=promisify(execFile),inspect=fileURLToPath(new URL('./cgi-profile-inspect.ps1',import.meta.url));
export async function inspectCgi(mode,args=[],timeout=5000){
    if(!Number.isInteger(timeout)||timeout<1||timeout>5000)throw Error('cgi_inspection_budget');
    const {stdout,stderr}=await exec('C:/Windows/System32/WindowsPowerShell/v1.0/powershell.exe',['-NoProfile','-NonInteractive','-File',inspect,'-Mode',mode,...args],{windowsHide:true,timeout,maxBuffer:32768,env:{...process.env,PSModulePath:'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\Modules'}});
    if(stderr)throw Error('inspection_stderr');return JSON.parse(stdout);
}
export function resolveCgi(){const cli=resolveOwnedStdioRuntime('plugin-tester');const cgi=cli.replace(/\/php\.exe$/,'/php-cgi.exe');if(cgi===cli||realpathSync(cgi).replaceAll('\\','/')!==cgi)throw Error('cgi_runtime');return cgi;}
export async function reserveCgiPort(){
    const server=createServer();server.on('connection',s=>s.destroy());
    await new Promise((resolve,reject)=>{server.once('error',reject);server.listen({host:'127.0.0.1',port:0},resolve);});
    const port=server.address().port;await new Promise(resolve=>server.close(resolve));
    if(port<1024||port>65535||[10002,10003,10004,10005].includes(port))throw Error('reserved_port');return port;
}
export function startCgi(cgi,ini,port,marker,mode,remaining=null){
    if(remaining!==null&&typeof remaining!=='function')throw Error('cgi_cleanup_budget');
    const budget=cap=>{const ms=remaining===null?cap:remaining();if(!Number.isInteger(ms)||ms<1)throw Error('cgi_cleanup_budget');return Math.min(cap,ms);};
    if(cgi!==resolveCgi()||!Number.isInteger(port)||port<1024||port>65535||[10002,10003,10004,10005].includes(port)||!['no_fault','refusal','native_timeout','disconnect','stalled_input'].includes(mode)||!/^[a-f0-9]{32}$/.test(marker)||realpathSync(ini).replaceAll('\\','/').split('/').slice(-2).join('/')!==`alynt-cgi-profile-${marker}/php.ini`)throw Error('cgi_owner');
    const env={SystemRoot:process.env.SystemRoot,WINDIR:process.env.WINDIR,TEMP:process.env.TEMP,TMP:process.env.TMP,PATH:path.dirname(cgi)+';C:/Windows/System32',PHP_INI_SCAN_DIR:'',PHP_FCGI_CHILDREN:'0',PHP_FCGI_MAX_REQUESTS:'0',ALYNT_CGI_MARKER:marker,ALYNT_CGI_CASE:mode};
    const child=spawn(cgi,['-c',ini,'-b',`127.0.0.1:${port}`],{env,cwd:path.dirname(ini),windowsHide:true,shell:false,stdio:['pipe','pipe','pipe']});
    let exited=false,closed=false,overflow=false,stdout=0,stderr=0,error=false,privateStderr='',privateStdout='',startError=null;
    child.stdin.on('error',()=>{error=true;});child.stdin.end();
    child.stdout.on('data',b=>{stdout+=b.length;if(stdout>4096)overflow=true;else privateStdout+=b.toString('utf8');});child.stderr.on('data',b=>{stderr+=b.length;if(stderr>4096)overflow=true;else privateStderr+=b.toString('utf8');});
    child.once('exit',()=>{exited=true;});child.once('error',e=>{error=true;startError=e.code??'spawn_error';});
    const completion=new Promise(resolve=>child.once('close',code=>{closed=true;resolve({closed:true,code});}));
    const args=['-OwnedPid',String(child.pid),'-OwnerPid',String(process.pid),'-Port',String(port),'-Cgi',cgi,'-Ini',ini];let identity=null;
    return {child,alive:()=>!exited&&!closed&&!error,privateStderr:()=>privateStderr,diagnostics:()=>({pid:child.pid,exit:child.exitCode,signal:child.signalCode,exited,closed,start_error:startError,identity,stdout:privateStdout,stderr:privateStderr}),async verify(listener=true){if(exited||closed||error)throw Error('cgi_startup_exit');const r=await inspectCgi('owned',args,budget(5000));identity=r;if(r.owned!==true||r.console_valid!==true||(listener&&r.listener!==true))throw Error('cgi_identity');return r;},async close(){
        if(!closed&&!exited)await this.verify(false);
        const result=await closeOwnedChild(child,completion,budget(2000));const portState=await inspectCgi('closed',['-Port',String(port),'-ConsolePid',String(identity?.console_pid??0)],budget(5000));
        if(!result.resolved||!result.pipes_destroyed||!closed||!exited||!portState.closed)throw Error('cgi_cleanup');
        return {exited,closed,pipes_destroyed:true,listener_closed:true,overflow,stderr_bytes:stderr,stdout_bytes:stdout,error};
    },async joined(ms){return boundedSettlement(completion,ms);}};
}
