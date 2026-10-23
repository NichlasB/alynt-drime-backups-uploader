// Fixed single-request FastCGI test transport, not an HTTP parser or production API.
function record(type, bytes = Buffer.alloc(0)) {
    const header = Buffer.alloc(8); header[0] = 1; header[1] = type; header.writeUInt16BE(1, 2); header.writeUInt16BE(bytes.length, 4);
    return Buffer.concat([header, bytes]);
}
export function cgiRequest(script, body, method = 'POST', complete = true) {
    if (typeof script !== 'string' || script.length > 512 || /[\r\n\0]/.test(script) || !Buffer.isBuffer(body) || body.length > 16384 || !['POST','GET'].includes(method) || typeof complete !== 'boolean') throw Error('cgi_request');
    const fields = {GATEWAY_INTERFACE:'CGI/1.1',SERVER_PROTOCOL:'HTTP/1.1',REQUEST_METHOD:method,QUERY_STRING:'',SCRIPT_FILENAME:script,SCRIPT_NAME:'/probe.php',REQUEST_URI:'/probe.php',DOCUMENT_ROOT:script.slice(0,-10),SERVER_NAME:'owned.invalid',SERVER_PORT:'1',REMOTE_ADDR:'127.0.0.1',REDIRECT_STATUS:'200',CONTENT_TYPE:'application/x-www-form-urlencoded',CONTENT_LENGTH:String(complete ? body.length : 1024)};
    const params = [];
    for (const [name,value] of Object.entries(fields)) {
        const a=Buffer.from(name),b=Buffer.from(value),length=(n)=>{if(n<128)return Buffer.from([n]);const x=Buffer.alloc(4);x.writeUInt32BE(n+0x80000000);return x;};
        params.push(length(a.length),length(b.length),a,b);
    }
    const begin=Buffer.alloc(8);begin.writeUInt16BE(1); // RESPONDER, no keep-connection flag.
    return Buffer.concat([record(1,begin),record(4,Buffer.concat(params)),record(4),record(5,body),...(complete?[record(5)]:[])]);
}
export class CgiResponse {
    #bytes=Buffer.alloc(0);#out=[];#err=[];#size=0;#records=0;#done=false;#result=null;#failed=false;
    push(chunk) {
        try {
            if(this.#failed||this.#done||!Buffer.isBuffer(chunk)||chunk.length>16384-this.#size)throw Error('cgi_response');
            this.#size+=chunk.length;this.#bytes=Buffer.concat([this.#bytes,chunk]);
            while(this.#bytes.length>=8){
                const b=this.#bytes,n=b.readUInt16BE(4),padding=b[6];
                if(b[0]!==1||b.readUInt16BE(2)!==1||b[7]!==0||![3,6,7].includes(b[1])||n>8192||++this.#records>64)throw Error('cgi_record');
                if(b.length<8+n+padding){--this.#records;break;}
                const data=b.subarray(8,8+n);this.#bytes=b.subarray(8+n+padding);
                if(b[1]===6)this.#out.push(data);else if(b[1]===7)this.#err.push(data);else{
                    // END_REQUEST's final three bytes are reserved, not application/protocol status.
                    if(n!==8||data[4]!==0||this.#bytes.length)throw Error('cgi_end');
                    this.#done=true;this.#result={application_status:data.readUInt32BE(),stdout:Buffer.concat(this.#out).toString('utf8'),stderr:Buffer.concat(this.#err).toString('utf8')};
                }
            }
            return this.#done;
        }catch(error){this.#failed=true;this.#result=null;throw error;}
    }
    finish(){if(this.#failed||!this.#done||this.#bytes.length)throw Error('cgi_incomplete');return this.#result;}
}
