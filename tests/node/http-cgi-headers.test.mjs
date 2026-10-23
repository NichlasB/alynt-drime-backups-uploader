import test from 'node:test';import assert from 'node:assert/strict';
import {acceptFixtureHeaders} from '../../scripts/tests/http-cgi-headers.mjs';
const post=(length='3')=>({method:'POST',target:'/probe.php',version:'1.1',headers:['Host','owned.invalid','Connection','close','Content-Length',length,'Content-Type','application/x-www-form-urlencoded']});
test('positive fixed POST lengths and GET; caller snapshot is not mutated',()=>{for(const length of ['0','3','16384']){const h=post(length),before=structuredClone(h);const result=acceptFixtureHeaders(h);assert.deepEqual(result,{method:'POST',length:Number(length)});assert.ok(Object.isFrozen(result));assert.deepEqual(h,before);}
    const h=post('0');h.method='GET';h.headers.splice(-2);assert.deepEqual(acceptFixtureHeaders(h),{method:'GET',length:0});});
for(const length of ['','00','01','+3','-1','3.0','3, 3',' 3','3 ','1e3','16385','9999999999999999999999'])test(`noncanonical/oversize CL ${JSON.stringify(length)} refuses`,()=>assert.throws(()=>acceptFixtureHeaders(post(length))));
for(const field of ['Transfer-Encoding','Trailer','Expect','Upgrade','Cookie','Authorization','Content-Encoding','X-Forwarded-Host','__proto__'])test(`unsupported ${field} refuses before a body is accepted`,()=>{const h=post();h.headers.push(field,'x');assert.throws(()=>acceptFixtureHeaders(h));});
test('duplicate header names cannot be hidden by case, matching values or a merged CL',()=>{for(const [name,value]of [['host','owned.invalid'],['CONTENT-LENGTH','3'],['connection','close'],['Content-Type','application/x-www-form-urlencoded']]){const h=post();h.headers.push(name,value);assert.throws(()=>acceptFixtureHeaders(h));}});
test('method, path, version, host, connection, content type and GET body aliases refuse',()=>{
    for(const [key,value]of [['method','post'],['method','PUT'],['target','/probe.php?x=1'],['target','http://owned.invalid/probe.php'],['target','/%70robe.php'],['version','1.0']]){const h=post();h[key]=value;assert.throws(()=>acceptFixtureHeaders(h));}
    for(const [index,value]of [[1,'owned.invalid:80'],[3,'keep-alive'],[7,'application/x-www-form-urlencoded; charset=UTF-8'],[7,'text/plain']]){const h=post();h.headers[index]=value;assert.throws(()=>acceptFixtureHeaders(h));}
    const get=post();get.method='GET';assert.throws(()=>acceptFixtureHeaders(get));
});
test('controls, folding, malformed/odd/sparse headers and inherited/accessor snapshots refuse',()=>{
    for(const value of ['x\r\ny','x\ny','x\0y','\tx','\u0080',1,null]){const h=post();h.headers[1]=value;assert.throws(()=>acceptFixtureHeaders(h));}
    for(const edit of [h=>h.headers.push('Host'),h=>delete h.headers[0],h=>h.headers=new Array(34),h=>h.headers[0]=' Host',h=>h.extra=1]){const h=post();edit(h);assert.throws(()=>acceptFixtureHeaders(h));}
    let calls=0;const h=post();Object.defineProperty(h,'method',{get(){calls++;return 'POST'}});assert.throws(()=>acceptFixtureHeaders(h));
    const a=post();Object.defineProperty(a.headers,'0',{get(){calls++;return 'Host'}});assert.throws(()=>acceptFixtureHeaders(a));assert.equal(calls,0);
    assert.throws(()=>acceptFixtureHeaders(Object.assign(Object.create({}),post())));
});
