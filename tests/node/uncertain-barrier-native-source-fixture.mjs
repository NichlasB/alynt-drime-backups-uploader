// Source-backed request ordering only; not native authentication/SQL/wait evidence.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createUncertainNativeRegistry } from '../../scripts/tests/uncertain-barrier-native-registry.mjs';
import { nativeNoFaultFixture,frame,commit,ok,barrierPackets } from './support/native-no-fault-fixture.mjs';
export const table='wp_alynt_core_probe_0123456789ab_options';
const metadata=readFileSync(new URL('../../includes/trait-native-options-metadata.php',import.meta.url),'utf8');
export const metadataSql=[...metadata.matchAll(/^\s*'(SELECT [^']+)' =>/gm)].map(m=>m[1]);
metadataSql.push(metadata.match(/->prepare\( '(SELECT CAST\(COUNT\(\*\) AS CHAR\) FROM information_schema.KEY_COLUMN_USAGE[^']+)' \)/)[1]);
const rowSource=readFileSync(new URL('../../includes/trait-registration-observation-barrier-rows.php',import.meta.url),'utf8').match(/->prepare\( "([^"\n]+)" \)/)[1];
const manifest=readFileSync(new URL('../../includes/class-scan-effect-manifest.php',import.meta.url),'utf8');
export const rowDefinitions=[...manifest.matchAll(/^\s*'([a-z_]+)'\s*=> array\( '([^']+)', ([0-9]+) \)/gm)].map(m=>({role:m[1],name:m[2],cap:Number(m[3])}));
export const rowSql=index=>rowSource.replaceAll('{$table}',table).replaceAll('{$cap}',String(rowDefinitions[index].cap));
export function sourceFixture(profile,factory=createUncertainNativeRegistry) {
    assert.equal(metadataSql.length,5);assert.equal(rowDefinitions.length,10);
    const f=nativeNoFaultFixture(()=>{const api=factory(profile);return Object.freeze({...api,frame:(id,d,b,key,tail=0)=>api.frame(id,d,b,key,tail)});});
    let id=1000;
    f.loss=(credit=true,tail=0)=>{assert.equal(f.send('writer','client',frame(commit()),credit),true);const key=Symbol('writer-credit');return f.api.frame(f.ids.writer,'server',frame(ok(),1),key,tail)==='response'&&f.api.cut(f.ids.writer,key);};
    f.prepare=(sql,statement=++id)=>{
        assert.equal(f.send('barrier','client',frame(Buffer.concat([Buffer.from([22]),Buffer.from(sql)]))),true,'compiled prepare');
        const ack=Buffer.alloc(12);ack.writeUInt32LE(statement,1);assert.equal(f.send('barrier','server',frame(ack,1)),true,'statement acknowledgement');return statement;
    };
    f.executeId=statement=>{const b=Buffer.alloc(10);b[0]=23;b.writeUInt32LE(statement,1);return f.send('barrier','client',frame(b));};
    f.closeId=statement=>{const b=Buffer.alloc(5);b[0]=25;b.writeUInt32LE(statement,1);return f.send('barrier','client',frame(b));};
    f.cycle=sql=>{const statement=f.prepare(sql);assert.equal(f.executeId(statement),true,'compiled statement execute');assert.equal(f.closeId(statement),true,'compiled statement close');};
    f.metadata=()=>{for(const sql of metadataSql)f.cycle(sql);};
    f.first=()=>{assert.equal(f.handshake('barrier'),true);f.metadata();assert.equal(f.api.bindBarrier(f.roles),true);};
    f.execute=()=>{const statement=f.prepare(rowSql(0),7);assert.equal(f.executeId(statement),true);return statement;};
    f.barrier=()=>{f.first();f.cycle(rowSql(0));f.metadata();for(let n=1;n<10;n++)f.cycle(rowSql(n));return f.committed('barrier')&&f.sendPackets('barrier',barrierPackets())&&f.api.close(f.ids.barrier);};
    return f;
}
