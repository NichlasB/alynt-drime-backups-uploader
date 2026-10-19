# V2.3 Closed Registration Admission Plan and One-Shot Handoff Safety Foundation

2026-10-19; approved R3 **repository-only, dormant, in-memory tests only**.
Excluded Git review bases: uploader `81fc2617d466b4c31db26021223f30727de1325d`,
dashboard `e26d282b4d2929322348474f0138986da59aa575`, toolkit
`fff4b05a82ec594fd4aacd9f01f702432c1cf719`. Review the complete five-file executable
delta plus direct dependencies, not a later plan/verification commit. Versions
remain uploader 0.5.25/dashboard 0.1.77, not a release candidate.

Companion dashboard owns
`docs/V2_3_CLOSED_REGISTRATION_NATIVE_ADMISSION_DESIGN.md`. This implements only
its candidate plan/equality/lifetime layer, **not the proposed native coordinator**.

## Scope and executed plan

Read source/design and implementation plans; Option A/check source protection;
positive RED before implementation; bounded immutable candidate/private handoff;
in-memory adverse cases and six restored assertion-killed controls; canonical
source-scoped Feature Light then final Feature Security; targeted QA, commit/push
and exact-head CI. No site/LocalWP/Novamira/browser/SQL, actual authentication,
users/options/cron/fixtures, schema changes, native handle/transaction/storage
write, public endpoint, production loading, grants/activation/effects/cancellation/
rollback, package/release/tag/deploy. A LocalWP target/Novamira check is N/A because
no site is accessed. Prior genuine WordPress/storage evidence remains historical.

## Implementation and ownership

- `includes/class-registration-admission-plan.php`: final immutable DATA object,
  factory accepts an explicit synthetic frame/proposal/scope-actor/UTC. Reuses
  unchanged complete eligibility and schema-2 register/capacity/receipt codec.
  Requires exact actor/installation, full-frame dependency, complete schema-8
  closed chain/settings/all-ten eligibility, schema-2 genesis/revision/context,
  all role IDs including collision rejection against retained schema-8 history.
  Prepared ledger bytes/timestamp/inputs remain private; no output getter.
- Factory rejects object/resource/float/reference graphs before retention, with
  a shallow depth-four/256 key-value-node bound appropriate to the fixed tuple
  and proposal shapes. Existing manifest bounds payloads to 6291456 bytes, existing
  ledger/parser bounds remain unchanged. This private traversal cannot be replaced
  with the schema-8 helper's smaller 131072-byte total payload limit. No generic
  parser or new dependency introduced.
- `matches` performs only primitive unaliased full-frame/proposal/context equality
  and original preparation/deadline checks. No semantic parser, rebase, remint,
  successor regeneration, callback, clock or IO. This is not a locking operation
  or proof of native row provenance. All support flags remain false.
- `includes/class-registration-admission-handoff.php`: zero-argument/zero-IO
  creation, no public operation/provider/enablement/handle port. Private `stage`
  constructs its own candidate, never imports an externally prepared object.
  Attempt consumed before input checks; premature/repeated calls and initial
  refusal cannot reopen it. Private `revalidate` consumes before comparison and
  releases plan/clock before deciding, for positive and refusal alike.
- Original synthetic monotonic start must match; elapsed strictly less than ten
  seconds, current monotonic/UTC cannot go backwards, original mutation deadline
  unchanged. These are **data assertions**, not real timer/authenticity/IO timeout
  guarantees. No system clock, nonce or UUID function is called.
- Both objects use unchanged noncloneable/nonserializable/redacted privacy trait;
  no raw bytes/IDs/contexts in public disposition/debug output. Private visibility
  does not defend against hostile same-process reflection/PHP or privileged SQL.
- `tests/Support/RegistrationAdmissionFixture.php` builds only in-memory primitive
  frames using existing pure fixtures; no core request/auth shims are installed by
  it. Two new test classes load the new source explicitly. Bootstrap, existing
  reader/constructor/native owners/guards/parsers/codecs and generated assets are
  unchanged. No production require or caller was added.

The pure factory deliberately does not authenticate an actor or certify who built
the frame. A self-consistent synthetic candidate is not a transferable admission
token: the private handoff rejects object adoption, has no public continuation,
and cannot acquire a native handle. The later native coordinator must establish
genuine same-stack origin/authentication independently; it cannot merely accept
this object or the previous reader's returned array from a caller.

## Discriminating tests and restored controls

Initial targeted run: four failures on missing approved plan, before source added.
Final new suite: **9 tests / 346 assertions**, no skips. Positive asserts the exact
existing schema-2 registered successor, all ten reserved identities, ledger-only
revision increment, unchanged generation, prepared timestamp and real candidate
receipt fields; never a fabricated always-unavailable "success". Positive private
stage -> exact revalidation required, with every storage/dispatch/execution flag
false and no native continuation or state mutation.

Coverage includes all **ten roles x five tuple fields**, scope/actor/proposal/
generation/revision/context/purpose/time changes, malformed and aliased data,
opaque-object import, exact original-start/UTC/monotonic bounds, stale/premature/
duplicate/failed lifetimes, privacy and missing production loading. A recomputed
context hash with the wrong dependency must fail even when the pure ledger codec
accepts it. Likewise a schema-8 history identity collision cannot hide behind a
valid independent schema-2 successor.

Six distinct deliberate source counterfactuals each caused an assertion failure,
not a syntax error or mock-count mismatch; all restored before final QA:

| Removed/broken behavior | Assertion that killed it |
|---|---|
| Always return unavailable after valid preparation | Positive candidate must be an object with exact successor/receipt |
| Full-frame dependency binding | Self-consistent wrong dependency must refuse |
| Schema-8 retained identity collision guard | Pure schema-2-valid reused terminal ID must refuse |
| Exact full-frame equality | Changed safety option ID must fail revalidation |
| Consumed staging guard | A stale/refused lifetime must not stage again |
| Original monotonic start equality | A new start cannot reset an otherwise valid budget |

No incomplete control remains. No genuine guard-removal, WordPress authentication,
physical lock,
storage/transaction, dropped-acknowledgement or crash proof was run or claimed.

## Canonical source-scoped reviews

Actual toolkit runner and canonical `FEATURE_LIGHT_REVIEW_PROMPT.md` followed,
then `FEATURE_SECURITY_REVIEW_PROMPT.md` after fixes/controls/validation. Scope:
five new executable/test/helper files and directly reused eligibility, schema-2
contract/data/transitions, privacy and pure fixture callers. No production caller.

Feature scope: candidate plan and one-shot DATA lifetime. Files reviewed: **five
new files plus direct dependencies**. Risk areas: R3 future authorization/data
boundary; no current UI/AJAX/REST/DB/cron/file-operation/external-service path.
Issues found: **one mechanical WPCS layout issue**, auto-fixed by expanding the
keyed metadata array; focused rerun clean. Manual decisions: **zero within scope**.
No changed component exceeds 300 lines, no duplication/debug leftovers or meaningful
bloat trigger: two cohesive responsibilities (immutable plan vs consumed lifetime)
and small helper, not arbitrary splitting. Bloat/Structure workflow unnecessary;
UI/UX N/A backend-only, no user-facing strings or output contexts changed.

Final focused Security review: bounded exact primitive validation, alias rejection,
full dependency/actor/scope/identity binding, once-only refusal semantics, no new
auth/provider/storage surfaces, private/redacted data and no bootstrap require
verified. No confirmed security defect or missing permanent regression handoff.
No DS3/Database/07A/whole-plugin security/readiness completion claimed; source-scoped
toolkit addendum only, historic whole-plugin rows unchanged. Full pre-release remains
reserved for a coherent release candidate before packaging/publishing this work.

## Validation and publication

Executed repository-only on 2026-10-19:

- `php vendor/bin/phpunit --do-not-cache-result --filter RegistrationAdmission`:
  **9 / 346**, no skips, after restoring all controls.
- `php vendor/bin/phpunit --do-not-cache-result --filter
  'RegistrationAdmission|RegistrationNativeReaderBoundaryTest|RegistrationConstructionTest|RegistrationRequestContractTest|ClosedRegistration|FixedCoreRequest|ScanLedgerCheckpoint|ClosedScanEffectLedgerTest|ScanEffectManifestTest|ScanEffectFenceContractTest|ScanTargetCodecTest|ScanObservationContractTest|ScanNativeObserverBoundaryTest|RealClosedWriter|NativeClosedBeginBoundary|NativePairedBoundary'`:
  **262 / 3300**, no skips. Existing tests/assertions untouched.
- `npm run lint`: **203 PHP files**, passed; existing PHPCS/PHPCompatibility
  comma-array functionWhitelist deprecation remains, not introduced here.
- `npm run build`, `npm run verify:runner`, `npm run lint:runner` passed; generated
  runner/assets unchanged. `npm run test:dev-tools`: **3**, passed.
- `npm audit --audit-level=moderate`,
  `php ../alynt-drime-backups-dashboard/composer.phar validate --strict`,
  `php ../alynt-drime-backups-dashboard/composer.phar audit`: passed, no reported
  vulnerabilities/advisories. No dependencies installed/updated.
- Companion dashboard `php vendor/bin/phpunit --do-not-cache-result`: **299 / 1423**,
  two existing local Sodium signing skips, no test changes. `npm run lint` **138
  files**, `npm run check:structure`, `npm run build`,
  `php composer.phar validate --strict`, `php composer.phar audit` and
  `npm audit --audit-level=moderate`: passed, no generated changes or reported
  vulnerabilities/advisories. Exact-head full PHP 7.4/8.3 CI is required for both
  source and companion heads. The focused uploader cohort above is the local
  source regression evidence; full uploader suite is required on Linux CI.
- Source restore workflow **Option A/check** found clean/synced uploader at the
  excluded base and the existing external September 20 archive under
  `C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/`.
  No backup/archive/restore/checkpoint branch created; this is not site protection.

Final diff/whitespace, exact commit IDs, remote synchronization and CI run links
are verified at handoff without generating a receipt-only documentation commit
loop. Toolkit has no configured CI; verify its remote head and check/status surfaces
rather than interpreting an empty combined `pending` status as a queued job.
The new tests prove pure DATA behavior only; green CI cannot prove native admission.

## Implementation-plan progress and next gate

Plan advancement: immutable closed admission candidate, complete revalidation and
consumed handoff semantics are now executable, not another documentation-only or
generic maintenance slice. Actual authenticated/native origin, role-only auth
fences across every callback phase, all-ten lock/metadata/CAS/readback/acknowledgement
integration, 40-read/one-write/original real budget and unchanged application-state
proof remain unimplemented. Options locks must still not be described as serializing
core revocation; exact equality is not ABA/restore/tamper protection.

Recommended next gated slice: **Plugin Tester local-only V2.3 Closed Registration
Native Admission and Owned Ledger Commit Proof**, dormant fixed coordinator with
genuine guarded construction -> all-ten locked comparison -> one owned schema-2
ledger registration CAS/readback/acknowledged commit, plus failure/cleanup controls.
This requires exact local target and disposable fixture users/storage confirmation
and approval for writes to the owned ledger fixture only. Existing application
state, public endpoints, production wiring, grants/activation, snapshot/queue/
effect execution, cancellation and rollback stay untouched. Do not start it under
this in-memory-only approval.
