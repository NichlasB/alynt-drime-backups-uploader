import test from 'node:test';import assert from 'node:assert/strict';
import {FixtureCgiHandoff} from '../../scripts/tests/http-cgi-handoff.mjs';
const script='C:/owned/alynt-cgi-profile-'+ 'a'.repeat(32)+'/probe.php';
const head=(n,method='POST')=>({method,target:'/probe.php',version:'1.1',headers:['Host','owned.invalid','Connection','close','Content-Length',String(n),...(method==='POST'?['Content-Type','application/x-www-form-urlencoded']:[])]});
const eof=()=>({complete:true,aborted:false,error:false});
function records(wire){const result=[];for(let i=0;i<wire.length;){assert.equal(wire[i],1);assert.equal(wire.readUInt16BE(i+2),1);const size=wire.readUInt16BE(i+4),pad=wire[i+6];assert.ok(i+8+size+pad<=wire.length);result.push({type:wire[i+1],body:wire.subarray(i+8,i+8+size)});i+=8+size+pad;}return result;}
function params(bytes){const fields={};for(let i=0;i<bytes.length;){let a=bytes[i++],b=bytes[i++];assert.ok(a<128&&b<128);const key=bytes.subarray(i,i+a).toString();i+=a;fields[key]=bytes.subarray(i,i+b).toString();i+=b;}return fields;}
for(const size of [0,3,16384])test(`actual composed POST ${size} preserves fixed CGI fields/body and erases forwarded buffer at cleanup`,()=>{
    const body=Buffer.alloc(size,120),h=new FixtureCgiHandoff(head(size),100,101,script);
    if(size)h.push(body,102);h.end(eof(),2099);assert.equal(h.prepare(3000),true);const wire=h.take(4099),r=records(wire);
    assert.deepEqual(r.map(x=>x.type),size?[1,4,4,5,5]:[1,4,4,5]);assert.equal(r[0].body.readUInt16BE(),1);assert.equal(r[0].body[2],0);
    const fields=params(r[1].body);assert.equal(fields.SCRIPT_FILENAME,script);assert.equal(fields.QUERY_STRING,'');assert.equal(fields.REQUEST_METHOD,'POST');assert.equal(fields.CONTENT_LENGTH,String(size));assert.equal(fields.CONTENT_TYPE,'application/x-www-form-urlencoded');assert.equal(fields.HTTP_COOKIE,undefined);assert.deepEqual(r[3].body,body);
    assert.equal(h.cleanup(9999),true);assert.equal(wire.every(b=>b===0),true);assert.throws(()=>h.take(9999));
});
test('GET stays GET with zero body and no synthesized content type',()=>{const h=new FixtureCgiHandoff(head(0,'GET'),0,0,script);h.end(eof(),1);h.prepare(2);const p=params(records(h.take(3))[1].body);assert.equal(p.REQUEST_METHOD,'GET');assert.equal(p.CONTENT_LENGTH,'0');assert.equal(p.CONTENT_TYPE,undefined);h.cleanup(4);});
test('no header/body/end shortcut or repeat prepare/take can forward; refusal consumes instance',()=>{
    for(const stage of ['head','body','end','repeat']){let h;
        if(stage==='head'){const bad=head(3);bad.headers.push('Transfer-Encoding','chunked');assert.throws(()=>new FixtureCgiHandoff(bad,0,0,script));continue;}
        h=new FixtureCgiHandoff(head(3),0,0,script);if(stage!=='body')h.push(Buffer.from('x=1'),1);if(stage==='repeat'){h.end(eof(),2);h.prepare(3);assert.throws(()=>h.prepare(3));}else assert.throws(()=>h.prepare(3));
        assert.throws(()=>h.take(4));assert.equal(h.cleanup(5),true);
    }
});
test('post-encoding cutoff and clock regression refuse instead of resetting origin',()=>{for(const at of [4000,9999,1]){const h=new FixtureCgiHandoff(head(3),0,0,script);h.push(Buffer.from('x=1'),1);h.end(eof(),2);h.prepare(3);assert.throws(()=>h.take(at));assert.throws(()=>h.take(10000));}});
test('extra bytes after preparation cancel and erase any wire buffer',()=>{const h=new FixtureCgiHandoff(head(3),0,0,script);h.push(Buffer.from('x=1'),1);h.end(eof(),2);h.prepare(3);const wire=h.take(4);assert.throws(()=>h.push(Buffer.from('x'),5));assert.ok(wire.every(b=>b===0));assert.throws(()=>h.take(6));});
test('foreign/unmarked/noncanonical script cannot become a CGI request',()=>{for(const value of [null,1,'probe.php',script.replace('/probe.php','/other.php'),script.replace('/owned/','/../'),script.replace('/owned/','//'),script+'?x=1',script.replace('a'.repeat(32),'A'.repeat(32)),script.replaceAll('/','\\')])assert.throws(()=>new FixtureCgiHandoff(head(0),0,0,value));});
