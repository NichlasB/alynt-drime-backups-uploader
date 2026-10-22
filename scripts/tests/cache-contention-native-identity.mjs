// Successor-only source seal. Historical native closure remains unchanged.
import { readFileSync,realpathSync,statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { verifyCacheWitnessIdentity } from './cache-witness-native-identity.mjs';
export const cacheContentionDigest='31481ac16832920ccca35a118a0c7b3c77c18ef017c93abc90e5b930c98d4137';
const digest=s=>createHash('sha256').update(s).digest('hex');
export function verifyCacheContentionIdentity() {
    if(arguments.length)return false;
    try {
        if(!verifyCacheWitnessIdentity())return false;
        const url=new URL('./cache-contention-native-identities.json',import.meta.url);
        if(statSync(url).size>16384)return false;
        const raw=readFileSync(url,'utf8').replaceAll('\r\n','\n');if(digest(raw)!==cacheContentionDigest)return false;
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
