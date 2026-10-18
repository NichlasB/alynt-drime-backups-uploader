# V2.3 Fixed Genuine-Core Closed-Writer Request Construction Safety Foundation

Approved 2026-10-18, repo-only, through validation/commit/push/exact-head CI.
Uploader recovery checkpoint `f60b81c123d2d9f5a472515655d5645b217304ce` and dashboard
checkpoint `ed6db353ee7b7904a3a839da025dae66421200a1` were clean/synced. Toolkit
restore-point Option A/check verified the uploader Git checkpoint before editing;
no archive, branch, backup, rollback or site operation. The initial sandboxed check
could not identify Git; its escalated read-only rerun verified the actual clean repo.

## Approved R3 plan and limits

Implement a separate real-purpose scalar request contract and a final private-
construction owner with no caller-supplied providers, authentication, site, clock,
nonce, enablement or storage ports. Loading remains explicit/test-only, unregistered
in bootstrap/hooks/routes/CLI. Construction itself performs no IO. Only explicit
request inspection invokes fixed core guard definitions; all issuance/consumption
still returns unavailable because there is no authoritative raw reader or storage
backend. Do not issue an actionable command, create a schema-8 record, expose an
admission handle or manufacture a durable-success/target outcome from guard truth.

Define exact real issuance/command/receipt actions, strict original 300-second
windows, and bounded once-unslashed forms. Guard the current single-site/native
scope, local logged-in cookie/session and current-user identity, fresh user metadata
and current role definitions, then genuine core capability/nonce semantics. Reject
switched scope, external/custom caches, database drop-ins and unavailable dependencies.
Keep request lifetime private, non-reentrant, non-cloneable/non-serializable and
always clear it in finally. No public callback/continuation minting API is added.
A transaction continuation cannot be created before a future verified durable begin.

Tests use in-memory core-shaped shims in isolated PHP processes only. They prove
fixed call routing, grammar, freshness ordering, refusal, redaction and lifetime,
not genuine authentication or WordPress/database/cache behavior. Do not bootstrap
WordPress, touch LocalWP, create actual users/sessions/options, access SQL, change
stored permissions, delete actual caches or register handlers. No grants, activation,
rollback, schedules, production wiring, release, deploy or version/dependency changes.

Run scoped security/architecture/error/adversarial reviews and targeted tests, then
full repository lint/tests/build/audits. Preserve old validators and fixture domains;
publish the bounded source and dashboard plan records and verify exact-head CI.
Only actually executed results will be recorded below.

## Source-backed guard decisions

Core cookie validation checks the cookie hash and session manager; a valid nonce
is not a replacement for that check. See [wp_validate_auth_cookie](https://developer.wordpress.org/reference/functions/wp_validate_auth_cookie/)
and [wp_verify_nonce](https://developer.wordpress.org/reference/functions/wp_verify_nonce/).
User cache eviction must explicitly include user metadata for WordPress 6.0
compatibility; its automatic inclusion in clean_user_cache was added later.
See [clean_user_cache](https://developer.wordpress.org/reference/functions/clean_user_cache/).

Fresh WP_User role capabilities depend on the global role view. A new WP_Roles
can itself use the supplied global role array instead of persisted definitions;
therefore custom global role definitions are unsupported. The guard temporarily
uses a fresh same-site role view for core user_can and restores the previous view
in finally, without changing the current actor or persistent roles. This is a
source-backed implementation decision, not an executed real-core proof. See
[WP_User::get_role_caps](https://developer.wordpress.org/reference/classes/wp_user/get_role_caps/),
[WP_Roles::get_roles_data](https://developer.wordpress.org/reference/classes/wp_roles/get_roles_data/)
and [user_can](https://developer.wordpress.org/reference/functions/user_can/).

## Implemented boundary and private lifetime

New, explicitly test-loaded definitions only:
`includes/class-real-closed-writer-request-contract.php`,
`includes/trait-real-closed-writer-core-guard.php` and
`includes/class-real-closed-writer-request-owner.php`. No existing validator,
fixture contract, data model, bootstrap or handler is widened. The real contract
independently implements the approved typed grammar and action domains; it does
not call the fixture request parser/actions or accept a source selector.

`Owner::create()` accepts zero arguments and performs no IO, even without WordPress.
Its constructor/clone are private, its class final, restoration/serialization throw
fixed failures, and there is no enablement/provider/clock/auth port. Public
`issue()` and `consume()` inspect exact POST-only forms but **always return the same
redacted unavailable disposition**, storage/dispatch/execution false. They do not
issue a UUID/nonce/envelope, read settings/safety, invoke a backend, produce schema-8
receipts or expose a transaction continuation. In this foundation "consume" is an
inspection entry point, not consumption of a persistent command.

The owner latches single-site blog/table/native-wpdb-object metadata on its first
inspection. This is not verification of a physical native connection or transaction.
It refuses changed objects/tables/blogs, switched/multisite/CLI/SHORTINIT contexts,
database/object-cache drop-ins, external object caches, custom role/user classes or global role arrays
and missing dependencies. It never calls an installing/defaulting settings helper.

The guard binds the same actor and exact original cookie for the whole private
request lifetime. It evicts user and explicit user-meta caches before core cookie/
session validation, evicts role/alloptions/notoptions caches, creates a fresh
same-site core role/user view and calls core `user_can(..., 'manage_options')`,
preserving capability filters. It never sets the current user, changes roles,
creates sessions, or writes permissions. The temporary role view is restored in
finally, including on denial/throw. Authentication is checked before and after
the exact core nonce; current actor/session/scope changes deny. Cookie expiry is
strict (no POST grace extension), while command expiry is independently exclusive
and at most 300 seconds. The real clock cannot be injected or silently regress.

Narrow PHPCS annotations explain exact raw cookie/method comparisons (normalizing
would change the core cookie or broaden accepted methods) and the temporary,
unconditionally restored role view. No file-wide suppression or lint-rule change.
Actual invocation of these definitions in WordPress would perform controlled auth
reads/cache evictions and normal core callbacks; that has **not** been approved or
executed on a site here. Same-privilege PHP code, compromised callbacks, concurrent
auth changes after the final check and unsupported cache environments are not
made safe by a private constructor or a fixed API name.

The private active flag blocks re-entry, and finally clears it on every exit.
Cookies, nonces, envelopes and capability results are not retained as owner fields.
No public `within_fixture(array, callback)` equivalent or data-to-handle converter
exists. This is a one-stack **inspection lifetime**, not the transaction-owned
continuation: without a verified durable begin, that continuation remains absent.

## Executed focused validation and review

Test-first contract checks failed **24 / 24** for the missing implementation;
initial owner checks failed **2 / 2** for the missing owner. These are missing-
feature checks, not an existing production regression. Final targeted command
`php vendor/bin/phpunit --do-not-cache-result --filter
'FixedCoreRequest|RealClosedWriterRequestContractTest'` passed **69 tests / 470
assertions**, no skips. The selection additionally including existing
`RealClosedWriterModelTest|RealClosedWriterRecoveryTest` passed **74 / 601** before
the final three guard cases were added. PHP syntax passed all ten new PHP files;
new runtime files are 162..213 lines, helpers 23/43 lines, and tests 17..73 lines.

Tests prove scalar/size/UTF-8/decimal/once-unslashed grammar, per-field nonce-action
binding and disjoint purpose domains; zero-IO construction/no missing-dependency
autoload; no injected enablement/auth ports; stale session/user/role cache refusal;
core-call routing and filter denial; actor/session/blog/table substitution;
original future/expired windows and independent receipt read; re-entrancy;
exception redaction/restoration; private lifetime invalidation; copy/serialization
refusal; and no private SQL call. Shims deliberately simulate WordPress 6.0's lack
of implicit metadata eviction. A test-only ReflectionProperty compatibility guard
avoids a PHP 8.5 deprecation without weakening its lifetime assertion.

Build, deterministic runner verification, runner syntax, all three Node development
tests, strict Composer validation and zero-advisory Composer/npm audits passed.
Scoped auth/security/privacy/architecture/error/adversarial review found no state
writer, live registration, fixture promotion or public admission handle. Existing
tests/assertions/skips and dependency/version/generated contracts are unchanged.
Full uploader PHPCS passed **164 files** (existing nonfatal PHPCompatibility
`functionWhitelist` deprecation); the final three runtime files were also checked
after the scoped review changes. Dashboard full PHPUnit passed **299 / 1423** with
two existing Windows skips, PHPCS passed 138 files, and structure/build/strict
Composer validation/zero-advisory Composer/npm audits passed. The uploader full
suite and publication results will be recorded only after successful execution.
This is not a full pre-release/security suite, genuine-core
authentication proof, native SQL/cache proof, or production acceptance.

## Implementation-plan progress and next gate

The approved fixed request grammar/owner and private inspection-lifetime foundation
is implemented, while all persistent admission remains unavailable. This advances
the V2.3 trusted construction item rather than adding generic maintenance work.

Recommended next gated slice: **Plugin Tester local-only V2.3 Fixed Genuine-Core
Closed-Writer Request Guard Proof**, with separately confirmed owned disposable
users/session/role-option fixtures and independent cleanup/preservation audit.
Prove actual cookie/session and user/role-definition freshness, core nonce/filter
semantics, actor/scope changes, expiry and restoration, keeping actual users/options/
cron untouched and storage admission, grants, production wiring, activation and
rollback disabled. Current unit shims cannot replace that proof; a raw observation
provider and native transactional backend remain later explicit integration gates.
