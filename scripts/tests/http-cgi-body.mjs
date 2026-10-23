// Bounded in-memory fixture bytes only. No IncomingMessage, stream, socket, storage or native handle.
import {HttpCgiBudget} from './http-cgi-budget.mjs';
import {acceptFixtureHeaders} from './http-cgi-headers.mjs';
export class FixtureHttpBody {
    #budget;#head;#buffer;#offset=0;#ended=false;#taken=false;#failed=false;
    constructor(snapshot,origin,now){
        this.#budget=new HttpCgiBudget(origin);
        if(!this.#budget.check('headers',now))throw Error('http_cgi_header_time');
        this.#head=acceptFixtureHeaders(snapshot);
        if(!this.#budget.check('body',now))throw Error('http_cgi_body_time');
        this.#buffer=Buffer.alloc(this.#head.length);Object.freeze(this);
    }
    #burn(){this.#failed=true;this.#ended=true;this.#taken=true;this.#buffer.fill(0);}
    push(chunk,now){
        if(this.#failed||this.#ended||!this.#budget.check('body',now)||!Buffer.isBuffer(chunk)||chunk.length===0||chunk.length>this.#buffer.length-this.#offset){this.#burn();throw Error('http_cgi_body');}
        this.#buffer.set(chunk,this.#offset);this.#offset+=chunk.length;return true;
    }
    end(facts,now){
        try{
            if(this.#failed||this.#ended||!this.#budget.check('body',now)||facts===null||typeof facts!=='object'||Object.getPrototypeOf(facts)!==Object.prototype)throw Error('http_cgi_end');
            const d=Object.getOwnPropertyDescriptors(facts),names=['complete','aborted','error'];
            if(Reflect.ownKeys(d).length!==3||names.some(k=>!Object.hasOwn(d,k)||!Object.hasOwn(d[k],'value'))||d.complete.value!==true||d.aborted.value!==false||d.error.value!==false||this.#offset!==this.#head.length)throw Error('http_cgi_end');
            this.#ended=true;return true;
        }catch{this.#burn();throw Error('http_cgi_end');}
    }
    take(now){
        if(this.#failed||!this.#ended||this.#taken||!this.#budget.check('handoff',now)){this.#burn();throw Error('http_cgi_take');}
        this.#taken=true;const body=Buffer.from(this.#buffer);this.#buffer.fill(0);
        return Object.freeze({method:this.#head.method,body});
    }
    remainingHandoff(now){
        if(this.#failed||!this.#taken){this.#burn();return 0;}
        const remaining=this.#budget.remaining('handoff',now);if(remaining===0)this.#burn();return remaining;
    }
    cleanup(now){this.#burn();return this.#budget.check('cleanup',now);}
}
