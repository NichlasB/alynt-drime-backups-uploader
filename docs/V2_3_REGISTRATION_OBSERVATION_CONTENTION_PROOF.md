# V2.3 Native Registration Observation Contention and Failure Proof

2026-10-19. Approved R3 Plugin Tester local-only, resolved target and owned
disposable users/storage fixture writes, bounded failures and cleanup confirmed.
Excluded coherent review baselines: uploader
`ff02e7380e9e2bf310f2eafc9af4b02eae33279d`, dashboard
`6a48e7dda2b5504d65c43a9a3f23873e1a7219ba`, toolkit
`fe65c15e5d37707779878d59c0889c171987086f`. Review includes the entire contention
proof delta, supervisor/worker/CI callers and directly exercised existing native
barrier, session, row, metadata, genuine guard, handoff and candidate contracts;
not merely the latest documentation commit. Development versions remain uploader
0.5.25 / dashboard0.1.77. No production runtime source or wiring changes.

## Target, isolation and restoration

Site key `plugin-tester`, local-only, LocalWP Plugin Tester,
`http://plugin-tester.local`, WordPress at
`C:/Users/Captain/Local Sites/plugin-tester/app/public`. Fresh Local sites.json
metadata: XPa4sCxmk, MariaDB10.6.23 loopback10004 / local, PHP8.5.1, HTTP10005.
Initial bundled mysql.exe SELECT verified origin, **77 application tables** and
zero pre-existing proof tables before PHP/database bootstrap. Genuine core7.1.2,
untouched native cache flag NULL. Novamira guide/complete inventory/doctor checked;
exact profile exists but OAuth expired, so approved LocalWP fallback used. No
credential reconnection, browser, live site or application plugin operation.

Option B exact external source restore point, not a site/database backup:
`C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/alynt-drime-backups-uploader-20261019-171445.zip`
and matching `.txt`, 10,661 files. Snapshot helper's restricted Git discovery was
unavailable; independent elevated Git inspection established clean/synced ff02e73.
No checkpoint branch, stash, source rollback, release or deployment.

Existing audit creates exactly three fresh random-prefix marker-owned options,
users and usermeta tables. Full genuine WordPress boots only on those tables,
without actual wp-config/users/sessions, plugins/themes, cron or external HTTP.
Fixture users/roles/session seeding and synthetic history stay explicitly owned;
this is NOT an actual uncertain registration admission or durable recovery origin.

Each emitted proof receipt includes independent bundled mysql.exe inventories and
checksums of all77 application tables, plus in-memory actual options/users/usermeta
digests. All reconciled receipts passed state preservation and exact three-table
cleanup/absence checks, including failed development/control receipts. SELECT
previews precede owned cleanup. Final restored contention, barrier and guard runs
also pass; no proof process/session/table residue remains.

## New permanent proof and execution boundaries

Four cohesive CLI-only files separate peer execution, bounded process/channel
supervision, genuine coordinator scenarios and explicitly physical failure cases.
The existing allowlisted supervisor/worker and quality syntax checks include the
new `registration-native-contention` mode. No runtime callback/provider/SQL port,
public hook/route, version/schema/dependency/generated asset change is added.

One fixed peer at a time independently verifies exact LocalWP metadata and the
parent's random fixture marker. It locks all ten compiled manifest rows safety
first on its own native transaction, previews the ledger before-image, and may
issue exactly one prepared CAS UPDATE of the owned ledger. Observer and borrowed
auth sessions remain distinct and do not receive this DML. Peer session Com_update
is checked (one, or zero for disconnect); independent full-frame comparison permits
only the expected ledger replacement after acknowledged commit, never other rows.

IPC is an ephemeral loopback-only TCP channel with marker handshake, not a
WordPress endpoint. Windows PHP process pipes are not used for live event waits.
Connect/accept3s, event10s, child command12s, wait discovery8s, clean exit5s and
EOF/abort6s are bounded. Output is capped65536 bytes; fixture command262143 bytes,
each ledger131072. Native connect/read timeouts3/5s, own row/metadata waits2/3s;
the unchanged native owner has its original strict10s acceptance budget. Timeouts
are not a claim of hard cancellation at10s during blocking native I/O.

Wait evidence joins actual INNODB_LOCK_WAITS / TRX / LOCKS, restricted to the
provided exact owned observer thread, blocking peer thread and random owned table.
The peer releases/commits/rolls back or disconnects ONLY after that actual wait is
established. Polling sleeps are scheduling backoff, not proof of contention.
KILL CONNECTION is confined to the waiting owned observer ID, never authentication,
an application connection, another site, global services or settings.

Final native proof: **611 checks / 9 groups**, empty worker stderr, no protected
option API read or browser header:

| Permanent group | Observable protection / proof limit |
|---|---|
| Genuine delayed peer commit | Observer actually waits, then sees exact registered history after peer commit; nine genuine guards, ten native reads, both zero-DML and metadata checks. |
| Genuine peer rollback | Rolled-back owned ledger CAS is absent at the barrier; no retry/closure authority. |
| Genuine row-lock timeout | Actual wait established, unchanged native2s policy expires (bounded1.5–10s assertion); false, one attempted payload read, definite owned rollback, no retry. |
| Genuine owned observer disconnect | Actual waiting owned connection terminated; false, one attempted read, poisoned/closed, borrowed authentication unchanged. This is a real owned transport disconnect, NOT lost COMMIT acknowledgement or server crash. |
| Genuine commit after barrier | Peer starts locking/writing only at final guard8 after observer phase closed / reader null, commits before observer returns; captured absence remains point-in-time and explicitly non-authorizing despite now-present registration. |
| Physical unread BEGIN acknowledgement | Actual MYSQLI_ASYNC command queued, deliberately never reaped; sent latch poisons and owned handle closes. Not an authenticated coordinator or packet-loss proof. |
| Physical unread COMMIT acknowledgement | Actual owned BEGIN acknowledged, COMMIT queued but never reaped; no idle-state promotion, reconnect or retry. No admission DML/crash durability claim. |
| Genuine original real deadline | One bounded10.1s nonce-callback delay before BEGIN; original clock expires, false with zero observation payload reads / zero-DML checks and complete cleanup. This is a real clock test, not lock serialization by sleep. |
| Physical peer channel EOF | Parent aborts a held uncommitted fixture CAS; exact frame unchanged and peer thread independently absent, no orphan session. |

Every positive preserves all six false authority flags, private primitive result
shape, nine genuine guards and original window. All terminal paths preserve
borrowed authentication facts, clear private origin/staged/inflight/auth/physical/
reader state, restore hooks/roles/error handler and reject a consumed-owner retry.
Synthetic writer history is intentionally NOT described as genuine admission.

## Non-vacuous controls and development findings

Five semantic counterfactuals were assertion-killed, with exact source SHA256
restoration after each; no control edit is committed:

| Temporarily broken protection | Failing semantic assertion |
|---|---|
| Always-unavailable observe_native | Genuine pre-BEGIN peer/positive path must actually be reached. |
| Remove FOR UPDATE | Exact owned observer wait never established despite an otherwise closed ten-read result. |
| Remove post-close guard renewal | Positive fails nine genuine guards / complete barrier contract. |
| Grant retry_supported | Explicit no-authority assertion fails. |
| Ignore unread BEGIN/COMMIT sent states | Physical unread acknowledgement cannot become definite success/retry. |

Final exact-restored proofs: contention **611/9**, predecessor native barrier
**4247/9**, genuine core guard **266/11**, all audited/cleaned. Controls demonstrate
counterfactual test value, not a production-bug red/green claim.

Development fixes were test/harness defects, not weakened runtime guards:

- Windows process-pipe nonblocking assumptions deadlocked IPC. Only the verified
  owned stuck peer was terminated; its audit then reported unchanged application
  checksums and full fixture cleanup. TCP events and bounded polling-before-drain
  in the existing supervisor fix the root cause; EOF cleanup has permanent proof.
- Native prepared COUNT(*) yielded an integer, unlike an assumed string. Exact
  numeric count1 now establishes the actual joined wait; no wait is fabricated.
- Constructor failures now abort owned peers; peer diagnostics/rollback/close
  failures stay failure status, with redacted errors over the bounded channel.
- One control attempt returned a non-JSON audit error and was NOT counted as a
  killed control. Source restored; independent inspection found zero owned proof
  processes/tables/waits. The audited authority control rerun then failed correctly.
- Initial asset build was sandbox-write denied outside workspace. Approved repo
  access rerun passed; generated outputs remain byte-equivalent in Git.

## Canonical source-scoped reviews

**Feature Light:** nine executable/config files and direct dependencies; DB/IPC/
owned-process operations only. Harness findings above fixed; no production defect,
UX surface, hook, settings or public-contract change. UI/UX N/A backend-only.

**Feature Bloat/Structure:** explicit excluded uploader base above, same base for
before/after measurement. Seven changed PHP files, zero oversized; all
Consolidate/retain, no forced split or unrelated cleanup. The canonical helper's
known CODE=1 anomaly is not used for code-size claims; TOTAL governs. Direct and
hook/filter/AJAX/REST/cron/string references checked: peer/proofs are CLI-worker
only, production bootstrap never loads them. No Phase2 split is justified.

**07 discovery/handling:** all12 categories considered within source scope. Empty/
corrupt/stale frame/auth/input cases retain predecessor protections; concurrency,
native failure, resource deadlines and owned cleanup are the new high-impact
scenarios above, now covered at genuine/physical layers. Browser/UI, external
services and application deletion cascades N/A. No global/server failure injection
or profile widening. Recovery deliberately refuses rather than retries. No new
production handling required after Phase1; harness corrections have bounded scope.

**07A Phase1 inventory/Phase2 outcome:** PHPUnit9.6.35; six new pure CLI rejection
cases **6/24** protect missing/invalid port, prefix, marker and mode before I/O;
they do NOT prove native authentication/transactions. Existing API/state/privacy
boundary tests4/99 and observation suite remain, with no consolidation/removal.
Nine new native groups are Strong for their stated layers; no API shims stand in
for genuine WordPress, and physical primitives are not mislabeled coordinators.
No artificial success path, added skip, lowered assertion or percentage target.
Explicit limits and joined lock events replace timing-based serialization.
Full exact-head CI gates final Clean status; platform skips are not proof.

**Final Feature Security:** after fixes, controls and restored validation, reviewed
exact CLI target/marker/channel/argument bounds, prepared owned CAS/lock queries,
thread+table-constrained disconnect, nine independent genuine guards/physical pins,
unchanged no-DML observer and sent-latch semantics, no handle/secret export and
owned cleanup. No outstanding confirmed runtime security defect/regression handoff.
No canonical Database Review, whole-plugin Security Audit or release-readiness
refresh claimed. Parser/traversal limits unchanged; deeper06 not triggered.
FULL_PRE_RELEASE remains required for the next coherent RC before packaging.

## Validation and next approval boundary

Related local PHPUnit: **346 tests / 7210 assertions**, no skips, including new
CLI6/24. Full WPCS222 passes (existing functionWhitelist configuration deprecation
only); new proof/supervisor syntax, build, deterministic runner verification/syntax,
developer-tool3 tests and npm moderate audit pass; generated outputs unchanged.
Dashboard local299/1423 with two existing Sodium skips, structure pass. The prior
Windows full run completed1551/17500 with four platform skips, lint/syntax pass;
its completed one-off reminder was deleted. That is pre-delta baseline evidence,
not a substitute for the new pushed-head full Linux suite. Exact publication/CI
results belong in the consolidated handoff and toolkit source-scoped addendum.

Implementation-plan progress: the approved native observer contention/failure
evidence gap is closed for these owned scenarios. Runtime/wiring remain unchanged;
the next meaningful product work is gated admission-origin lifecycle integration,
not maintenance. No real uncertain-write origin retention/recovery, lost COMMIT
wire acknowledgement, server crash, durable ingress closure, safe retry, hostile
SQL exclusion, restore-ABA, grant/activation/effects/schedule rollback or production
readiness follows from this proof. Authentication is not serialized at COMMIT.

Recommended next gated slice: **local-only V2.3 Uncertain Admission Origin Retention
and Observation Lifecycle Integration Design**. Design the exact same-owner
original provenance handoff before real admission and uncertain outcomes, with
loss/expiry/consumption rules and a bounded owned-proof plan. Keep it design-only:
no SQL/fixtures, storage writes, public endpoints, production wiring, grants,
activation or rollback. New explicit trust/lifecycle design approval is required;
do not wire an observer to synthetic fixture history as a recovery shortcut.
