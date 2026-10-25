# V2.3 Socket-Free Projection Zero-Row Response and Provenance Safety Foundation

2026-10-25. Approved local-only R2 synthetic implementation, not a native proof.
Uploader0.5.25/dashboard0.1.77 unchanged. Parent/design owner: dashboard
docs/V2_3_DRIVER_SESSION_OBSERVATION_PROVENANCE_DESIGN.md. This record owns the
new test-only decoder; actual worker/storage/native execution/wiring unchanged.
Explicit excluded coherent review bases: uploader
0d41664559cb5b16b3374e61eec6f6c75388cf1a, dashboard
e3533ac31f399fd9cc72c93f2e7ec20c16a5cdcf, toolkit
3d58393f90dd603d483665fb1000cedfdf9c84be. Scope: two new MJS files, this record,
both implementation plans and the parent design successor pointer. No historical
native source group/manifest or existing worker/relay/ledger/decoder is modified.

## Implemented candidate contract

`scripts/tests/projection-zero-row-decoder.mjs` has no transport/worker imports;
only Node's proxy classifier. `tests/node/projection-zero-row.test.mjs` exercises
the real new module using invented bytes and virtual bigint nanoseconds. It reads
repository source for cross-language catalog binding and in-memory guard controls,
not installed WordPress or database state. No production caller is introduced.

The factory accepts an exact plain data context: five distinct private synthetic
symbols (attempt/pair/generation/request/session), original epoch, twelve lowercase
hex seed, schema `local`, one of the exact eight v2 N names, catalog fingerprint,
protocol enum and exact capabilities. No getters, proxies, callbacks, global
symbols, reused identities or caller SQL/allowlist. Weak symbol retention is
test-tool Node24 behavior, not a new WordPress/PHP runtime requirement. The fixed
v2 fingerprint and N names are checked against the unchanged PHP catalog source.
The owned table is derived as `wp_alynt_ro_<seed>_options`, never supplied by caller.
Schema `local` is a synthetic fixture choice, not live configuration attestation.

Every feed/finish binds all five identities. A new symbol cannot stand in for a
stale generation/session/request; consuming one exchange does not admit retries
or reconnects. This is synthetic identity consistency, NOT real authentication,
installation/source attestation, endpoint ownership or limited-account proof.

Fixed five-packet grammar (one exchange only):

1. Client sequence0: exact COM_QUERY byte03 plus `SELECT option_value FROM
   wp_alynt_ro_<seed>_options WHERE option_name = '<N>' LIMIT 1`.
2. Server sequence1: canonical single-byte column count01.
3. Server sequence2: exact column definition: lenenc `def`, `local`, owned table
   twice (alias/original), `option_value` twice; fixed field length0c, charset45,
   max sizeffffffff, LONG_BLOB typefb, flags0011 (NOT_NULL|BLOB), decimals/filler0.
4. Server sequence3: exact ordinary EOF `fe00000200` (no warning, autocommit only).
5. Server sequence4: immediate identical final EOF; no intervening row or error.

Profile `projection-text41-ordinary-eof-v1`, capability mask0x00088209 pins
LONG_PASSWORD/CONNECT_WITH_DB/PROTOCOL_41/SECURE_CONNECTION/PLUGIN_AUTH. Handshake
negotiation is NOT parsed/proved. Other bits/profiles refuse, including compressed,
TLS, multi-result, EOF-deprecation or extended/cached metadata candidates. Metadata
is a deliberately narrow fixture, not a promise that a genuine driver emits it.
No profile downgrade or genuine server setting change is permitted by this work.

Packet choices follow the primary [result-set layout](https://mariadb.com/docs/server/reference/clientserver-protocol/4-server-response-packets/result-set-packets),
[EOF fields](https://mariadb.com/docs/server/reference/clientserver-protocol/4-server-response-packets/eof_packet)
and [length encodings](https://mariadb.com/docs/server/reference/clientserver-protocol/protocol-data-types).
EOF/status validation uses full bytes/length, not a leading-fe heuristic;
[status flags](https://mariadb.com/docs/server/reference/clientserver-protocol/4-server-response-packets/ok_packet)
make extra results, warnings and transaction-state differences refuse here.

Header-first limits:4KiB/frame,16KiB aggregate including headers, eight frames.
The exact grammar is stricter (five frames and small exact lengths); the larger
ceilings are defensive rejection bounds, not demonstrated native capacity.
Complete headers must have exact stage length/sequence before payload allocation.
Partial fragments never earn a result. Input is copied, not retained/exposed;
own Buffer length/buffer getters, proxies and shared memory refuse. Header/payload
are cleared on refusal/completion. Total accounting and refusal are irreversible.
The original half-open20s virtual window is never restarted; regression/expiry
also invalidates finish. Any bytes after the final response poison the candidate.
Finish is one-shot and returns frozen booleans only, never handles or raw bytes.
Finish covers only this selected exchange, not future traffic/EOF; a later
transport adapter must independently prove its own full lifetime and closure.

## Proof boundary

Even a complete positive has every authentication/native/backing-absence/
forwarding/delivery/resource-closure/storage/wiring/execution claim false. A wire
zero-row result alone cannot establish backing absence, hidden-row/collation safety,
earned cache state, actual command execution/delivery or all-handle coverage.
No bridge to the cost ledger, bulk29-row response, setup/reconnect grammar,
physical transaction, Database Review or A1-A4 closure is claimed. No fabricated
native success path, suppressed response or selected-loss experiment is added.

## Source-scoped reviews and regression record

Canonical Feature Light: six changed/new plugin files, Other/test-only parser and
records. No UI/REST/AJAX/hooks/cron/DB/file-service mutation; WordPress API/escaping/
nonce/i18n checks not applicable to this isolated byte module. No meaningful bloat,
duplicate subsystem or split trigger: one decoder and one parameterized suite;
Bloat and UI/UX workflows unnecessary. No FULL/whole-plugin row refresh.

07 Phase1 (read-only) explored all twelve categories source-scoped. Seven
applicable risk families: empty/partial results, frame/clock boundaries, identity
reuse/shared input, malformed metadata/results, hostile object inputs, extra
activity and retry/closure. No real external service, WordPress environment,
plugin conflict, user permission, deletion cascade or browser path in this scope.
One confirmed Medium boundary defect: own Buffer.buffer accessor could execute
instead of refusing (theoretical hostile API input, no production caller).
07 Phase2 strategy Prevent: reject own buffer property before reading it. No
production behavior/guards changed. Baseline targeted612 tests; all passed.

07A Phase1 inventory: pure Node24 tests call actual decoder; independent golden
metadata fixture, positive N8, every split/truncated prefix, byte corruption,
row/error/NULL/status/lenenc, identity/profile and original-clock controls. These
protect observable feed/finish outcomes, not merely source text. Static PHP-source
binding is labeled contract drift detection, not PHP/WordPress runtime proof.
No redundant removal/consolidation justified; no mock of the unit under test.
07A Phase2 adds the Buffer getter/proxy regression: RED613 tests/one failure from
the intended getter exception, GREEN613/613 after the bounded fix. Five permanent
in-memory source controls assert-kill removed association/result/header-length/
deadline/sticky-refusal guards; original source never mutated on disk. No test
skips, weakened expectation, native mock success or coverage-percentage claim.
Full Node/PHP CI evidence belongs in the toolkit entry; Windows full PHP suite
is not claimed. Pure resource checks are limits, not physical/resource closure.

Final focused Feature Security follows fixes and validation: exact byte/context
validation, descriptor/proxy refusal, no public authority/SQL execution/IO/sensitive
logging, no write-capable handles. The confirmed scoped accessor defect has
permanent pure-unit regression coverage; no outstanding scoped security-fix
handoff. Final whole-plugin security audit/FULL suite remains a future RC gate.

Restore OptionA verified clean uploader0d41664 and existing external20261024-161220
archive, no snapshot/checkpoint/restore created. Static Site Operations/registry
only; no LocalWP/Novamira/site/SQL/native inspection or operation. Publication
counts/run links are recorded source-scoped in toolkit evidence after exact-head CI.

Executed local checks: targeted Node613/613, full Node1677/1677 (no skips/failures),
related PHP20/1538 no skips, both MJS syntax checks, uploader PHPCS255 files,
build/runner verification/runner syntax and unchanged generated assets. Existing
PHPCS functionWhitelist configuration deprecation remains (no new lint violation).
Dashboard PHP299/1423 with two existing local Sodium skips, PHPCS138 files and
structure check passed. Local Markdown targets43 per repo and whitespace checks
passed. No local Windows full uploader PHP-suite or native-runtime pass claimed.

Implementation-plan progress: narrow completed-zero-row/provenance obligations
now have a non-vacuous synthetic decoder. Actual observation and all A1-A4 remain
Blocked. Recommended next gated slice: **local-only V2.3 Fixed Projection Observer
Adapter and Session Provenance Integration Design**, design-only, to specify
source-bound immutable-byte handling and genuine limited-session/request binding
before any observer attachment or native operation. Keep actual worker/storage/
native execution/production wiring unchanged.
