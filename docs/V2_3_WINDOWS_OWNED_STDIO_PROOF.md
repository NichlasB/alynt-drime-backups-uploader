# V2.3 Windows Owned-Process Stdio Feasibility and Cleanup Proof

2026-10-20. Approved R3 **owned child stdin/stdout/stderr/processes only**. No
WordPress bootstrap, site service, database, authentication, native SQL session,
listener/socket/networking, response suppression, production wiring, grants,
activation/effects/rollback, package/release/tag/deploy. GitHub publication/CI is
separate repository work, not networking in the physical proof. Versions0.5.25/
0.1.77 unchanged. No old native relay, decoder or runtime source changed.

## Boundary and safety

Excluded Git bases uploader `d59f4ae0d9586c028614eb34104dea8106b05b95`, dashboard
`26dcd0fb58385f0553441bd67038dbb6973760b2`: complete new owned-stdio feature, not a
docs tail. Toolkit startsfc416cc. All clean/synced before edits. Uploader owns
six source/test files, two CI syntax-loop additions and this record; dashboard
owns two plan/parent pointers. Governing design is dashboard
`docs/V2_3_NATIVE_PROFILE_WITNESS_PRIVATE_CONTROL_CHANNEL_DESIGN.md`, staged step2;
predecessor `docs/V2_3_SOCKET_FREE_NATIVE_PROFILE_CONTROL_FOUNDATION.md` unchanged.
No site target confirmation is consumed.

Master/toolkit/repository governance and canonical restore workflow followed.
Pristine source-only Option B archive and sidecar under
`C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/`:
`alynt-drime-backups-uploader-20261020-173907.zip` and matching `.txt`,11035 files,
SHA256 `1A11B2DE9488DBBB3D47C31F10027AF41E606C56F36F44910C2B417A55E2C78C`.
No checkpoint/stash/restore/site/database backup.

Resolved standalone runtime: **Windows NT10.0.26200.0, Node24.12.0,
`C:/php/php.exe` PHP8.5.1 ZTS x64**, binary SHA256
`EB602CBB4ADD910DCAD2AE0CD83DE92618350ECBF65B6A5E6F117CA9F8AC2C0B`.
PHP `-n`, no ini/bootstrap/autoload; minimal Windows/temp environment, no credentials,
fixed executable/worker/mode allowlist, no shell, hidden window. Network and nested
process functions disabled additionally. Compiled mysqlnd exists but is never
called; no mysqli extension is enabled or database connection opened by this
proof. No claim that this standalone binary is the future LocalWP bundled runtime.

## Implemented physical harness

- `owned-stdio-io.php`: bounded4096-byte one-byte-read line assembly; deliberately
  small five-byte write requests, short-write loop, checked flush and EOF refusal.
- `owned-stdio-worker.php`: fixed standalone CLI fixture, refuses WordPress/CGI;
  no descendants or files. Compiled invented probe/reply/ACK/final exchange, exact
  payload comparison and fixed failure modes. No real ROOT/BIND, nonce or authority.
- `owned-stdio-lines.mjs`: pure ASCII framing,4096-byte lines/4 lines/16KiB total,
  permanent malformed/consumer/partial-EOF refusal; no process access.
- `owned-stdio-case.mjs`: one fixed PHP child at a time, continuously drain stdout/
  stderr, serialize bounded writes and count callbacks/errors. Normal stderr capped
 256KiB; deliberate blocked-stdout fixture attempts at most4MiB without retaining
  raw output. Deliberate blocked-input fixture queues one1MiB write. Only the owned
  ChildProcess object may be terminated, never a process-name/PID search or broad
  taskkill. Capture spawn/exit/close, destroy owned pipes and settle pending write.
- `owned-stdio-proof.mjs`: explicit `--owned-stdio-only` Windows opt-in, exact case
  assertions and redacted aggregate receipt; fail stops before another case.
- `tests/node/owned-stdio-lines.test.mjs`:11 pure regressions. Linux CI runs these
  and syntax-checks the new scripts; **does not run the Windows process experiments**.

Six-second per-case outer watchdog,750ms deliberately stalled-peer watchdog from
its compiled readiness/writing marker,8500ms cleanup-unresolved refusal. No portable
one-second blocking-I/O cancellation assumed. A late ACK is rejected by PHP only
after its blocking read returns; never confused with watchdog termination.

## Actual result and important correction

Initial seven-byte `fread(STDIN,7)` blocked at the1066-byte message tail with parent
waiting for reply. All356 parent write callbacks completed, but no reply; the6s
watchdog terminated **that owned child**, exit/close/pipes reconciled. This is an
observed Windows I/O assumption defect in the new fixture, not a runtime plugin
defect. One-byte reads fixed the same positive exchange; no stream_select,
nonblocking-pipe, fd3, TCP listener or fallback transport was introduced.

Final restored-source command:
`node scripts/tests/owned-stdio-proof.mjs --owned-stdio-only`
passed **141 checks /15 groups**, all15 children spawned/exited/closed and all owned
pipes destroyed, no unresolved cleanup. Positive roundtrip and stderr variants
each exchanged1095 input bytes through366 serialized parent writes/callbacks,
1095 actual one-byte PHP reads,14 small PHP writes before final; clean EOF exit0,
no kill/timeout/refusal. Final run107ms/119ms; stderr variant drained131072 bytes.
This proves fragmentation/flush/liveness of this fixed dialog, not that an OS
short-write return occurred or that throughput/timing generalizes to another runtime.

| Compiled case | Actual protection |
|---|---|
| roundtrip / stderr | Genuine bidirectional progress before input closure, complete callbacks, checked flush/EOF, concurrent stderr drain. |
| eof / partial | Exact refusal23/24, no final result. |
| malformed / oversize | Exact refusal25/26, no final result. |
| output-overflow / stderr-overflow | Parent framing/cap refusal and owned termination/close; raw bytes discarded. |
| output-partial | Child exit0 still cannot complete a partial output line. |
| exit | Exact child27; subsequent write to its closed stdin refuses via callback, no uncaught stream error. |
| stall-read | Owned waiting reader terminated/closed by watchdog,842ms final run. |
| stall-write | Parent pauses reads; child writing marker observed but no completion marker; watchdog terminates/close,863ms final run. |
| no-read | One1MiB parent write returns backpressure and remains pending until teardown; exactly one error callback settles,931ms final run. |
| late-ack |1.1s delayed ACK returns, PHP rejects28/no final;1231ms total, no watchdog claim. |
| tail | A complete final message is observed, but trailing input forces25: final alone is not overall success. |

No retained file/user/storage fixtures, no native proof/session cleanup, no
transaction/physical COMMIT/uncertainty/safe-retry claim. Child lifetime evidence is
the actual owned spawn/exit/close/pipe lifecycle, not merely a successful kill call.
Supervisor hard-crash/OS crash, descendant job-object/fate-sharing, failed OS kill,
other PHP builds/configurations and real WordPress/root/nonce integration are not
proved. Source fails closed on unresolved cleanup; no arbitrary termination fallback.

## Canonical source-scoped reviews

Feature Light: new test-only PHP/MJS/CI/records and minimum design/old supervisor
dependencies. Other/file-process test tooling risk, no UI/public contract. Fixed
the Windows read-tail assumption above; strengthened actual byte/write/read/error
counter checks and pending-write callback settlement, no artificial success path.

Bloat/Structure: canonical helper, same explicitd59f4ae base before/after, reports
PHP TOTAL57/78, CODE1/1 tokenizer artifact (not actual one-line source), neither
over300. Supplemental MJS TOTAL parser28/case102/driver40/test30, all below250.
Classify Consolidate/retain, no deletion/split/defer or Phase2 structural edits.
Only test/script imports; hook/filter/shortcode/AJAX/REST/cron registrations absent.
Two-line CI addition syntax-checks without executing new physical cases.

07 read-only inventory: all12 categories considered source-scoped. Empty/partial
EOF, numeric/string/byte limits, concurrent pipe drains/backpressure, corrupt lines,
resource extremes, fixed process/credential boundary, close/lifetime cascade and
one-shot recovery apply. WordPress variations, external APIs, plugin conflicts,
database and browser/UI behavior unchanged/not exercised. One confirmed Medium
fixture I/O defect fixed; no Critical/High production finding. Bounded pure and
owned-process cases handed to07A, no schema/Database Review claim.

07A baseline11 pure tests pass. Final11 scoped/full237 Node pass/no skips; old226
tests preserved. Six meaningful controls assertion-killed and exact-SHA256-restored:
aggregate cap, line cap, line count, partial EOF, consumer refusal and actual
positive-final counterfactual. Count/consumer reruns preserve increment/callback
side effects so failures isolate the guard. Physical positive counterfactual still
exited0/closed/destroyed pipes before its final assertion failed; a permanently
unavailable owner cannot pass. No watchdog/kill/cleanup guard disabled for a control.
Restored hashes: framing
`BDBB1A932CED5F5FEC5A2F043D4613FE3CED14A66EBC46B3CD3DA9706F54B45D`, case
`6ED5B45CA6FA901F1165390B23E995B39404E1CBBAE5D4D6071A188BF8E1F356`.
No mocking physical I/O or inferring it from synthetic tests; no line-coverage claim.

Final focused Feature Security after fixes/restoration/final validation: fixed
local executable/source/modes, no shell/credential/argument token, strict bounded
input/output, concurrent discarded stderr, private ChildProcess ownership and
required exit/close/pending-callback completion. No confirmed remaining scoped
issue, no production security/authentication/nonce guarantee inferred. No UI/UX,
whole-plugin readiness/Database/full pre-release refresh. Deeper06 unnecessary:
bounded fixed workload/caps/no new SQL/parser traversal; no optimization by weakening
guards. Full pre-release remains required for a coherent RC before packaging.

Local related isolated PHP173/10171/no skips; WPCS235 plus explicit new PHP files
pass (existing PHPCompatibility whitelist-format deprecation only), PHP/MJS syntax,
build/runner verification pass. Dashboard299/1423/two existing Windows Sodium skips,
WPCS138/structure/build. No full Windows uploader PHP claim. Exact pushed-head
Linux full suites and actual counts are recorded in toolkit's source-scoped entry;
do not infer current CI from a predecessor. Generated assets/runtime PHP unchanged.

Primary references checked2026-10-20: [Node24.12 child processes](https://nodejs.org/download/release/v24.12.0/docs/api/child_process.html),
[PHP standard streams](https://www.php.net/manual/en/wrappers.php.php),
[fread](https://www.php.net/manual/en/function.fread.php), [fflush](https://www.php.net/manual/en/function.fflush.php)
and [Windows stream_select caveat](https://www.php.net/manual/en/function.stream-select.php).
Physical results above, not API existence, establish this limited feasibility.

Implementation-plan progress: owned standalone Windows pipe/cleanup feasibility
milestone complete; native profile/guarded original-root integration remains gated.
**Recommended next gated slice: Plugin Tester local-only V2.3 Observation-Only
Native Witness and Private Stdio Integration / Owned Cleanup Proof**. Fresh exact
target/owned fixture/listener/process scope confirmation required. Start with an
equivalent owned-stdio preflight on its resolved bundled PHP binary **before** any
WordPress/database fixture work. Preserve positive admission/real guards/original
deadlines/normal forwarding and prove native negotiation/root/pair correlation;
keep response suppression, production wiring, grants, activation and rollback disabled.
