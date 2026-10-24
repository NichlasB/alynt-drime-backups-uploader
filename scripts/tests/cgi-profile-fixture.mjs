// Standalone marked fixture only. No WordPress tree or recursive cleanup.
import {mkdirSync,writeFileSync,readFileSync,realpathSync,readdirSync,lstatSync,unlinkSync,rmdirSync,existsSync} from 'node:fs';
import {tmpdir} from 'node:os';import path from 'node:path';import {randomBytes,createHash} from 'node:crypto';
const sha=v=>createHash('sha256').update(v).digest('hex');
const source=readFileSync(new URL('./cgi-profile-probe.php',import.meta.url));
const config='max_execution_time=1\nmax_input_time=5\npost_max_size=16K\nmemory_limit=32M\nmax_input_vars=16\noutput_buffering=0\nimplicit_flush=1\ndisplay_errors=0\nlog_errors=1\nhtml_errors=0\nauto_prepend_file=\nauto_append_file=\nuser_ini.filename=\nsession.auto_start=0\nallow_url_fopen=0\ncgi.fix_pathinfo=0\ndisable_functions=exec,shell_exec,system,passthru,proc_open,popen,dl\n';
const check=(value,label)=>{if(!value)throw Error(label);};
export function createCgiFixture(){
    const marker=randomBytes(16).toString('hex'),root=path.join(realpathSync(tmpdir()),`alynt-cgi-profile-${marker}`);
    const owner=JSON.stringify({marker,source:sha(source),config:sha(config)});
    mkdirSync(root);writeFileSync(path.join(root,'owner.json'),owner,{flag:'wx'});
    writeFileSync(path.join(root,'php.ini'),config,{flag:'wx'});writeFileSync(path.join(root,'probe.php'),source,{flag:'wx'});
    return Object.freeze({root,marker,ini:path.join(root,'php.ini'),script:path.join(root,'probe.php').replaceAll('\\','/'),remove(){
        check(path.dirname(root)===realpathSync(tmpdir())&&path.basename(root)===`alynt-cgi-profile-${marker}`&&realpathSync(root)===root&&!lstatSync(root).isSymbolicLink(),'cleanup_root');
        const files=readdirSync(root);check(files.every(f=>['owner.json','php.ini','probe.php','stage.json','shutdown.json','process.json','diagnostic.json'].includes(f)),'cleanup_inventory');
        // Validate every file before reading through it or removing any file.
        for(const file of files){const s=lstatSync(path.join(root,file));check(s.isFile()&&!s.isSymbolicLink()&&s.size<16384,'cleanup_file');}
        check(readFileSync(path.join(root,'owner.json'),'utf8')===owner,'cleanup_owner');
        check(readFileSync(path.join(root,'php.ini'),'utf8')===config&&sha(readFileSync(path.join(root,'probe.php')))===sha(source),'cleanup_sources');
        for(const file of files)unlinkSync(path.join(root,file));rmdirSync(root);check(!existsSync(root),'cleanup_absence');
        return true;
    }});
}
