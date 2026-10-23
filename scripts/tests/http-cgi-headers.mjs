// Semantic gate for a future standard-parser snapshot. No wire parsing, URL routing or IO.
const keys=Object.freeze(['method','target','version','headers']);
const allowed=Object.freeze(['host','connection','content-length','content-type']);
const form='application/x-www-form-urlencoded';
export function acceptFixtureHeaders(snapshot){
    if(snapshot===null||typeof snapshot!=='object'||Object.getPrototypeOf(snapshot)!==Object.prototype)throw Error('http_cgi_head');
    const descriptors=Object.getOwnPropertyDescriptors(snapshot);
    if(Reflect.ownKeys(descriptors).length!==keys.length||keys.some(k=>!Object.hasOwn(descriptors,k)||!Object.hasOwn(descriptors[k],'value')))throw Error('http_cgi_head');
    const {method,target,version,headers}=snapshot;
    if(!['GET','POST'].includes(method)||target!=='/probe.php'||version!=='1.1'||!Array.isArray(headers)||headers.length%2||headers.length>32)throw Error('http_cgi_head');
    const fields=new Map();let bytes=0;
    for(let i=0;i<headers.length;i+=2){
        // Do not execute getters from a hand-built synthetic snapshot.
        const name=Object.getOwnPropertyDescriptor(headers,String(i)),value=Object.getOwnPropertyDescriptor(headers,String(i+1));
        if(!name||!value||!Object.hasOwn(name,'value')||!Object.hasOwn(value,'value')||typeof name.value!=='string'||typeof value.value!=='string')throw Error('http_cgi_field');
        const n=name.value,v=value.value;
        if(n.length>64||v.length>1024||!/^[A-Za-z-]+$/.test(n)||/[^\x20-\x7e]/.test(v))throw Error('http_cgi_field');
        const lower=n.toLowerCase();bytes+=n.length+v.length+4;
        if(bytes>4096||!allowed.includes(lower)||fields.has(lower))throw Error('http_cgi_field');
        fields.set(lower,v);
    }
    const length=fields.get('content-length');
    if(fields.get('host')!=='owned.invalid'||fields.get('connection')!=='close'||typeof length!=='string'||!/^(0|[1-9][0-9]{0,4})$/.test(length)||Number(length)>16384)throw Error('http_cgi_framing');
    if(method==='GET'?(length!=='0'||fields.has('content-type')):fields.get('content-type')!==form)throw Error('http_cgi_method');
    return Object.freeze({method,length:Number(length)});
}
