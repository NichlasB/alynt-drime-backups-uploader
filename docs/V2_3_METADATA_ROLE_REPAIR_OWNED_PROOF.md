# V2.3 Metadata/Safety Statement-Role Repair and Ordered Owned Proof

2026-10-22. Approved harness-only correction, source-backed regressions, fresh
prerequisites and ONE new ordered invocation on the same confirmed Plugin Tester
local-only target. This is not a production feature, release or deployment.

## Scope and immutable history

Review baseline: excluded uploader d1bb06a69bc1d8338254db9e308acf0219b24717;
related dashboard baseline573cb95033ab324a402ff1c910e35d52d78eb59a. The coherent
native feature boundary remains excluded be42b57fff92da1ef65aa1608be29ce4766eeb1e.
Review the changed registry, new fixed role tracker, source-backed tests and two
manifest verifiers, plus directly affected metadata/row sources, case/helper and
cleanup callers. Production includes, loaders, generated runner, guards, budgets,
versions, grants, activation and rollback are unchanged. No public endpoint.

The [previous failed attempt](V2_3_UNCERTAIN_BARRIER_NATIVE_OWNED_PROOF.md) is
historical:61-file dba7632d7f48340a3f06565312abf7f34c113193d38b45af9b7ed83f88eee20c
at d1bb06a. Its failure is not retroactively repaired or re-certified. This approved
successor has62 fixed files, seven groups, LF-normalized manifest digest
5c93e289e5fe06d291b59efed65ebbd016e44b94670591d721abe65cd5852675.
Both PHP/Node verifiers and immutable predecessor identities pass independently.

Fresh source-only snapshots (not site/database backups): uploader11855 files,
alynt-drime-backups-uploader-20261022-115122.zip SHA256
DF14602C3CCB3CF2123CFC62DB34FCF63472B08540A34E159B8C89F9AD57E78C;
dashboard13317 files, alynt-drime-backups-dashboard-20261022-115131.zip SHA256
A994843B14486AC17A4D4EBE5AAA88F50AD7683552F941221077A9B25536C9E8.

## UB6/UB7 correction and non-vacuous regression evidence

Fixed role tracker accepts the exact five compiled metadata prepare/ACK/execute/
close cycles before binding, then safety row0, the second five metadata cycles,
and remaining rows1-9. Only an executed, bound first safety statement can qualify
for the held timeout. Unknown SQL, IDs, premature binding, replayed execute,
duplicate prepare, wrong close/order and missing post-safety metadata fail closed.
Successful barrier COMMIT now requires all10 row lifetimes plus both metadata
passes. No arbitrary SELECT exemption, clock renewal, retry or transport bypass.

Source fixture extracts actual production metadata SQL, locking SELECT template
and manifest caps; tests require exact correspondence to the fixed classifier.
RED before repair: both selected/released source-backed positives fail at the
legitimate metadata execute (assertion mismatch, not syntax/bootstrap failure).
GREEN after repair:26 registry/source groups; related64 Node groups, no skips.
Two new in-memory semantic controls independently assertion-kill statement-ID
and metadata-order checks; the existing execute-credit controls remain effective.
Disk source is unchanged by controls. Eight scoped controls total across the
native source/registry/protocol tests. Synthetic packets do not prove SQL results,
binary bound parameters, genuine authentication, physical locks or native cleanup.
The original source-pinned root still owns bound names and result semantics.

## ONE new ordered native invocation

Exact target: plugin-tester/local-only, LocalWP Plugin Tester,
http://plugin-tester.local, C:/Users/Captain/Local Sites/plugin-tester/app/public,
database10004/web10005. Bundled PHP8.5.1/mysqlnd, MariaDB10.6.23,64-bit Windows;
not a broader supported profile. Fresh metadata/ports/bundled preflight passed.
Novamira complete inventory/selected doctor checked: expired OAuth remains;
no credential repair, bundled approved LocalWP tools used. Initial77 application
tables, zero owned fixtures. Existing password used via environment, not recorded.

1. Owned tree containment and10-check/2-group cleanup prerequisite passed.
2. Fresh separate genuine guard prelude266 checks/11 groups passed,
   qualification_attempted=false, its fixtures removed.
3. SAME62-file no-fault composition passed:960 worker checks,10 genuine denials,
   24 guards/four nonce domains, original committed qualifier and terminal,
   6159 channel bytes, both exact COMMIT drains, eight equal streams. Exit AND
   close0, no stderr/watchdog, erased slots and independently verified cleanup.
4. Fresh selected-loss case PASSED:960 checks/24 guards,6159 channel bytes,
   actual uncertain qualifier and original once-only terminal. Exactly11 writer
   response bytes withheld before any target write attempt/completion; original
   request credited, eight conserved/seven equal streams,429496 transport bytes.
   Barrier COMMIT delivered and exact drain accepted. Writer drain is correctly
   false for deliberately lost response, not synthesized as successful. Transport,
   worker and private channel conjunctively establish this case's qualification;
   registry alone still reports private_qualification_proven=false.
5. Released-contention case FAILED at direct_ack during helper construction,
   before relay/qualification worker creation. Its fixture tables were created.
   No wait witness, lock contention, uncertain result, terminal or timeout proof.
   Held case NOT RUN. Stop-on-first-failure honored; no second invocation/retry.

First three fixture lifetimes (guard/no-fault/selected) each proved known sessions
absent,10 locks available,77 application checksums unchanged BEFORE AND AFTER
previewed removal of three owned tables (9 usermeta/3 users/41 options).
Released-case cleanup is UNRESOLVED, not complete: retained exact owned prefix
wp_alynt_core_probe_7f3e5973b625_ has options8 rows, users0, usermeta0, one valid
owner marker (not published). Independent read-only final audit:77 non-fixture
tables, exactly these three fixture tables, zero other local database sessions,
zero InnoDB transactions and zero owned proof processes. debug.log unchanged:
3244712 bytes,2026-10-22T08:58:37.4524157Z. The released case did not return its
pre-create baseline to the caller; no final whole-attempt checksum claim is made.
Do not fabricate that baseline, set transport receipts true, invoke ten-row cleanup
on an eight-row pre-worker fixture, or broad-DROP prefixes to make cleanup green.

## New blockers (read-only diagnosis; not repaired or retried)

UB8 High/Harness: uncertain-barrier-native-helper.php initial guard compares
argv[1] directly to Windows realpath. Case passes forward-slash root to helpers,
whereas fixture calls pass realpathSync(root). Socket/database-free bundled-PHP
comparison confirms unequal strings for the identical target; helper exits before
HELLO/connection. Parent waits for direct_ack; no larger timeout is a solution.

UB9 High/Harness cleanup: startUncertainNativeHelpers startup catch joins children
but throws without returning owned construction/closure receipts. Case creates
fixtures first; helpers assignment never completes, transport never exists and
finally cannot satisfy its cleanup gate. Original baseline/marker are lost with
the case closure. Proper recovery must distinguish never-opened resources from
unresolved ownership and initial eight-row fixtures from initialized ten-row ones.
Do not weaken established cleanup predicates or infer historical preservation.

## Source-scoped canonical checkpoint

Feature scope: fixed test-only observer/CLI file/process/SQL proof surface.
Files reviewed:16 source/test/manifest artifacts (eight changed artifacts plus
direct metadata/row/manifest/case/helper/helper-PHP/fixture/proof dependencies),
and five uploader/dashboard owning records. Risk areas:DB proof,File Ops,Other
(owned process/private IPC);no UI/AJAX/REST/cron/production change. Issues found4,
issues fixed2 (UB6/UB7),issues requiring new decision2 (UB8/UB9). Feature Light
categories1-9 reviewed;native acceptance is explicitly blocked,not a clean pass.
Bloat/Structure Phase1 used explicit be42b57:18 PHP files,max99 TOTAL,zero oversized;
helper CODE1 anomaly disclosed. Twelve script mjs files measured separately,
max110 TOTAL; new tracker49, source fixture31, source tests47. Keep/consolidate
classification, no removal/split justified; no Phase2 bloat edits needed.

07 categories1-12 assessed source-scoped: invalid/empty/order/ID/metadata/timeout
inputs, stale source, credits/roles/resource caps, helper startup and cleanup.
UI/browser/HTTP/bulk/deletion cascades inapplicable. UB8/UB9 are native-runtime
handoffs; source-negative tests cannot substitute physical acceptance.
07A Phase1 baseline32 Node plus manifest1/15 passed; approved Phase2 adds14 Node
groups and strengthens12 registry groups. RED2/GREEN26, related64 pass; no test
deletion, skip, artificial success, weakened guard or coverage-percentage claim.
Native integration status **Blocked**, not Clean: released failure/retained fixtures
and unrun held case. No formal Database Review or whole-plugin readiness refresh.
Final focused Feature Security reviewed after full QA: strict fixed SQL/ASCII/
owned-prefix/statement-ID/order fences;no supplied policy,write-capable handle,
public endpoint,credential output or new privileged read. Original genuine guards,
four nonce domains,source identities and resource limits remain unchanged. No new
confirmed security vulnerability;UB8/UB9 availability/cleanup blockers are not
waived. No regression handoff for a new security defect. FULL_PRE_RELEASE at RC.

## Validation/publication and next gate

Targeted PHP49 tests/1019 assertions;Node64/64;bundled/socket-free preflight and new
Node/PHP syntax pass. Full local PHP8.5.1:2113 tests/50813 assertions,four existing
Windows symlink skips,zero failures/errors,18:29.966/178MiB. Full Node743/743,
zero failures/skips. WPCS248 passes;existing PHPCompatibility functionWhitelist
comma-array deprecation disclosed,not suppressed. Build and deterministic runner
verify unchanged7356eacd57ed67f4265db8198f8ca5809e07f4bd985b1d6d00a88cb915112117.
Dashboard docs preservation299/1423,two existing Windows Sodium skips,
WPCS138/structure/build pass. Local Markdown links24 uploader/32 dashboard resolve;
whitespace clean,production/generated source unchanged. Exact-head CI/counts and
publication receipts are recorded in the toolkit's owning source-scoped evidence;
green isolated CI does not replace failed native contention/cleanup acceptance.

Implementation-plan progress: R1 now has same-composition genuine/no-fault AND
selected-uncertain terminal acceptance with cleanup; joined contention/timeout
and released-fixture cleanup remain BLOCKED. R2-R7 remain NO-GO.

**Recommended next gated slice: harness-only Windows direct-helper target-path
and pre-worker ownership/cleanup recovery correction, source-backed startup
regressions, explicit recovery of the exact three retained owned fixture tables,
then fresh prerequisites and ONE new ordered contention/timeout proof on the same
confirmed Plugin Tester target.** Recovery must verify exact ownership/state,
preserve application state without inventing old checksums, retain guards/budgets
and disabled wiring. Additional repair/recovery/native-attempt approval required.
