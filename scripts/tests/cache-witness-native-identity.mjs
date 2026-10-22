// Successor-only source seal. Historical native closure remains unchanged.
import { readFileSync,realpathSync,statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { verifyUncertainNativeIdentity } from './uncertain-barrier-native-identity.mjs';
export const cacheWitnessDigest='f3b7e19fdb2f5ced97312efffa4351784f083f4e141d3612edbf10a1a84ec91b';
const digest=s=>createHash('sha256').update(s).digest('hex');
export function verifyCacheWitnessIdentity() {
    if(arguments.length)return false;
    try {
        if(!verifyUncertainNativeIdentity())return false;
        const url=new URL('./cache-witness-native-identities.json',import.meta.url);
        if(statSync(url).size>16384)return false;
        const raw=readFileSync(url,'utf8').replaceAll('\r\n','\n');if(digest(raw)!==cacheWitnessDigest)return false;
        const groups=JSON.parse(raw);if(Object.keys(groups).join(',')!=='model,integration')return false;
        const base=realpathSync(new URL('../../',import.meta.url));let total=0;
        for(const rows of Object.values(groups)){
            if(!rows||Array.isArray(rows)||!Object.keys(rows).length||Object.keys(rows).length>32)return false;
            for(const [name,hash] of Object.entries(rows)){
                if(!/^scripts\/tests\/[A-Za-z0-9_-]+\.(?:mjs|php)$/.test(name)||!/^[a-f0-9]{64}$/.test(hash))return false;
                const path=realpathSync(new URL('../../'+name,import.meta.url)),size=statSync(path).size;
                if(!path.startsWith(base+(process.platform==='win32'?'\\':'/'))||size>262144||(total+=size)>2097152||digest(readFileSync(path,'utf8').replaceAll('\r\n','\n'))!==hash)return false;
            }
        }
        return true;
    }catch{return false;}
}
