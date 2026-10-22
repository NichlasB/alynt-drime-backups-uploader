// Actual PHP source evaluated in memory only. No WP bootstrap, sockets or storage.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const ingressUrl = new URL('../../includes/class-registration-entry-ingress.php', import.meta.url);
const policyUrl = new URL('../../includes/class-registration-entry-policy.php', import.meta.url);
const ingress = readFileSync(ingressUrl, 'utf8'), policy = readFileSync(policyUrl, 'utf8');
const hash = value => createHash('sha256').update(value).digest('hex');
const prelude = `define('ABSPATH', getcwd()); require 'includes/class-registration-request-contract.php';
function demand($v) { if (true !== $v) { throw new RuntimeException('semantic_assertion'); } }
$f = array('action'=>'alynt_drime_backups_register_closed','purpose'=>'register_closed','confirm'=>'register','nonce'=>'synthetic');
$parse = function($f, $post=null, $method='POST', $complete=true) { return Alynt_Drime_Backups_Uploader_Registration_Entry_Ingress::parse($method,'application/x-www-form-urlencoded','',array(),array(),http_build_query($f,'','&'),$post ?? array_map('addslashes',$f),$complete,array()); };
`;
function exercise(source, body) {
    const code = prelude + `eval(base64_decode('${Buffer.from(source.replace(/^<\?php\s*/, '')).toString('base64')}')); echo "loaded\\n";
try { ${body} echo "completed\\n"; } catch (RuntimeException $e) { if ('semantic_assertion' !== $e->getMessage()) { throw $e; } echo "assertion_killed\\n"; exit(42); }`;
    return spawnSync(process.env.PHP_BINARY || 'php', ['-r', code], { cwd: root, encoding: 'utf8', timeout: 15000, maxBuffer: 1048576 });
}

const positive = `demand(is_array($parse($f)));`;
for (const [name, original, needle, replacement, body] of [
    ['positive parser', ingress, '? $post : false;', '? false : false;', positive],
    ['method', ingress, "'POST' !== $method", 'false', positive + `demand(false === $parse($f,null,'GET'));`],
    ['complete body', ingress, 'true !== $complete', 'false', positive + `demand(false === $parse($f,null,'POST',false));`],
    ['raw/POST agreement', ingress, '$inspect[ $key ] !== $fields[ $key ]', 'false', positive + `$p=$f; $p['nonce']='other'; demand(false === $parse($f,$p));`],
    ['duplicate-collapsing replacement', ingress, '$fields = self::decode( $raw );', 'parse_str( $raw, $fields );', positive + `$raw=http_build_query($f,'','&').'&nonce=synthetic'; demand(false===Alynt_Drime_Backups_Uploader_Registration_Entry_Ingress::parse('POST','application/x-www-form-urlencoded','',array(),array(),$raw,$f,true,array()));`],
    ['controls with matching POST', ingress, "preg_match( '/\\p{Cc}/u', $value )", 'false', positive + `$f['nonce']="\\xc2\\x80"; demand(false === $parse($f));`],
    ['one domain unslash', ingress, "unset( $post['action'] );", "unset( $post['action'] ); $post = array_map('stripslashes', $post);", `$f['nonce']="a\\\\b"; $p=$parse($f); demand(is_array($p)); demand($p['nonce']===addslashes($f['nonce']));`],
    ['cleanup acknowledgement', policy, 'true === $clean', 'true', `$p=Alynt_Drime_Backups_Uploader_Registration_Entry_Policy::start('issuer_get','GET',100); foreach(array('authenticate','manage_options','installation','authenticate_again') as $e) demand($p->step($e,100)); demand(false===$p->finish(null,false,100));`],
    ['ordered auth events', policy, '$event !== $this->events[0]', 'false', `$p=Alynt_Drime_Backups_Uploader_Registration_Entry_Policy::start('closed_post','POST',100); demand(false===$p->step('protected_capture',100));`],
    ['nonce role isolation', policy, "'small_mutation_nonce'", "'small_read_nonce'", `$p=Alynt_Drime_Backups_Uploader_Registration_Entry_Policy::start('closed_post','POST',100); demand($p->step('authenticate',100)); demand($p->step('manage_options',100)); demand(false===$p->step('small_read_nonce',100));`],
    ['admission expiry', policy, '$now - $this->start < 10', 'true', `$p=Alynt_Drime_Backups_Uploader_Registration_Entry_Policy::start('closed_post','POST',100); demand(false===$p->step('authenticate',110));`],
    ['terminal consumption', policy, '$kind  = $this->kind;\n\t\t$this->erase();', '$kind  = $this->kind;', `$p=Alynt_Drime_Backups_Uploader_Registration_Entry_Policy::start('issuer_get','GET',100); foreach(array('authenticate','manage_options','installation','authenticate_again') as $e) demand($p->step($e,100)); demand(is_array($p->finish(null,true,100))); demand(false===$p->finish(null,true,100));`],
]) test(`entry semantic control is assertion-killed: ${name}`, () => {
    assert.equal(original.split(needle).length, 2, 'one exact source target');
    const baseline = exercise(original, body);
    assert.ifError(baseline.error); assert.equal(baseline.status, 0, baseline.stderr); assert.equal(baseline.stderr, ''); assert.equal(baseline.stdout, 'loaded\ncompleted\n');
    const altered = exercise(original.replace(needle, replacement), body);
    assert.ifError(altered.error); assert.equal(altered.status, 42, altered.stderr); assert.equal(altered.stderr, ''); assert.equal(altered.stdout, 'loaded\nassertion_killed\n');
    assert.equal(hash(readFileSync(ingressUrl, 'utf8')), hash(ingress));
    assert.equal(hash(readFileSync(policyUrl, 'utf8')), hash(policy));
});

test('runtime loaders still omit the entry family and native entry remains unavailable', () => {
    for (const path of ['alynt-drime-backups-uploader.php', 'includes/class-plugin.php']) {
        const source = readFileSync(new URL('../../' + path, import.meta.url), 'utf8');
        assert.doesNotMatch(source, /registration-entry-(?:ingress|policy)|admin_post_alynt_drime_backups_register_closed/);
    }
    const native = readFileSync(new URL('../../includes/class-registration-native-lifecycle.php', import.meta.url), 'utf8');
    assert.match(native, /function native_entry_available\(\)[\s\S]*?return false;/);
    for (const source of [ingress, policy]) assert.doesNotMatch(source, /\$_(?:GET|POST|REQUEST|SERVER)|\b(?:add_action|wp_create_nonce|wp_verify_nonce|mysqli_connect|header|wp_send_json|file_get_contents)\s*\(/);
});
