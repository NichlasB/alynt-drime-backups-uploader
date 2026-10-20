# V2.3 Socket-Free Native-Profile Selection and Control Transcript Foundation

2026-10-20. Approved R3 repository-only, **synthetic fixtures only**. Uploader
0.5.25 owns seven test-only MJS files and this record; dashboard0.1.77 owns plan/
design pointers. No production PHP, old decoder, native relay/supervisor/worker,
dependency, workflow, asset, version or wiring changes. No IPC, socket/listener,
native process experiment, WordPress/authentication/storage/database/users,
suppression, grant, activation/effect/rollback, package/release/tag/deploy operation.

## Boundary, safety and implementation

Explicit excluded bases: uploader `a78bc9c7e460f78d17dafb44e1608b8c074ca562`,
dashboard `8e2053f553aa3fc51958f5991bbfcbf2dcafd0ff` (includes the whole approved
native-profile/control design, not merely its final pointer commit). Start heads
uploadera78bc9c/dashboard5676342/toolkit4a3ad69, all clean/synced. Governing contract:
dashboard `docs/V2_3_NATIVE_PROFILE_WITNESS_PRIVATE_CONTROL_CHANNEL_DESIGN.md`.
Existing native/nonce/callback7/8 contracts are dependencies, not new proof here.

Master/toolkit/restore and repository governance followed. Pristine Option B
source-only snapshot under
`C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/`:
`alynt-drime-backups-uploader-20261020-171655.zip` plus matching `.txt`,11007 files,
SHA256 `2D0D33DC4A6123F89ED485DC53FC4057507E3421DCE29262E999DD3630F13DC5`.
No checkpoint/stash/restore or site/database backup. No site operations attempted;
prior Plugin Tester confirmations are not consumed as approval for a later proof.

- `scripts/tests/native-witness-profile.mjs`: strict bounded protocol10/MariaDB10.6
  candidate; six reserved greeting bytes distinct from extended capabilities;
  exact trusted user/database/native-password,20 opaque auth bytes, optional
  capabilities/attributes bounded1024 bytes/16 pairs, no unknown negotiation.
  Client collation8/45 is a conservative candidate, **not observed native evidence**.
  Canonical seven-byte status2 OK only. No auth material returned or retained.
- `scripts/tests/native-profile-witness.mjs`: three factory-local distinct Symbol
  lifetimes, explicit role lookup (not property order), independent bounded frame
  assembly/accounting. Capture closed/auth alive at binding; auth cannot disappear
  before writer completion. Same writer/capture profile, original10s window and
  >=6s binding. Opaque intermediate query/prepared/result traffic, exact whole
  COM_QUERY target once, first exact seq1 OK, no pending prior egress or partial
  server response. Response may precede request accounting without controlling
  delivery. Terminal match requires complete accounting/closed modeled lifetimes
  and literal synthetic no-fault receipt. Failures consume the observation.
- `scripts/tests/native-control-transcript.mjs`: canonical ASCII JSON+LF, exact
  ordered schemas/sequence/tokens/roles,4096-byte lines/8 lines/32KiB attempt;
  rejection/partial EOF/extra/reused correlation permanently refuse. Two distinct
  virtual clock epochs, supervisor ROOT T0 before bytes, no BIND budget reset,
  >=6s on both clocks, <=1s BIND-start-to-ACK interval including fragmentation.
  No deadline refresh at end of BIND. The factory consumes supplied invented
  tokens/identities; it does not generate secrets or establish a real channel.
- Three Node test files plus one shared independent invented-byte fixture.
  APIs/results are frozen and redacted; all authentication/native/IPC/delivery/
  forwarding/storage/retry/dispatch/execution claims are absent or explicitly false.
  No public command, transport instruction, suppression method or handle export.

The selector and control model are deliberately not wired together or to a real
owner. Numeric role IDs, supplied Symbols, clocks, accounting and terminal receipt
are synthetic assertions, not physical identity, authenticated callback admission,
byte delivery or transaction evidence. Recognition relies on the reviewed fixed
synchronous exclusive-writer/local-server source contract, not a generic SQL or
resultset parser. Production guards7/8 and original native deadlines are unchanged.

## Canonical source-scoped review sequence

### Feature Light, then Bloat/Structure

Feature scope: seven MJS files, owning record/two dashboard pointers, minimum old
protocol/decoder/relay and native admission root/transaction/session references.
Risk areas Other/test tooling only. Initial quality review identified three
new-helper defects: property-order role interpretation, high-bit ASCII alias and
implicit string coercion. Permanent regressions failed on each original helper,
then passed after explicit role lookup/ASCII range/type validation. No runtime
production defect, public contract change or manual product decision.

Canonical bloat helper with explicit uploadera78bc9c reports zero PHP/JS/CSS files:
it excludes `.mjs`, **not evidence of zero changed source**. Supplemental measured
TOTAL: profile67, selector97, control79; tests78/104/110; fixture44 at final
measurement (later review evidence changes, if any, are remeasured at closeout).
All below250, fixture below150. Classify all Consolidate/retain, no removals,
split-now or pre-release02 deferral; no Phase2 structural edits needed. Direct,
string and WordPress hook/filter/shortcode/AJAX/REST/cron references checked: new
helpers are test imports only, no production registration. Separate passive helpers
preserve old decoder/relay contracts without a generic provider/authority layer.

### 07 Edge Cases: read-only inventory then bounded fixes

All12 canonical categories considered **only for this source boundary**:

| Category | Applicable behavior / disposition |
|---|---|
| Empty | Missing/empty input or incomplete transcript cannot complete. |
| Boundary | Threads/flags/attributes/frames/line sizes/counts/bytes/BigInt clocks. |
| Concurrency | Modeled response/accounting race and partial BIND/late ACK, no real concurrency claim. |
| External services | No service calls; candidate native negotiation is not interoperability proof. |
| WordPress variations | Not exercised; runtime/auth/nonces unchanged, no WordPress bootstrap. |
| Conflicts | Module-local names, no hook/global/public surface additions. |
| Corruption | Malformed/truncated/coalesced messages, noncanonical bytes/types and aliases. |
| Input extremes | Preallocation/attempt caps; no unbounded queue or resynchronization. |
| Permissions | Lifetime/root separation is synthetic only; no authorization obtained. |
| Deletion/lifetime | Closed capture/auth/writer and fourth/reused lifetime refusal. |
| Recovery | Permanent failure/one-shot finish, no retry or fallback root. |
| Browser/client | No UI/browser surface; Node tooling only. |

Two additional Medium helper-contract defects confirmed by failing regressions:
F4 BIND completion reset the ACK interval after slow partial serialization;
F5 borrowed auth could close before selected writer completion. Prevent with
first-BIND-byte clock sampling and ordered lifetime closure. No production fix.
Final security/07A malformed-shape crosscheck added F6: inherited schema name
`constructor` could throw instead of permanently refusing. The new test failed
with TypeError; own-key schema lookup makes the same malformed inputs refuse.
Six confirmed helper defects total (F1-F6), all fixed; no Critical/High production
finding. Missing/corrupt/limit/clock/lifetime scenarios hand off to07A as pure-unit
or bounded in-memory resource tests, not Database Review/native concurrency.

### 07A Adversarial Test-Suite Review

Baseline after initial implementation97 new tests; two fixture defects corrected
without changing valid requirements (1024-byte attribute fixture arithmetic and
nonzero thread mutation wrongly expected to reject). Three initial red-to-green
regressions, two late edge regressions and one schema-refusal regression. Final111 scoped/226 full Node tests,
no failures/skips. Existing old decoder/relay tests preserved unchanged. Independent
expected masks/frames; exact positive match required so always-unavailable fails.
No mocks of the unit under test, wall-clock sleeps, native data or new dependencies.

One weak test found by a surviving writer-binding mutation: it refused because
already bound, not because the foreign lifetime differed. Strengthened to test
before first binding; same mutation now assertion-killed. No test removed/weakened.
Six semantic controls assertion-killed: capability allowlist(9 failures), writer
lifetime(1), exact selected OK(5), root correlation(1), Node ACK deadline(1), and
always-unavailable positive(9). Each helper restored to exact pre-control SHA256
before continuing. First control output-filter harness truncated the assertion
name; repeated with TAP filtered ERR_ASSERTION, not misreported as a source defect.
Three later hardenings have direct red-to-green evidence; controls never touched
runtime/native code. Final full validation runs only restored intended source.

Final scoped automation status requires exact-head Linux full suites; publication
counts/run links are recorded in toolkit's source-scoped entry, not inferred from
predecessor CI. No line-coverage percentage claimed; contract/mutation evidence
is the relevant measure. No consolidation/removals justified. Physical pipe and
native integration remain separate approval/runtime handoffs, not blockers for
completion of the approved pure helper contract.

### Final Feature Security gate

After routine fixes, control restoration and final targeted/full local checks:
bounded input before parse/allocation; exact fixed schemas and literal command/OK;
private factory lifetimes/tokens never exported; separate clocks/no refreshed root;
no protected reads, WordPress authorization, nonce handler, SQL, filesystem, socket,
process or callback in the new helpers. No arbitrary dispatch/delivery/secret API.
No confirmed production security issue; F1-F6 and test weakness protected permanently
at the pure layer. No claim that synthetic role/token validation authenticates
WordPress or proves separate nonce domains; unchanged genuine guard source remains
the authority and needs later approved integration. No whole-plugin security,
Database, UI/UX or full pre-release completion. No deeper06 required for this bounded
scope: explicit resource ceilings, preallocation rejection and representative
64MiB/4096-frame tests cover the new traversal; no general SQL/parser/performance work.

## Validation and remaining gate

Local commands: targeted three-file `node --test`111/111; full existing
`npm run test:dev-tools`226/226, no skips (only existing watcher uses owned temporary
filesystem fixtures). Related isolated PHPUnit filter
`Registration(NativeFailure|NativeQualification|ClosedQualification|NativeOutcome|AdmissionOutcome|AdmissionJoin)`
173 tests/10171 assertions/no skips. WPCS235 (existing PHPCompatibility whitelist
format deprecation only), build/runner verification; dashboard PHPUnit299/1423/
two existing local Sodium skips, WPCS138/structure/build. No full Windows uploader
PHP suite claim; exact-head Linux PHP7.4/8.3 CI must complete after push. Generated
assets unchanged. No native/IPC/WordPress/SQL/process-failure experiment executed.

Protocol references rechecked2026-10-20:
[MariaDB handshake](https://mariadb.com/docs/server/reference/clientserver-protocol/1-connecting/connection)
and [OK packet](https://mariadb.com/docs/server/reference/clientserver-protocol/4-server-response-packets/ok_packet).
These support byte-layout constraints, not native compatibility or authentication.

Implementation-plan progress: the approved socket-free candidate profile/passive
selection and private transcript contract is now executable and adversarially
tested; safe-local work reaches the physical-channel proof gate.
**Recommended next gated slice: local-only V2.3 Windows Owned-Process Stdio
Feasibility and Cleanup Proof**, owned child stdin/stdout/stderr only, **no WordPress,
database, networking/native sessions or response suppression**. Prove partial
read/write/flush/EOF, bounded output, stalled-peer watchdog and owned child cleanup
before separately approving native observation-only integration. This task does
not authorize that experiment or any production wiring.
