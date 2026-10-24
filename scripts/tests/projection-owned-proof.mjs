// Approved R3 operator only. No site/database/process operation on import or wrong flag.
import {spawn} from 'node:child_process';import {readFileSync,realpathSync,existsSync} from 'node:fs';
import {createHash,randomBytes} from 'node:crypto';import path from 'node:path';import {fileURLToPath} from 'node:url';
const here=path.dirname(fileURLToPath(import.meta.url)),root='C:/Users/Captain/Local Sites/plugin-tester/app/public';
const services='C:/Users/Captain/AppData/Roaming/Local/lightning-services';
const mysql=services+'/mariadb-10.6.23+0/bin/win32/bin/mysql.exe',php=services+'/php-8.5.1+1/bin/win64/php.exe';
const check=(v,code)=>{if(!v)throw Error(code);},sha=v=>createHash('sha256').update(v).digest('hex');
export function names(seed){check(typeof seed==='string'&&/^[a-f0-9]{12}$/.test(seed),'name_scope');const p='wp_alynt_ro_'+seed+'_';return {prefix:p,base:p+'src_',invoker:'alynt_ro_'+seed+'_i',definer:'alynt_ro_'+seed+'_d'};}
export function workerEnvironment(source){return {SystemRoot:source.SystemRoot??'C:/Windows',WINDIR:source.WINDIR??'C:/Windows',PATH:'C:/Windows/System32'};}
export function grantShape(text,tables){const lines=text.split(/\r?\n/);return lines.length===tables.length+1&&lines.filter(s=>s.startsWith('GRANT USAGE ON *.* TO ')&&!s.includes('WITH GRANT OPTION')).length===1&&tables.every(t=>lines.filter(s=>s.startsWith('GRANT SELECT ON `local`.`'+t+'` TO ')&&!s.includes('WITH GRANT OPTION')).length===1);}
export const value=s=>{check(typeof s==='string','sql_value_type');return "_utf8mb4 X'"+Buffer.from(s,'utf8').toString('hex')+"'";};
async function child(file,args,input,env,ms=10000){
    return new Promise((resolve,reject)=>{let out='',err='',reason=null,finished=false,join=null;
        const p=spawn(file,args,{env,windowsHide:true,stdio:['pipe','pipe','pipe']});
        const settle=(error,result)=>{if(finished)return;finished=true;clearTimeout(timer);clearTimeout(join);error?reject(error):resolve(result);};
        const stop=code=>{if(reason)return;reason=code;p.kill();join=setTimeout(()=>{const e=Error('owned_process_unsettled');e.unsettled=true;p.unref();p.stdout.destroy();p.stderr.destroy();p.stdin.destroy();settle(e);},2000);};
        const timer=setTimeout(()=>stop('owned_process_deadline'),ms);
        p.stdout.on('data',b=>{out+=b;if(out.length>262144)stop('owned_output_bound');});p.stderr.on('data',b=>{err+=b;if(err.length>32768)stop('owned_output_bound');});
        p.stdin.on('error',()=>{reason??='owned_input_error';});p.on('error',()=>settle(Error('owned_process_start')));
        p.on('close',(code,signal)=>settle(reason?Error(reason):null,{code,signal,out,err,closed:true}));p.stdin.end(input);
    });
}
async function sql(statement,password=process.env.MYSQL_PWD,user='root'){
    const r=await child(mysql,['-h','127.0.0.1','-P','10004','-u',user,'-D','local','-N','-B','-r','--connect-timeout=3'],`SET SESSION lock_wait_timeout=2; SET SESSION max_statement_time=5; ${statement};`,{...workerEnvironment(process.env),MYSQL_PWD:password},15000);
    if(r.code!==0||r.signal||r.err!==''){const e=Error('supervisor_sql_refused');e.databaseErrorCode=Number(/^ERROR ([0-9]+)/m.exec(r.err)?.[1]??0);throw e;}return r.out.trimEnd();
}
async function inventory(){
    const names=(await sql('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name')).split(/\r?\n/);check(names.length>=3&&names.every(s=>/^[a-zA-Z0-9_]+$/.test(s)),'inventory_scope');
    const sums=await sql('CHECKSUM TABLE '+names.map(n=>'`'+n+'`').join(',')+' EXTENDED');
    check(sums.split(/\r?\n/).length===names.length&&sums.split(/\r?\n/).every(s=>/^local\.[A-Za-z0-9_]+\t[0-9]+$/.test(s)),'application_checksum');return {names,digest:sha(sums)};
}
async function permissions(){const r=await sql('CHECKSUM TABLE mysql.global_priv,mysql.db,mysql.tables_priv,mysql.columns_priv,mysql.proxies_priv,mysql.roles_mapping EXTENDED');check(r.split(/\r?\n/).length===6&&r.split(/\r?\n/).every(s=>/^mysql\.[a-z_]+\t[0-9]+$/.test(s)),'permission_checksum');return sha(r);}
function sourceIdentity(){return Object.fromEntries(['wp-settings.php','wp-includes/version.php','wp-includes/class-wpdb.php','wp-includes/option.php','wp-includes/cache.php','wp-includes/class-wp-object-cache.php','wp-config.php'].map(f=>[f,sha(readFileSync(root+'/'+f))]));}
async function main(){
    const receipt={passed:false,cases:[],cleanup:[],admission:false,wiring:false};const seed=randomBytes(6).toString('hex'),n=names(seed),marker=randomBytes(12).toString('hex'),password=randomBytes(32).toString('hex'),definerPassword=randomBytes(32).toString('hex');
    let before=null,privBefore=null,sourceBefore=null,phase='preflight',unsettled=false;const tables=[],views=[],accounts=[];
    const account=u=>`'${u}'@'127.0.0.1'`;
    const keys=['siteurl','home','blogname','active_plugins','template','stylesheet','WPLANG',n.prefix+'user_roles'];
    const run=async mode=>{const r=await child(php,['-n','-d','extension_dir='+path.dirname(php)+'/ext','-d','extension=mysqli','-d','display_errors=0','-d','log_errors=0',here+'/projection-proof-worker.php','--owned-projection-worker'],JSON.stringify({seed,marker,password,mode}),workerEnvironment(process.env));
        let result;try{result=JSON.parse(r.out);}catch{throw Error('worker_receipt_missing');}receipt.cases.push(result);check(r.code===0&&!r.signal&&r.err===''&&result.passed===true,'worker_'+mode+'_refused');return result;};
    try{
        check(process.env.MYSQL_PWD&&existsSync(mysql)&&existsSync(php),'runtime');
        const sites=Object.values(JSON.parse(readFileSync('C:/Users/Captain/AppData/Roaming/Local/sites.json','utf8'))).filter(s=>s.domain==='plugin-tester.local');
        check(sites.length===1&&realpathSync(sites[0].path+'/app/public')===realpathSync(root)&&sites[0].services.mariadb.ports.MYSQL[0]===10004&&sites[0].services.mariadb.version==='10.6.23'&&sites[0].services.php.version==='8.5.1','target_metadata');
        check((await sql("SELECT option_value FROM wp_options WHERE option_name='siteurl'"))==='http://plugin-tester.local','origin');
        check((await sql('SELECT @@general_log,@@log_bin'))==='0\t0','credential_query_logging');
        before=await inventory();privBefore=await permissions();sourceBefore=sourceIdentity();
        check(before.names.every(t=>!t.startsWith('wp_alynt_ro_'))&&(await sql("SELECT COUNT(*) FROM mysql.user WHERE User REGEXP '^alynt_ro_[a-f0-9]+_(d|i)$'"))==='0','preexisting_owned_objects');
        check(!existsSync('C:/Users/Captain/Documents/AI Workflows/owned-projection-'+seed+'-absent'),'file_negative_scope');
        for(const suffix of ['options','users','usermeta']){phase='create_table';await sql(`CREATE TABLE ${n.base+suffix} LIKE wp_${suffix}`);tables.push(n.base+suffix);}
        phase='seed_owned_rows';
        const role='a:1:{s:13:"administrator";a:2:{s:4:"name";s:13:"Administrator";s:12:"capabilities";a:2:{s:4:"read";b:1;s:14:"manage_options";b:1;}}}';
        const safe=['http://plugin-tester.local','http://plugin-tester.local','Owned projection '+marker,'a:0:{}','owned-no-theme','owned-no-theme','',role];
        const rows=keys.map((k,i)=>`(${value(k)},${value(safe[i])},'yes')`);for(const k of ['alynt_drime_backups_settings','alynt_drime_backups_schedule_safety_state'])rows.push(`(${value(k)},${value('owned-projection-canary-'+k)},'yes')`);rows.push(`('proof_owner',${value(marker)},'no')`);
        await sql(`INSERT INTO ${n.base}options(option_name,option_value,autoload) VALUES ${rows.join(',')}`);
        await sql(`INSERT INTO ${n.base}users(ID,user_login,user_pass,user_nicename,user_email,user_url,user_registered,user_activation_key,user_status,display_name) VALUES(1,'owned_admin','owned-not-an-auth-proof','owned_admin','owned@example.invalid','','2026-01-01 00:00:00','',0,'Owned admin'),(2,'owned_subscriber','owned-not-an-auth-proof','owned_subscriber','owned2@example.invalid','','2026-01-01 00:00:00','',0,'Owned subscriber')`);
        await sql(`INSERT INTO ${n.base}usermeta(user_id,meta_key,meta_value) VALUES(1,${value(n.prefix+'capabilities')},'a:1:{s:13:"administrator";b:1;}'),(2,${value(n.prefix+'capabilities')},'a:1:{s:10:"subscriber";b:1;}'),(1,'session_tokens','a:0:{}'),(2,'session_tokens','a:0:{}')`);
        phase='create_owned_accounts';
        await sql(`CREATE USER ${account(n.definer)} IDENTIFIED BY '${definerPassword}' ACCOUNT LOCK`);accounts.push(n.definer);
        await sql(`CREATE USER ${account(n.invoker)} IDENTIFIED BY '${password}' WITH MAX_USER_CONNECTIONS 2`);accounts.push(n.invoker);
        check((await sql(`SELECT JSON_UNQUOTE(JSON_EXTRACT(Priv,'$.account_locked')) FROM mysql.global_priv WHERE User='${n.definer}' AND Host='127.0.0.1'`))==='true','definer_lock');receipt.definer_account_locked=true;
        for(const table of tables)await sql(`GRANT SELECT ON local.${table} TO ${account(n.definer)}`);
        check(grantShape(await sql(`SHOW GRANTS FOR ${account(n.definer)}`),tables),'definer_effective_privileges');receipt.definer_select_only=true;
        const columns={options:'option_id,option_name,option_value,autoload',users:'ID,user_login,user_pass,user_nicename,user_email,user_url,user_registered,user_activation_key,user_status,display_name',usermeta:'umeta_id,user_id,meta_key,meta_value'};
        phase='create_owned_views';
        for(const suffix of ['options','users','usermeta','unsafe']){
            const base=suffix==='unsafe'?'options':suffix,predicate=suffix==='options'?` WHERE option_name IN (${keys.map(value).join(',')})`:suffix==='users'?' WHERE ID IN (1,2)':suffix==='usermeta'?` WHERE user_id IN (1,2) AND meta_key IN (${value(n.prefix+'capabilities')},'session_tokens')`:'';
            await sql(`CREATE ALGORITHM=MERGE DEFINER=${account(n.definer)} SQL SECURITY DEFINER VIEW ${n.prefix+suffix} AS SELECT ${columns[base]} FROM local.${n.base+base}${predicate}`);views.push(n.prefix+suffix);
            if(suffix!=='unsafe')await sql(`GRANT SELECT ON local.${n.prefix+suffix} TO ${account(n.invoker)}`);
        }
        phase='capability';await run('capability');
        phase='unsafe_canary_control';await sql(`GRANT SELECT ON local.${n.prefix}unsafe TO ${account(n.invoker)}`);await run('unsafe');await sql(`REVOKE SELECT ON local.${n.prefix}unsafe FROM ${account(n.invoker)}`);
        for(const mode of ['autoload','fallback']){phase=mode;
            await sql(`UPDATE ${n.base}options SET autoload='${mode==='autoload'?'yes':'no'}' WHERE option_name<>'proof_owner'`);await run(mode);
        }
        receipt.proof_completed=true;
    }catch(error){receipt.failure_phase=phase;receipt.failure=/^[a-z_]+$/.test(error.message)?error.message:'private_failure';if(error.databaseErrorCode)receipt.database_error_code=error.databaseErrorCode;unsettled=error.unsettled===true;}
    finally{
        try{
            check(!unsettled,'retain_unsettled_ownership');
            if(tables.length){
                const ownedMarker=await sql(`SELECT option_value FROM ${n.base}options WHERE option_name='proof_owner'`);
                let emptyCreatedSetup=false;
                // Successful CREATE acknowledgements + exact empty pre-account setup
                // are a separate ownership proof; never infer ownership from a name alone.
                if(ownedMarker===''&&phase==='seed_owned_rows'&&accounts.length===0&&views.length===0){
                    emptyCreatedSetup=true;for(const table of tables)emptyCreatedSetup=emptyCreatedSetup&&(await sql(`SELECT COUNT(*) FROM ${table}`))==='0';
                }
                check(ownedMarker===marker||emptyCreatedSetup,'cleanup_marker');if(emptyCreatedSetup)receipt.empty_acknowledged_setup_cleanup=true;
            }
            const ownedSessions=await sql(`SELECT COUNT(*) FROM information_schema.processlist WHERE USER IN ('${n.invoker}','${n.definer}')`);check(ownedSessions==='0','owned_sessions_remain');receipt.owned_sessions_closed=true;
            for(const view of [...views].reverse()){
                check((await sql(`SELECT COUNT(*) FROM information_schema.views WHERE table_schema='local' AND table_name='${view}' AND DEFINER='${n.definer}@127.0.0.1' AND SECURITY_TYPE='DEFINER'`))==='1','cleanup_view_identity');
                await sql(`SELECT COUNT(*) FROM ${view}`);await sql(`DROP VIEW ${view}`);receipt.cleanup.push({kind:'view',name:view,removed:true});
            }
            for(const user of [...accounts].reverse()){
                check((await sql(`SELECT COUNT(*) FROM mysql.user WHERE User='${user}' AND Host='127.0.0.1'`))==='1','cleanup_account_identity');await sql(`SHOW GRANTS FOR ${account(user)}`);await sql(`DROP USER ${account(user)}`);receipt.cleanup.push({kind:'database_account',name:user,removed:true});
            }
            for(const table of [...tables].reverse()){const count=Number(await sql(`SELECT COUNT(*) FROM ${table}`));check(Number.isInteger(count)&&count>=0&&count<=32,'cleanup_row_preview');await sql(`DROP TABLE ${table}`);receipt.cleanup.push({kind:'table',name:table,rows_previewed:count,removed:true});}
            receipt.owned_objects_absent=(await sql(`SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='local' AND table_name REGEXP '^${n.prefix}'`))==='0';
            receipt.owned_accounts_absent=(await sql(`SELECT COUNT(*) FROM mysql.user WHERE User IN ('${n.invoker}','${n.definer}')`))==='0';
        }catch(error){receipt.cleanup_unresolved=true;receipt.retained_scope={seed,...n};receipt.cleanup_failure=/^[a-z_]+$/.test(error.message)?error.message:'private_cleanup_failure';}
        if(before){try{const after=await inventory();receipt.application_tables=before.names.length;receipt.application_preserved=before.digest===after.digest&&JSON.stringify(before.names)===JSON.stringify(after.names);}catch{receipt.application_preserved=false;}}
        if(privBefore){try{receipt.existing_permissions_preserved=privBefore===await permissions();}catch{receipt.existing_permissions_preserved=false;}}
        if(sourceBefore)receipt.core_config_unchanged=JSON.stringify(sourceBefore)===JSON.stringify(sourceIdentity());
    }
    receipt.passed=receipt.proof_completed===true&&!receipt.cleanup_unresolved&&receipt.application_preserved&&receipt.existing_permissions_preserved&&receipt.owned_objects_absent&&receipt.owned_accounts_absent&&receipt.core_config_unchanged;
    receipt.minimum_wp6='not_executed';receipt.production_profile_qualified=false;process.stdout.write(JSON.stringify(receipt,null,2)+'\n');if(!receipt.passed)process.exitCode=1;
}
if(path.resolve(process.argv[1]??'')===fileURLToPath(import.meta.url)){
    if(process.platform!=='win32'||process.argv.length!==3||process.argv[2]!=='--run-owned-projection'){process.stdout.write('{"passed":false,"refused_before_operations":true}\n');process.exitCode=1;}else await main();
}
