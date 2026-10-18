# V2.3 Closed-Writer Admission Contract and Provenance Safety Foundation

Follow-up 2026-10-18: the separately approved Plugin Tester integrated proof is
recorded in [V2_3_ADMISSION_ISOLATED_PROOF.md](V2_3_ADMISSION_ISOLATED_PROOF.md).
Genuine-core authentication and owned native persistence exercise this unchanged
fixture lane; they do not promote its source/receipts into real permission.
Historical next-step and validation statements below describe this foundation's
original scope. Real-domain construction and production integration remain gated.

Approved 2026-10-17: local repository implementation, in-memory fixtures only,
validation, commit, push and exact-head CI. Approval-covered R3 request/storage
interpretation; no site, LocalWP, database, users/options/cron, authenticated
grants, production wiring, activation, rollback, release or deployment. Versions
remain uploader 0.5.25 and dashboard 0.1.77. Clean Git baseline
`162185ff41a878f5db19468b6e6d1036f033148b` is the recovery point; toolkit
restore-point check confirmed it before edits.

## Plan and boundaries

Implement the approved dashboard closed-writer admission design as pure,
test-loaded contracts and a fixture-only request boundary. Keep existing schema 6
and request adapters unchanged. Validate exact short-lived purpose-bound forms,
lossless finite schema-6 projection, fixture-only schema-7 receipts, immutable
original intent and three-write retry budget, scoped one-shot continuation and
redacted receipt observation. Exercise deterministic in-memory failures and
interleavings, then run relevant and full repository QA, review, commit/push and
verify exact-head CI in both repositories.

Injected fixture authentication/nonces are not genuine WordPress authentication.
The model and simulated transaction contract cannot certify SQL atomicity,
physical target effects, production cache consistency or real provenance. No
factory may construct reserved authenticated/internal domains. The new definitions
must remain absent from production bootstrap/handlers. No dormant-only release.

## Implemented contract

Nine new dormant include definitions separate scalar/canonical form validation,
read-only snapshot validation, finite projection/context, immutable records,
strict successor validation, pure candidate transitions, request coordination and
one-shot fixture continuation. They are loaded explicitly by tests only. No
existing validator, adapter, bootstrap, handler, storage backend or SQL proof is
changed. The coordinator's injected ports are test seams, not a production factory
or a sandbox against same-privilege PHP code. The pure model returns candidate
data; it has no storage adapter and cannot commit anything. Its public
`simulate_locked()` accepts exact synthetic bytes, not a posted observed hash,
and confers no request admission. The private-constructor continuation limits the
coordinator's simulated transaction owner to one entry on its original identity,
original envelope/deadline and exact confirmed post-begin state, then invalidates
in `finally` even after an exception. A separate one-use confirmation rechecks
identity, still-locked state/target bytes and the original clock/deadline before
the fixture may assign the simulated committed pair. A clock tick rejects an old
prepared commit timestamp rather than silently refreshing the original intent.

The fixture contract is `closed_writer_request_v1`; actions exclusively use
`alynt_drime_backups_closed_writer_fixture_issue_v1`,
`alynt_drime_backups_closed_writer_fixture_command_v1` and
`alynt_drime_backups_closed_writer_fixture_receipt_v1`. All receipts use
`closed_writer_admission_fixture_v1`. Real action prefixes, authenticated/internal
receipt constructors and production factory paths remain absent. The fixed target
is a synthetic existing JSON settings object with existing matching site UUID;
PHP-serialized WordPress option decoding is deliberately not implemented.

Purposes are upgrade/disable-begin/fresh-abandon/independent-receipt-read only.
There is no form finish, target, field, desired value or execution flag. Every
form leaf is a bounded UTF-8 string, unslashed once; canonical decimals check
overflow before casting and timestamps have a separate bounded positive ceiling.
The deadline is exclusive, at most 300 seconds, independent of nonce lifetime.
Complete typed envelopes bind fixture nonce actions regardless of form key order.
Issuance derives all epochs, hashes, pending facts and UUIDs from fixed fixtures,
rechecking fixture session/capability/nonce after observation. Opaque private
context binds exact synthetic target bytes and state consistency, not actor/time.
Already-disabled no-op requires fresh matching context/null pending, performs no
write and reserves no completion UUID.

Schema 7 freezes the entire schema-6 barrier group without recursively archiving a
successor. Original header counters/time plus protected root fields reconstruct
strict schema 6 through the unchanged validator backed by bounded immutable bytes.
Sorted structural/map consistency hashing never rewrites frozen evidence or
reorders lists. Old fixture evidence is not promoted. Authenticated grant stays
null; authenticated and real-internal receipt maps stay empty. Immutable new
fixture upgrade/closure/completion/history records have distinct purposes and a
validated contiguous, nonregressing fencing chain. The reserved completion UUID
is allocated once outside actual begin write attempts, retained on retries and
checked against old/new/current/reserved identities. There is no eviction,
pending-expiry cleanup or counter reset.

The closed coordinator performs at most three actual upgrade/begin comparisons,
with independent fresh guards before reads, after observations and immediately
before writes. Fresh abandon has one original-epoch simulated comparison only.
Exact retained intent is observed before current context equality; changed
digest/purpose/domain conflicts. Only an exact strict read-back can confirm a
candidate, not a generic truthy sink result. A false/throwing acknowledgement never
replays a target operation. Default-off construction/denials perform no private
IO. Fresh receipt reads have their own nonce/deadline and post-read authorization.
Results are bounded/redacted with distinct closure and secondary dispositions;
`simulated_committed` always carries `physical_target_effects_proven=false`, plus
dispatch/execution false. Lost response authorization denies/redacts without
undoing or deleting simulated committed evidence.

## Focused review and proof limits

Reviewed authorization/nonce/order, schema reconstruction/provenance, privacy,
retry/clock/counter/capacity, pending/no-op/ABA fencing, failure/acknowledgement and
lifecycle boundaries. Corrected during implementation: guard budgets count writes
not calls; counter ceilings do not accidentally inherit the timestamp reserve;
exact deadline rejects at expiry; malformed issuance confirmation/IDs reject
before private reads; no-op does not allocate an unnecessary completion identity.
Existing tests/assertions/skips and CI policy are not weakened.

The fixture's atomic pair assignment is **not SQL serialization evidence**. The
simulated compare/transaction ports are trusted test owners; arbitrary production
providers are neither accepted wiring nor authenticated provenance. No WordPress
auth callbacks run inside the normal simulated locked-entry phase. Genuine
sessions, SQL lock waits/atomicity, object-disabled serialized option decoding,
supported engine/cache/drop-in/reconnect policy, durable schema-7 storage and the
final pre-transaction role/session race still need integrated owned-site proof.
Prior genuine-core and schema-6 SQL receipts remain separate historical evidence,
not certification of this new bridge. Production writer participation,
lifecycle/effects/downgrade behavior and all enabling/grant/rollback transitions
remain separate shipping blockers.

## Executed evidence

The final new in-memory suite passed **105 tests / 1588 assertions** without skips using
`php vendor/bin/phpunit --do-not-cache-result --filter ClosedWriterAdmission`.
It covers exact parsing/action/issue domains; zero-IO default-off and forbidden
forms; malformed/source/real-domain denial; frozen legacy evidence; independent
receipt reads; no-op and stale-context distinction; guarded three-write retry and
fixed reserved IDs; each authorization-loss phase including post-commit redaction;
competing begins/recovery fencing; invalidated retained continuations; failed,
false/truthy/throwing acknowledgements; target/clock/identity mismatch; unsupported
autoload/storage; and shared history/counter reserve. Fifty optional new history
records and fifty frozen legacy records each block further history growth without
pruning while allowing the already-reserved terminal operation. All measured new
records, including full-width clock and near-maximum fencing counters, fit 1024 bytes; encoded state
stays bounded at 128 KiB with 4096-byte pending/initial reserve and depth 16.
Additional final review regressions prove pre-commit expiry/timestamp and target
changes reject without replacing the original closed pending or target evidence.
Canonical counters allow zero as specified, but a zero cannot match the existing
closed schema-6 epoch; clocks remain strictly positive. Overflow is rejected before
integer conversion, not repaired or coerced.

Local full uploader regression selection passed **1116 tests / 9200 assertions**,
four existing Windows skips, in **10:09.511**. That run began with the initial
100-test foundation selection; the final pre-commit/counter refinements were then
validated by the complete final **105 / 1588** focused suite above. Exact-head CI
must run the complete final suite on PHP 7.4/8.3 before publication is complete.
Full PHPCS passed 155 files; its existing nonfatal PHPCompatibility
`functionWhitelist` deprecation is unchanged. All 17 new PHP files passed syntax
and ownership/structure checks (runtime at most 251 lines, tests/support below
150). `npm run build`, `verify:runner`, `lint:runner`, `test:dev-tools` (3 tests),
`npm audit --audit-level=moderate`, Composer `validate --strict` and `audit` passed;
both dependency audits report zero vulnerabilities and generated assets stayed
unchanged.

Dashboard full PHPUnit passed **299 / 1423**, two existing Windows skips, plus full
PHPCS (138 files), `npm run check:structure`, build, Composer strict validation and
zero-vulnerability Composer/npm audits. Final staged/diff/source review found only
the new fixture-loaded foundation, additive tests and related plan/evidence docs;
no existing runtime validator/handler/bootstrap, dependency, generated, workflow or
version changed. No browser, LocalWP, SQL, genuine-core, lifecycle, package or live
check was run or inferred. Site-operations guidance was read; no site target or
Novamira access is needed for this repository-only in-memory lane. Commit/push and
exact-head CI are the remaining publication gates, not another site proof.

Implementation-plan progress: the approved closed admission contract/provenance
foundation now has executable pure/fixture evidence, not real authenticated
transactional persistence. **Recommended next gated slice: Plugin Tester local-only
V2.3 Closed-Writer Admission Isolated Storage and Authenticated Request Proof.**
Approve the integrated genuine-core construction and owned disposable users/storage
proof, with independent cleanup/preservation audit, before claiming the new bridge
durable or authenticated. Keep actual users/options/cron, production wiring,
grants, activation and rollback untouched/disabled. No maintenance-only release is
warranted.

## Publication receipt

Implementation `eeb6f4b6b165351e368a50179918af3a9fec2136` is committed/pushed to
`origin/master`. Exact-commit [Quality 39072819784](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/39072819784)
passed PHP 7.4 and 8.3, each **1121 tests / 9351 assertions**, no skips, plus full
PHPCS, runner/developer-proof syntax checks and the Node build/developer-tool/
deterministic-asset/audit job. This is the complete final suite, including all
105 new in-memory tests; it closes the earlier local full-selection/final-focused
distinction. Only the existing nonblocking ubuntu-latest migration annotation
remains; no workflow or test policy was weakened.

Dashboard plan commit `771ff381d76ad8e4d9b8775ea5c5a6fc8c576eb1` passed exact-commit
[CI 39072823321](https://github.com/NichlasB/alynt-drime-backups-dashboard/actions/runs/39072823321),
PHP 7.4/8.3 each **299 / 1430**, no skips, plus lint/structure/build/strict Composer
and dependency audits. Both repositories were clean/synced after these pushes.
The approved repository foundation and its implementation publication gates are
complete. Versions, actual site/database/users/options/cron, production wiring,
real grants/domains, activation and rollback remain unchanged/disabled; no release
or deployment was performed. The exact next meaningful approval is the integrated
Plugin Tester proof named above, not generic cleanup or a dormant package. This
receipt-only follow-up needs final exact-head CI verification before handoff;
no recursive receipt commit or site access is needed.
