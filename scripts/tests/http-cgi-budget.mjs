// Socket-free experimental fixture accounting. Not a timer, native root or production admission.
const phases=Object.freeze(['headers','body','handoff','cleanup']);
const limits=Object.freeze([1000,2000,4000,10000]);
const time=value=>typeof value==='number'&&Number.isFinite(value)&&value>=0&&value<=Number.MAX_SAFE_INTEGER-10000;

export class HttpCgiBudget {
    #origin;#last;#phase=-1;#failed=false;
    constructor(origin){if(!time(origin))throw Error('http_cgi_epoch');this.#origin=origin;this.#last=origin;Object.freeze(this);}
    check(phase,now){
        const index=typeof phase==='string'?phases.indexOf(phase):-1;
        if(index<0||!time(now)||now<this.#last){this.#failed=true;return false;}
        this.#last=now;
        // Refusal never prevents cleanup, but cleanup never re-arms work or extends its epoch.
        if(index===3){this.#phase=3;return now-this.#origin<limits[3];}
        if(this.#failed||index<this.#phase||index>this.#phase+1||now-this.#origin>=limits[index]){this.#failed=true;return false;}
        this.#phase=index;return true;
    }
    remaining(phase,now){if(!this.check(phase,now))return 0;return Math.max(0,Math.floor(limits[phases.indexOf(phase)]-(now-this.#origin)));}
}
