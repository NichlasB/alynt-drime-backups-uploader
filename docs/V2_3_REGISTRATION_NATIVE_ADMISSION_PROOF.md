# V2.3 Closed Registration Native Admission and Owned Ledger Commit Proof

## Successor lifecycle design — 2026-10-19

The approved **repository-only design**, owned by dashboard
`docs/V2_3_REGISTRATION_ADMISSION_OBSERVATION_LIFECYCLE_DESIGN.md`, covers exact
single-owner original provenance retained before DML, independent read issuance,
uncertain outcomes, one-shot/loss/expiry and proof requirements. Excluded coherent
baselines: uploader `ca20a8b639d18e966ec17eaecda99b672f014e05`, dashboard
`f436bd83dc9d817331dd41f321549d04a984389f`. This checkpoint changes docs only:
no WordPress/SQL/fixtures/source/tests/wiring/versions, grants, activation or
rollback. The historical native proof below is not an actual uncertain-admission
to-observation integration proof. Admission false does not establish no write;
observation's separately constructed synthetic origin cannot close that gap.

Recommended next gated slice: **local-only V2.3 Admission-Origin Lifecycle DATA
Safety Foundation**, bounded in-memory one-shot state/retention/loss contracts and
non-vacuous controls, no auth/storage/native execution. Fresh approval required.

## Historical owned native admission proof

2026-10-19; approved R3 **Plugin Tester local-only**, exact disposable fixture
creation/testing/cleanup confirmed. Native admission writes confined to the owned
ledger fixture. Excluded review bases: uploader
`1470a4024de1bce257f9610c313b708861dbea17`, dashboard
`f571596bcd4b363008c82dd3a7b358d5a7bd5326`, toolkit
`1359cf8b6e6637ffaa3ad7e942954b8788fe4c3b`. Scope is the complete **16-file
executable/config delta (14 PHP)** plus direct dependencies, not a later docs tail.
Versions remain uploader 0.5.25/dashboard 0.1.77; not a release candidate.

## Target, authority and implementation

- Confirmed `plugin-tester`, `local-only`, Plugin Tester,
  `http://plugin-tester.local`,
  `C:/Users/Captain/Local Sites/plugin-tester/app/public`. Local metadata resolved
  database/web ports 10004/10005; initial bundled mysql read verified exact origin,
  idle session, MariaDB 10.6.23, 77 application tables and zero proof leftovers
  before full-core bootstrap. Novamira guide/complete inventory/doctor resolved the
  exact profile but its OAuth token was expired; prescribed LocalWP fallback used,
  no reconnection or credential changes.
- `class-registration-native-admission.php`: dormant final zero-IO factory,
  private `admit` only test-invoked by reflection, separate once-attempted outer
  lifetime, no posted frame/proposal/plan/provider/actor/clock or handle port.
  Original core/physical auth session, actor/cookie/method/installation and real
  monotonic/UTC observations stay pinned through all callback phases.
- Initial genuine issuance guard precedes protected capture. Role-only
  `pre_wp_load_alloptions` short-circuit plus independent exact-role-query fence
  spans every callback phase. Only installed callbacks/error handler are restored.
  Two equal ten-row sweeps use a separate server-read-only connection; it closes
  before unchanged genuine constructor/UUID/nonce work. Fresh owned writer is
  neither capture nor borrowed authentication connection. Independent issuance
  and command-domain guards are renewed before BEGIN and after owned close.
- `trait-registration-admission-candidate.php`: moves existing pure preparation,
  full eligibility, retained-identity collision and unaliased-graph checks into
  private sharing. Existing immutable plan facade and handoff tests retain their
  API/behavior; no byte getter or importable native capability added.
- `trait-native-options-metadata.php`: unchanged metadata method extracted from
  existing closed-BEGIN storage. New owner imports metadata only, not the old
  safety/settings writers. Existing owners' row/write policies remain unchanged.
- New admission storage/transaction traits: fixed twenty capture + ten locked
  comparison + ten locked readback projections, one ledger CAS, no retry. All ten
  exact tuples must equal the privately prepared frame; metadata is checked again
  after safety-row MDL. Only the ledger value/length changes, with exact ID and
  binary name/autoload/old-value predicates and affected_rows=1. No parser, core
  auth/nonce/UUID, producer or extension callback runs under owned row locks.
- BEGIN/COMMIT intent latched before IO; acknowledged owned session required.
  Definite precommit failure rolls back only owned resources; ambiguous state
  poisons/consumes without reconnect/replay/compensation. Post-close auth refusal
  can hide an already committed historical receipt; it cannot erase that commit.
- No production bootstrap/caller, public endpoint, application-state write,
  grant, activation, snapshot/queue/effect execution, rollback, version/dependency
  update, package/release/tag/deploy. Public support flags stay false. Private
  visibility does not protect against hostile privileged PHP/reflection or SQL.

## Genuine proof and discriminating controls

Explicit `registration-native-admission` mode extends the existing fixed-target
supervisor/worker/audit allowlists; four `registration-admission-proof-*.php`
helpers contain permanent assertions. CI syntax-checks them but never runs SQL.
Fresh process bootstraps **actual WordPress 7.1.2 / PHP 8.5.1**, isolated prefix,
owned users/roles/sessions and options. No site plugins/themes or external HTTP;
only three fresh marker-verified `wp_alynt_core_probe_<12hex>_` tables. Fixture
setup/adversarial mutations are distinct from the coordinator's ledger-only delta.

Final normal proof: **2,766 checks / 14 case groups**, no worker stderr. Covers:
genuine positive 40/1 acknowledged commit with exact schema-2 registered receipt
and nine unchanged tuples; actual nonce/UUID origins and independent NOWAIT checks
on every core auth callback; bad issuance zero reads, bad command zero BEGIN;
all ten post-capture stale roles; last-row contention/definite rollback; malformed
forms/purposes, subscriber and warm-cache revocations; independent autoload/query
fences; auth physical swap/ambient transaction rejection; request/clock/budget/
scope/reentry losses; pre-BEGIN versus postcommit session revocation; all-ten lock
exclusion/exact CAS; readback refusal after real UPDATE; poisoned unknown protocol
outcomes and killed strictly owned writer; metadata/caps/malformed/active-grant/
autoload/missing-row refusals, supported nonautoload positives, final reconciliation.

Eight distinct temporary source counterfactuals were assertion-killed, not merely
syntax/mock-count failures, then exactly restored; final normal proof rerun green:

| Broken behavior | Semantic failure |
|---|---|
| Always-unavailable final decision | Expected positive true; actual false after real 40/1 commit |
| Initial issuance guard removed | Expected 0/0 reads/writes; actual 20/0 |
| Full-command pre-BEGIN guard removed | Expected 20/0; actual 40/1 |
| Full locked tuple equality removed | Expected stale-role 21/0; actual 31/1 |
| Binary autoload CAS removed | Actual collation accepted conflicting autoload |
| Binary old-value CAS removed | Actual collation accepted conflicting value |
| Original elapsed budget removed | Expected refusal; actual positive 40/1 |
| Both stack-local option fences omitted | Unauthorized bulk-autoload fetch detected |

Every normal/control/regression run independently verified **77 unchanged
application-table checksums**, exact actual options/users/usermeta digests, exact
row-count previews for the three owned tables, and their removal/absence. This
includes failed controls; no fixture leftovers or application cleanup required.

## Canonical source-scoped review checkpoint

Actual toolkit runner and canonical prompts followed, not whole-plugin reruns:

1. **Feature Light:** scope DB/auth/concurrency/test file operations; 16 changed
   executable/config files and direct dependencies. Mechanical WPCS alignment/
   spacing issues fixed; no outstanding manual decision or production defect.
   Escalated native integrity/edge/testing to 05/07/07A and sharing to Bloat.
2. **Feature Bloat and Structure, phases 1/2:** explicit-base helper reports
   **14 PHP / zero oversized**, total-line counts 51/246/86/119/145/80/91/60/81/
   61/83/88/51/29 in helper file order. All classified keep/consolidate; no
   remove/split/deferred oversized item. Candidate and metadata sharing avoid
   public capabilities/duplicated validators. Existing reader remains unchanged;
   its short fixed fence policy is deliberately retained locally across the new
   owner's full lifetime rather than broadening an unrelated refactor. Helper's
   CODE=1 on every file is an unreliable metric; TOTAL counts used, no claimed
   code-line reduction. Final helper rerun unchanged; no further updates needed.
3. **05 Database, phases 1/2:** source-scoped inventory below; no additional
   schema/index/security/integrity/cleanup changes needed after review. This
   conclusion includes genuine physical owned-fixture evidence, not in-memory
   tests standing in for Database Review.
4. **07 Edge Cases, phases 1/2:** all twelve canonical categories inventoried:
   empty/missing, bounds, concurrency, IO, WP profiles, plugin callbacks,
   corruption, inputs, permissions, deletion, recovery, browser N/A. Closed
   refusal/no repair and explicit unknown/postcommit semantics retained. Test
   gaps routed to 07A; no speculative production changes needed.
5. **07A Adversarial Test-Suite, phases 1/2:** baseline focused 20/539 and initial
   genuine 639/4 distinguished from historical reader/checkpoint tests. Gaps in
   real guard ordering/fences/profile, CAS/readback/ack protocols, resource/scope
   limits and shared metadata regression addressed by permanent 2766/14 proof,
   eight controls and rerun predecessors. Pure plan/handoff tests prove data only;
   three new boundary tests (69 assertions) prove dormant API/privacy/zero IO,
   not genuine positive admission. No deleted/weakened tests, artificial success,
   coverage-percentage claim or CI-based native proof. Source-scoped final status
   Clean subject to exact-head full CI at handoff; runtime proof executed locally.
6. **Final Feature Security:** after fixes/controls/validation, strict bounded
   exact validation (not lossy sanitization of signed context), genuine guards,
   separate nonce purposes, no protected reads before authorization, fixed native
   bound values/validated identifiers, private consumed handles, no public wiring
   or output/debug/secret surface checked. No confirmed security defect or missing
   permanent regression handoff. Native prepared statements intentionally replace
   wpdb writes to preserve exclusive physical ownership; never generic SQL input.

UI/UX/output escaping/i18n N/A: no UI or public output changed. No formal 03/04/06/
13 or historical whole-plugin readiness refresh claimed. Canonical full pre-release
suite remains mandatory for the next coherent RC before packaging/publishing.

### Source-scoped database inventory / terminal decisions

New application tables/meta/transients/migrations/indexes: **zero**. Existing ten
fixed options from `Scan_Effect_Manifest::rows()` are all required, nonautoload,
with no creation/default/repair. Ledger is schema-2 JSON; other nine retain their
existing codecs. Core auth uses owned-prefix roles/users/usermeta only; no new
application user-meta key. Relationship: prepared full dependency/actor/scope/
generation/identities -> one historical ledger record, no new database FK.

Required options schema: InnoDB, utf8mb4 profile; option_id bigint(20) unsigned
NOT NULL auto_increment PRIMARY; option_name varchar(191) NOT NULL full unique
BTREE; option_value longtext NOT NULL; autoload varchar(20) NOT NULL, optional
standard autoload index. Exact metadata/no trigger or inbound/outbound FK and
visible global SELECT/TRIGGER/REFERENCES required; unsupported refuses.

Query inventory by source/method, all dormant on-demand only, no cron/page load:

| Location | Complete fixed query family / predicates / bounds |
|---|---|
| admission storage `admission_row` | Prepared SELECT conditional prefetch caps for ID/name/autoload/value + octet wire string; fixed validated table, bound manifest option_name, FORCE INDEX(option_name), LIMIT 2, optional FOR UPDATE. No ORDER/JOIN; at most 40. |
| admission storage `admission_write` | Prepared UPDATE SET option_value=? WHERE option_id=? AND BINARY option_name=? AND BINARY autoload=? AND BINARY option_value=? LIMIT 1; one attempt, no insert/delete. |
| shared options metadata `metadata` | Fixed USER_PRIVILEGES COUNT; bound TABLES/COLUMNS/TRIGGERS/TABLE_CONSTRAINTS/KEY_COLUMN_USAGE metadata, ordered/limited columns and constraints; validated-table SHOW INDEX. No payload or metadata write. |
| admission owner `admit` | Bound exact core role-option SELECT LIMIT 1 is the sole permitted wpdb options query during core auth; other options queries fenced before SQL. |
| admission transaction | Fixed START TRANSACTION READ WRITE, COMMIT AND NO CHAIN NO RELEASE; existing session helper verifies owned physical/session state and rollback-or-poison release. No caller-selected statement. |
| proof supervisor/storage/helpers | Exactly three fresh CREATE TABLE LIKE core fixtures, marker/user/session/option setup and adversarial fixture changes; exact marker/count cleanup preview then exact DROP; actual application tables read-only checksum/digest audit. Not installed schema or migrations. |

Unique option_name/primary indexes match access patterns; no joins, sort workload,
new index or migration justified. Lifecycle is prepare -> all-ten locked compare
-> one ledger successor -> readback -> acknowledged commit; refusals never provision
or prune. Existing bounded ledger capacity/headroom retained; no active retention,
uninstall or scheduled cleanup added. Fixture cleanup belongs solely to supervisor,
not application uninstall. Phase 2 terminal outcome: **No updates needed** beyond
approved implementation; no application schema/index/cleanup operations executed.

## Validation, fixes and proof limits

- Final genuine admission **2766/14**, existing native ledger **740/13**, native
  reader **700/11**, core guard **266/11**, native closed-BEGIN **169/12**: all green,
  unchanged 77-table audits and all fixture cleanup verified independently.
- Local targeted PHPUnit **265 tests / 3369 assertions**, no skips; focused new/
  related baseline **20/539**, three new boundary tests **3/69**. Full uploader
  suite required on exact-head Linux PHP 7.4/8.3 CI; not falsely claimed run locally.
- Uploader WPCS **208 PHP files**, build/runner verification/syntax, dev-tools
  **3 tests**, PHP syntax for all changed proof helpers/worker/supervisor and
  PowerShell AST passed. Existing PHPCompatibility comma-array deprecation only.
- Uploader/dash Composer strict validate/audit and npm moderate audits passed,
  zero reported advisories/vulnerabilities. Global Composer absent; available
  dashboard composer.phar used successfully, no dependencies changed.
- Dashboard full local **299/1423**, two existing local Sodium signing skips;
  WPCS **138**, structure/build passed. Full CI must exercise both signing cases.
  Generated runner/assets unchanged. Final diff/whitespace/source-loading review
  passed; exact remote synchronization and CI recorded in final handoff.
- Test-only fixes: prepared COUNT native integer/string mismatch corrected with
  CAST AS CHAR, not a weakened exact-one assertion. Temporary control restore
  initially matched a wrong return; next positive detected it, exact source
  restored, all eight controls rerun with unique context/whole-source equality,
  final normal proof green. Every failed run still audited unchanged state/cleanup.
- Source Option A/check protection verified clean/synced excluded uploader base
  and existing external September 20 archive; no new site backup/archive/restore.

Physical isolated CAS/locks/acknowledged rollback and actual retained receipt are
proved. Protocol acknowledgement omission tests deliberately manipulate private
phase over two actual SQL outcomes; they are **not real lost-wire/crash/power-loss
proof**. Readback-failure primitive deliberately initializes the read cursor to
force real UPDATE then refusal; it is not thirty real captures. Those isolated
primitive cases are not presented as full genuine admission-path tests.

Ten-second limit is acceptance, not hard IO cancellation (row wait 2s, MDL 3s,
native connect/read timeouts retained). Forty bounds protected projections, not
all auth/metadata/session SQL. Frame cap 6291456 and successor cap 131072 plus
existing reserves/parser limits are bounds, not measured peak memory. No separate
deep parser/performance rewrite warranted. Options locks do not serialize core
auth revocation; historical COMMIT is not auth-valid-at-COMMIT. Exact bytes do not
prove no ABA/restore/privileged tamper. No production participant/cache convergence,
public receipt observation, absence=>retry/quiescence or in-flight closure proved.

## Implementation-plan progress and next gate

Genuine trusted same-stack construction -> all-ten locked revalidation -> exact
owned schema-2 registration commit is now executable and physically tested, not
maintenance-only work. Safe-local scope ends before a new observation/closure
trust contract; no activation/effect milestone is implied by this proof.

Recommended next gated slice: **local-only V2.3 Guarded Registration Receipt
Observation and In-Flight Closure Design**, repository/design-only. Define exact
trusted read-purpose construction, all-ten observation barrier and historical
presence versus not-observed semantics, including delayed in-flight work and lost
lookup context. Do not enable reserve/effects or infer safe retry from absence.
Keep production wiring, application storage, grants/activation/execution/rollback
disabled. Approve that bounded design before further implementation.
