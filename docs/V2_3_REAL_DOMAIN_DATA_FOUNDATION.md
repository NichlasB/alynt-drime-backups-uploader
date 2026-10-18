# V2.3 Real-Domain Closed Provenance and Serialized-Target Safety Foundation

Approved 2026-10-18: local repository implementation, in-memory fixtures only,
through validation, commit, push and exact-head CI. The canonical design is
dashboard `docs/V2_3_TRUSTED_REAL_DOMAIN_CONSTRUCTION_DESIGN.md`. Clean source
recovery checkpoints: uploader `10f96b629bc509a6e0836b8ab7480a37cf80e122`, dashboard
`e93b18791842ec36eee79bf68735a5ead71739ac`. Toolkit restore-point **check** verified
the clean uploader baseline; no archive, backup, branch switch or site action.

## Bounded implementation plan

Add a test-loaded primitive serialized-array parser and fixed disable-only target
codec, with exact byte preservation and bounded failure. Add independent strict
schema-8 real-shaped data/receipt validation and pure closed upgrade/begin/abandon/
completion candidates. Reuse only existing pure finite schema-6 projection and
plain structural helpers, never relabel schema 7 or call its model to mint real
records. Explicitly mark plans as candidate-only/non-admitting and default them
off. No request/nonce/auth provider, genuine-core factory, admission handle,
storage backend, callback, SQL, LocalWP, site, grant, activation or rollback.

Test malformed/object/reference/coercing/oversized data; source/domain/identity
segregation, frozen projection, exact context/target changes, clocks/counters,
pending/recovery/ABA, combined history and worst-case record/state reserve. Run
targeted and full repository QA, scoped security/architecture/error/adversarial
review, then commit/push and verify exact-head CI. No runtime bootstrap,
existing validator, public contract, version, dependency or release changes.

Real-shaped records assembled by pure functions or tests remain synthetic data.
Shape validity and consistency hashes cannot certify an authenticated event or
authorize any persistent write. No mock authorization flag may acquire admission.
Execution evidence and next gates will be recorded only after actual validation.

## Implemented boundary

Six new runtime-definition files are explicitly test-loaded only:
`class-serialized-settings-parser.php`, `class-serialized-settings-target.php`,
`class-real-closed-writer-data.php`, `class-real-closed-writer-model.php`,
`trait-real-closed-writer-validation.php`, `trait-real-closed-writer-candidates.php`.
No bootstrap or existing validator changes. `plan()` and `completion()` default
off and return only `candidate_only: true`, `admission_proven: false`, dispatch/
execution false, candidate state and optional **private** target bytes. The pure
simulation switch is not authority; no request body, real caller or storage sink
can obtain an admission handle from these outputs. Schema-8 receipt domains name
candidate data roles, not independently authenticated historical events.

The new model uses only the existing contract's **pure typed layout validator**
and projection's strict schema-6 source/reconstruction and canonical
consistency helpers. It does not call fixture request parsing/actions, fixture
schema-7 construction, admission callbacks, or fixture continuations. Its new hash
namespace includes `real_closed_data_version: 1`. Its own plain-data guard also
bounds traversal before JSON encoding: 65536 combined nodes, 128 KiB total string
bytes and depths 0..15, shared across the whole input. Exact encoded state/record
byte limits still apply afterward. Broad, deeply nested, recursive or oversized
data cannot trigger serialization callbacks or unbounded traversal/allocation.
Original schema-6 digest and
complete frozen legacy data are preserved, never promoted or rewritten.

The parser accepts canonical primitive arrays only, at most 1 MiB, 4096 combined
key/value nodes and depths 0..15. It rejects objects/references/floats, coercing or
duplicate keys, invalid UTF-8 and malformed/overflowing lengths/counts before
instantiation or unbounded allocation. The codec replaces only the root Boolean's
four bytes, reparses, and verifies identical remaining typed data. No generic PHP
unserialization or whole-option reserialization is used by the implementation.
Autoload metadata and fixed blog arguments are explicit **simulated** facts, not
proof of actual WordPress row/scope properties. The future owner must obtain and
verify those facts itself, never use this data API as a trusted provider.

All three counters advance together. New authenticated/internal receipt histories
remain separate and share the 50 optional-record budget with frozen legacy maps;
mandatory slots, reserved unused IDs, 1024-byte receipts, 128 KiB/depth-16 state,
4096-byte pending reserve and terminal counter headroom are checked. Exhaustion
denies without pruning, reset, expiry cleanup or repair. Completion reconstructs
the exact pre-begin data from the retained chain solely to recheck original context
(including blog/autoload/codec/source), not to roll any stored state backward.
Fresh abandon creates no target candidate. Already-disabled/null-pending is an
unchanged non-admitting candidate, not a renewed command/epoch.

## Focused reviews and executed evidence

Test-first codec selection failed 33/33 before the files existed; initial model
selection failed 2/2 for the absent data definition. These were missing-feature
checks, not a claim of an existing production regression. After implementation,
the focused `php vendor/bin/phpunit --do-not-cache-result --filter
'RealClosedWriter|SerializedSettings'` passed **99 tests / 1103 assertions**, no
skips. One negative-test sentinel originally equaled the existing binding and was
corrected; no existing tests or acceptance thresholds were weakened.

Security/privacy review covers source/domain separation, false-by-default outputs,
non-coercing grammar, callback-free decoding, private errors and secret-like target
sentinels excluded from receipts/status. Architecture review keeps original readers,
validators/bootstrap and production wiring unchanged. Error/adversarial review
covers duplicate/current/reserved/legacy identities, removed/mixed history, ABA,
original expiry, fresh abandon, counter overflow, combined history capacity,
maximal clocks/counters and exact parser byte/depth/node boundaries. An explicit
`JsonSerializable` trap proves rejection before encoding callback execution.
These scoped source/test reviews are not a full pre-release/security suite or an
independent real authentication, SQL, cache, lifecycle or concurrency proof.

PHP syntax passed all 14 new PHP files. New runtime files are 46..247 lines and
the test helper is 36 lines, within the 300/150 structure limits. Full uploader
PHPCS passed **161 files** with the existing nonfatal PHPCompatibility
`functionWhitelist` deprecation. Build, deterministic runner verification, runner
syntax and all three Node development-tool tests passed. Strict Composer validation
and Composer/npm (`--audit-level=moderate`) audits passed with no vulnerabilities.
Dashboard full PHPUnit passed **299 / 1423**, two existing Windows skips; PHPCS
passed 138 files, and structure/build/strict Composer validation and both audits
passed. The final focused selection includes the traversal guard; exact-head
full-suite CI is required before completion.

## Initial publication evidence

Implementation `08420f74ef04fb7f9c76624f0b0821ced599c493` is pushed to
`origin/master`; [Quality 39123850277](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/39123850277)
passed PHP 7.4/8.3, each **1219 tests / 10440 assertions**, no skips, plus PHPCS,
runner/proof syntax, Node build/audit/development tests and generated-asset checks.
Dashboard plan commit `9a63c40f6074f811a576f8033049e0f051533782` passed
[CI 39123856687](https://github.com/NichlasB/alynt-drime-backups-dashboard/actions/runs/39123856687),
each PHP job **299 / 1430**, no skips, with all build/structure/lint/audit gates.
These counts precede the final additional traversal-budget test; the final
focused selection above includes it. Final exact-head CI is required for the
traversal guard and verification-record follow-up before handoff; no recursive
receipt commit or site operation is necessary.

## Next explicit gate

Implementation-plan progress: the approved non-admitting schema-8/serialized-target
data foundation is implemented; no additional maintenance-only slice substitutes
for the next trusted-construction decision. This does **not** make real requests,
atomic storage, effective scan stopping, cache coherence or rollback ready.

Recommended next gated slice: **local-only V2.3 Fixed Genuine-Core Closed-Writer
Request Construction Safety Foundation**. Implement the separate real request
contract and dormant fixed owner, with zero-IO construction, genuine-core guard
definitions and private one-use lifetime; no injected auth/provider ports, no
production registration, storage/target writes, grants, activation or rollback.
Any actual Plugin Tester user/storage creation or native SQL proof requires its
own explicitly confirmed target/owned-fixture scope. No live operation, package,
release, version/dependency change or deployment is part of this foundation.
