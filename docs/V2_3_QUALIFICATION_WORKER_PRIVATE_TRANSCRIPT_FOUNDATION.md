# V2.3 Fixed Qualification Observation Worker and Private Transcript Safety Foundation

Status: implemented, isolated tests only; native request entry remains closed.
Date: 2026-10-20. Versions unchanged (uploader0.5.25/dashboard0.1.77).

## Authority, scope and review boundary

User approved local-only implementation, validation, commit, push and exact-head
CI using isolated tests. Storage, actual authentication/WordPress bootstrap,
networking/IPC, native execution and production wiring remain disabled. No site,
SQL, user, fixture, process/pipe experiment, package, release, tag or deploy ran.
Repository QA does not confer permission for any later native experiment.

Governing design: dashboard
`docs/V2_3_REAL_UNCERTAIN_COMMIT_QUALIFICATION_PROVENANCE_DESIGN.md` and predecessor
[source-identity foundation](V2_3_QUALIFICATION_OBSERVATION_SOURCE_IDENTITY_FOUNDATION.md).
Coherent excluded Git review bases are uploader
`9d3c9d107331f5a7074cc58ac4d91ac6ab91267b` and dashboard
`4a84f89fbc28c9c4f7cab63a2d22e0e78c264a49`, covering the observation family,
compatibility repair and this successor, not merely a documentation tail.
Current-slice starting heads: uploader
`60c2c717f0c10a18b55d900599642369f64d61ad`, dashboard
`2e35fba847db732b1d2a2996681e71d4eea44c6b`. Direct dependencies outside the diff
were inspected; this is not a historical whole-plugin review.

Before edits, source-only OptionB ZIP snapshots and sidecars were created in the
toolkit-snapshots folder, not site/database restore points:

- uploader `alynt-drime-backups-uploader-20261020-225710.zip`,11229 files,
  SHA256 `F84341FBB56B2DB48EBD564BAFFA84329C39120C8C5E020CA6A0D5A396ADA8F6`;
- dashboard `alynt-drime-backups-dashboard-20261020-225725.zip`,13207 files,
  SHA256 `4A140EE7EB8A1916B1421B84B4794E5A95B9EEFF310C6E2381B8EB1A36EF36BF`.

## Fixed owner and extraction boundary

`tests/Support/QualificationObservationWorker.php` creates exactly the existing
final proof composition, only after full fixed source-manifest verification. No
owner/class/provider/callback/handle may be supplied. There is no public run,
owner, extraction getter or native factory. Private `enter()` unconditionally
consumes/refuses and discards references: it does not invoke even the original
root's early-refusal path. This foundation is deliberately not an executable
request worker or CLI entry. No production hook or source method changed.

Private `take()` consumes before validation. It copies the owned private observer
receipt and clears that slot before checking source identity, original slots and
encoding. It requires original reader/writer/physical/raw/context/origin/clock
slots empty, ID list empty, zero outcome tick, no active/join/interruption and all
required consumed/closed/spent/cleanup flags exact. It does not clear those source
slots, close a handle or repair flags to manufacture successful evidence.
Missing/malformed receipt, retained state, changed source or exceptions mean
inconclusive false. Finally clears worker owner/context and observer receipt;
there is no raw graph, Throwable/message, credential or private DATA export.

Positive tests use privileged test Reflection to seed the worker's own instance,
call the actual unchanged private qualifier alias, perform synthetic clearing and
mirror remaining root-finally slots. They never substitute an externally supplied
owner into the worker. These are **synthetic post-root fixtures**, not execution
or erasure proof of an authenticated root. Seeded40/1 counters are not native IO.

## Separate bounded transcript, no IPC or authority

`tests/Support/QualificationPrivateTranscript.php` is a pure codec/parser for
`alynt.qualification-observation.isolated.v1`; old v1/v2 grammars remain unchanged.
It accepts exactly one canonical printable-ASCII JSON frame and one LF, at most
4096 bytes total/4095 line bytes, depth8. Version1/sequence1, fixed ordered keys
and strict primitive types are required. Three distinct64-lowercase-hex synthetic
labels bind attempt/root/pair. Production/composition/projection/worker/transcript
pins are fixed claims. Receipt is exactly9 fields, actual result13 fields; no
additional command, nonce, clock, raw returns, handles or guard counters.

Only a one-entry/one-return closed-array receipt with exact validity/seal/empty/
noninterference predicates is encodable. Actual qualifier result is copied, never
reclassified from external frames, corrected to released=true or promoted from
uncertain to current acceptance. Candidate/lifecycle claims remain explicitly
non-authority; authenticated/barrier/closure/retry/storage/dispatch/execution and
diagnostic flags remain false.

Envelope always declares isolated_only=true and root_invocation_proven,
root_erasure_proven, authentication_proven, native_proven, ipc_proven,
storage_supported, dispatch_supported and execution_supported=false.
`original_slots_empty=true` describes the inspected synthetic state only.
The parser verifies structure/context, **not source provenance/authentication**.
Caller-constructed valid bytes remain synthetic claims; public encode is not a
receipt-import route into source classification or a write-capable handle.

Feed checks remaining byte allowance before accumulation, rejects objects without
serialization callbacks, controls/non-ASCII/backslashes, duplicate/extra/missing
keys, whitespace/escapes/exponents, other domains/pins/sequence, extra frames and
trailing bytes. Failure latches and clears retained context/frame/line. Finish is
one-shot explicit synthetic EOF, not OS EOF, worker exit or cleanup certification.
No global replay store, actual pipe, listener, socket, timer or supervisor exists.

## Source identity

Original production/proof/projection pins remain respectively:

- `15ea222c67467802ebacef48c21bf14d8dda4592f6fbc3b04f2dde0fe06b8c85`;
- `859f3b3ac4655a24dd2b957601a32282cddf9aafe54eae34f32d72ba913a2b20`;
- `8c43fd880c6cc4950ee900f994d6a64d32e6aa0449827b72cfbd69f628ba4170`.

New complete-file canonical token pins:

- worker `1aa5e59b4d585c856b34058f1991571d4ee99cde741b8fbb10ba1e45a06b9726`;
- transcript `12a642532d06610c8450e296c094ea5b1ca284097f243f3166f02622ce22ad8e`.

Manifest's private support reader has a fixed three-basename allowlist and finite
262144-byte source limit; no caller-selected expected hashes or filesystem paths
are public. Existing PHP7.4/PHP8 token canonicalization remains unchanged. Pins
are reviewed local-source identity under a trusted runner, not cryptographic
attestation against malicious Reflection, source editing or a compromised host.

## Canonical source-scoped review checkpoint

Actual toolkit prompts used, in order: DS2 Feature Light; Feature Bloat and
Structure; DS3 03 Error Handling;07 Edge Cases;06 Performance for byte/traversal
bounds;07A Adversarial Test Suite after07; final focused Feature Security after
implementation and local checks. Findings are scoped to this feature and direct
callers/tests, not a new whole-plugin or historical release-readiness assertion.

Feature Light: risk Other/test-support. Fixed owner/source gate, strictly private
one-shot extraction, separate protocol and explicit proof limits align with the
governing design. No public runtime contract or dashboard implementation changed.
No confirmed production defect; no pre-fix red run or fabricated red-to-green claim.
Review hardening made context length and sequence explicit and added a causal
closed-entry test; original production/proof/projection hashes were not repinned.

Bloat/Structure helper at explicit uploader base above:16 PHP/0 oversized. Ordered
TOTAL lines (Controls/Projection/Observation/Transparency/Worker/Transcript tests;
SourceManifest test; observation fixture/owner/projection/worker/wrapper/transcript/
manifest/worker fixture; memory test):
28/51/58/60/69/80/49/26/56/29/57/32/62/61/31/36.
Reported CODE1 is the helper limitation, not a reduction claim. Retain/consolidate
all; no additional split/removal, architecture churn or Phase2 bloat edit warranted.

03 Error Handling, all12 canonical categories: silent failures (1) are deliberate
inconclusive false at this caller contract, not uncertain history/success; checked
source reads and consume-before-validate/finally erasure preserve refusal. Poor
messages (4): no user message or error/private graph export. Loading states (2),
confirmations (3), success feedback (5), empty displays (6), form validation (7),
timeout/retry (8), network (9), permission UX (10), external services (11) and bulk
feedback (12) N/A: no corresponding UI/async/destructive/remote operation introduced.
Fixed pinned layout governs Reflection; no arbitrary owner/provider can turn
cleanup into a callback. Inventory0 confirmed issues, Phase2 No updates needed.

07 Edge Cases, all12 canonical categories plus regression handoff: empty state (1)
missing/false observation refuses; boundaries (2), corruption (7) and input extremes
(8) cover exact types/order/labels/pins, canonical JSON, bytes/depth and flags.
Concurrency (3) is only per-instance one-shot/reentry, not multi-session proof.
Environment (5) retains PHP7.4/PHP8 source identity; plugin/theme conflicts (6)
introduce no production names/hooks. Permissions (9) add no new authorization path;
actual nonce/guard order remains predecessor isolated coverage, not refreshed WP
proof. Recovery (11) means permanent refusal/no rearm, not automatic retry. External
services (4), deletion cascades (10) and browser/client UI (12) N/A, no such work.
No newly added clock/native concurrency path. Inventory0 unhandled confirmed gaps;
Phase2 No updates needed beyond implemented guards. Positive committed/uncertain
qualifier returns, retained-slot non-erasure and malformed/truncated/replay cases
handed to07A with existing isolated automated coverage as described below.

06 Performance, all10 canonical categories: uncached expensive work (2) is bounded
test-only source verification, no runtime cache justified; memory (5) has finite
byte/depth/field/context/source caps, no external traversal/object callback or
unbounded buffer. N+1 (1), SQL (3), assets (4), lazy loading (6), JavaScript (7),
HTTP (8), background work (9) and tables (10) N/A: no changed operations. Inventory0
confirmed bottlenecks, Phase2 No updates needed. No benchmark, native deadline or
pipe-backpressure claim from synthetic bytes.

07A:31 new cases, Strong for their narrow isolated boundary: four actual qualifier
outcomes, original-slot refusal/non-erasure, positive extraction/receipt erasure,
closed entry, every truncation, fragmentation1/2/7/4096, malformed/canonical JSON,
source/context/authority/sequence faults, limits, callbacks and replay. Causal fresh
entry test asserts the original root's spent flag remains false (would fail if even
early root finally ran). Wrong-hash/path controls are descriptor controls, not
actual-source mutation kills; no such kill claimed. Synthetic reflected lifecycle
is not genuine root/authentication/storage/transaction proof. No new skip, exclusion,
artificial success, relaxed guard or weaker expected result added.

Final Feature Security: source-gated fixed construction, no external owner/handle,
private consumed extraction and separate bounded no-authority grammar checked.
Structural frame acceptance is never a source/authentication certificate. No new
runtime endpoint, privilege, nonce domain, source rewrite/eval, secret export,
protected read, SQL, write path or native execution. No confirmed scoped security
defect remains. No UI/UX, Database Review, physical transaction proof or whole-plugin
FULL_PRE_RELEASE claim; full suite remains required for a future coherent RC.

## Executed validation and publication

- New worker/transcript tests:31 tests/7884 assertions, no skips/errors/failures.
- Related `php ./vendor/bin/phpunit --filter 'Qualification' --do-not-cache-result`:
  125/12735, no skips/errors/failures.
- All15 qualification PHP syntax checks pass; WPCS235 pass (tests excluded by
  existing configuration), existing third-party PHPCompatibility deprecation noted.
- Runner PHP lint, Node472/472/no skips, build and deterministic verification pass;
  runner SHA256 `7356eacd57ed67f4265db8198f8ca5809e07f4bd985b1d6d00a88cb915112117`
  unchanged. Production/native harness/config directories byte-unchanged.
- Full Windows PHP and dashboard QA, actual exact-head PHP7.4/8.3 CI/log counts,
  links and toolkit docs-only remote/status evidence recorded at toolkit closeout.
  Pending runs are not complete until those records say so.

## Implementation-plan progress and next gate

The approved fixed-owner extraction and strict private synthetic transcript now
advance the observation-proof milestone. Safe execution remains deliberately
closed; genuine uncertain-request provenance is still not established.

**Recommended next gated slice: local-only V2.3 Fixed Qualification Worker
Request-Entry and Private Stdio Integration Design**, design-only. Define the fixed
genuine root-entry/guard/counter/clock and private handoff contract, independent
cleanup/audit conjuncts and a distinct native-proof domain, without running it.
Do not turn this isolated frame's false proof flags into true or extend old v1/v2
grammars. Keep actual authentication, WordPress/storage/native/IPC experiments and
production wiring disabled; future owned native proof needs separate explicit
approval and exact Plugin Tester target/fixture/process cleanup confirmation.
