# V2.3 Test-Only Qualification Observation and Source-Identity Safety Foundation

Approved repository-only foundation, 2026-10-20; uploader0.5.25/dashboard0.1.77,
not a release candidate. Governing dashboard record:
`docs/V2_3_REAL_UNCERTAIN_COMMIT_QUALIFICATION_PROVENANCE_DESIGN.md`.
No production file, bootstrap, hook, endpoint, configuration or native harness is
changed. Storage, actual authentication, WordPress execution, networking/IPC,
native experiments, production wiring, grants, activation and rollback stay disabled.

## Review boundary and restore point

Explicit excluded Git review bases:

- Uploader `9d3c9d107331f5a7074cc58ac4d91ac6ab91267b`: all ten new test/support
  PHP files and this owning record, not merely a later verification commit.
- Dashboard `4a84f89fbc28c9c4f7cab63a2d22e0e78c264a49`: governing qualification
  design/plan/pointer and this successor's two plan/design updates.

Direct dependencies reviewed outside the diff: final native admission class,
root/join/qualification/construction/core-guard/capture/session/sealed-result/privacy
traits, existing qualification fixtures and failure/native-qualification API tests.
That dependency inspection is not a whole-plugin review.

Source-only snapshots under `wp-plugin-toolkit` sibling `toolkit-snapshots`:

- Uploader `alynt-drime-backups-uploader-20261020-221153.zip`,11191 files,
  SHA256 `10FAE161EDCF90B3D63BABB33CAB1248D527E08009FC3398916CC23EF449B644`.
- Dashboard `alynt-drime-backups-dashboard-20261020-221159.zip`,13197 files,
  SHA256 `FB86868DC12C61D06C7179FD95C3F19F05164652667CB99895F60539538A05D8`.

These are source restore points, not site/database backups. Initial repositories
were clean/synced at uploader9d3c9d10/dashboardd4ccde89/toolkit015bcece. No existing
application state, user, option, cron, SQL, native worker or owned-site fixture ran.

## Fixed composition and observation contract

`tests/Support/QualificationObservationOwner.php` is a separate final proof class.
It reuses all original production trait implementations/resolutions. Its five
class-local methods match original signatures and canonical body tokens. The sole
behavioral exception is a private alias/wrapper of `qualify_admission_outcome`,
plus three private observation fields. No public runner/getter/result/handle port,
provider callback, class generator, source rewrite, eval or production seam added.

The wrapper consumes its observation opportunity before collection; calls the real
alias once per wrapper invocation; returns its exact value or propagates the same
Throwable object. Observation failure cannot bypass the alias or fabricate a
result. Nested/replayed calls permanently invalidate observation, not source
behavior; the outer finally cannot resurrect invalidated evidence. Original source
one-shot qualification, pin/clock, guard order and root cleanup/erasure are unchanged.

Before/after snapshots retain only seals/empty-slot booleans, bounded counter values
and a digest of strictly typed finite capture fields. Projection copies the actual
13-field result without classifying history or repairing client release. Unknown
keys/types/enums, authority flags and incomplete candidate/lifecycle shape fail
closed. Private state retains no origin, IDs, proposal, clock, credentials, raw
capture, exception message, row or native handle. Public privacy/serialization
behavior remains unchanged. Tests explicitly clear the private projection.

`valid` means observational fidelity, **not** successful qualification: a faithful
false return or exception may have valid observation. Any later complete proof
must also require `kind=closed_array`, exactly one entry/return, the actual expected
result and every independent source/request/cleanup conjunct. A phase or spent
flag alone is not evidence that qualification ran.

## Complete source/state manifest

`QualificationSourceManifest.php::verify()` accepts no arguments. Before the fixed
fixture exercises the owner it checks trusted repository source only, with a
262144-byte cap per source file and a visited set for recursive traits. The known
production inventory is67 methods/29 properties/20 recursive traits. Snapshots pin
method parameter/default/type/modifier/return/reference metadata, token bodies,
source file/start/end, property/default metadata, constants, recursive/direct
traits, aliases, finality, parent and interfaces. Token names, rather than numeric
token IDs, are hashed; whitespace/comments/open tags are ignored. PHP8.1's two
named ampersand token kinds canonicalize to PHP7.4's literal `&`, retaining the
lexeme, context and independent parameter/reference metadata.

Reviewed fixed SHA256 manifests:

- Production: `15ea222c67467802ebacef48c21bf14d8dda4592f6fbc3b04f2dde0fe06b8c85`.
- Proof composition: `859f3b3ac4655a24dd2b957601a32282cddf9aafe54eae34f32d72ba913a2b20`.
- Projection source: `8c43fd880c6cc4950ee900f994d6a64d32e6aa0449827b72cfbd69f628ba4170`.

Only the fixed wrapper trait/alias/three private fields are removed for equivalence;
the five class-local source locations are normalized after pinning both complete
inventories. Their signatures and tokens must still match. Any changed descriptor,
new method/state/alias, missing method or source movement invalidates the pinned
snapshot. Deliberate source changes require review, not automatic repinning.

The source reader is test-only filesystem access, not plugin storage or a native
resource port. This is reviewed-local-source identity, not attestation against a
malicious runner, Reflection user, altered PHP process or compromised filesystem.
The inert factory alone does not verify provenance; a future fixed worker must
call the manifest before entry and cannot accept an externally supplied class.

## Source-scoped canonical review sequence and findings

Actual canonical toolkit prompts: Feature Light, Feature Bloat and Structure,
DS3 03 Error Handling,07 Edge Cases,06 Performance,07A Adversarial Test-Suite
Review, then final focused Feature Security after fixes and local validation.
Risk: Other/test-only security-sensitive proof infrastructure, not a runtime port.
Phase1 inspection/plan stayed read-only; scoped Phase2 test strengthening uses the
approved dormant foundation and source restore points. No production defect found.

| ID | Severity / classification | Evidence and disposition |
|---|---|---|
| QO-01 | High, proof-support reentry risk | Outer finally could restore a nested-invalidated receipt. Added sticky interruption state and exact reentry regression; no pre-fix red execution claimed. |
| QO-02 | High, source-identity gap | Pin the complete proof snapshot and projection source as well as original source; otherwise an unreviewed observer change could evade source equivalence. Positive manifest plus67 missing-method and14 descriptor faults reject drift. |
| QO-03 | Medium, harness compatibility | Removed an unnecessary PHP8-only mixed return declaration from a hostile fixture; PHP7.4-compatible attribute/comment idiom preserves PHP8 deprecation visibility. Supported-version verification belongs to exact-head CI. |
| QO-04 | Medium, boundary-test gap | Added exact0/32 event and0/40 read,0/1 write boundary controls, out-of-range/wrong-type/object rejection, and no serialization callback. |

Feature Light covered all nine categories: scope/maintainability/error semantics,
unchanged WP guards, integrity/resources/docs/escalation; accessibility/UI N/A.
No unrelated refactor or new outcome schema warranted. Bloat helper explicit base
above:10 PHP/0 oversized, before TOTAL28/33/58/60/40/26/56/29/32/53 and final
28/51/58/60/40/26/56/29/32/53 in controls/projection-test/observation-test/
transparency-test/manifest-test/fixture/owner/projection/wrapper/manifest order.
Helper reports CODE1 each (measurement limitation, not a reduction claim). All
Consolidate/retain with distinct contracts; zero removals/splits/02 deferrals or
extra Phase2 structure changes justified.

03 reviewed all12 categories: deliberate null/invalid observation distinguishes
diagnostic failure from preserved source return/throw; no retries, relaxed original
10s clock, hidden success or leaked error messages. Permission failures remain
source-owned, no new auth callbacks. Loading/confirmation/success/empty-state/form/
bulk/network/external-service UX N/A: no UI, requests or service added. No new
logging/storage side effect or further error-handling edit justified.

07 reviewed12 scenario categories plus regression handoff. Eight applicable groups,
0 critical/4 high/4 medium: early empty input; bad pin/IDs/clock; stale seal/handle;
malformed capture/result; source drift; replay/reentry; return/throw transparency;
resource/type bounds. Protected by exact rejection/result/state assertions. Actual
WP/environment conflicts and permissions remain runtime handoffs, not shim proof;
no external services/browser/deletion cascade/shared concurrent state here. Recovery
is fail-closed observation, never retry. No artificial success or weaker guard added.

06 reviewed all10 categories. Finite18 event fields0..32,5 commands,4 guards,
reads0..40/writes0..1, fixed13-field result, receipt under2048 bytes and bounded
trusted source files; malformed objects never reach serialization. Manifest IO is
repeated only by tests, not production; no cache/lazy-loader/benchmark optimization
warranted. N+1/SQL/query/index/table, assets/JS, HTTP and background work N/A.
No exhaustion/load/physical query-count or runtime performance claim.

## 07A behavior/risk map and test inventory

Pure/isolated API tests only, PHPUnit9.6 with existing bootstrap/shims; no genuine
WP integration. Existing production root/qualifier/native-outcome API-double suites
remain separate. No test deleted, skipped, weakened or consolidated; no production
fix, new test framework, coverage percentage or mutation tool installed.

| Test / case group | Risk and primary assertions | Layer / quality / limitation |
|---|---|---|
| QualificationSourceManifestTest: positive, all67 omissions,14 descriptor cases | Complete fixed identity passes; every missing method or signature/state/source/alias fault fails | Read-only source/reflection unit; Strong for identity, not execution provenance |
| QualificationObservationTest:4 histories | Actual alias returns committed/uncertain/rolled-back; exact13 fields copied, counters/capture unchanged, replay clears | Isolated synthetic DATA; Strong, not actual transaction/authentication |
| Same:7 faults | Bad pin/IDs/clock/seal/remaining slot/reentry/raw contradiction returns false, no copied result | Isolated actual qualifier; Strong for local refusal, not full request guards |
| Same:cleanup/privacy/early root | Original cleanup methods clear observed slots; private receipt only; malformed root zero IO/zero qualifier entries despite finally-spent | Isolated methods/early root; Strong narrow assertions, not authenticated root-finally proof |
| QualificationObservationProjectionTest:shape/seal/IO/release/bounds | Bad authority/shape/types reject; actual false client-release stays false; finite resource boundaries | Pure projection plus actual alias; Strong, projection is not a second classifier |
| QualificationObservationTransparencyTest:returns/throw/reentry/object | Same return/Throwable identity, no source bypass, sticky invalidation, no JsonSerializable callback | Wrapper-only hostile double; Strong for wrapper, does NOT pass fixed manifest |
| QualificationObservationControlsTest:six counterfactual receipts | Positive uncertain-source oracle assertion-kills missing call, always-false, fabricated result, unsealed, changed capture and IO receipts | Oracle falsifiability only; NOT actual-source mutation kills or native controls |

The positive histories prevent an always-unavailable alias from passing. Descriptor
controls would fail a missing/changed original guard method, but do not prove real
WordPress authorization. Return transparency cannot establish a full native request.
Reflection fixtures use current clocks with300s intent bounds and original10s source
limit, no sleeps/retries; an extreme scheduler stall must fail rather than extend it.

No ceremonial before/after coverage gain or red-to-green production fix is claimed.
QO-01's regression added after correction; counterfactual receipts catch only
PHPUnit assertion failures, not syntax/import/bootstrap errors. No code patched or
evaluated to obtain controls. Phase2 scoped status remains Partially verified until
full-suite/exact-head CI; completion and actual log counts recorded in toolkit.

## Executed validation and final focused security

- New targeted: `php ./vendor/bin/phpunit --filter
  'Qualification(SourceManifest|Observation)' --do-not-cache-result`:
  **37 tests/607 assertions**, no skips/errors/warnings/risky/incomplete.
- Related before final additional resource test: existing native failure,
  qualification/outcome/join and selected harness contract plus new tests:
  **210/10755**, no skips. The added boundary test separately passed above.
- All ten PHP files syntax pass. WPCS235 pass; configured WPCS excludes tests,
  so this is production regression lint, not a claim the new test files are WPCS.
- Runner lint/build/deterministic verify pass, unchanged SHA256
  `7356eacd57ed67f4265db8198f8ca5809e07f4bd985b1d6d00a88cb915112117`;
  Node472 pass/fail0/skip0. Existing PHPCompatibility functionWhitelist format
  deprecation recorded, not suppressed. No generated artifact accepted/changed.
- Dashboard299/1423/two known Windows Sodium skips, WPCS138, structure/build pass.
  Full uploader PHP and exact-head Linux PHP7.4/8.3/Node logs recorded at closeout
  in toolkit; local and CI counts are never inferred from each other.

Final Feature Security: fixed finite primitives, exact shape/enums/authority flags;
no HTML/output surface, SQL, auth/nonce operation, hooks or endpoint; trusted bounded
source reads only. Production guards/nonces/erasure/source stay unchanged. No raw
private payload/throwable/handle export, source eval/provider, state write or authority
promotion. QO-01..04 addressed with permanent narrow tests; no unresolved scoped
security defect. 04 WP/Database/UI/UX and whole-plugin13/full pre-release readiness
are not claimed. FULL_PRE_RELEASE remains required for a coherent RC before release.

## Approved verification repair — 2026-10-20

Initial publication `24f318abfbbc85d392567f5ad10f367935fdd967` is **failed history**,
not retroactively green: Quality39491954756 PHP8.3 passed1803/31846, PHP7.4 failed
19 manifest-before-exercise assertions (1803/31496), Node472 passed. Windows full
PHP8.5 at128M failed9 errors/17 failures/4 skips (1803/31681), with upload memory
refusal and dependent missing mock response state. Toolkit retains those facts.

User-approved repair is repository-only tests/source-manifest/configuration/docs.
No production file, native harness, dependencies or minimum PHP contract changed.
Restore-point workflow Option B before edits, no checkpoint/rollback:

- Uploader `alynt-drime-backups-uploader-20261020-223712.zip`,11218 files,
  SHA256 `20196FBD72C595887FB69A792C4502BCFB2D979B4F302B6AF34846D7EA47F89D`.
- Dashboard `alynt-drime-backups-dashboard-20261020-223718.zip`,13202 files,
  SHA256 `9DC5D42288EA65C7D1CB16C8F4F2DE4F7774CE86FD03C6C673E4CCB18B865526`.

Archives/sidecars are in the same external toolkit-snapshots directories above;
source-only, not site/database backups. Repair starts at uploader24f318ab,
dashboardf11fb651/toolkit585f608, clean/synced; original coherent review bases remain
uploader9d3c9d10/dashboard4a84f89, with24f318ab as the narrower repair-diff boundary.

### QO-05: token identity compatibility, not a guard bypass

PHP8.1+ tokenizes reference/bitwise `&` as
`T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG` or
`T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG`; PHP7.4 uses the literal character.
Original hash representation therefore differed despite identical source. Normalize
only those exact named-token/`&` pairs to the same literal. No token deletion,
signature normalization, missing guard, source movement or extra class/state allowed.
`&&`, other operators and quoted strings retain their distinct tokens.

Read-only comparison found precisely three changed method-body descriptors in both
classes: `plain`, `plain_observation`, `walk`. Their old-to-canonical SHA256:

- plain: `6ce66d0db927b70206efa62424c21270f5b0b417a9eddd035653173d1625f59f`
  to `23943e72d44221851ef81fa4bc46a385ad33a92103779f1e27380e45b42e67fa`.
- plain_observation: `d276ded09f6a32a07a95c95bbe6609939e3730f7d4c8f048cc870fd4614ac480`
  to `d74b5bf0709018dc57314a7227050a59b2753bc8ac5c8be577410990cb8b89c7`.
- walk: `b46e2039fa2cfc63b9149a71b611ed26d20bdb0707b9b48a57297dcd41a3314d`
  to `b65238ee13ff21829a7d56993fd6177f9933887c41e236f60c2dbbfe7d2e94bb`.

Reconstructing the old token representation for those three descriptors reproduces
the complete old production `22d43c6d3293213f0b287fc2e744852c715d763443e99c425b9458190812d13e`
and proof `351c0d8ad977067cae2211a9c05489b25e8bae207d2e4f50c98fc03c9260db04`
pins exactly. Every other descriptor is unchanged; projection pin remains unchanged.
New reviewed pins above are not an arbitrary repin to suppress a mismatch.

Independent canonical-token fixture fails against the old algorithm with expected
`1e09ac8d...` versus actual `fed224c1...`, then passes after normalization. Removing
the reference marker or changing bitwise `&` to `|` still changes the digest. An
additional descriptor fault rejects losing `plain`'s by-reference parameter: now15
descriptor cases, with all67 missing-method checks retained. Genuine PHP7.4 runtime
confirmation is exact-head Linux CI, not a simulated version or new dependency.

### QO-06: bounded test-process memory, unchanged production guard

The full suite at128M can exceed its headroom for the unchanged32MiB multipart
fixture plus32MiB production reserve. Fresh affected upload groups passed37/199;
a bounded48MiB representative suite-overhead regression reproduced the precise
`alynt_drime_chunk_exceeds_memory` refusal before configuration changes. This is a
test-environment resource mismatch, not justification to weaken the guard, shrink
fixture chunks or change expected upload outcomes.

Set **only phpunit.xml** to a finite256M test-process budget (also bounded in CI;
never production php.ini, WordPress memory constants or a live setting). A second
test uses the actual unchanged guard with a temporary limit equal to current usage
plus64MiB-minus-one:32MiB chunk correctly refuses with the exact error,1MiB chunk
passes, original limit restored in finally and asserted. No actual chunk allocation
or upload/network request in that negative test. The48MiB overhead test asserts its
buffer remains live through the guard and the configured256M budget; no exhaustion.

Before fix: focused3 tests/7 assertions, two intended failures (token identity and
actual memory guard). After fix: same3/11 pass. Repaired qualification plus memory
tests **41/619**; those plus all five affected upload groups **78/818**, no skips.
No weaker assertion, new exclusion/skip, unlimited memory or production edit.

### Focused review continuation and validation

Canonical Feature Light: four executable/config paths plus owning/dashboard docs;
Other/test-only, two confirmed test/harness defects QO-05/QO-06 fixed, no production
defect or new approval boundary. Keep scope/trust, fail-closed errors, compatibility,
finite resource budget and documentation; UI/UX/hooks/REST/auth/SQL N/A.
Bloat explicit original base:11 PHP/0 oversized, final TOTAL
28/51/58/60/49/26/56/29/32/55/36 (prior path order plus UploaderMemoryBudgetTest).
Reported CODE1 limitation unchanged; all Consolidate/retain, no split/removal or
extra architecture work justified. Original03/07/06 review contracts preserved;
07A Phase2 continuation adds credible red-to-green harness regressions above and
requires full supported-version CI before completion. No runtime/Database claim.

All11 scoped PHP syntax, WPCS235 (tests excluded), Node472/no skips, runner lint/
build/deterministic verify pass; runner7356EACD unchanged. Dashboard299/1423/two
known Windows Sodium skips, WPCS138/structure/build pass. Full Windows verbose
rerun and actual exact-head Linux PHP7.4/8.3 counts/skips/run links are recorded
in toolkit at closeout; prior failures remain failed history. Existing third-party
PHPCompatibility deprecation recorded, not suppressed.

Final focused Feature Security after repair/local targeted validation: canonical
ampersands retain source semantics and by-reference metadata; no arbitrary runtime
profiles or accepted alternate hashes, removed manifest checks, observer bypass or
external authority input. Finite test-only budget with actual low-headroom refusal
coverage; no storage/auth/native/wiring/secret change. No confirmed security defect
or new runtime authority; no whole-plugin13/full pre-release readiness claim.

## Proof limits and next gate

This proves isolated observation and complete local source composition, **not**
direct execution of the original final class, a real authenticated native request,
uncertain-COMMIT provenance, transaction durability, physical cleanup, retry safety
or authority. The synthetic qualifier state is never presented as a native witness.
No private worker/strict transcript or actual IPC is implemented in this slice.

Implementation-plan progress: source-preserving observation foundation is complete;
the next meaningful step is fixed proof-entry/transcript support, not maintenance.
**Recommended next gated slice: local-only V2.3 Fixed Qualification Observation
Worker and Private Transcript Safety Foundation**, isolated API/synthetic-message
tests only. Require fixed manifest before entry, exact observer-result extraction
and clearing, a separate bounded strict private domain and no external receipt
feedback. Keep actual authentication/storage/networking/IPC/native execution and
production wiring disabled. A later genuine owned native proof needs new explicit
approval and exact Plugin Tester target/scope confirmation.

## Approved fixed worker/transcript successor — 2026-10-20

Implemented isolated-only successor:
[fixed worker and private transcript foundation](V2_3_QUALIFICATION_WORKER_PRIVATE_TRANSCRIPT_FOUNDATION.md).
Source-gated fixed owner, private one-shot extraction/clear and separate bounded
synthetic frame; request entry remains unconditionally closed. Actual qualifier
positive tests use synthetic post-root state, not authenticated root/erasure or
native IPC proof. Original three source pins and runtime wiring unchanged.
Canonical scoped reviews, new31/7884 and related125/12735 pass; full QA/exact-head
CI recorded in toolkit. Next gate is design-only fixed request-entry/private stdio
integration, not native execution or another generic DATA layer.
