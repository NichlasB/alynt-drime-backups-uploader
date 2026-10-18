# V2.3 Closed Registration Construction and Eligibility Owned Proof

Approved 2026-10-18, R3 **Plugin Tester local-only**, including disposable fixture
users/storage creation and cleanup. Target `plugin-tester`, `plugin-tester.local`,
`C:\Users\Captain\Local Sites\plugin-tester\app\public`. Existing application
state, production wiring and execution remain untouched. Versions 0.5.25 / 0.1.77.

Explicit excluded review bases: uploader `5ac87ad27764bdd8d9a7001ca366892406277402`,
dashboard `76d45ff8b476a195bf2dd57d6fab65579de2773f`, toolkit
`7d4da8e1852075bb7ccee710f02a46628d0ec93d`. For the coherent constructor plus proof
boundary, review uploader from `20f78f94b795dc6537d1c5bb464b29da27d78dcd`; the
[foundation record](V2_3_REGISTRATION_CONSTRUCTION_FOUNDATION.md) owns that source.
This checkpoint does not silently narrow review to a verification/docs commit.

## Implementation and operational scope

Three new CLI proof files: `scripts/tests/registration-proof-{fixtures,cases,denials}.php`.
Existing fixed-core supervisor, worker and independent PowerShell audit add one
explicit `registration-construction` mode. Quality CI syntax-checks the new files,
but **does not run this site/database proof**. No runtime include, bootstrap, shared
guard/model/codec, dependency, version, generated asset or public API changed.

Novamira HQ guide and complete inventory confirmed the exact connected domain;
doctor found expired OAuth authorization. No reconnect/credential change attempted.
Normal LocalWP access used current `sites.json`: MariaDB 10.6.23, port 10004,
web port 10005, matching bundled mysql client/PHP 8.5.1. Required initial mysql
read verified `local`, exact origin, 77 application tables and zero old fixtures.
Source restore workflow Option A/check confirmed clean synced master at the base,
and the existing external September 20 archive; no backup/restore/branch created.

The unchanged supervisor creates three unique marker-owned tables, using the
existing options/users/usermeta shapes. Full native WordPress **7.1.2** loads against
only those tables with random process-local salts, fixture users/roles/sessions,
plugins/themes disabled, cron/external HTTP/update execution blocked. No actual
wp-config, user session or application option is imported. User/session mutation
controls operate only on disposable fixture tables, never actual users.

Test setup builds explicitly synthetic valid retained schema-eight and schema-two
genesis bytes and seeds ten owned rows. A proof-only prepared SQL capture obtains
their exact IDs/names/autoload/octet counts/values. Reflection passes already
captured data to the existing PRIVATE builder. This is **not a new authorizing
reader**, production entry point, callback port or assertion of genuine prior
admission. The genuine core boundary and synthetic historical data are distinct.

During construction/public calls, WordPress protected-option traps cover all ten
roles, SQL guards refuse protected and non-owned access, and writes remain fenced.
The two early session-loss hooks deliberately perform exactly one owned session
deletion each, counted separately from constructor writes. Other fixture updates
restore only their own captured test state. No read/write-capable handle is exported.

## Executed genuine proof and non-vacuity

Command (MYSQL_PWD supplied privately from the approved LocalWP default source):

```powershell
./scripts/tests/fixed-core-proof-audit.ps1 -Proof registration-construction
```

Final **851 assertions / 10 case groups**, passed, worker exit 0, empty stderr:

- Genuine core source provenance for nonce/UUID/random/auth APIs; positive PRIVATE
  blueprint, ten unique UUIDv4 roles disjoint from retained IDs, actual fixture
  actor/server site, original 300-second window and three original authorization
  guards surrounding nonce creation. Always-unavailable cannot satisfy this path.
- Genuine issue/command/reservation/receipt/blog nonce domains pairwise disjoint;
  every variable typed field invalidates the command nonce. Literal protocol fields
  use separate typed-purpose domains, not malformed forms as false positives.
- Every one of ten actually stored owned autoload changes alters the direct
  dependency digest, independent of random UUIDs or clock rollover. Unchanged
  original tuples reconcile exactly; construction never stores a successor.
- Public issue/consume remain exactly unavailable and never reach the private
  constructor or export IDs/nonces/blueprints/handles. Serialization refused,
  debug metadata redacted and no browser headers emitted.
- Malformed methods/forms rejected before core reads; real subscriber/same-user
  different-session and warmed-cache role/user/capability/session revocation deny.
- Session loss during first and renewed issuance prevents command nonce creation.
  Session/capability/actor/cookie/method loss inside genuine command nonce creation
  refuses a blueprint; throws/reentry consume the owner without retry.
- Invalid full grant/retained chain/schema/settings/connection/genesis/generation/
  scope refuse. Separately built **valid** pending schema-eight and schema-two
  controls refuse; failures are not merely malformed-shape tests.
- Stale original typed deadline refuses before auth reads despite a still-valid
  genuine nonce. All ten owned protected tuples are unchanged after denials.

Three temporary source controls were ASSERTION-killed and restored: private builder
always false (positive fails); full schema-eight validator removed (bad grant
candidate admitted); final post-creation guard/same-request removed (revoked session
exports a blueprint). The last control was rerun with behavioral cases before the
event-order assertion: the first event-count-only kill is **not** the claimed
behavioral evidence. Runtime `includes/` diff is empty after restoration. Later
valid-pending/early-loss coverage strengthens the final 851-check proof; not all
controls were rerun after those additions. Existing API-double initial-guard/PRNG
controls are distinct evidence, not a genuine-core UUID instrumentation claim.

The unchanged `-Proof guard` was also rerun: **266 / 11**, including real nonce
expiry, forged/expired cookies, actor/session mismatch and warm-cache revocation.
Every successful and intentional-failure proof run independently verified all
**77 application-table checksums unchanged**, actual options/users/usermeta byte
digests unchanged, exact owned cleanup row-count previews and all three fixture
tables absent. Fixtures are disposable and were removed; application data was not.

## Canonical source-scoped reviews and proof limits

FEATURE_LIGHT_REVIEW_PROMPT.md completed on seven changed executable/config files
and directly reused constructor/guard/fixture/supervisor call sites. Risk: Other
(genuine authentication/intent), DB/File Ops (owned CLI fixture lifecycle only).
Three test-harness construction mistakes fixed during development: expiry-field
mutation exceeded the grammar's 300-second cap; pending fixture read a nonexistent
safety root UUID instead of ledger scope; deliberate early session deletion was
initially counted as a constructor write. Corrected controls retain strict
assertions and exact expected owned mutation count. No product defect or manual
decision. Failure/cleanup receipts stayed explicit throughout. Seven-file change
is cohesive, reuses existing supervisor and native fixture helpers; no meaningful
bloat trigger, separate bloat workflow unnecessary. UI/UX N/A backend/CLI only.

Final FEATURE_SECURITY_REVIEW_PROMPT.md after fixes/validation/restoration: no
outstanding finding in this source scope. Reviewed exact local target/metadata,
marker/unique table ownership, prepared SQL values/allowlisted identifiers, private
salts and no credentials/nonces/cookies/raw rows in receipts, core hooks removed in
finally, bounded worker/deadline/cleanup, no public success or wiring, all-ten SQL
traps and proof-layer distinctions. No confirmed missing-coverage 07A handoff.
Toolkit addendum is source-scoped; no historical whole-plugin row refreshed.

NOT a Database Review, physical transaction/atomicity/lock/CAS/commit/receipt proof,
authoritative protected-read-before-auth integration, browser/HTTP endpoint proof,
pairing proof or complete PHP/WP compatibility certification. Capture occurs in
test setup; hashes and synthetic historical labels are not authenticated provenance.
No native acceptance-budget or dropped-ack recovery claim. Full pre-release remains
required for a coherent RC before packaging/publishing; no package/release/deploy.

## Repository validation and publication

Targeted uploader PHPUnit: **250 tests / 2936 assertions**, no skips, using the
foundation record's same Registration/ClosedRegistration/FixedCore/scan/native
boundary filter, no skips. Uploader `npm run lint`: **200 files**, clean (existing
dependency-config functionWhitelist deprecation banner only). `npm run build`,
`verify:runner`, `lint:runner`, `test:dev-tools` (**3** tests), moderate npm audit,
Composer strict validation/audit: pass, no advisories or generated changes. All
five changed/new PHP proof scripts pass syntax; PowerShell AST parse has no errors.

Dashboard full local PHPUnit: **299 / 1423**, two existing Sodium-unavailable
signing skips; lint **138 files**, structure/build, Composer strict validation/audit
and moderate npm audit pass. Exact dashboard head
`24c19d88c018f3d296fa1d95922aafb5ab6b26c0`:
[CI 39165073375](https://github.com/NichlasB/alynt-drime-backups-dashboard/actions/runs/39165073375)
success, PHP 7.4/8.3 each **299 / 1430**, no skips.

Full uploader suite and final exact pushed-head CI are verified at handoff. The
genuine proof above runs locally, not in GitHub Actions. No normal tests weakened,
skipped or deleted. New proof script syntax runs in both CI PHP versions; source
remains PHP 7.4 compatible. Toolkit `3aded79d17345d6bf35fbdb30e878c09d7d121a0`
is pushed; GitHub reports zero workflows/check-runs, CI N/A rather than green.

## Implementation-plan progress and next approval boundary

Implementation-plan progress: the existing dormant constructor now has genuine
core auth/nonce/UUID and isolated stored-frame evidence; no maintenance-only work
substituted for the plan. Public construction still lacks a fixed authoritative
reader, so the next product step crosses a new protected-read integration gate.

Recommended next gated slice: **Plugin Tester local-only V2.3 Fixed Registration
Native Reader and Guarded Construction Integration**, dormant/private, fixed
all-ten read-only capture after fresh authorization, renewed full validation and
construction, with owned disposable fixtures/cleanup on this same exact target.
Keep public endpoints unavailable, existing application state, production wiring,
grants/activation, native ledger writes, reservation/apply/effects/cancellation and
rollback untouched. Approve that new protected-read/auth integration, not a release.
