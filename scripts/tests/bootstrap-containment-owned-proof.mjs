// Explicit approved-local operator. Importing it never opens a process or database.
import {spawn} from 'node:child_process';
import {readFileSync,existsSync,realpathSync} from 'node:fs';
import {createHash,randomBytes} from 'node:crypto';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const here=path.dirname(fileURLToPath(import.meta.url));
const root='C:/Users/Captain/Local Sites/plugin-tester/app/public';
const services='C:/Users/Captain/AppData/Roaming/Local/lightning-services';
const mysql=services+'/mariadb-10.6.23+0/bin/win32/bin/mysql.exe';
const php=services+'/php-8.5.1+1/bin/win64/php.exe';
const check=(value,code)=>{if(!value)throw Error(code);};
const sha=value=>createHash('sha256').update(value).digest('hex');
export function fixtureTable(prefix){check(/^wp_alynt_bootstrap_probe_[a-f0-9]{12}_$/.test(prefix),'prefix_scope');return prefix+'options';}
export function validateReceipt(r,profile){
    return r?.passed===true&&r.profile===profile&&r.route_status==='blocked_before_installation_check'&&r.route_reached===false&&r.authentication_reached===false&&r.owner_reached===false&&r.request_completed_data_queries===0&&r.request_cached_protected_values===0&&r.request_attempts?.length===1&&r.request_attempts[0]==='bulk_autoload'&&r.cold_alloptions===true&&r.dispatch_before_execute===true&&r.safe_read_completed===true&&r.prevented_control_queries===5&&r.semantic_control_assertion_killed===true&&r.control_only_completed_canary_reads===2&&r.control_cache_hydrated===true&&JSON.stringify(r.guarded_option_branch)===JSON.stringify(profile==='fallback'?['bulk_autoload','bulk_all']:['bulk_autoload'])&&r.native_execution===false&&r.admission===false&&r.production_wiring===false;
}
function child(file,args,input,ms){
    return new Promise((resolve,reject)=>{
        const p=spawn(file,args,{windowsHide:true,stdio:['pipe','pipe','pipe']});let out='',err='',reason=null,done=false;
        const timer=setTimeout(()=>{reason='owned_child_deadline';p.kill();},ms);
        const capture=(chunk,stderr)=>{if(stderr)err+=chunk;else out+=chunk;if(out.length+err.length>262144){reason='owned_child_output_bound';p.kill();}};
        p.stdout.on('data',v=>capture(v,false));p.stderr.on('data',v=>capture(v,true));p.stdin.on('error',()=>{reason??='owned_child_stdin';});
        p.on('error',()=>{clearTimeout(timer);done=true;reject(Error('owned_child_start'));});
        p.on('close',(code,signal)=>{if(done)return;done=true;clearTimeout(timer);if(reason||code!==0||signal||err!=='')reject(Error(reason??'owned_child_refused'));else resolve(out);});
        p.stdin.end(input);
    });
}
async function sql(statement){return (await child(mysql,['-h','127.0.0.1','-P','10004','-u','root','-D','local','-N','-B','-r','--connect-timeout=3'],`SET SESSION lock_wait_timeout=2; SET SESSION max_statement_time=5; ${statement};`,15000)).trimEnd();}
async function inventory(){
    const names=(await sql('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name')).split(/\r?\n/);
    check(names.length>=3&&names.every(v=>/^[A-Za-z0-9_]+$/.test(v)),'inventory_scope');
    const checksums=await sql('CHECKSUM TABLE '+names.map(v=>'`'+v+'`').join(',')+' EXTENDED');
    check(checksums.split(/\r?\n/).length===names.length&&checksums.split(/\r?\n/).every(v=>/^local\.[A-Za-z0-9_]+\t[0-9]+$/.test(v)),'checksum_unavailable');
    return {names,digest:sha(checksums)};
}
function sourceSnapshot(){
    const files=['wp-settings.php','wp-includes/version.php','wp-includes/class-wpdb.php','wp-includes/load.php','wp-includes/functions.php','wp-includes/option.php','wp-includes/cache.php','wp-includes/class-wp-object-cache.php','wp-includes/plugin.php','wp-includes/class-wp-hook.php','wp-admin/tools.php','wp-admin/admin-post.php','wp-config.php'];
    const data=Object.fromEntries(files.map(f=>[f,sha(readFileSync(root+'/'+f))]));
    for(const f of ['class-registration-closed-entry-request-owner.php','trait-registration-closed-entry-scope.php'])data['owner/'+f]=sha(readFileSync(path.join(here,'../../includes',f)));
    return data;
}
async function main(){
    const receipt={proof:'pre_owner_bootstrap_containment_feasibility_not_http_authentication',passed:false,cases:[],cleanup:[],admission:false,production_wiring:false};let before=null,sources=null;
    try{
        check(process.env.MYSQL_PWD&&existsSync(mysql)&&existsSync(php),'runtime_preflight');
        const sites=JSON.parse(readFileSync('C:/Users/Captain/AppData/Roaming/Local/sites.json','utf8'));
        const matches=Object.values(sites).filter(s=>s.domain==='plugin-tester.local');
        check(matches.length===1&&realpathSync(matches[0].path+'/app/public')===realpathSync(root)&&matches[0].services.mariadb.ports.MYSQL[0]===10004&&matches[0].services.nginx.ports.HTTP[0]===10005&&matches[0].services.mariadb.version==='10.6.23'&&matches[0].services.php.version==='8.5.1','target_metadata');
        check((await sql("SELECT option_value FROM wp_options WHERE option_name='siteurl'"))==='http://plugin-tester.local','database_origin');
        before=await inventory();check(before.names.every(v=>!v.startsWith('wp_alynt_bootstrap_probe_')),'preexisting_fixture');sources=sourceSnapshot();
        check(/private function entry_available\(\)[\s\S]*?return false;/.test(readFileSync(path.join(here,'../../includes/class-registration-closed-entry-request-owner.php'),'utf8')),'disabled_admission');
        for(const profile of ['autoload','fallback']){
            const prefix='wp_alynt_bootstrap_probe_'+randomBytes(6).toString('hex')+'_',table=fixtureTable(prefix),marker=randomBytes(12).toString('hex');let created=false;
            try{
                check((await sql(`SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='${table}'`))==='0','fixture_collision');
                await sql(`CREATE TABLE ${table} LIKE wp_options`);created=true;
                const autoload=profile==='autoload'?'yes':'no';
                await sql(`INSERT INTO ${table}(option_name,option_value,autoload) VALUES ('proof_owner','${marker}','no'),('proof_safe','${marker}','no'),('alynt_drime_backups_settings','owned-canary-alynt_drime_backups_settings','${autoload}'),('alynt_drime_backups_schedule_safety_state','owned-canary-alynt_drime_backups_schedule_safety_state','${autoload}')`);
                const out=await child(php,['-n','-d','extension_dir='+path.dirname(php)+'/ext','-d','extension=mysqli','-d','display_errors=0','-d','log_errors=0',here+'/bootstrap-containment-worker.php','--owned-bootstrap',prefix,profile,marker],'',10000);
                const r=JSON.parse(out);receipt.cases.push(r);check(validateReceipt(r,profile),'worker_evidence');
            }finally{
                if(created){
                    // Exact freshly created table + private in-memory ownership marker.
                    check((await sql(`SELECT option_value FROM ${table} WHERE option_name='proof_owner'`))===marker,'cleanup_ownership');
                    const rows=Number(await sql(`SELECT COUNT(*) FROM ${table}`));check(rows===4,'cleanup_preview');
                    await sql(`DROP TABLE ${table}`);
                    const absent=(await sql(`SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='${table}'`))==='0';
                    receipt.cleanup.push({table,rows_previewed:rows,absent});check(absent,'cleanup_absence');
                }
            }
        }
        receipt.passed=true;
    }catch(error){receipt.failure=/^[a-z_]+$/.test(error.message)?error.message:'private_operator_failure';}
    finally{
        if(before){try{const after=await inventory();receipt.application_tables=before.names.length;receipt.application_preserved=before.digest===after.digest&&JSON.stringify(before.names)===JSON.stringify(after.names);receipt.owned_tables_absent=after.names.every(v=>!v.startsWith('wp_alynt_bootstrap_probe_'));}catch{receipt.application_preserved=false;}}
        if(sources){receipt.core_owner_config_unchanged=JSON.stringify(sources)===JSON.stringify(sourceSnapshot());receipt.source_sha256=sources;}
    }
    receipt.passed=receipt.passed&&receipt.application_preserved===true&&receipt.owned_tables_absent===true&&receipt.core_owner_config_unchanged===true;
    receipt.profile_qualification='Blocked';receipt.minimum_wp_6_runtime='not_available_not_executed';
    process.stdout.write(JSON.stringify(receipt,null,2)+'\n');if(!receipt.passed)process.exitCode=1;
}
if(path.resolve(process.argv[1]??'')===fileURLToPath(import.meta.url)){
    if(process.platform!=='win32'||process.argv.length!==3||process.argv[2]!=='--run-owned-bootstrap'){process.stdout.write('{"passed":false,"refused_before_operations":true}\n');process.exitCode=1;}
    else await main();
}
