# V2.3 Native Observation / Private Stdio Hardening and Owned Cleanup Proof

2026-10-20. Approved R3 exact-confirmed `plugin-tester`, local-only, LocalWP
“Plugin Tester”, `http://plugin-tester.local`, WordPress at
`C:/Users/Captain/Local Sites/plugin-tester/app/public`, MariaDB10004/web10005.
Only owned disposable users/storage fixtures/listeners/processes and cleanup.
Existing application state, runtime PHP, suppression and production wiring stay
untouched. No grants/activation/effects/rollback/package/release/tag/deployment.
Versions0.5.25/0.1.77 unchanged; not an RC.

## Ownership and coherent review boundary

Starts uploader`9c96575f38e67f65670afae10851092eea243ec9` synced with15 known
unpublished native-draft paths, now approved for hardening/publication; dashboard
`5a9e3210e945c955791fc154f8a58e5eb088d185` and toolkit`058f383` clean/synced.
Explicit excluded coherent bases uploader`f11ba9c7dfa1c2d7cb0f94b03ce2c954863e045f`
and dashboard`d222004dc73eb16320b9e203371be4207f3adbbf` cover the attempted integration
and supporting post-COMMIT design/foundation, not merely a verification tail.
Review native adapter/control/worker/fixture-owner/stdio-selection/CI changes,
supporting pure drain and directly affected session/policy/admission/auth/cleanup.
Dashboard owns plan/design pointers only. Old strict witnesses/tests unchanged.

Source-only restore points under toolkit-snapshots, not site/database backups:

- uploader `alynt-drime-backups-uploader-20261020-193429.zip`,11087 files,
 SHA256`E83D33C844CDC4AC81D94E9B74C8745B05782AAEC7BED41A0CF4AB003305EEBE`;
- dashboard `alynt-drime-backups-dashboard-20261020-194216.zip`,13168 files,
 SHA256`C1628456B61C6828E5351E9DEAC374ECBF1B8D44578A2C0120D8201072DEA2E9`.

Master/toolkit/repository/Site Operations/LocalWP DB workflows followed; fresh
target confirmation before changes/experiments. Novamira exact-origin inventory
available, doctor OAuth expired, no credential change. Normal LocalWP fallback:
bundled mysql.exe initial target/inventory/checksums before PHP/WordPress access.
Bundled PHP8.5.1 resolved from active service metadata, not stale top-level PHP.

## Findings and bounded fixes

Feature scope: dormant proof tooling/records. Risk Other/process/file operations/
owned DB fixtures, no public UI/AJAX/REST/cron/API change. Five harness findings
fixed; no production defect fix or requirement relaxation:

1. Medium postamble mismatch: reuse exact fixed-drain grammar for the two existing
 session/policy SELECTs,10/5-column one-row results, idle EOFs and normal QUIT.
 Original transaction checks, COMMIT/OK facts,10s root and three profiles retained.
2. Medium coarse credits: parent owns a unique Symbol per physical frame; exact
 pair/direction/Symbol accompanies observation and successful write callback.
 Selected event stays attached to its credit; one pending per direction, both
 permitted cross-direction callback races supported.
3. Medium exception leakage: passive membrane latches false/throw, catches even
 failure-report exceptions, never gives transport instructions. Header4KiB drain
 refusal precedes allocation but invalidates observation, not forwarding. Observer
 adds no body allocation/copy; transparent relay retains2MiB frame/64MiB bytes/
 4096 frame-pair/shared8MiB queue caps and normal timeout/backpressure.
4. High owned-harness cleanup risk: supervisor finally attempts only its own child
 cleanup; actual close/pipes or unresolved outcome recorded. Outer owner now
 requires worker close and pipe destruction before independent session absence/
 exact row-preview/DROP. Unresolved ownership retains exact fixtures for review.
5. Medium unjoined FINAL: actual registry must accept timely provisional claim
 after fixed drain. Worker exit0 alone cannot pass. Final native observation
 requires selected egress, drain/QUIT, all role closures and transport success;
 enclosing owner independently requires process/listener/session/fixture cleanup
 and unchanged application data. No synthetic receipt substitutes for native facts.

Private inherited canonical stdio retains bounded envelopes,3-byte parent writes,
1-byte PHP reads. Genuine callback7 BIND: idle,20 protected reads/zero writes,
capture closed. Callback8 rechecks original pin/budget with no writer I/O. Separate
nonce domains/all real guards retained, no channel callback inside locked native
transaction. No write-capable observer handle/raw token/SQL/auth/row/session ID export.

## Executed native proof and cleanup

Before SQL, bundled-PHP stdio preflight **141 checks/15 groups**, pass. Every owned
child exited/closed/pipes destroyed, including bounded stalls/overflow/EOF/late ACK/
backpressure/tail cases. Physical pipe evidence, not OS hard-crash/job-object or
failed-kill guarantee, and not native fault injection.

`fixed-core-proof-audit.ps1 -Proof observation-native`: **passed**, exit0.
Genuine full WordPress7.1.2/PHP8.5.1 worker **242 checks/2 groups**: malformed/
bad-nonce zero-protected-I/O denials plus positive joined admission, original receipt,
ten guards/separate nonce domains, closed native clients, unchanged borrowed auth
facts and no remaining locks. Private channel1241 bytes/195 small parent writes,
no token export. Strict three profiles/role lifetimes, exact COMMIT/canonical idle
OK and exact-frame egress, fixed drain/QUIT and provisional FINAL all observed.
Six complete/equal physical streams,256878 bytes, three normal QUITs. Worker exit0,
closed/pipes destroyed, no stderr/timeout, listener closed/live pairs0/queued bytes0.

Independent sessions absent before owned DROP; three tables removed after exact
previews7/3/41 and absence verified. Actual options/users/usermeta digests unchanged;
independent mysql audit **all77 application-table checksums unchanged**, no fixtures.
Separate genuine `-Proof guard`: **266 checks/11 groups**, pass: stale original
intent/live nonce control, expiry, wrong actor/capabilities, raw revocation versus
warm caches, malformed before protected reads, public unavailable/no-handle guards.
Its three owned tables removed after previews9/3/31; all77 checksums unchanged.
No existing users/options/cron or production/client/Drime state changed. Earlier
failed attempt remains historical in `V2_3_NATIVE_OBSERVATION_STDIO_BLOCKED_PROOF.md`.

## Canonical source-scoped reviews

Feature Light: five findings above fixed; no open manual requirement decision in
this bounded passive scope. Backend-only, no UI/UX workflow.
Bloat/Structure Phase1/2: canonical explicitf11ba9c helper before/after four PHP
files87/52/41/53 TOTAL, zero oversized; CODE1 tokenizer artifact. MJS omitted by
helper; supplemental actual counts registry97/control48/supervisor48/membrane13,
stdio case103/proof41/runtime18, frames77/server64, tests110/43. All Consolidate/
retain; no removal/split/02 deferral or structural Phase2 edit. Direct/string and
hook/filter/shortcode/AJAX/REST/cron search confirms test-only imports, no wiring.

07 read-only inventory then bounded handling: all12 categories considered. Empty
claim; packet/clock/cap boundaries; callback races; unavailable local transport;
fixed WP/runtime/profile variations; plugin/content isolation; malformed messages/
results; volume; nonce/role/protected-read permission guards; process→pipe→listener
→session→fixture cascade; no retry/rebind; no browser surface. One High owned-cleanup
and four Medium harness findings above addressed and handed to07A. No production
Critical/High finding. Unsupported negotiation refuses, never relaxed for green.

06 ten categories considered: no runtime queries/assets/HTTP/cache/index/job change;
fixed three-row searches and bounded field/line loops, advancing parser offsets,
header-before-allocation observation latch, bounded queues/process timers. Native
postamble unchanged. No throughput/exhaustion claim; no further optimization needed.

07A: **25 scoped /331 full Node**, no fail/skip. Actual registry positive path,
foreign/replayed credits, both callback races, expired root, opposite partial,
arbitrary-query refusal, header latch, observer false/throw/reporter throw while
stream equality/forwarding persists. Actual private owner uses isolated Writable/
PassThrough API streams: fragmented positive, unavailable, wrong root, zero checks,
trailing data. These are not OS/auth/native proof; separate actual runs above are.
Six actual-source **in-memory** semantic controls assertion-killed: unavailable
positive owner, exact credit, fixed query, final claim, transport receipt, opposite
partial. Exactly one source target each, import validation before assertion trap;
no filesystem/native/auth/SQL mutation. Registry disk SHA256 remains
`50363ED87CD71B879B38A4B8E4A5D20441A4598CB76B71624046EE3343BBB035`;
membrane`75E6101E64B8500F78E68D02D0114FA6D884B5CEFF8F580C16725A36B1A53C68`.
Counterfactual harness regressions, not production red-to-green or physical fault
experiments. No assertions/skips weakened, artificial success path or tests removed.
Exact-head full CI counts/status recorded in toolkit after completion, not inferred
from predecessor CI; no coverage percentage or Windows full-uploader-suite claim.

Local related isolated PHP **173/10171**, no skips; WPCS235 pass (existing
PHPCompatibility whitelist-format deprecation), new syntax/runner lint/build/
deterministic verify pass, generated assets unchanged (runner7356eacd).
Final Feature Security after fixes/controls/native/local QA: exact targets/allowlisted
identifiers/prepared values, genuine auth/separate nonces, private bounded messages,
no observer authority/export/transport instructions, redacted errors/owned cleanup.
No remaining confirmed scoped security defect. No whole-plugin Security/Database/
historical readiness refresh. Full pre-release remains required at coherent RC.

## Limits and next gate

Proves passive observation of an acknowledged commit with native validation and
cleanup on this exact profile. Does not prove lost ACK/suppression, uncertainty/
zero delivery/safe retry, real failure-origin qualification, arbitrary runtimes,
or physical failure isolation of every native teardown. API controls and stdio
preflight must not be presented as those claims. Production remains dormant.

Implementation-plan progress: blocked native observation now hardened/proved in
approved no-suppression lane; uncertainty is the next meaningful gated boundary.
**Recommended next gated slice: local-only V2.3 Selected COMMIT Response-Loss and
Uncertainty Witness Design**, design-only. Specify owned lost-ack experiment,
failure-isolation/cleanup and provenance gates before any suppression/native fault
experiment. Keep native experiments/storage writes, production wiring, grants,
activation and rollback disabled in that design.
