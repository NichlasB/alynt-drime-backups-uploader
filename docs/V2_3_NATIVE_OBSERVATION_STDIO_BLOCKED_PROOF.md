# V2.3 Observation-Only Native Witness / Private Stdio Integration — Blocked

Historical failed attempt, preserved as executed. Separately approved successor
`V2_3_NATIVE_OBSERVATION_STDIO_HARDENING_PROOF.md` records the later bounded fix and
passing proof; it does not change this attempt's outcome or proof limits.

2026-10-20. **Not complete; not release evidence.** Approved exact Plugin Tester
local-only target and owned disposable users/storage/listeners/process cleanup.
No response suppression, production wiring, grants, activation, rollback, release,
package or deployment. No production PHP source changed.

## Boundary and prerequisites

Explicit excluded review bases uploader
`f11ba9c7dfa1c2d7cb0f94b03ce2c954863e045f`, dashboard
`d222004dc73eb16320b9e203371be4207f3adbbf`, toolkit
`0d47b4a1ba236e4f4c0d07bc1d0dbac59be55a71`. These identify the complete new
integration attempt, not a later docs-only commit. All three started clean/synced.
Draft executable changes remain **uncommitted and unpublished** because the native
verification failed. Dashboard remains unchanged. Versions0.5.25/0.1.77 unchanged.

Fresh source-only Option B snapshot before edits:
`alynt-drime-backups-uploader-20261020-180734.zip` and matching `.txt`,11059 files,
SHA256 `C379F59383D9E46A6576E5CC3776F5BAE2AF797EF213ADAEF59800C2CE452DB5`, under
`C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/`.
Not a site/database backup; no restore performed.

Site key `plugin-tester`, local-only, LocalWP Plugin Tester,
`http://plugin-tester.local`, WordPress
`C:/Users/Captain/Local Sites/plugin-tester/app/public`, resolved MariaDB10004 and
web10005. User confirmed this exact target and owned scope. Novamira HQ inventory
contained the exact origin; doctor found expired OAuth. No credential reconnection
attempted. Used the approved direct LocalWP workflow instead: initial database
target/baseline inspection through bundled mysql.exe, not PHP/wp-load first.

Bundled runtime resolved from LocalWP metadata to php-8.5.1+1/bin/win64/php.exe.
Before any SQL/fixture/bootstrap operation:
`node scripts/tests/owned-stdio-proof.mjs --plugin-tester-bundled-stdio-only`
passed **141 checks/15 groups**. Every owned child exited/closed, pipes destroyed;
same bounded fixed no-WordPress/no-database/no-network worker as the predecessor.
This establishes bundled pipe feasibility, not native integration completion.

## Native attempt and non-vacuous result

Executed the existing independently audited three-table owner with the new
`observation-native` selection, via `fixed-core-proof-audit.ps1`, MYSQL_PWD only
from the confirmed local source. Only owned cloned options/users/usermeta tables
were created. Full core content isolation, genuine authentication APIs, query
fences and original native runtime stayed intact.

The genuine worker completed its positive joined admission and malformed/bad-nonce
zero-protected-I/O checks: **242 checks/2 groups** reported privately. This is not
the overall proof result. Private inherited stdio BOOT/ROOT/BIND/BOUND/FINAL
completed1241 bytes/195 small parent writes; original idle root bound at callback7,
same pin/budget checked at callback8, real guards still run, no channel callback
inside the locked native section. Tokens/handles/nonce/fixture values not exported.

Observed three strict candidate-profile authenticated pairs, exact role lifetimes,
the literal COM_QUERY `COMMIT AND NO CHAIN NO RELEASE`, canonical idle OK and both
forwarding completion callbacks. No transport failure: six complete/equal streams,
256967 bytes, three normal QUITs; no suppression. Worker exit0 was **insufficient**:
the independent observer returned `passed:false`, reason `writer_command`; the
supervisor and enclosing native proof correctly failed (proof exit1).

### Confirmed source/contract mismatch

`includes/trait-registration-admission-transaction.php` performs
`session(false)` immediately after a true COMMIT return, before marking committed.
`includes/trait-native-closed-begin-session.php::session()` issues a session-facts
SELECT and calls `policy()` for further verification. These are required existing
post-ack safety checks, not an unexpected mutation or a second COMMIT.

The governing dashboard native-profile/private-channel design and synthetic model
reject extra writer commands/responses after selecting the COMMIT/OK. The new
observer mirrors that narrow contract and therefore cannot finalize successfully
on the real unchanged owner. Its coarse `writer_command` label does not identify
the raw failing packet; the source establishes the unavoidable subsequent SELECTs.
No raw SQL/result/auth packet was logged and no wire-specific claim beyond that
label is made. Do not weaken the runtime's session/policy checks, allow arbitrary
post-commit commands/results, or treat target/OK observation alone as completion.

## Cleanup and proof limits

Even though verification failed, actual owned worker exit/close and pipe destruction
completed, no timeout/stderr. Relay listener closed, zero live pairs and zero queued
bytes. Independent database inspection confirmed all observed relay server sessions
absent **before** the exact owned DROP preview/cleanup. All three owned tables were
removed and their absence verified. Existing options/users/usermeta digests unchanged;
independent bundled-mysql audit: **all77 application-table checksums unchanged** and
no guard fixture tables left. No application users/options/cron changed.

PHP/MJS syntax checks and74 existing profile/relay Node regressions passed/no skips.
New observer/control drafts have not completed focused adversarial reviews or final
security validation. No new semantic mutation controls, full local suite, commit,
push or exact-head CI verification claimed. Predecessor CI is historical, not proof
of this uncommitted work. No canonical workflow completion or whole-plugin readiness
row refreshed; no Database Review/uncertainty/zero-delivery/safe-retry proof.

Implementation-plan progress: bundled runtime and real pre-BEGIN private-root/pair
rendezvous exercised; the observation milestone remains **blocked**, not complete.
No further native experiment until the contract discrepancy is resolved.

**Recommended next gated slice: local-only V2.3 Post-COMMIT Session-Validation Drain
and Observation Finalization Design**, design-only. Define how to retain the exact
selected COMMIT/OK observation while bounding and distinguishing the unchanged
post-ack session/policy validation and normal close; preserve original deadlines,
normal forwarding and fail-closed selection. No storage writes/native experiments,
suppression or production wiring in that design. Lost-ack work remains later-gated.
