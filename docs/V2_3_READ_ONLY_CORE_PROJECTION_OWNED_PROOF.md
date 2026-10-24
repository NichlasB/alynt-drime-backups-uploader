# V2.3 Owned Read-Only Core Projection Feasibility / Privilege-Denial Proof

2026-10-24. Explicit R3 authority for two disposable DB accounts,owned tables/views,
permissions/private workers and cleanup;fresh exact confirmation of Plugin Tester
local-only,http://plugin-tester.local,WordPress at
`C:/Users/Captain/Local Sites/plugin-tester/app/public`,DB10004. No existing account,
application data/configuration/service,production wiring or admission change.
Versions uploader0.5.25/dashboard0.1.77 unchanged;no package/release/tag/deploy.
R2 is the product readiness gate,not a substitute for this explicit R3 permission.

Excluded bases:uploader `a011678076f881f091bfc16231522df082c46360`,dashboard
`d592ac953d5aa00a2cee979880d0cfed4a313505`,toolkit
`d251acb1b935413b0393e16f5e2055d529d0cd29`.
Parent:dashboard `docs/V2_3_PRE_OWNER_READ_CONTRACT_ISOLATED_AUTH_PROFILE_DESIGN.md`.
Predecessor:[strict bootstrap proof](V2_3_PRE_OWNER_BOOTSTRAP_CONTAINMENT_OWNED_PROOF.md).
No historical whole-plugin readiness or predecessor proof counts are refreshed.

## Result: DB containment earned; bootstrap completion remains Blocked

Final ordered proof passes **four groups/994 checks**:capability25,unsafe-control12,
autoload478,fallback479. The total includes one deliberately assertion-killed
zero-protected-value control and many included-source/path checks;it is not994
distinct query scenarios. MariaDB10.6.23,PHP8.5.1,genuine WordPress7.1.2.

The limited invoker reads eight safe options and populated two-user/four-usermeta
views,with NO PHP query observer in that capability stage. Locked definer and
effective SELECT-only grants are verified;the worker receives only invoker
credentials through bounded private stdin in an allowlisted environment. No root
MYSQL_PWD,definer credential,existing wp-config or privileged handle in the worker.
Account-lock metadata is verified;no separate definer login experiment is claimed.

Actual engine permission errors,not empty results,protect13 denial cases:

| Cases | Actual error |
|---|---|
| Owned base,qualified base,UNION base,application empty projection,ungranted unsafe view |1142|
| INSERT/UPDATE/DELETE view,ALTER owned source,GRANT owned base |1142|
| CREATE USER using already-owned definer name |1227|
| EXECUTE using absent owned routine name |1370|
| INTO OUTFILE into verified absent owned directory |1045|

The application SELECT uses WHERE1=0 to avoid payload disclosure even under an
unexpected grant. Negative mutations target only owned objects. The routine case
proves EXECUTE permission refusal,not execution of a provisioned procedure;the
file case proves permission refusal,not a disk-write/cleanup experiment. No new
routine,third account,server setting or existing privilege is created/changed.

The supervisor temporarily grants the SAME invoker SELECT on a deliberately
unsafe owned view. Real canary rows become visible and the zero-value assertion
fails as required. This is a labeled control-only disclosure of two synthetic
values,not pre-auth access to actual protected application data. Revoke that grant
before both fresh bootstrap workers;each verifies exactly three readable views.
An always-denying account or empty/sanitized source cannot pass these positives.

## Cold core/cache evidence and exact new blocker

Both fresh workers verify genuine empty nonpersistent core cache,execute real
bulk options queries against the DB projection and hydrate eight safe keys with
zero protected values. Autoload completes1 data query;empty-autoload/fallback
completes2. The populated source includes the two protected-name canaries with
autoload flags varied between profiles;neither reaches the request/cache.
No core/query rewrite,preloaded alloptions,pre_wp_load_alloptions short circuit,
SHORTINIT,installation bypass,cache adapter,auth mock or owner-availability change.

Next genuine core SELECT requests `wp_attachment_pages_enabled`,outside the fixed
eight-key contract. Both stop BEFORE that query executes:
`blocked_at_first_dependency`,unapproved_read,query SHA256
`b231fae6f6a25829223381916deb90bd44e4c5baf79d289d4b43f68a5bb5686d`.
Do not add that key,raise the key budget or permit writes during this proof.

This advances beyond the predecessor's first bulk-read blockade but is still a
CLI bootstrap prerequisite,not completed WordPress bootstrap,Tools/admin-post
routing,HTTP/SAPI framing,cookie/nonce/capability proof or positive owner admission.
Fixture users have synthetic non-authentication passwords and empty session data;
their row visibility does not establish genuine WordPress authentication. WP6.0
runtime is not available/established/executed;the compatibility lane stays Blocked.
Ordinary profiles remain Unsupported;no production credential migration is earned.

## Setup findings, repairs and preservation

Two setup failures are retained as failures,not counted as passing native groups:

1. Seed encoder emitted CONVERT(0x...) for empty WPLANG. MariaDB's read-only
   diagnostic reproduces1054(unknown column0x). Three acknowledged source CREATEs
   were retained because no marker had been inserted. Exact read-only preview
   proved all3 tables empty,0 accounts,0 views;explicit approved cleanup removed
   ONLY `wp_alynt_ro_4d71e31c882d_src_{options,users,usermeta}` and verified absence.
   The attempt's application_preserved=false was the80-versus77 inventory mismatch,
   NOT evidence of a write to existing rows;it did not independently qualify a
   completed preservation closeout. No actual application mutation is in its path.
2. Empty-safe CONVERT(X'...' USING utf8mb4) literals then caused view error1271:
   literal and column coercibility2 with general_ci versus unicode_520_ci.
   Both owned accounts and3 tables for seedfab67a8051df were automatically cleaned;
   all77 application checksums,existing permission checksums and source hashes match.

Final encoding uses `_utf8mb4 X'...'`:valid empty/quote/control/UTF8 bytes and literal
coercibility4,allowing the existing column collation to govern comparison. Read-only
native expressions verify empty length0 and IN comparison;no column/session collation
change. Permanent pure regressions protect this encoding. Cleanup now distinguishes
acknowledged,empty,pre-account setup from marker-owned populated setup;names alone
never confer cleanup authority. Diagnostics expose numeric error codes,not raw SQL,
credentials,grants/password hashes or stderr. No semantic guard was weakened.

Final seed79167898fdd7:four views (options/users/usermeta/unsafe),two DB principals
(`alynt_ro_79167898fdd7_i`,locked`..._d`) and3 source tables were previewed and
removed. Source row previews:11 options(including private ownership marker),2users,
4usermeta. Owned sessions closed BEFORE drops;view definer/security identity and
private grants previewed before exact DROP VIEW/USER/TABLE. Final accounts/objects
independently absent. No retained fixture,listener or file;no actual users/sessions/
options/cron/config/services changed. Private PHP processes exited and pipes closed.

Fresh final proof's full77 application inventory/EXTENDED checksums match.
Existing mysql.global_priv/db/tables_priv/columns_priv/proxies_priv/roles_mapping
checksums match after cleanup;all temporary privileges removed. Seven core/config
hashes match. No claim of physical-engine page/timing isolation,hostile DB-admin
containment,feature transaction atomicity or general timeout-fault qualification.
Supervisor query logging/binlog were verified off;no logging configuration changed.
Novamira profile exists but doctor reports expired OAuth/discovery provider_error;
no reconnection or credential change. Bundled MySQL preflight preceded PHP DB work.

## Source safety, reviews and validation

Pristine canonical OptionB archive completed before edits:
`alynt-drime-backups-uploader-20261024-161220.zip` and matching.txt in toolkit
snapshots/uploader;12435files,clean excluded uploader head,SHA256
`2062956EF74F6A9DC8A0A194B89317A320BD1C314A0C9F2E4715936777E6933B`.
No source restore,checkpoint,site/database backup or application rollback.

Four test-only scripts:fixed envelope/canary counter,limited capability worker,
genuine-core bootstrap observer,and Windows explicit supervisor. No production
includes/hooks/endpoints/registration. Two test files add fixed-envelope/CLI/parse
coverage plus SQL encoding,grant shape,environment scrub and name-boundary checks.
No WordPress/database operation runs in CI;native sources parse on each PHP lane.

Canonical Feature Light:9 changed executable/test/doc files,DB/FileOps/Other;
two source-backed setup defects repaired with meaningful regression evidence and
scoped cleanup diagnostics. No new architecture/refactor or arbitrary source split:
small cohesive scripts,not a generic permission framework. Bloat/Structure and
UI/UX not needed;no rendered interface. No formal DS3/Database/FULL_PRE_RELEASE
completion or refreshed historical readiness. The full suite remains an RC gate.

Final canonical Feature Security after fixes/local QA reviews fixed target,
private credential input,environment/root separation,effective privileges,owned
unsafe control/revocation,unchanged auth/intent/availability,budgets and ownership/
preview/absence cleanup. No remaining confirmed scoped security defect or
security-fix regression handoff. This is not whole-plugin or production certification.

Local Node980/980 at established Windows concurrency2,no failures/skips;focused5/5.
Focused PHP154tests/1934assertions,no failures/skips;PHPCS255pass with existing
PHPCompatibility ruleset metadata deprecation. Uploader build/runner deterministic
SHA7356eacd57ed67f4265db8198f8ca5809e07f4bd985b1d6d00a88cb915112117 unchanged,
npm audit0vulnerabilities. Dashboard299/1423,2 existing Windows Sodium skips;
PHPCS138/structure/build pass. Exact-head CI and publication belong in toolkit.
No local full uploader PHP suite or minimum-WP runtime claim;native proof is separate.

Implementation-plan progress:R2 has database-enforced owned projection and privilege
evidence,not an ordinary or completed authenticated profile. Recommended next gated
slice: **design-only V2.3 Fixed Core Projection Dependency Manifest and Cold Bootstrap
Completion Design**,reviewing `wp_attachment_pages_enabled`,a finite versioned key/
value budget and missing-dependency handling before changing the eight-key contract.
Keep storage writes,native experiments,admission,wiring,feature grants,activation/
rollback disabled during that design;R3-R7 remain independent. No further native run
or automatic whitelist/grant enlargement is authorized by this proof.
