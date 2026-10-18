# V2.3 Scan-Effect Fencing and Post-Commit Cache-Publication Contract Safety Foundation

Approved R3 repository-only implementation, 2026-10-18. **Dormant, test-loaded,
in-memory only.** Excluded coherent review base: uploader
`7679bfbfaed2c7e5c947cf42cfe8c0670d6fdbc3`; companion dashboard plan base
`f530a2e204119b29fbcaf834cb7034f6d080233a`. This entire feature delta, not a
later verification commit, is the canonical focused-review scope.

## Ownership, boundaries and bounded plan

Implements the next model milestone from companion dashboard
`docs/V2_3_CACHE_CONVERGENCE_SCAN_STOP_ADMISSION_DESIGN.md`. Uploader owns two
cohesive pure contracts and their two PHPUnit files; dashboard owns only plan/
design cross-references. No existing production caller or loader is changed.
Current schemas, purpose nonce domains, grants and native two-row owner remain
unchanged. Versions remain uploader 0.5.25 and dashboard 0.1.77.

Plan: (1) establish clean/synced Git and session-contract regression baseline;
(2) model fixed scan ownership/target ordering and separate cache observations;
(3) test positive and adversarial interleavings; (4) canonical source-scoped
reviews and bounded guard-removal controls; (5) validation, commit/push and
exact-head full-suite CI. Only this approved dormant R3 boundary is exercised.

The restore-point wrapper/shared workflow ran Option A/check against uploader:
clean master at the excluded base, checkpoint eligible, existing external archive
`alynt-drime-backups-uploader-20260920-211159.zip` under the configured toolkit
snapshot root. Clean committed source is the current recovery anchor. No new
archive, restore branch, backup, rollback or application-state operation occurred.

## Contracts, not permission

`includes/class-scan-effect-fence-contract.php` evaluates at most **64 flat
events**, at most **16 lifetime registration identities**, one pending operation
at a time, two fixed logical targets (`snapshot`, `queue`), and fixed 64-character
lowercase hex synthetic operation/intent/context values. It stores no payload,
path, package, user, nonce or settings bytes. It returns fixed reason codes,
counts, non-authorizing candidate facts and false storage/dispatch/execution flags.

- `register` means hypothetical scheduled-participant admission, not a real grant.
  Admin/CLI/remote are rejected in this hypothetical authority lane; **legacy
  production manual semantics are not altered**. Exact own revalidation is not
  confused with a new admission blocked by foreign pending work.
- `prepare` captures the model's current target revision without effects. An
  `effect` is one **abstract serialized commit boundary**: exact immutable owned
  identity, current generation, active candidate phase and prepared target revision
  must agree. `drift` represents a competing writer such as upload queue removal.
  The revision is an abstraction for exact raw row/CAS comparison, not a codec,
  stored version field, SQL implementation or proof against real stale arrays.
  Logical `queue` covers additions/prepend only as one shared sink obligation;
  actual queue methods and producer snapshot writes are not instrumented.
- A hypothetical acknowledged closure advances generation and closes ingress.
  Effects that win first remain registered/draining; closure that wins first
  rejects stale effects. There is no reopen, lease expiry, prune or ABA recovery.
  Unknown effect acknowledgement retains registration, latches uncertainty and
  blocks replay/terminal guesses. This model intentionally has no resolution API.
- Only exact matching terminal evidence retires owned work. A current closed
  barrier with no pending/unresolved work and `all_modeled` participants can yield
  a quiescent **candidate**. That enumeration asserts a synthetic scenario, not
  discovery or proof of real participants. It neither cancels running file reads
  nor governs uploads, backup creation, retention or Drime. A later event invalidates
  the earlier barrier observation; cached state/empty queue/missing cron/TTL cannot
  establish it. Malformed events poison candidate admission rather than recover it.

`includes/class-cache-publication-contract.php` evaluates one synthetic command
with at most **12 flat observations** and one pinned positive integer blog ID.
The only candidate profile is single-blog/no drop-ins with strict false/null
external-cache observations; this **does not detect a real runtime profile**.

- A confirmed historical commit and unknown acknowledgement are separate. A
  duplicate commit/caller receipt cannot overwrite the original classification.
- Publication starts only after simulated transaction release and original-scope
  comparison. A safety-only command has fixed symbolic safety/alloptions/notoptions
  keys; a pair also has settings. No arbitrary option names, flush, proposed-array
  seeding, hooks, provider handles or retry jobs are accepted.
- Every delete observation rechecks the pinned scope. A scope mismatch stops
  remaining observations permanently; no switch-back retry. `true`, `false` and
  `threw` are distinct **observations**, not executed API calls. False can mean
  absence or failure. All attempts, including all true observations, leave
  `publication_unconfirmed=true`; neither convergence nor a durable receipt follows.
- Post-commit revocation withholds the outward historical-result candidate but
  does not suppress hygiene or erase the historical fact. Unknown-commit hygiene
  remains non-resolving. A crash before eviction leaves the same historical fact;
  stale repopulation/arrays held by other workers cannot become authority here.
  `outward_candidate` is only a synthetic historical-result eligibility observation,
  not a public success/disabled/effective-stop response.

No callbacks are invoked. Keys, identifiers and scalar types are strict; extra
fields/arguments, sparse traces and unsupported events are refused. Trace list
validation compares scalar keys, not recursively supplied values. Result envelopes
contain no private synthetic identity and no transferable owner or write handle.

## Canonical focused review sequence and outcomes

Main agent read the actual toolkit prompts and followed their phases. All claims
below are **source-scoped**, not whole-plugin release readiness or Database Review.

| Sequence / canonical prompt | Inventory, action and terminal outcome |
|---|---|
| DS2 `FEATURE_LIGHT_REVIEW_PROMPT.md` | Four new PHP files plus minimal unchanged session/privacy/entry-point/native boundary context. One meaningful pre-publication finding F1: scope could change after initial publication comparison. Add per-delete pinned-blog checks and regression; six mechanical WPCS formatting corrections. No manual product decision. |
| DS2 `FEATURE_BLOAT_AND_STRUCTURE_REVIEW_PROMPT.md` | Mandatory helper with explicit excluded base above; four PHP files, zero oversized. All classified Consolidate/keep cohesive with no cleanup/split needed. Reference searches cover root/plugin loaders, hooks/filter/REST/AJAX/cron registrations; only tests reference these new classes. Same-base remeasurement required after final edits. No Phase 2 edits justified; no unnecessary splitting. |
| DS3 `03-ERROR_HANDLING_REVIEW_PROMPT.md` | Terminal No updates needed: malformed/unsupported traces refuse; unknown effect is latched; rejection/false/throw have explicit bounded reason codes; failed publication cannot erase acknowledged history. No public UX, logging, actual API or exception-catching surface. |
| DS3 `04-WP_BEST_PRACTICES_REVIEW_PROMPT.md` | Terminal No updates needed after formatting: prefixed classes, ABSPATH guards, PHP 7.4 syntax, zero registrations/new request surfaces/options/queries. No need to substitute cached WordPress APIs into a pure model. Full existing WPCS command executed. Existing dependency-configuration deprecation banner is not hidden or represented as a code error. |
| DS3 `06-PERFORMANCE_REVIEW_PROMPT.md` | Terminal No updates needed: fixed 64/12 event and 16 history limits, two target counters, flat fixed-field validation, bounded reason output, no recursion/callbacks/queries/retries. Limits are refusal rather than truncation/unknown-record expiry. No speculative caches or unbounded stress tests. |
| DS3 `07-EDGE_CASES_REVIEW_PROMPT.md` | Source inventory below; terminal No updates needed after F1 fix. Relevant categories: empty/malformed traces, numeric/string/capacity limits, deterministic race order, cache profile/scope variation, corrupted identity, permission-lane negatives and interrupted acknowledgement. External services/UI/browser/deletion cascades are outside this no-I/O model. Candidate protections handed to 07A; physical gaps remain explicitly gated. |
| DS3 `07A-ADVERSARIAL_TEST_SUITE_REVIEW_PROMPT.md` | Pure-unit behavior map below; baseline 21/655, add two discriminating cases for independently prepared positive snapshot/queue effects and maximal closing generation/closed-before-start. No existing tests removed, weakened, consolidated or skipped. Eight bounded temporary mutants assertion-killed/restored; exact-head full-suite CI required before terminal Clean. |
| DS2 `FEATURE_SECURITY_REVIEW_PROMPT.md` (last) | Focused security gate after fixes/test strengthening: strict bounded shapes, no input coercion or private outputs, no WP request/auth/storage/provider surface or production loading. F1 permanently covered; no remaining confirmed security defect within approved scope. No genuine authentication, nonce, transaction or cache-convergence claim. |

### Edge-case / behavior-to-test map (07 then 07A)

All cases are pure unit using explicit deterministic interleavings. The new model
is not mocked; WordPress shims in the repository bootstrap are not integration proof.
Each listed group protects exact outcomes/counts in the two new test classes.

| Risk / scenario | Permanent check and discriminator | Runtime handoff |
|---|---|---|
| Effect before closure versus closure before effect | Positive ordered effect, draining until terminal+barrier; both snapshot and queue denied after closure | Native competing transactions and real participating workers required |
| Own pending versus foreign/new work, stale intent/context/generation | Exact identities, duplicate identity and admin/CLI/remote rejection; own effect succeeds | Trusted construction/authentication and all actual sinks required |
| Unknown ack / crash / ABA / missing prepare | No replay/terminal clearing; no reopen; each target prepared independently; single-use prepare/terminal | Wire-loss/crash/restore and locked resolution proof required |
| Stale prepared whole queue/snapshot | Drift between prepare and effect denies persistence; no resurrection candidate | Real codecs/row bytes, upload progress/removals and exact CAS required |
| Premature quiescence | Closed ingress, current generation, proven modeled participant set and zero pending; cache/cron/TTL shortcuts refused | Actual participant inventory, drain/barrier and old-binary lifecycle proof required |
| Cache ordering / fixed keys / changed scope (F1) | No publish under transaction; original scope checked for every key; false/throw/revocation/unknown/crash cases distinct | Genuine core cache and native post-commit integration required |
| Resource/privacy/input extremes | Exact/over-limit 64/65 and 12/13 traces, 16 retained histories, 64 KiB invalid identifier, strict types/callback/extra field/sparse rejection, max generation; output omits IDs | No physical capacity/storage claim |

No weak/misleading or same-layer redundant test removal is justified. No coverage
driver/percentage claim. An owner that always returns unavailable fails the positive
ordering control; no fake real grant or success path is added to existing owners.

Eight temporary counterfactuals each produced the intended PHPUnit **assertion
failure**, not syntax/bootstrap/environment errors, and were restored immediately:
stale-target guard; immutable owned identity; pending-work barrier; release before
publication; per-delete blog scope (F1); unknown versus confirmed history;
post-commit outward revocation; always-unavailable candidate model. These are bounded
source mutations, not an installed mutation framework or physical failure proof.

## Validation and publication evidence

Local pre-edit session-contract baseline: **7 tests / 185 assertions**, no skips.
First new-model pass **20 / 646**; after F1, **21 / 655**. Final strengthened
new-model scope is **23 / 673**, and combined with session/native boundary and
entry-point-policy regressions the exact command
`php vendor/bin/phpunit --do-not-cache-result --filter 'ScanEffectFenceContractTest|CachePublicationContractTest|ClosedWriterSessionContractTest|NativePairedBoundary|NativeClosedBeginBoundary|CadenceAuthorityEntryPointPolicy'`
passes **37 / 974**, no skips, 1.413s. Full-suite CI and pushed heads are recorded
after execution; targeted results are not full-suite/final-head certificates.

Mandatory same-base structure helper before/after: four changed PHP files, zero
oversized. Final TOTAL lines: scan contract **167**, cache contract **112**, scan
tests **116**, cache tests **90**. Earlier scan test TOTAL **105** grew only for
the two adversarial positive/boundary groups; no structural split is justified.
The helper's CODE column reports one for these files; use measured TOTAL, not that
known scanner artifact. `git diff --check` passes; reference inspection confirms
fixed production include list does not load either class and only tests call them.

Executed uploader build, deterministic runner verification, runner syntax,
development-tool tests (**three pass**), strict Composer validation, Composer audit
and npm moderate audit. No advisories/vulnerabilities or generated asset/runner diff.
Full WPCS passes **178 files**, no code errors/warnings; the existing
PHPCompatibility functionWhitelist configuration deprecation banner remains.

Actual readers/cache APIs/storage writes/fixtures/users/options/cron, LocalWP/live
sites, credentials/pairing, production wiring, grants, activation and schedule
rollback are untouched/disabled. No Novamira/site operation needed or invoked.
Existing genuine native evidence is historical, not rerun or enlarged. No package,
release, tag, deploy, database/schema review or physical transaction/cache/worker
proof. FULL_PRE_RELEASE remains reserved for the next coherent release candidate.

Implementation-plan progress: the approved in-memory ordering/publication contract
foundation advances the open cache/effective-scan-stop item; native persistence and
real participants remain the next gated integration decision, not maintenance work.

Recommended next gated slice: **local-only V2.3 Native Scan-Effect Storage and
Participant Integration Design**. Specify fixed registration/snapshot/queue row
codecs and caps, global lock order, exact target CAS/receipt capacity, producer
prepare/persist separation, settings/connection-writer participation and upload
progress coexistence before approving any additional native rows or actual sinks.
Keep that slice design-only, with no SQL/fixture/cache/API operation, production
wiring, grants, activation or rollback. Real native/cache/worker proof remains a
separate explicit approval after the physical contract is reviewed.

### Exact source-head CI and final focused outcome

Uploader implementation `9f2407d0e3e34cec245e0d4d6ac23b36063b6b7a` passes
[Quality 39146621883](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/39146621883):
PHP 7.4 and 8.3 each **1403 tests / 12481 assertions**, no skips, and Node build/
runner/audit checks. Thus 07A's source-scoped Phase 2 terminal status is **Clean**:
23 new tests / 673 assertions, eight assertion-killed/restored controls, targeted
and full-suite verification complete. No local full-uploader execution is claimed;
the exact pushed-head full matrix provides that regression layer, not runtime proof.
The final Feature Security gate has no remaining confirmed in-scope defect.

Companion dashboard plan head `12792bdc8fd3974916a609a100f3ba426a2c574f` passes
[CI 39146644262](https://github.com/NichlasB/alynt-drime-backups-dashboard/actions/runs/39146644262):
PHP 7.4/8.3 each **299 / 1430**, no skips. Local dashboard full suite **299 / 1423**
retains two existing Windows skips; lint **138 files**, structure/build, strict
manifest and both audits pass, no generated diff. Neither result proves the models
in WordPress. CI's informational Ubuntu image-migration notice is not hidden or
represented as a test failure. Toolkit's source-scoped checklist addendum preserves
all historical whole-plugin rows; toolkit has zero configured CI workflows, not a
passing CI claim. This receipt-only uploader follow-up is independently pushed and
verified at its final exact head before handoff; earlier CI is not substituted.
