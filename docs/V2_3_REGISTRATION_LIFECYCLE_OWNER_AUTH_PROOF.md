# V2.3 Guarded Lifecycle Owner Authentication and Cleanup Proof

2026-10-19. Approved R3 **Plugin Tester local-only**, resolved target and owned
disposable users/storage/testing/cleanup confirmed. Native admission/observer
transactions, production wiring, grants, activation and rollback remain disabled.
Versions stay uploader0.5.25/dashboard0.1.77. No package/release/tag/deploy.

## Ownership, source boundary and safety

Uploader owns the proof scripts and this record. Dashboard owns the lifecycle
design and implementation-plan cross-reference. Excluded coherent review bases:
uploader `1259bbdec189992fcd625524ac8ae11a2bad9807`, dashboard
`1c26c5892615a738d053e1a27079eee0c5fc106c`; these cover the completed fixed-owner
foundation **and** its new genuine proof, not merely documentation-tail commits.
Current task began on uploader95c9384/dashboard875bab0/toolkitda4b538, clean/synced.

Option B source-only ZIP plus matching `.txt` (not a site/database backup):
`C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/alynt-drime-backups-uploader-20261019-211031.zip`,
10,734 files, SHA256
`33EE5F128A98B5B8BFED48F1ED22D7CEB11F6947246EB02624912AC1D71B65FF`.
No stash/checkpoint branch or source rollback applied.

Confirmed `plugin-tester`, LocalWP “Plugin Tester”, `http://plugin-tester.local`,
`C:/Users/Captain/Local Sites/plugin-tester/app/public`, current DB10004/web10005.
Metadata XPa4sCxmk, MariaDB10.6.23, PHP8.5.1, genuine WordPress7.1.2. Novamira HQ
inventory found the exact profile; doctor reported expired authorization, so no
reconnection/credential change attempted. Bundled LocalWP mysql.exe establishes
the initial origin/table/fixture baseline before any PHP bootstrap.

Earlier sandbox-only socket checks incorrectly suggested stopped services; the
approved execution context verified both listeners. This was an environment
visibility limitation, not evidence of WordPress failure. The first SQL command
used an incorrect win64 client path and did not run; discovery resolved the
installed MariaDB win32 client before the successful baseline/proof.

## Implemented proof and actual runtime evidence

The explicit CLI mode `registration-lifecycle-owner` reuses the existing bounded
supervisor/audit. New cases, denials and fixtures exercise the actual fixed
`Registration_Lifecycle_Request_Owner::prepare_admission` by private reflection.
No owner/source/public entry point or success provider was added. CI syntax-checks
the new proof files on PHP7.4/8.3 but does **not** run WordPress or create fixtures.

Every run creates three fresh marker-owned options/users/usermeta tables, three
disposable users and owned sessions/roles. Full unmodified core loads without the
application wp-config, plugins, themes or existing user/session state. All guards
use actual core cookie/session validation, capability refresh and nonce APIs;
reflection verifies their source is under WordPress core. Synthetic closed
schema8/ledger2 bytes are seeded/captured by the harness before invocation, not
claimed to be authentic admission history or an authorizing native capture.

Final genuine proof: **4564 checks / 7 groups**, worker exit0/stderr empty:

| Group | Contract protected |
|---|---|
| Positive original preparation | Exactly six nonce verifications, one command nonce creation; original actor/context, ten distinct fresh UUID-shaped identities, same retained origin/IDs/clock before the last two guards, then erasure. |
| Independent nonce denial | Each of six genuine verifications independently rejects; consumed owner cannot retry. |
| Initial request/auth denial | Malformed/extra/read intent, wrong nonce domain, stale session, different administrator, subscriber, forged/expired cookie reject before command nonce creation. |
| Complete captured DATA | Each of ten malformed row tuples rejects after initial authorization, before command nonce creation. |
| Warm-cache revocation | Raw owned session, capability, role and user revocation defeats stale native caches. |
| Late retained-stage denial | Both final guards independently face authority/actor/cookie/method/table/blog loss, original-budget reset/expiry/regression, altered origin/IDs, reentry, exception and warning; exact cleanup required. |
| Privacy and recovery | No public continuation/native methods, retained owner cannot serialize, debug output stays closed; final positive preparation recovers. |

Verifier order is issuance/issuance/issuance/command/issuance/command, not a
single nonce reused as authorization. Origin/IDs/clock are absent at the first
four and present at the final two. Guard fences are checked at each genuine
verifier; protected option API/query traps record zero attempted calls. One
original monotonic start is preserved. Fault clock changes are explicit reflection
instrumentation, not actual ten-second stalls. Unlike the API doubles, genuine
CSPRNG calls are not intercepted: ten retained identities and one nonce creation
are observed, not an asserted total RNG invocation count.

The run checks no unplanned WordPress writes, unchanged ten protected fixture
rows, consumed/private-state clearing, and exact owned hook/role/error-handler
restoration on every return. Light review strengthened fault completion assertions
outside the caught owner stack, so failed fixture instrumentation cannot count as
the intended refusal. Late role revocation uses a separate marker-checked owned
connection while leaving the owner's role-only option fence intact. No core
verifier result is fabricated and no guard is weakened to manufacture a positive.

Independent bundled-mysql audit on **every** positive/control/predecessor run:
all **77 application-table checksums unchanged**, no owned fixture tables remain.
Supervisor separately verifies actual options/users/usermeta unchanged. Final
cleanup previews exactly9 usermeta/3 users/41 options rows, drops only fresh owned
tables and verifies absence. These disposable fixtures were intentionally removed;
they can be recreated by rerunning the approved harness. Application data untouched.
Transaction-free predecessors pass: genuine core guard **266/11**, private
registration construction **851/10**. No native admission/barrier proof rerun.

## Falsifiability and source-scoped reviews

Eight individual semantic controls are assertion-killed, then SHA256-exactly
restored: always unavailable; omit pre-retention command guard; omit final issuance
guard; omit final command guard; omit original provenance equality; omit complete
identity equality; omit final origin erase; omit original clock guard. First four
fail positive/order assertions, equality/clock omissions fail actual negative
cases, erase omission leaves origin alive. No bootstrap/syntax failure counted.
The wrapper's structured `proof_exit_code`, worker assertion and independent audit
are checked, not merely the outer PowerShell exit. An initial ambiguous restore
hunk was caught by the hash gate and corrected; controls were rerun with unique
context, followed by the pristine final proof and related suite.

Restored owner SHA256:
`FCFF2FB71766AE80EF631447367D2C95CD066A80F2AB1177F0ECAFF1A774FD95`.
All runtime includes/production loaders are unchanged against this task's start.

Canonical DS2 source-scoped review sequence:

1. **FEATURE_LIGHT_REVIEW_PROMPT.md**: 12 changed PHP across coherent foundation/
   proof boundary, audit PS/workflow and direct guard/fixture/cleanup dependencies.
   Risk: owned DB/auth/files, no UI/REST/AJAX/cron/external API. Harness fault
   completion and fenced late-role control strengthened; CI syntax coverage added.
   No remaining manual decision or runtime defect; genuine proof stays CLI-only.
2. **FEATURE_BLOAT_AND_STRUCTURE_REVIEW_PROMPT.md**, both phases, explicit1259bbd
   helper before/after: 12PHP, zero oversized, all Consolidate/retain. TOTAL
   owner219/handoff184/packet60/supervisor61/worker86/cases56/denials46/fixtures79/
   cleanup63/API-denials80/API-flow29/API-fixture31. CODE=1 is the known helper
   anomaly, not a size/reduction claim. Direct/string/require/hook/filter/REST/
   AJAX/cron references checked; no unused production entry or forced split.
   No additional Phase2 edits or pre-release02 deferral justified.
3. **FEATURE_SECURITY_REVIEW_PROMPT.md**, final after fixes/restoration/validation:
   exact target/owned markers, allowlisted modes/tables and prepared values,
   no application credentials/config imported, genuine authorization before
   candidate validation, independent nonce domains, original provenance/clock,
   private consumption/erasure and redacted receipt checked. No confirmed
   outstanding security defect/regression handoff. No whole-plugin audit claimed.

Risk/test-layer review distinguishes the strong isolated API tests (13/1072)
from the seven genuine owned-core groups. They overlap intentionally at different
layers; no tests removed, expectations weakened, skips added or coverage target
invented. DS3 07/07A were consulted for this assessment, not recorded as completed
canonical whole-plugin workflows. UI/UX and Database Review were not run. Full
pre-release remains required for a coherent RC before packaging/publishing.

## Verification limits and next gate

Executed commands include `& ./scripts/tests/fixed-core-proof-audit.ps1 -Proof
registration-lifecycle-owner` (confirmed local target and private MYSQL_PWD source),
the same audit with `guard`/`registration-construction`, `php vendor/bin/phpunit
--do-not-cache-result --filter RegistrationLifecycleOwner`, the related381 filter,
`php vendor/bin/phpunit --do-not-cache-result`, `php vendor/bin/phpcs`, explicit
PHP syntax/PowerShell parser checks, `npm run lint:runner`, `npm run build`,
`npm run verify:runner` and `npm run test:dev-tools`. Dashboard:
`php vendor/bin/phpunit --do-not-cache-result` and `npm run check:structure`.

Related isolated suite **381/10690**, no skips; WPCS226, proof PHP/PowerShell syntax,
runner lint/build/deterministic verification and dev-tools3 pass; generated outputs
unchanged. Dashboard local299/1423, two existing Sodium skips; structure passes.
Full-suite and exact-pushed-head CI results are recorded in the toolkit scoped
publication entry after retrieval, not inferred from predecessor runs.

This proves genuine local authentication/cleanup of fixed preparation only, not
native frame capture, actual admission origin before DML, writer/observer locks,
release acknowledgement, actual uncertain COMMIT recovery, durable ingress closure,
safe retry, dispatch or production readiness. Private true is preparation
consistency; false is not proof of no commit in a future joined native lifecycle.
No native transaction was executed by this proof; fixture seeding/revocation and
cleanup are not zero-DML or physical-atomicity evidence.

Implementation-plan progress: the fixed retained lifecycle owner now has genuine
owned authentication and cleanup evidence. The meaningful next item is gated
native joining, not further generic safe-local maintenance.

**Recommended next gated slice: local-only V2.3 Fixed Native Admission Join and
Lifecycle Outcome Integration Design**, design-only. Map reuse of the existing
native admission transaction inside this exact root without a second constructor,
before-DML origin retention, sent/commit/uncertain/release provenance and mandatory
erase/transfer ordering. Existing standalone `admit()` constructs its own proposal
and returns a boolean that may follow commit; calling it as-is cannot establish
same-owner provenance. No storage writes/native execution/fixtures, production
wiring, grants, activation or rollback in that design slice. Fresh approval required.
