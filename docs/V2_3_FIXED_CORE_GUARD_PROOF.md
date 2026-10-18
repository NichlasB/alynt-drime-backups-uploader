# V2.3 Fixed Genuine-Core Closed-Writer Request Guard Proof

## Status and approved boundary — 2026-10-18

**Blocked: genuine native-core positive frame control fails.** This is not a
completed authentication proof. No authorization guard was changed to force a
pass, and no fake success port or fixture cache-flag normalization was added.

Workflow: WordPress Component Testing, Troubleshooting, And Debugging Workflow,
under Site Operations. Target explicitly confirmed by the user: `plugin-tester
local-only`, Plugin Tester, `http://plugin-tester.local`,
`C:\Users\Captain\Local Sites\plugin-tester\app\public`. The approved R3 scope
includes owned disposable authentication/storage fixtures, cleanup/preservation
audits and source validation/commit/push/exact-head CI. It does not include a new
authorization-policy implementation, application writes or production wiring.
Novamira HQ guide and complete inventory were checked; the exact local profile
was available. Direct LocalWP tooling was used for the isolated native-core proof.

Source baselines: uploader `226263b9e3f237c59d9124b3c4eea111be721fde`;
dashboard companion records `17a6f3c5acfbcd165865766634d1aa13eb60ab36`.
Both were clean/synced before work. Toolkit restore-point Option A/check verified
the uploader committed recovery baseline; no new archive/backup was created.
Actual production source, bootstrap, versions and dependencies remain unchanged.

## Overview / fixture isolation

The new developer-only entry point is
`scripts/tests/fixed-core-proof-audit.ps1`. It resolves LocalWP metadata and matching
bundled MySQL/PHP runtimes; initial database access is MySQL, not `wp-load.php`.
The inspected target has MariaDB 10.6.23 on `127.0.0.1:10004`, database `local`,
with registry-approved credential source passed only through `MYSQL_PWD`.
No credential, cookie, token, nonce, raw SQL or private option/user data is printed
or committed. Paths/ports must be re-resolved on each future run.

The PHP supervisor creates exactly three fresh tables like the existing native
options/users/usermeta schema, under a random `wp_alynt_core_probe_<12 hex>_`
prefix, after proving their absence. The native-core worker verifies the exact
target and ownership marker **before** bootstrap. It loads genuine WordPress
`wp-settings.php` with that prefix, not the site's actual `wp-config.php`.
Plugin/mu-plugin directories are nonexistent, the fixture theme is nonexistent,
HTTP is denied, cron spawning is disabled, salts are ephemeral, and no browser
authentication headers are emitted. Core's ordinary initialization can write
disposable fixture options, including fixture cron, not actual application cron.
This is isolated genuine-core component integration, not the site's web login.

A process-local query fence refuses non-owned table accesses, protected application
option reads and guard-phase writes. Denials are counted even if the public owner
catches an exception and still returns unavailable. Deliberate fixture revocations
use a narrow synchronous fixture-only mutation bracket. The fence is for these
fixed trusted test queries, not a general SQL parser or a same-privilege PHP sandbox.
The supervisor never loads the owner or registers a site handler. SQL execution is
never part of CI; CI syntax-checks the six new PHP proof files only.

## Functionality inventory / test matrix

| Area | Expected observation | Result |
|---|---|---|
| Target, native core APIs, prefix and ownership | Exact local metadata, native `wpdb`, genuine auth API definitions, owned storage | Passed before the frame control |
| Native admin positive control | Private frame and Boolean guard reach genuine truth without public admission | **Blocked at frame**; guard/auth cases not reached |
| Issuance/command/receipt/fixture nonce domains | Exact cross-domain acceptance/refusal using genuine nonces | Prepared, not executed past blocker |
| Public issue/all consume purposes | Auth/capability checks on both sides of nonce; exact unavailable result, no handles | Prepared, not executed past blocker |
| Malformed/method/stale/changed intent | Deny before inappropriate reads; nonce cannot extend intent | Prepared, not executed past blocker |
| Revoked session/user/capability/role caches | Warm cached authority must not survive raw owned-fixture revocation | Prepared, not executed past blocker |
| Authority/actor/cookie/method/table/blog change during core nonce | Final native guard refuses and restores prior role view | Prepared, not executed past blocker |
| Actual application preservation and owned cleanup | Parent digest plus independent MySQL inventory/checksums unchanged; zero owned tables | Passed, including all failed attempts |

Reflection observes existing private decisions in the developer-only harness; it
does not add a public success path. A public owner that always returns unavailable
cannot satisfy the positive control. The later cases above remain explicitly
unverified until the real frame blocker is fixed and the unchanged assertions run.

## Finding G1 — native unset external-cache flag rejected

- Severity: **Medium**, dormant compatibility/availability defect; no authorization
  bypass, application mutation or production incident was observed.
- Reproduction: execute the audit entry point against the confirmed Plugin Tester
  target. Full core bootstrap and owned fixture seed succeed, but
  `Real_Closed_Writer_Request_Owner`'s private `frame()` returns false.
- Expected: an ordinary native single-site cache environment with no cache/database
  drop-in should reach the meaningful authentication positive control.
- Actual: `trait-real-closed-writer-core-guard.php` rejects
  `false !== wp_using_ext_object_cache()`. Genuine WordPress 7.1.2's native getter
  returns **null** when `$_wp_using_ext_object_cache` was never initialized. Its
  `wp_start_object_cache()` uses falsiness to load built-in `cache.php` and does
  not assign literal false in that normal path. There are no approved custom
  drop-ins. Other checked native frame metadata matched.
- Source evidence: Plugin Tester's `wp-includes/load.php`, getter lines 810–819
  and `wp_start_object_cache()` below it. This is direct inspected source/runtime
  evidence, not a claim about every WordPress version or cache installation.
- Why isolated tests missed it: the core-shaped shim returns literal false by
  default. The prior 197-test selection can pass while native frame admission
  fails. Those tests remain useful isolated evidence, not integration acceptance.
- Automated regression disposition: **deferred**. Owning follow-up is the exact
  gated compatibility slice below. Keep this native positive-control failure;
  add focused null/false versus unsupported-value regression cases before the
  approved dormant guard fix. No production behavior was changed in this proof.
- No workaround: the fixture does not call `wp_using_ext_object_cache(false)` to
  manufacture success, and the guard is not loosened under proof-only approval.

Harness setup findings were corrected before G1: an early overly broad identifier
check matched option-name text, and a second pattern mistook the column following
`ON DUPLICATE KEY UPDATE` for a table. The fence now checks read table positions
and exact leading owned DML destinations separately. These were harness failures,
not production guard defects. Every attempt retained cleanup/preservation checks.

## Tested actions / independent cleanup

Final recorded reproduction before publication returned exit **1** with
`Native frame must be admitted for guard inspection` and cache flag type `NULL`.
Worker stderr was empty. The temporary fixture contained **31 options, 3 users,
7 usermeta rows** when previewed for cleanup. The supervisor dropped only its
three exact created tables and verified each absence. These disposable records
are intentionally not recoverable; all genuine application records were retained.

Two distinct audits ran: supervisor in-memory SHA-256 digests of actual options
(including IDs/values/autoload), users and usermeta; and a separate MySQL-client
inventory plus `CHECKSUM TABLE ... EXTENDED` for **all 77 existing application
tables** before/after. All checksums/inventory matched and zero guard fixture
tables remained. These checks cover observed database preservation, not native
transaction/admission atomicity or malicious external concurrency.

## Clean checks / validation

- Existing targeted baseline: `php vendor/bin/phpunit --do-not-cache-result
  --filter 'FixedCoreRequest|RealClosedWriter|SerializedSettings'` passed
  **197 tests / 1752 assertions**, no skips, 14.175 seconds, 44 MiB.
- Full uploader PHPCS passed **164 files**; existing third-party nonfatal
  PHPCompatibility `functionWhitelist` configuration deprecation remains.
- Build, deterministic runner verification and all **3** Node developer-tool
  tests passed; generated runtime assets were not changed.
- PHP syntax passed all six proof files. PowerShell parser validation passed.
- Final targeted rerun passed **197 / 1752**, no skips, 14.374 seconds. Strict
  Composer validation passed; Composer and moderate-level npm audits reported
  zero advisories. Dashboard companion validation passed **299 / 1423**, with
  two existing Windows skips, PHPCS **138 files**, structure and build checks.
  No new test skip or weakened assertion was introduced.
- Exact-head publication/CI results are recorded separately below/at handoff.
  CI passing does **not** make the deliberately blocked native-core proof pass.
- Debug log exists on Plugin Tester, 3,231,317 bytes, last written
  2026-10-17 21:07:29 UTC, before this run. No before/after log-content audit is
  claimed. Query Monitor and WP Debug Toolkit were inactive; neither was enabled.
  Plugin Check was active on the actual site but not loaded/run in this isolated
  worker. No runtime source changed or package was prepared.
- UI, AJAX/REST routing, activation/deactivation/uninstall and whole-plugin
  acceptance are outside this dormant backend-only component proof. No UI/UX
  workflow was run. No Database Review or physical transaction proof is claimed.

## Changes / final status / next approval gate

Files: seven developer-only scripts, one CI syntax-check line, this owning report
and foundation pointer; dashboard plan/design pointers only. No source under
`includes/`, root/bootstrap, public routes, actual users/options/cron/settings/
safety, grants, activation, rollback, dependencies or versions changed. No package,
release, tag, deploy, production/client/Drime mutation or application writer ran.
Toolkit historical whole-plugin review rows are not refreshed by this blocked
runtime pass. No workflow completion is claimed or registered.

Implementation-plan progress: the actual native-core environment proof uncovered
a concrete compatibility mismatch hidden by isolated shims. The guard acceptance
lane is **blocked**, not complete; do not move to raw observation/native backend.

Recommended next action: approve **local-only V2.3 Native Cache-Flag Compatibility
Fix and Guard-Proof Rerun**. Scope: a narrowly reviewed dormant frame predicate
recognizing only genuine unset-null/literal-false native cache states, retaining
drop-in/external-cache/unknown-value refusal; non-vacuous null/false/unsupported
regression controls; rerun this confirmed owned-fixture proof and both audits,
then validation/commit/push/exact-head CI. Keep public results unavailable,
production wiring, application writes, grants, activation and rollback disabled.
This changes an R3 authorization-environment predicate and needs its own explicit
approval; the already confirmed local target/owned fixture scope need not be
reconfirmed if unchanged. Full pre-release remains reserved for the next coherent
candidate before packaging/publishing this work.
