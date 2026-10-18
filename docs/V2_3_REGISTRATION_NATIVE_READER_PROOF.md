# V2.3 Fixed Registration Native Reader and Guarded Construction Integration

Approved 2026-10-18, R3 **Plugin Tester local-only**, owned disposable fixtures and
cleanup; public endpoints, application writes and execution stay disabled. Target
`plugin-tester`, `plugin-tester.local`,
`C:\Users\Captain\Local Sites\plugin-tester\app\public`. Versions remain
0.5.25 / 0.1.77; no release candidate, package, tag, publication or deployment.

Native integration delta excluded uploader `010e15d420735c73839aeaf14a50c5b7b8332a20`,
dashboard `24c19d88c018f3d296fa1d95922aafb5ab6b26c0`, toolkit
`3aded79d17345d6bf35fbdb30e878c09d7d121a0`. Broader focused trust-boundary review
uses uploader `20f78f94b795dc6537d1c5bb464b29da27d78dcd` for constructor/eligibility
and this integration, not merely the latest documentation/verification commit.
The [previous captured-frame proof](V2_3_REGISTRATION_CONSTRUCTION_OWNED_PROOF.md)
remains historical evidence with its own explicit limitations.

## Implemented boundary

New `includes/class-registration-native-reader.php` (232 lines), no existing runtime
source changed. Reuses the unchanged fixed genuine-core guard, native session/table
policy, SQL-bounded ten-row projection, complete schema-eight/schema-two eligibility
and private UUID/nonce constructor. No general query/provider/clock/actor/endpoint
argument or caller-supplied frame. Only zero-argument `create`, non-authorizing
disposition and privacy methods are public; no public operation method or wiring.

Private `read_registration(method, body)` is invoked only by proof reflection:

1. Consume a once-attempted lifetime, strict POST/small issuance grammar, pin core
   installation and original physical auth session. Native connection metadata
   SELECTs are allowed before authorization; protected option payloads are not.
2. Stack-local `pre_wp_load_alloptions` bypass prevents bulk autoload fetching while
   genuine `WP_Roles` falls back to the exact fresh role-option query. A separate
   query fence permits only that fixed role SQL on the pinned options table. This
   is **not** a fabricated role/capability map or replacement authentication port.
   Missing/unsupported short-circuit/query-shape changes refuse before bulk SQL.
   Both filters and the prior error handler are restored even on throws/refusals.
3. Fresh original cookie/session/local capability/issuance nonce guards, same actor,
   cookie/core frame and physical session, original monotonic acceptance budget.
   Then exclusively own a separate server-read-only MariaDB session and verify the
   fixed InnoDB/full unique option-name profile. Borrowed auth is never configured,
   repaired, reconnected, committed or closed by this owner.
4. Two callback-free fixed-order sweeps, twenty protected projections maximum,
   SQL prefetch caps, exact binary names/row IDs/autoload/length/raw bytes and session
   identity throughout. Compare complete frames; renew original authorization.
5. Close the owned reader before invoking the existing private constructor. Full
   eligibility and retained-history/genesis/ID/capacity checks still apply; no lazy
   validator installation. Original actor/physical session and ten-second budget
   are checked again before returning a PRIVATE handle-free blueprint.

Every attempted capture consumes the owner; no retry/remint or nested native
attempt during construction. Exceptions refuse with no public/private payload log.
The ten-second acceptance budget can be exceeded by blocking IO; it is **not** a
hard timeout. Existing connect/read timeout settings remain unchanged. This narrow
loopback MariaDB 10.6/mysqlnd/64-bit/utf8mb4 profile is not a plugin-wide new floor;
no broad compatibility, hostile same-process PHP isolation or provenance seal claim.

## Confirmed defects and fixes (unreleased scope)

- **F1 — Medium, physical-session adoption:** initial implementation acquired the
  borrowed physical identity after nonce callbacks. Genuine `wpdb->db_connect`
  during the initial nonce produced a blueprint with 20 reads instead of refusing
  before reads (RED). Snapshot/pin native metadata before callbacks and compare
  after guards (GREEN). No shared native/core trait changed.
- **F2 — Medium, protected-read ordering/privacy:** the native read counter alone
  missed WordPress role lookup bulk-loading a protected option marked autoload=yes
  before admission. A genuine fixture subscriber and actual bulk-SQL observer
  reproduced it (RED). Role-only fallback plus independent query refusal fixes
  this NEW private lane (GREEN), without changing the shared guard or older owner.
  Deliberately removing the short-circuit independently exercises fail-closed
  aggregate-query refusal. Restoration is asserted for every helper invocation.

The F2 guarantee starts at this private invocation. It is not a claim that ordinary
WordPress bootstrap never loads autoloaded options, or that older API-double proofs
established this new bulk-read property. Existing public surfaces stay dormant and
unavailable; their prior recorded proof scope is not silently expanded.

One harness expectation was corrected: deliberately deleting/reinserting a fixture
option creates a new option_id. Its exact expected baseline is recaptured after
that intentional owned mutation, not falsely compared against the old identity.
No assertion was removed to accept a product defect or fabricate a success path.

## Genuine proof, controls and cleanup

Current LocalWP metadata resolved MariaDB 10.6.23/port 10004, PHP 8.5.1 and web port
10005. Initial bundled mysql read verified exact local origin/database, 77 tables
and zero stale fixtures. Novamira HQ guide/complete inventory found the exact
domain; doctor reported expired authorization. Normal LocalWP workflow used, no
reconnect, credential or permission change. Restore Option A/check confirmed clean
synced source at the baseline and existing September 20 external archive; no new
backup, restore or checkpoint branch.

Existing supervisor/audit has an explicit `registration-native-reader` mode, three
new narrow proof helpers, and CI syntax checks. Full genuine WordPress **7.1.2**
boots against only fresh marker-owned options/users/usermeta tables, isolated
salts/users/roles/sessions, no actual wp-config/plugins/themes/cron/external HTTP.
Synthetic retained safety/genesis data is explicitly not genuine prior admission.

```powershell
./scripts/tests/fixed-core-proof-audit.ps1 -Proof registration-native-reader
```

Final **700 assertions / 11 case groups**, worker exit 0, empty stderr. Covers:

- Positive twenty-read native capture -> complete eligibility -> ten core UUID roles
  and full command nonce, direct digest equality with independently captured rows,
  borrowed physical session unchanged and no exported handle/support flag.
- Genuine separate thread/server-read-only profile; a proof-only owned no-op UPDATE
  is refused by MariaDB **1792**, no data changed. Reader is closed before nonce
  creation, and no native UPDATE/transaction exists in the implementation.
- Every missing role fails at its exact read; all autoload variants, compiled
  connection/ledger/queue prefetch caps, binary name mismatch, malformed full state,
  grant/genesis/settings/codec refusals, exact fixture reconciliation.
- Wrong nonce/method/form/domain, subscriber and warmed-cache role/user/capability/
  session losses: **zero protected reads**. Actual bulk autoload observer also
  establishes no hidden payload fetch during this lane's authorization.
- Physical handle swap and borrowed ambient transaction: refuse without repair.
  Post-capture session/capability/actor/cookie/method/table/blog loss, exceptions,
  reentry and original-budget expiry refuse with reader closed/lifetime consumed.
- Real session loss during command nonce creation after reader close refuses.
  Exact hook restoration includes failure paths, not only successful requests.
- Deliberate owned dependency change AFTER both sweeps proves the returned digest
  describes captured bytes, not current storage; all authority flags remain false.

Three temporary controls were assertion-killed and restored: initial auth removed
(invalid nonce causes **20 unauthorized protected reads**, not just a hook-count
failure); always-unavailable final result (positive fails); both authorization
option fences removed (genuine subscriber bulk-payload regression fails). F1/F2 also
have permanent actual-core RED/GREEN scripted coverage. No artificial core success
port, weakened fail-closed validator or retained source mutation.

Rerun regressions: captured constructor proof **851 / 10**, core guard **266 / 11**,
both passed. Every successful and intentional-failure run verified all **77
application-table checksums unchanged**, actual options/users/usermeta byte digests
unchanged, exact cleanup row-count previews and all three owned tables absent.
Disposable fixtures removed; existing application users/options/cron untouched.

## Source-scoped canonical review checkpoint

FEATURE_LIGHT_REVIEW_PROMPT.md completed: nine executable/config files plus direct
constructor/guard/native-policy callers reviewed. DB and Other (authentication,
private lifetime) risk, no UI. Cohesive 232-line owner reuses existing traits and
supervisor, no meaningful bloat trigger; separate bloat workflow unnecessary.
One harness identity-baseline correction above; F1/F2 reviewed under security.
Zero outstanding manual decisions; no UI/UX workflow for backend-only changes.

Final FEATURE_SECURITY_REVIEW_PROMPT.md completed after fixes, restored controls
and validation. F1/F2 fixed with permanent scripted genuine-core/native regressions;
no outstanding issue or missing-coverage 07A handoff for this source scope. Reviewed
fixed target/metadata and SELECT surface, original session/capability/nonce ordering,
protected/autoload query fence, exact binary/type/byte caps, no caller data/provider,
owned lifetime/filter restoration, no credential/cookie/nonce/raw-row logs, no public
handle or production require. Genuine integration runs locally; CI syntax checks
do not claim to execute Plugin Tester SQL/auth proof. Shared guards unchanged.

Not a formal DS3/Database/07A or whole-plugin release/security refresh. No physical
transaction, locks/CAS/commit, durable registration/receipt, cancellation/rollback,
schema migration, dropped-ack recovery, deterministic concurrent-sweep/ABA test or
HTTP/browser endpoint claim. Two equal sweeps are NOT an atomic snapshot or fresh
authority at nonce creation; later native admission must lock and revalidate all
dependencies. FULL_PRE_RELEASE_WORKFLOW_PROMPT.md remains required for a coherent RC.

## Validation and publication

New boundary tests: **3 / 18**, initial missing-source RED; combined construction
tests **13 / 279**. Final affected-boundary PHPUnit **253 / 2954**, no skips, same
filter as the previous proof plus RegistrationNativeReaderBoundaryTest. Uploader
full WPCS **201 files** passed, existing dependency-config deprecation only.
Build/deterministic runner/runner syntax/dev-tools (**3**) and npm moderate audit,
Composer strict validation/audit pass; generated files unchanged, no advisories.
Three new proof scripts pass syntax; changed PHP 7.4 syntax, no dependency change.

Dashboard unchanged-runtime PHPUnit **299 / 1423**, two existing local Sodium skips;
WPCS **138 files**, structure/build and Composer/npm audits pass. Final exact-head
Linux CI runs the full suites and new proof syntax checks; results verified at
handoff, not inferred from earlier runs. Toolkit checklist addendum is source-scoped;
historical whole-plugin rows/version evidence untouched. No full Windows runner
suite rerun for this bounded change; full repository suites are verified in CI.

## Progress and next gated slice

Implementation-plan progress: genuine, bounded protected capture is now connected
to the dormant constructor with two observed integration gaps fixed. This advances
the native registration lane, not maintenance churn or write authority.

Recommended next gated slice: **local-only V2.3 Closed Registration Native Admission
and Locked Revalidation Design**, design-only. Define the trusted same-stack command
handoff, fresh original authorization under the native acceptance budget, all-ten
locked revalidation, ledger-only conditional transition/receipt and unknown-outcome
rules. The demonstrated captured/current gap must not be promoted into permission
to commit. Keep storage writes, public endpoints, production wiring, grants,
activation/effects/cancellation/rollback and deployment disabled during that design.
