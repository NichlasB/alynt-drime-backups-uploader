# V2.3 Fixed Native Raw Observation and Session-Ownership Proof

2026-10-18. R3, explicitly approved **Plugin Tester local-only**, including
owned disposable authentication/options fixture creation and cleanup. Uploader
review base (excluded): `e95edd01961ceb8b09bed2bf7bf48b1331a398b4`; companion
dashboard base: `7d67804b25a70c0ac579a2ffcc6c7af55390c055`. This boundary covers
the complete new native observer, not an evidence-only trailing commit.

## Implemented boundary

Three unregistered/test-loaded definitions: the fixed native observer, private
session/profile trait, and private bounded-row trait. Existing genuine request
owner/core guard, model, schema/nonce contracts and production bootstrap are
unchanged. Versions remain uploader **0.5.25** / dashboard **0.1.77**.

The new public `create()` takes no ports. Public `inspect()` always returns its
fixed unavailable shape with storage/dispatch/execution false; debug/serialization/
clone/restoration cannot export private state or handles. This is a planning
observation only. It uses the existing fresh `receipt_read` intent grammar/nonce
domain, but does **not** resolve a receipt, claim absence/no effect, or admit work.
Issuance and all three mutation purposes refuse before protected reads.

The private operation parses exact POST/form bytes, checks the original UTC
deadline, pins the core frame/actor/cookie, and passively verifies the genuine
`wpdb` native handle/session is idle with autocommit enabled. It renews genuine
core session/capability/nonce checks before reads and afterward. Native fences
detect ambient transactions, autocommit-off, closed handles, replacement handles
and changed physical connection IDs even on the same `wpdb` object. They never
repair, begin, commit, roll back, reconnect, ping, configure or close that borrowed
authentication connection. The unchanged core guard's process-local cache/role
refresh remains outside storage reads/locks.

Only after authorization can a private, nonpersistent mysqli connection open.
The deliberately narrow candidate profile requires trusted configuration for
`127.0.0.1:<port>`, mysqlnd, 64-bit PHP, utf8mb4, no custom flags/collation, and
MariaDB 10.6. It compares database/server facts with the authentication connection,
requires a distinct physical thread, and establishes/verifies session READ ONLY.
Connect/read timeouts are 3s/5s, LOCAL INFILE is disabled, and the original wall
window plus a real 10s monotonic budget bound acceptance, including after close.
A blocking call can overrun that budget: no hard cancellation/timing proof is
claimed. Scoped warning-to-refusal handling restores the prior PHP handler; global
mysqli reporting is never changed. Positive observation requires acknowledged
close; every exit clears and closes only the owned handle.

The options table must be a base InnoDB table with a full, single-column unique
`option_name` index. Fixed prepared indexed SELECTs read safety A, settings A,
safety B, settings B, at most four reads. Conditional SQL projections impose byte
bounds **before fetching**: safety 131072, settings 1048576, names 64, autoload 20,
row ID 20. Oversized data becomes NULL and refuses; it is not fetched then trimmed.
The existing pure contract checks canonical IDs/lengths, exact names, non-autoload
metadata, distinct row IDs, repeated tuples, duplicate-free JSON, closed schema
6/8 and primitive serialized settings. Settings UUID is read from validated bytes
and compared with the intent; no defaults, PHP unserialization or UUID creation.
Only a hashes-only data contract survives close; the existing semantic context v1
is unchanged and the private binding is never posted/exported.

Trusted local endpoint/configuration and honest same-process/core/server code
are assumptions, not cryptographic primary-server certification. Unknown remote,
TLS/socket/proxy/failover/persistent profiles refuse; this is not a new minimum
version or compatibility claim for the whole plugin.

## Owned native proof and preservation

Confirmed target: `plugin-tester`, mode `local-only`, **Plugin Tester**,
`http://plugin-tester.local`,
`C:\Users\Captain\Local Sites\plugin-tester\app\public`.
Local metadata resolved DB 10004 / web 10005. Sandboxed listener enumeration
incorrectly reported closed ports; an unsandboxed read-only loopback probe and
bundled MySQL preflight confirmed readiness. Novamira profile was present but its
token had expired and discovery failed. No reconnect/credential change was made;
the documented LocalWP fallback was used.

Executed explicit proof command (with MYSQL_PWD set transiently from the confirmed
local secret source and restored in finally):

```powershell
& .\scripts\tests\fixed-core-proof-audit.ps1 -Proof native-observation
& .\scripts\tests\fixed-core-proof-audit.ps1 -Proof guard
```

The existing supervisor still hard-resolves this exact target/current metadata,
requires no custom drop-ins, owns three fresh marker-verified tables, bootstraps
genuine core against only those tables and excludes actual wp-config, plugins,
themes, external HTTP and cron. The new mode uses genuine owned users/roles/session
tokens and nonces; existing protected option API/query traps remain active. The
private source decision is inspected by reflection without injecting callbacks,
authorization providers or a source success path. Schema-6/8 fixture genesis is
explicitly **synthetic in-memory data**, not a genuine authenticated provenance
event. Only its native reading/authentication/session facts are proven here.

Native proof: **122 checks / eight groups**, genuine **WordPress 7.1.2 / PHP 8.5.1 /
MariaDB 10.6.23**, native cache flag remains NULL. Positive exact-byte/context
observations execute all four reads and public inspect is also checked to execute
four reads while remaining unavailable. Other cases cover schema 8, non-autoload
off vs yes, malformed/stale/wrong-domain/mutation-purpose requests, subscriber,
revoked tokens, revocation during the post-read nonce check, ambient/during-auth
transactions, disabled autocommit, same-wpdb handle replacement, closed handles,
SQL pre-fetch caps, duplicate JSON, object serialization, UUID mismatch and
recovered positive state. No browser headers or protected WordPress option API
reads occur. The separate original guard-only mode was rerun: **266 checks / eleven
groups**, not merged into the new native count.

The native whitespace control verifies equal semantic context across fresh reads;
its unequal binding also includes a fresh native lifetime. It is not independent
evidence that safety bytes alone enter the binding. That discrimination belongs
to the unchanged pure observation test and its earlier raw-byte omission mutant.

Each executed proof previews exact owned-table row counts before dropping those
three tables and verifies their absence. All **77 existing application tables**
retain inventory/checksums; independent actual options/users/usermeta digests also
match. Fixture users/session tokens exist only in the owned tables and are removed
with them. Existing application users, options, cron and files remain untouched.
These owned fixture drops are intentional and not needed after proof; regeneration
uses fresh names/markers, not application backup restoration.

## Findings and bounded source-scoped review

Feature scope: native read-only planning observation, authentication idle fences,
owned proof harness. Risk areas: DB / authentication / Other (privacy, resources).
Canonical `FEATURE_LIGHT_REVIEW_PROMPT.md` applied to the eight changed PHP files,
audit wrapper/CI, owning records and directly reused guard/contracts. Findings:

1. Native integration mismatch (fixed): MariaDB direct Boolean-to-CHAR projection
   yields ON/OFF for some flags. Fixed metadata SQL now adds numeric zero before
   casting, keeping strict string 0/1 checks. Positive native control failed before
   this correction with zero protected reads; the same control now passes.
2. Harness reliability (fixed): rapid per-table mysql client calls intermittently
   returned empty checksum output, aborting **before fixture creation**. Complete
   managed-process capture did not alone resolve it. Batched `CHECKSUM TABLE ...
   EXTENDED` now returns one strictly reconciled row for every inventoried table,
   refusing missing/duplicate/unknown/NULL rows. No weakened preservation check or
   automatic retry was added. Subsequent complete proof modes pass.
3. Review hardening (fixed): pin original actor/cookie across both renewed guards,
   bound row-ID projection, check the original budget after pure parsing/close,
   and require acknowledged private close before positive acceptance. No new
   authority or public response success is introduced.
4. Test defect (fixed): the initial static API tripwire matched a comment naming
   mysqli_report. It now excludes PHP comment tokens and still rejects actual
   forbidden calls. Initial targeted run had one test failure; corrected run passes.

Feature files reviewed: **14** (12 uploader including records/CI/audit, two
dashboard records), plus directly reused core guard, request owner/contract,
pure observation/JSON/model/serialized-target dependencies and existing isolated
core fixture fences. Issues found/fixed during the slice: four grouped findings
above; no manual product decision is required within this approved boundary.

Measured explicit base above: eight changed PHP files, zero oversized; runtime
209/98/98 lines, proof supervisor/worker 60/71, new proof cases/fixture 73/48,
API test 45. Helper CODE=1 is not a credible complexity metric and is not used.
Conditional Bloat/Structure Phase 1: no feature-stage bloat/structure issue; no
Phase 2 edits required. Reuse of the existing isolated core harness avoids parallel
bootstrap machinery. No UI/UX workflow: no UI/output/route/registration changed.

Source-scoped DS3 phase-1 inventories (not whole-plugin release readiness):

| Workflow | Inventory/outcome |
| --- | --- |
| 03 Error Handling | All native false/throw/warning/close paths mapped to refusal/finally; proof supervisor handles child failure and previews cleanup. Fixed unavailable is deliberate, no admin UX surface. No further runtime update needed after the findings above. |
| 04 WP Best Practices | No deprecated calls/new routes/hooks/assets/options; exact validation plus genuine core auth reused. Fixed mysqli preparation is intentional because wpdb may reconnect and option APIs auto-unserialize/cache raw data. Fixture CREATE LIKE/exact DROP are not migrations/dbDelta. WPCS executed, no remaining source violations. |
| 07 Edge Cases | Twelve categories considered: empty/missing data refuse; byte/integer/time bounds strict; repeat reads not atomic; no remote service/browser/UI; unsupported multisite/drop-in/cache profiles refuse; prefixed definitions; malformed/old schema refuses; no bulk/cascade/recovery writes. High-impact authentication/session/resource cases map to permanent native controls, pure contract boundaries and four API tests. No additional runtime fix justified within this read-only boundary. |
| 07A Adversarial Test-Suite | Narrow inventory: four API tests, eight native proof groups, reused pure raw/JSON/session tests and original eleven genuine-core guard groups. API tests are isolated, not genuine auth; native positive observations prevent always-unavailable greenwashing. No suite consolidation/percentage target/new mocking layer justified. Mutation evidence and final suite results recorded below. |

Three isolated source counterfactuals were executed against the same owned proof:

| Temporary mutation | Required control that failed | Preservation |
| --- | --- | --- |
| Bypass the pre-read genuine guard | Protected-read ordering/count assertion fails, rather than accepting unavailable after unauthorized reads. | All three fixtures removed; all 77 application checksums unchanged. |
| Return true from auth-session continuity fence | Private observation decision assertion fails on session-continuity controls. | Same complete cleanup/preservation audit. |
| Remove SQL value CASE cap | Oversized fixture reaches the wrong read count, so native pre-fetch protection fails even though later pure validation still refuses. | Same complete cleanup/preservation audit; largest fixture 1048577 bytes. |

Each mutant exits 1 due to its behavioral assertion, not bootstrap/syntax failure.
All three runtime SHA-256 fingerprints match the pre-mutation originals exactly
after restoration. Final restored native **122** and original guard **266** proofs
pass again with complete cleanup/preservation. Mutants were run only after the
full local suite finished, not while its source snapshot was changing.

Final canonical `FEATURE_SECURITY_REVIEW_PROMPT.md` is the last focused source
gate after fixes, validation and mutation restoration. Exact input/cookie handling,
genuine repeated capability/session/nonce guards, distinct read/issue/mutation
domains, pre-auth fences, SQL preparation/identifier allowlists, pre-fetch bounds,
private cleanup and no public handle paths are checked. No outstanding confirmed
security defect in this scoped dormant boundary; no new regression handoff beyond
the permanent API/native controls. No whole-plugin Security/Database Review or
physical transaction proof is claimed. Full pre-release remains required before
a coherent candidate containing this work is packaged/published.

## Validation and next gate

- API boundary: **4 tests / 28 assertions**; combined related selection
  **252 / 2341**, no skips.
- Uploader WPCS: **171 files**; build/runner verification/runner lint pass with no
  generated changes; three Node dev-tool tests pass. Strict Composer manifest,
  Composer audit and npm moderate audit pass with zero advisories/vulnerabilities.
  Existing third-party PHPCS functionWhitelist deprecation remains visible.
- Dashboard: **299 / 1423**, two existing Windows skips; WPCS **138**, structure
  and build pass. Companion runtime is unchanged.
- Full local uploader `php vendor/bin/phpunit --do-not-cache-result`: **1373 tests /
  11638 assertions**, four existing Windows skips, passed in **16:10.796**. The
  slow runner cases finished normally; no test was killed, weakened or excluded.
- Final security/source review after mutation restoration: no outstanding issue
  within the approved dormant boundary. Source-scoped 03/04/07 inventories and
  07A inspection terminate with **No updates needed** after the completed feature
  fixes/controls; historical whole-plugin checklist rows are not refreshed.
- Exact pushed-head CI: pending publication; record and verify it independently
  rather than inferring it from targeted or prior-head results.

Restore-point Option A/check verified clean uploader master at the exact base,
checkpoint eligible, using the existing external archive; no new backup, archive,
checkpoint or restore was performed.

Implementation-plan progress: the fixed bounded native planning-reader and passive
authentication/private-session ownership gap now has genuine isolated evidence.
No paired writer, native transaction/admission/locking resolution barrier,
authenticated schema-8 event, competing-writer/ABA exclusion, uncertain COMMIT,
trigger/FK metadata-lock proof, effective scan stop, production integration,
minimum-WP runtime matrix, package/release/deploy or live operation is proven or
enabled. The reader is not a reusable storage capability.

**Recommended next gated slice: Plugin Tester local-only V2.3 Fixed Native
Closed-Begin Transaction Safety Foundation and Owned Failure Proof**. The existing
raw/native integration design already defines the wider protocol; no additional
maintenance or generic design round is recommended. Approve a dormant fixed
transaction owner with metadata/lock revalidation and a durable closed-begin
safety-row transition against separately confirmed owned disposable fixtures.
Prove definite failure/rollback, session poisoning and no application-session
adoption. Keep paired target writes, actual application state, production wiring,
grants, activation, schedule execution and rollback-feature execution disabled.
This new native SQL-write boundary needs separate explicit fixture/write approval;
the current reader proof is not that approval or evidence for it.
