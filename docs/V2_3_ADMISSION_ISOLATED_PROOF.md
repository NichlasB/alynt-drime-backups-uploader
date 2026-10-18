# V2.3 Closed-Writer Admission Isolated Storage and Authenticated Request Proof

## Approved plan and fixed boundary

Approved 2026-10-18: Plugin Tester local-only, including native LocalWP SQL at
`127.0.0.1:10004`, database `local`, read-only preservation audits and creation,
testing and audited removal of owned disposable users/storage. Exact target:
`C:/Users/Captain/Local Sites/plugin-tester/app/public`,
`http://plugin-tester.local`. Clean uploader baseline
`785f155163c360c7c48c22dc15b0bc84a70e5a6e` is the source recovery point.

Build a developer-only fixed harness coupling genuine WordPress core sessions,
capabilities and nonces to the existing schema-7 fixture admission boundary and
one-shot continuation on native, non-reconnecting owned InnoDB connections. Use
empty structure copies of options/users/usermeta plus an owned IPC table; never
copy actual rows. Authenticate outside locks, lock safety before the fixed
synthetic JSON target, and commit target/completion together. Exercise denial,
revocation, failure/acknowledgement and independent-process fencing cases. Keep
the query fence installed through shutdown, preview/remove only this parent's
tables, independently audit absence and compare actual users/options/cron before
and after. Validate, review, commit, push and verify exact-head CI.

This does not widen existing validators or relabel fixture provenance. Genuine
authentication of a developer request does not convert its fixture receipt into
reserved authenticated/internal permission. The target is synthetic JSON, not a
PHP-serialized production settings provider. No actual users/options/cron,
production wiring, grants, activation, rollback, version, package or deployment
changes are authorized. Native cache bypass is not a production cache policy;
final-auth-to-lock role/session races remain explicit. Do not combine historical
proof receipts as a substitute for running this integrated harness.

## Implemented proof boundary

Nine explicit `scripts/tests/admission-*.php` files provide the fixed CLI target,
isolated storage, fixture seeding, core-auth/native ports, paired transaction,
request/failure cases and independent workers. Existing auth/core helpers and the
state-only CAS are reused as definitions, not as historical proof receipts. Core
loads only after all three wpdb table pointers are isolated. The fourth table is
bounded IPC; children authenticate independently and never receive raw cookies,
nonces, session tokens or passwords through argv/IPC. No handler or production
include loads these files. CI adds syntax checks only, never LocalWP SQL.

The proof uses genuine `wp_validate_auth_cookie`, session tokens, capability and
nonce functions, without replacing core security callbacks. Fresh session/role
checks surround private observations, closed comparisons and transaction entry.
The native fixed owner rejects nested transactions, uses no reconnect/restart,
and locks safety before target. Compare and terminal writes have exact byte
predicates; raw reads bypass process-local option cache. The terminal pair is
confirmed on the same connection with the original deadline and prepared clock,
then committed atomically. No request-authorization callback runs under target
locks; IPC pauses are explicit developer fault instrumentation only.

The reused boundary still returns `simulated_committed` and
`physical_target_effects_proven=false`: this proof separately verifies physical
effects on the **synthetic owned fixture**, not production settings or real-domain
permission. Existing runtime definitions/validators/bootstrap stay unchanged.

## Executed evidence

Final new harness run passed **18 grouped checks** on WordPress 7.1.2, LocalWP PHP
8.5.1 and MariaDB 10.6.23, covering:

- actual-table write fencing before SQL and default-off zero native IO;
- guest/subscriber/GET/wrong nonce/purpose/site denials before private reads;
- genuine-admin closed upgrade, frozen legacy projection, fixed-target plus
  terminal atomicity and invalidated continuations;
- no request auth callback under locks; duplicates/no-op/fresh receipt reads
  without replay; genuinely signed changed retained-identity conflicts;
- session revocation despite a still-valid nonce, role loss, post-observation
  denial, post-begin pending retention and post-commit response redaction;
- false/throwing begin acknowledgements without target replay, and native SQL
  faults before or after target staging that roll back both paired writes;
- missing/corrupt/autoloaded/reserved-domain rejection without repair,
  nested-transaction rejection and planted process-cache bypass;
- an independently authenticated original writer fenced after fresh abandon and
  a successor begin; actual safety/target lock timeouts and original expiry
  after a real lock wait;
- owned-process termination before COMMIT with rollback, and after COMMIT with
  fresh authenticated receipt/target reconciliation and no replay.

The first development run reached all initial request/failure checks but rejected
a later upgrade success setup without an internal acknowledgement. It failed
closed and removed all four owned tables; independent actual-table hashes matched.
The conservative prepared-clock equality can reject a real-second transition;
the first redacted result alone does not establish that as its definitive cause.
Deterministic success setups now start early in a real second (bounded wait, no
frozen/rewound clock, intent refresh or uncertain-command retry). The final
complete run passed after
the signed-collision/auth-under-lock/redaction refinements. The existing nonfatal
LocalWP Imagick startup warning remains; no PHP configuration was changed.

Final cleanup previewed **19 rows**: 6 options, 3 fixture users, 9 usermeta and 1
IPC row. All four exact parent-created tables were removed and their absence
verified. Independent bundled-MySQL audits after each run matched the original
545 actual options, 2 users and 71 usermeta rows and every aggregate hash,
including cron/activation/session data. No actual safety option or probe table
remains; no browser cookie/header was emitted. These were disposable synthetic
fixtures, not user data; no recovery is needed.

## Limits and next meaningful gate

This is an integrated actual-core/native-SQL proof of the **fixture lane**, not a
real authenticated/internal receipt constructor, installed HTTP handler, real
PHP-serialized settings decoder, production cache publication policy, or a
same-privilege PHP/SQL sandbox. Only the fixed native proof statements were
exercised. Supported production engine/drop-in/cache/reconnect behavior, complete
writer participation and lifecycle/downgrade/restore handling remain unproven.
Killed-worker tests are not server-power-loss or dropped-COMMIT-packet tests.
Final authorization cannot atomically exclude a later role/session change across
row-lock acquisition. Capacity/counter/size edges remain covered by the existing
105 in-memory admission tests, not newly claimed SQL maximal-state runs.

Implementation-plan progress: the integrated core-auth plus owned durable fixture
admission milestone is proven; the lane now reaches a new real-domain construction
decision. **Recommended next gated slice: local-only V2.3 Trusted Real-Domain
Closed-Writer Construction and Provenance Integration Design.** Define the fixed
non-injectable construction/receipt boundary, rejection of fixture-lane successors,
real serialized target/cache policy and proof requirements before implementing any
real domain. Keep authenticated grants, production wiring, activation and rollback
disabled. No cleanup-only release or deployment is warranted.

## Repository validation and focused review

Focused uploader selection `ClosedWriterAdmission|ClosedWriterBarrier|
ClosedConsentProvenance|ConsentRequest` passed **331 tests / 4270 assertions**, no
skips, in 1:40.288. Full PHPCS passed 155 files (existing nonfatal
PHPCompatibility `functionWhitelist` deprecation only). All nine new proof files
passed syntax; wrong-root and extra-argument invocations refused before bootstrap.
Build, deterministic runner verification, runner syntax, three developer-tool
tests, Composer strict validation and Composer/npm audits passed with no reported
dependency vulnerabilities. The uploader has no local `composer.phar`; the
existing adjacent dashboard Composer runtime was used, without dependency changes.

Dashboard full PHPUnit passed **299 / 1423**, two existing Windows skips; PHPCS
138 files, structure, build, strict Composer validation and both dependency audits
passed. Focused source/diff review covered authentication/nonce ordering,
provenance segregation, native transaction/lock/acknowledgement semantics, privacy,
owned-resource shutdown/cleanup and unsupported environments. No older validator,
runtime/bootstrap/provider, unit test/assertion/skip, dependency, generated asset,
version or package changed. Existing packaging excludes `scripts/` and `docs/`;
no release/package workflow was invoked. The only CI change adds syntax coverage.

Full uploader regression passed **1121 tests / 9297 assertions**, four existing
Windows skips, in **12:34.169**. This includes the complete unchanged 105-test
admission foundation; the separate native/core harness is not counted as PHPUnit
tests. Final staged source/ownership/whitespace review passed. Exact-head CI and
publication results follow only after their successful execution.

## Publication receipt

Proof `7578a4d8b0360105658967e15aa2a2e5c016a233` is committed/pushed to
`origin/master`. Exact-commit [Quality 39119232692](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/39119232692)
passed PHP 7.4/8.3, each **1121 tests / 9351 assertions**, no skips, plus full
PHPCS, runner/developer-proof syntax and Node build/developer-tool/deterministic
asset/audit gates. The logs confirm syntax coverage of all nine new proof files
on both PHP versions; actual-core/SQL execution remains the separately approved
local proof, not a CI action.

Dashboard plan `907a409b7358a13e54bd8dbec5ee006ac864add9` passed exact-commit
[CI 39119242469](https://github.com/NichlasB/alynt-drime-backups-dashboard/actions/runs/39119242469),
PHP 7.4/8.3 each **299 / 1430**, no skips, with all other quality/build/audit jobs
green. Both repositories were clean/synced after publication. This completes the
approved integrated fixture admission/storage proof and its publication gates;
the exact next decision remains the local-only trusted real-domain construction
and provenance integration design above. Actual site state, grants, wiring,
activation, rollback and versions stay unchanged; no release/deploy occurred.
This receipt-only follow-up requires final exact-head CI before handoff; no
recursive receipt commit or additional site proof is needed.
