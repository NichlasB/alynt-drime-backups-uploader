# V2.3 Pre-Owner Bootstrap Read-Containment / Route Feasibility Owned Proof (R2)

2026-10-24. Explicit approved and freshly confirmed `plugin-tester local-only`:
LocalWP Plugin Tester, http://plugin-tester.local, WordPress at
`C:/Users/Captain/Local Sites/plugin-tester/app/public`, MariaDB port10004.
Only owned disposable fixture creation, testing and previewed cleanup. No existing
application state, services, configuration, production ingress or owner admission
change. Versions remain uploader0.5.25/dashboard0.1.77; no package/release/deploy.

Excluded review bases: uploader `f543ea0b924fe49a024f754b967441455ed7eaa7`,
dashboard `5a5a3b52c62b03b8bddc8025e255a3f7acf4d1e8`, toolkit
`6b5cba8cce74553a5d2607bbd7f0f527a3132485`. Parent: dashboard
`docs/V2_3_FIXED_PROFILE_ADMISSION_GENUINE_HTTP_INTEGRATION_DESIGN.md`.

## Decision and measured boundary

**Containment controls pass; genuine route/profile qualification is Blocked.**
Real WordPress7.1.2 `wp-settings.php` reaches
`wp_not_installed -> is_blog_installed -> wp_load_alloptions` before admin routing.
With a genuine cold, nonpersistent core object cache, its first application-data
query is a bulk autoload-options projection. The early proof fence throws before
execution. No Tools/admin-post route, authentication, owner or native feature was
reached. Stop at this dependency: no allow-all-SELECT, sanitized alloptions cache,
core replacement, SQL rewriting, SHORTINIT, fake screen or availability bypass.

This is a CLI bootstrap prerequisite experiment using actual installed core, NOT
an HTTP request or successful route traversal. The SERVER route label is only
fixture input; it does not certify HTTP/SAPI framing or routing. Because the shared
bootstrap is blocked, neither actual route was probed. Cookie mapping, absent GET
framing, POST slashing, genuine authentication and supported HTTP profile remain
unproved. No WP6.0 runtime fixture was supplied/established or executed; that lane
remains Blocked, not waived, implicitly downloaded or a raised minimum version.

## Owned implementation and non-vacuous evidence

Three scripts only, never included by production:

- `bootstrap-containment-policy.php`: one exact owned-table/key/projection safe
  SELECT; every other query refuses. Bulk shape classification is diagnostic,
  not permission. Writes, joins, foreign/application tables and unknowns refuse.
- `bootstrap-containment-worker.php`: genuine core hooks before wp-settings,
  absent drop-ins/content/plugins, fresh process/cache, source identity checks,
  zero real `wpdb::num_queries` at denial and no cached protected values.
- `bootstrap-containment-owned-proof.mjs`: explicit Windows-only operator,
  current metadata/origin preflight, fixed bundled runtimes, random exact owned
  prefix/marker, independent whole-database before/after checksum inventory,
  source/config/owner hashes and previewed ownership-checked DROP of owned tables.

Final ordered proof: **two profiles,208 checks** (104each, including one deliberately
assertion-killed semantic control in each). Each request-phase trace has exactly
one attempted/prevented bulk-autoload query, zero completed data queries, zero
cached protected values, genuine cold cache and pre-execution dispatch evidence.
Zeros are derived from real dispatch/cache state, not initialized string counters.

After each stopped bootstrap, separately labeled controls establish:

- One exact safe read reaches genuine wpdb/database and returns the private owned
  marker. An always-denying policy fails this positive, not just negative tests.
- Five queries (two explicit protected keys, all-options bulk, application-table
  SELECT and owned UPDATE) are prevented with unchanged executed-query count.
- Autoload profile: real `wp_load_alloptions()` is denied before hydration.
- Fallback profile: a control-only narrow autoload-empty read reaches the genuine
  all-options fallback, which is denied. This is not a request-policy relaxation.
- Guard-removal controls allow ONLY fixed owned bulk shapes; genuine core returns
  both synthetic protected-name canaries and hydrates them into its real cache.
  The zero-protected-read assertion fails, and that exact failure is required.
  These four deliberately completed canary reads are CONTROL-ONLY, not hidden in
  the zero request-read claim. Restore the fence and erase the owned process cache.

Initial proof passed204 checks. Feature Light then replaced a constant receipt
zero with directly measured dispatch/cache fields; no guard or behavior changed.
A fresh final208-check proof passed after that evidence-only correction. No native
feature transaction, admission change or physical atomicity claim follows.

## Cleanup, preservation and limits

Final owned tables `wp_alynt_bootstrap_probe_d120ffe518e0_options` and
`wp_alynt_bootstrap_probe_f79819f5a004_options` each had four previewed rows and
matching private ownership marker; both were dropped and independently absent.
Initial proof's two tables also removed. All77 preexisting table inventories and
EXTENDED checksums match after each proof. No actual users/sessions/options/cron
were changed; no disposable users, listeners, CGI configs or services were needed.
Workers exited normally and closed owned connections; no artifact/retained table.

Thirteen core/config file hashes plus two owner-source hashes match before/after.
Core hashes include wp-settings `2e3185ee44bcdd9d9c4bef8ce4891827711dec2b5d5d4c08c951738ccef51f8d`,
wpdb `e15403e90032dd0508811301505e491d4212de5152581a98f51b744a1a3903bd`,
option `fe3f0b7e115b2a50c31162ed6428abe4088c080b0f71b9678d84806168fe90d0`.
Source audit distinguishes raw core session-metadata/charset initialization
(`SELECT @@SESSION.sql_mode`, SET NAMES/SESSION) and connection probes from
application-data `wpdb::query`/`_do_query` dispatch. No claim that the query filter
observes every server statement, arbitrary hostile PHP, raw mysqli bypass or every
supported core version. No telemetry/drop-in/server logging was installed.

Bundled MySQL read-only preflight preceded PHP DB work. Novamira HQ inventory
finds the exact profile; doctor reports expired OAuth and discovery provider_error.
No reauthorization/connection change. Existing diagnostic plugins/browser/debug
logs are outside this isolated bootstrap; no Plugin Check, UI, activation or full
component acceptance is claimed. Native failure/timeout cleanup and hard HTTP
resource-profile qualification are not proved by these normally exiting workers.

Pristine source OptionB snapshot completed BEFORE any edit:
`alynt-drime-backups-uploader-20261024-125512.zip` and matching `.txt` in toolkit
snapshots/uploader;12411files,clean excluded uploader head;SHA256
`0D7999D4D4ED331D647FEBBD5EF7E51A72D22AA3F0C1169A065ED2BA2D8085B3`.
No restore apply, checkpoint, database backup or application rollback.

## Source-scoped review / verification

Canonical Feature Light: five executable/test files plus this record and two plan
entries;DB/File Ops/Other, no UI/public runtime change. One evidence-reporting
issue fixed as above. No new architecture/refactor or Bloat/Structure run justified:
three small cohesive proof files reuse genuine core;no generic policy framework.
No UI/UX workflow. Source-scoped review is not historical whole-plugin readiness.

Final canonical Feature Security after correction/targeted validation reviews
fixed CLI target/runtime/identifiers, no actual-data read permission in worker,
owned-only negative control reads, marker+preview cleanup, no secrets in receipts,
unchanged literal unavailable owner and production load graph. No remaining
confirmed scoped security defect;no security regression handoff. PHP policy tests
and actual native canary controls protect distinct layers;Node receipt tests alone
do not prove WordPress. No formal Database/DS3/FULL_PRE_RELEASE completion claimed.

Executed isolated PHP149tests/1890assertions,no failures/skips;focused Node3/3.
Final full Node/build/lint, dashboard preservation and exact-head CI evidence are
recorded in toolkit closeout. No local full PHP suite or minimum-WP runtime matrix
claim;full PHP7.4/8.3 CI remains the publication gate. Original owner10s/20s and
HTTP1000/2000/4000/10000ms budgets unchanged;worker10s cap is not HTTP certification.

Implementation-plan progress:R2 bootstrap feasibility advances with a measured
architecture blocker, not authenticated route readiness. Stop operational work.
Recommended next gated slice: **design-only V2.3 Pre-Owner Bootstrap Protected-Read
Contract and Isolated Authentication Profile Design (R2)**, deciding whether a
genuine supported route can satisfy early containment without weakening it.
Keep storage writes, native experiments, admission, wiring, grants, activation and
rollback disabled during that design. R3-R7 remain independently gated.
