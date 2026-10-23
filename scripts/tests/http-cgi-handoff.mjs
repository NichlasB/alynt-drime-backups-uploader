// Socket-free experimental composition. Returns only synthetic FastCGI bytes, never native authority.
import {FixtureHttpBody} from './http-cgi-body.mjs';
import {cgiRequest} from './cgi-profile-codec.mjs';
function markedScript(value){
    if(typeof value!=='string'||value.length>512||!/^([A-Z]:\/|\/)/.test(value)||!/\/alynt-cgi-profile-[a-f0-9]{32}\/probe\.php$/.test(value))return false;
    const parts=value.split('/').slice(1);return parts.every(p=>p!==''&&p!=='.'&&p!=='..'&&/^[A-Za-z0-9 ._-]+$/.test(p));
}
export class FixtureCgiHandoff {
    #body;#script;#wire=null;#taken=false;#failed=false;
    constructor(snapshot,origin,now,script){
        // Lexical fixture path only. Physical hash/ownership remains a future operator obligation.
        if(!markedScript(script))throw Error('http_cgi_script');
        this.#script=script;this.#body=new FixtureHttpBody(snapshot,origin,now);Object.freeze(this);
    }
    #fail(now){this.#failed=true;this.#wire?.fill(0);this.#body.cleanup(now);throw Error('http_cgi_handoff');}
    push(chunk,now){if(this.#failed||this.#wire!==null||this.#taken)return this.#fail(now);try{return this.#body.push(chunk,now);}catch{return this.#fail(now);}}
    end(facts,now){if(this.#failed||this.#wire!==null||this.#taken)return this.#fail(now);try{return this.#body.end(facts,now);}catch{return this.#fail(now);}}
    prepare(now){
        if(this.#failed||this.#wire!==null||this.#taken)return this.#fail(now);
        let raw;
        try{
            raw=this.#body.take(now);const wire=cgiRequest(this.#script,raw.body,raw.method);
            if(!Buffer.isBuffer(wire))throw Error('http_cgi_wire');this.#wire=wire;
            if(this.#wire.length===0||this.#wire.length>18000)throw Error('http_cgi_wire');
            return true;
        }catch{return this.#fail(now);}finally{raw?.body.fill(0);}
    }
    take(now){
        // Recheck after encoding. A successful prepare is not permission to forward late bytes.
        if(this.#failed||this.#wire===null||this.#taken||this.#body.remainingHandoff(now)===0)return this.#fail(now);
        this.#taken=true;return this.#wire;
    }
    cleanup(now){this.#failed=true;this.#wire?.fill(0);return this.#body.cleanup(now);}
}
