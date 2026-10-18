# V2.3 Native Closed-Begin Transaction Foundation and Owned Failure Proof

Date: 2026-10-18. Approved R3, Plugin Tester local-only. Runtime owner: uploader.
This is a source-scoped foundation/review record, not release readiness.

## Authority, scope and explicit review boundary

User approval covers the fixed dormant transaction foundation and owned failure
proof, including disposable fixture creation/cleanup and safety-row-only runtime
writes. The previously confirmed target remains `plugin-tester`, `local-only`,
LocalWP **Plugin Tester**, `http://plugin-tester.local`,
`C:/Users/Captain/Local Sites/plugin-tester/app/public` (ID `XPa4sCxmk`).
Fresh metadata resolves MariaDB 10.6.23/TCP 10004, HTTP 10005 and PHP 8.5.1.
Novamira HQ lists the exact profile, but its OAuth token is expired; no credentials
are changed. Direct bundled `mysql.exe` preflight confirms the exact origin,
idle/autocommit state and 77 InnoDB application tables before PHP proof access.

Explicit excluded Git bases, through this milestone's final heads (included):

- Uploader `d24d738e7090fbecb9088288c23fa53abcf77719`.
- Dashboard planning `3eb84ac6e9045d33fc45badc18b8bafddb87a43b`.
- Toolkit evidence `f701fd91ffb6ba9ac63a1addd7536a6028a911a0`.

These bases precede the complete coherent native closed-begin feature, not merely
its final documentation/verification commit. Direct dependencies reviewed include
the unchanged native planning-reader/session trait, genuine-core request guard,
request grammar, observation/JSON contracts, primitive target parser, real model,
legacy projection/snapshot/validators and fixed-core isolated proof supervisor.
The wider design remains dashboard
`docs/V2_3_RAW_OBSERVATION_NATIVE_ADMISSION_DESIGN.md`.

Restore workflow Option A/check verifies a clean uploader `master` at the base,
with the existing external archive and Git checkpoint eligibility. No source
restore, site backup, schema migration or checkpoint branch is created here.

Existing application tables/users/options/cron and production loading/wiring stay
untouched. No settings/paired-target runtime writes, grants, activation,
schedule execution, schedule rollback, receipt resolution, remote actions,
package, release, tag or deployment. SQL rollback of an owned proof transaction
is not schedule rollback execution.

## Implemented boundary

`includes/class-native-closed-begin.php` is final, zero-IO and test-loaded only.
Its only public creation API accepts zero arguments. It has **no public request
execution method**, provider, callback, query port, handle or continuation export.
Existing public request/observer results remain unavailable. Serialization,
cloning and restoration are refused; debug/disposition flags remain false.
Private/reflection visibility is not a sandbox against trusted same-process PHP.

The private begin accepts only the existing typed `begin_disable` form. It consumes
the owner once, pins original wall/monotonic deadlines, authenticates real core
cookie/session/capability and the separate exact purpose nonce before protected
reads, and passively fences the original native authentication session. No
START/SET/COMMIT/ROLLBACK/reconnect/close runs on that borrowed connection.

SQL lifecycle review uses the primary MariaDB references for
[START TRANSACTION and metadata locking](https://mariadb.com/docs/server/reference/sql-statements/transactions/start-transaction),
[explicit COMMIT completion modifiers](https://mariadb.com/docs/server/reference/sql-statements/transactions/commit)
and [trigger metadata](https://mariadb.com/docs/server/reference/system-tables/information-schema/information-schema-tables/information-schema-triggers-table).
These explain the design; executed owned native evidence below establishes only
the named candidate-profile cases, not general deployment support.

The existing private factory opens a new nonpersistent **read-only** connection.
Only that verified idle owned connection is then configured for this writer:
READ WRITE, READ COMMITTED, row wait 2s, metadata wait 3s, connect/read timeouts
3s/5s. Actual policy is verified, including no active role and
`innodb_flush_log_at_trx_commit=1`. The original ten-second acceptance budget and
at-most-300-second intent window never refresh. Blocking calls can overrun;
neither hard cancellation nor power-loss durability is claimed.

The conservative MariaDB 10.6/mysqlnd/direct loopback profile checks the normal
four-column WP options schema, full unique `option_name` and primary indexes,
InnoDB/base-table engine, no generated/extra columns or unexpected constraints,
no triggers and no inbound/outbound foreign keys. Explicit global
SELECT/TRIGGER/REFERENCES visibility and no active role prevent hidden-empty
metadata from being treated as support. This is deliberately restrictive and
does not change plugin compatibility minimums or create privileges. Server/
installation endpoint trust remains an assumption, not cryptographic attestation.

Two bounded indexed observations are strictly parsed and the exact closed
successor is prepared **outside locks**. Legacy validators can call WordPress
helpers/error hooks, so they are never invoked in the native critical section.
One completion UUID and one candidate timestamp are reserved outside locks;
the final pre-lock genuine guard runs after preparation. No caller supplies a
clock, completion ID, table, DSN, validator, proposed bytes or support assertion.

The same owned session starts one transaction, locks **safety first**, revalidates
metadata under the table's transactional metadata lock, then locks settings.
Exact equality with the already validated full tuples (row ID, name, autoload,
length and bytes) is mandatory. Thus moving validation outside locks does not
permit an intervening byte/identity change. Inside locks there are only fixed
native operations, tuple comparisons and fixed clocks: no WordPress/domain
validator calls, hooks, autoloaded providers, UUID generation or arbitrary callbacks.

Only the existing fixed safety row can be updated, with exact row-ID and binary
name/autoload/before-value predicates and exactly one affected row. Both rows
are read back before commit; settings must remain identical. Explicit
`COMMIT AND NO CHAIN NO RELEASE` acknowledgement, pinned same-session idle facts,
post-commit exact readback, renewed genuine authorization and acknowledged close
are required for the private positive Boolean. That Boolean is **not admission**.

One attempt only; no automatic conflict retry, new command or replacement UUID.
Definite pre-commit failure rolls back only if original owned transaction facts
remain provable. Rollback must be acknowledged and verified idle. Unknown BEGIN,
COMMIT or rollback poisons/closes the owner without replay, reconnect,
compensation or automatic abandonment. A post-commit read/guard failure can
withhold the private positive result but cannot undo/reclassify a confirmed
commit. Public observers still do not resolve uncertain commits.

## Genuine proof and non-vacuous controls

Entry: `scripts/tests/fixed-core-proof-audit.ps1 -Proof native-closed-begin`.
The existing supervisor creates exactly three fresh, marker-owned options/users/
usermeta tables, boots genuine WordPress core against those fixtures only and
loads no site plugin/theme/config. Runtime definitions are explicitly loaded.
Existing application state is independently checksummed before/after every run.
Every exact owned cleanup target is counted before DROP and absence is verified.

Final native proof: **169 checks / 12 groups**, genuine WordPress 7.1.2,
PHP 8.5.1 and MariaDB 10.6.23. It establishes:

1. Actual authenticated closed begin commits, is visible from another native
   session, retains a valid closed/pending chain and never changes settings.
2. Stale replay, malformed/expired/wrong nonce domain, subscriber, revoked token
   and ambient authentication transaction deny without protected effects.
3. Corrupt legacy origin exercises genuine `is_wp_error_instance` callbacks
   outside SQL locks and refuses before BEGIN.
4. An independent real row lock causes a genuine lock timeout, confirmed owned
   rollback and no retry or safety UPDATE attempt.
5. Supported owned options metadata is positive; owned users schema and malformed
   identifiers are negative. No fixture privileges/triggers/schema are changed.
6. A genuine pre-lock nonce callback changes only owned safety whitespace;
   locked exact tuple comparison rejects the unchanged semantic context.
7. Both actual private locking reads exclude an independent NOWAIT locking read;
   no competing settings UPDATE is needed.
8. A private read-budget fence placed after actual native UPDATE forces a
   confirmed pre-commit rollback; original rows remain durable.
9. Wrong row ID, uppercase autoload and trailing-space before-value CAS each
   affect zero rows and roll back; case/trailing-space collation is not equality.
10. Missing-ack **protocol simulations** on real committed and uncommitted
    sessions produce the same poisoned disposition and cannot replay.
11. Acknowledged termination of the exact exclusively owned session is genuinely
    observed unusable; unknown BEGIN poisons without reconnect or session adoption.
12. Genuine post-commit session revocation withholds private success while the
    closed begin remains committed and target bytes remain unchanged.

The unchanged `native-observation` mode passes **122 / eight groups**, and
`guard` passes **266 / eleven groups**. All final runs remove their three owned
tables, preserve all **77 application-table checksums**, and emit no browser
headers/protected option-API reads. Synthetic schema-6/8 genesis is not promoted
to authenticated provenance; the guarded begin event is the boundary exercised.

### Counterfactual and red-to-green evidence

Bounded temporary source mutations are restored immediately in finally, never
committed. Required controls discriminate removed pre-read authorization,
settings lock, metadata refusal, exact CAS predicate, binary collation, rollback,
unknown-commit poisoning and a WordPress hook inserted under native locks.
All **eight final-source mutations** fail behavioral assertions and are restored.
Their discriminators are protected-read ordering, settings NOWAIT exclusion,
unsupported schema refusal, wrong-value CAS, case-sensitive autoload CAS,
post-update durable preservation, commit-sent poisoning and private positive
decision refusal respectively. Each actual worker failure is independently audited for
application preservation and exact fixture cleanup; a failed preflight is not
counted as mutation evidence. Final source fingerprints and restored positive
runs are checked before publication.

The transitive validator defect has separate genuine RED/GREEN evidence: the
new corrupt-origin/WordPress-error-hook regression fails the old critical-section
placement with protected-read ordering mismatch, then passes after preparation
moves before BEGIN/final authorization. No legacy validator is weakened and no
artificial success path is added. The full local suite started before this
finding was deliberately interrupted; only the later complete rerun counts.

Harness corrections are distinct from runtime defects: the deterministic abort
counter initially landed after commit and was corrected to force pre-commit
failure; mysqli NOWAIT/error probes now check both false/errno and exception
modes without changing global reporting policy; callback lock probes and owned
connection termination acknowledgements are checked, not assumed.
One independent inventory preflight returned insufficient rows and refused before
its worker; separate read-only inspection confirmed 77 tables/no fixtures before
the bounded repeat. It is not described as a successful proof.

## Canonical source-scoped reviews

All prompts below are the actual toolkit canonical files, read and applied in
their governing order. Scope is only this feature plus named direct dependencies;
historical whole-plugin readiness rows are not refreshed.

| Workflow | Phase/outcome and evidence |
| --- | --- |
| DS2 Feature Light | DB/auth/concurrency scope; nine changed PHP files plus proof/CI configuration and direct callers. No UI/REST/cron/client/Drime changes. Deeper focused DS3 justified for transaction/error/test-layer risk. |
| DS2 Feature Bloat and Structure | Explicit uploader base above; helper measures nine PHP files, none >300 total lines. Cohesive owner/session/storage and proof duties retained; no speculative split/removal. Consolidate/retain classification, no Phase 2 structure edits required. Helper CODE=1 output is not used as a credible code-line measure; TOTAL is the mandatory threshold. |
| DS3 03 Error Handling | Read-only inventory then bounded fixes/revalidation: explicit ownership before rollback, acknowledged close before positive result, unknown commit poisoning and no retry. No outward error/log/private-data surface. Final source-scoped No updates needed. |
| DS3 04 WP Best Practices | Genuine-core authorization/nonce domains retained. Prepared native parameters and fixed validated identifiers are deliberate transaction-ownership exceptions to option APIs/wpdb reconnect. Full configured WPCS run; no minimum-version/public schema/registration changes. No UI tasks apply. |
| DS3 06 Performance | No traffic-size loop/N+1 or new cached authority; at most eight bounded protected reads, fixed metadata passes and one UPDATE attempt. Existing JSON/primitive caps/traversal limits unchanged. Do not cache authorization/schema metadata or optimize away locked revalidation. No updates needed after resource inventory. |
| DS3 07 Edge Cases | Empty/corrupt/mixed rows, boundaries, replay, concurrency, native timeouts, original clock/scope/actor, ambient transactions, unknown IO, post-commit revocation and unsupported environments mapped. High transitive WordPress-under-lock scenario fixed by pre-lock preparation plus locked exact equality; regression handed to 07A. UI/file/HTTP/deletion-cascade categories N/A. |
| DS3 07A Adversarial Test Suite | API tests explicitly isolated, SQL/auth proof genuine, protocol ack loss explicitly simulated. Strong positive commit and negatives, real locks, rollback, zero-row CAS, post-commit revocation and source mutations protect distinct risks. Added permanent corrupt-origin error-hook regression; no tests removed/skipped/weakened. Full-suite/exact-head receipts below own completion status. |
| DS2 Feature Security, final | Exact grammar, purpose-bound genuine auth, byte/identity checks, private handles, failure classification, no callbacks under locks and fixture targeting reviewed after fixes. No remaining confirmed in-scope security defect; no full-plugin security/Database Review/production readiness claim. |

Source metrics and final lint/syntax/regression outcomes are verified again after
the source-scoped fixes. UI/UX is not run for this backend-only scope. The full
pre-release suite remains required for a later coherent release candidate before
packaging/publication; this checkpoint is not a substitute.

Final helper TOTAL measurements: owner **239**, session trait **114**, storage
trait **189**, supervisor **60**, worker **75**, proof cases **85**, proof failures
**128**, proof fixtures **45**, API test **49**; no changed PHP file exceeds 300.
Fixed repeated session checks that became adjacent after moving preparation were
consolidated without removing a check around intervening work. No runtime split
or deletion is justified; existing read-only observer/session files are unchanged.

Restored final runtime SHA-256 fingerprints:

- owner `3B74A0B3441006C7AA3CE6C09C74F6C208E35218CC36700251DB4EBE1FC23D6E`;
- session `7B2D0DA3019AF5C6EAF38A4DC320851B7FA36E1C83CDD96105AA3526B4A95D17`;
- storage `6D2E8FD531C5FE023FB3AED66F39093520095FAA0B3B84BC5E26155A828E41A9`.

## Verification and publication receipts

Implementation pushed on uploader `master` as
`1d0bae360fef5db9a73934704ae4dd9a025f3a66`; exact-head
[Quality 39139673150](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/39139673150)
succeeds: PHP 7.4 and 8.3 each **1377 tests / 11741 assertions**, no skips,
plus full WPCS/proof syntax and Node build/runner/audit. Dashboard planning
`4a2319d5023a43045c936b19bb34ebbbf1b0d411` passes exact-head
[CI 39139685042](https://github.com/NichlasB/alynt-drime-backups-dashboard/actions/runs/39139685042),
both PHP jobs **299 / 1430**, no skips, plus manifest/audits/build/structure/lint.
These results do not substitute for final receipt-only heads: those are checked
independently at handoff, without an endless self-referential receipt cycle.
No whole-plugin release readiness is inferred from the source-scoped checklist.

Final independent Windows full suite (`php vendor/bin/phpunit
--do-not-cache-result`) finishes successfully: **1377 tests / 11687 assertions**,
four pre-existing unrelated Windows skips, **14:39.154**, 88 MB. This is not
no-skip platform coverage; the CI jobs above each have no skips. The interrupted
earlier run is not counted as completed verification. No assertions/skips were
weakened or added to pass this milestone.

The final dashboard receipt head `aeda4ae610fba6ecc1db5bfa3d7ba4d6c23c5455`
independently passes
[CI 39140126874](https://github.com/NichlasB/alynt-drime-backups-dashboard/actions/runs/39140126874).
Toolkit source-scoped checklist evidence is published separately on `main`;
its API reports zero configured workflows, not a passing CI result.

Targeted regression: `php vendor/bin/phpunit --do-not-cache-result --filter
'NativeClosedBeginBoundary|NativeObserverBoundary|RawObservationContract|ClosedWriter(JsonContract|SessionContract|ContractBoundary)|FixedCoreRequest|RealClosedWriter|SerializedSettings'`
passes **256 tests / 2390 assertions**, no skips. Four new API methods add 49
assertions; they prove API/structure/zero-IO boundaries, not native storage.
Final WPCS checks **174 PHP files**, zero code errors/warnings; all changed
native proof and fixed-core supervisor PHP syntax checks pass.
Dashboard local tests pass **299 / 1423**, with two existing unrelated Windows
skips; structure/build pass. Uploader build/runner verification/syntax, three Node
dev-tool tests, strict Composer validation and zero-advisory npm/Composer audits
pass. An initial sandboxed build was denied access outside writable roots;
the authorized elevated rerun passed with no generated differences. PHPCS emits
its existing dependency-configuration deprecation banner, not an in-scope warning.

## Proof limits and next gate

No power-loss/server crash/failover/TLS/proxy/socket profile proof. Real connection
termination is not lost COMMIT acknowledgement proof; the two outcome latch
controls are explicitly simulations. No deadlock, concurrent administrator DDL,
hidden metadata credential profile, all-platform native support, authenticated
upgrade installation, paired target/state transaction, cache convergence,
in-flight resolution barrier, scan stop, old binary/restore/downgrade admission,
production integration or grant is certified. These are real future gates.

This dormant slice does not publish cache invalidations or an application-facing
event. Raw SQL evidence must not be presented as WordPress cache/effective-state
convergence. Revocation during the critical section is not atomically excluded.
An ordinary post-commit read is not uncertain receipt resolution.
One-shot poisoning prevents reuse of that owner; it is not a cross-request
unresolved-command registry or proof that a future caller cannot deliberately
construct another owner. Such callers remain unwired and require the next
continuation/resolution design/proof boundary before production use.

Implementation-plan progress: the open V2.3 native closed-begin/session transaction
gap advances with genuine safety-row storage proof, not maintenance churn.

Recommended next gated slice: **Plugin Tester local-only V2.3 Fixed Native Paired
Disable Completion and Locked Receipt Resolution Foundation/Owned Atomicity
Proof**, including a private one-use continuation from confirmed closed begin and
separate explicit approval for disposable settings **and** safety-row writes.
Keep application state, production wiring, grants/activation and schedule rollback
execution disabled. Do not silently broaden the present safety-row-only approval.
