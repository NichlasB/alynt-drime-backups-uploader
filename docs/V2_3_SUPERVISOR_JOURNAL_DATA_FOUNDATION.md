# V2.3 Supervisor Intent and Cleanup-Journal DATA Safety Foundation

2026-10-26. Approved in-memory-only foundation. Explicit excluded review bases:
uploader777f784efab80dd85a55a097d021c8783df09777,
dashboard2ff3ef7b9803b9701efe7c605a15ea901f84ec43,
toolkit5e21abcc6250ae0937d5d0d2fc188cb5188e1896. Parents are dashboard
docs/V2_3_ELEVATED_OWNED_PRINCIPAL_SUPERVISOR_DESIGN.md and
docs/V2_3_TRUSTED_SUPERVISOR_HANDOFF_READ_ONLY_AUDIT.md. The dated human/host
metadata is context only; no renewed target inspection or operational authority.

## Implemented contract and scope

`scripts/tests/supervisor-journal-data.mjs` is a separate test-only Node DATA
module with one util import. No OS/IO adapter, actual authentication, account,
permission, journal storage, process, source-image lease or production wiring.
Existing WordPress/plugin/worker/relay/native code is unchanged. Versions stay
uploader0.5.25/dashboard0.1.77 and PHP7.4/WP6.0 compatibility is unchanged.

Exports: fixed profile `supervisor-intent-journal.data.v1`,
`parseSupervisorIntentData(text)` and `createSupervisorJournalData(options,
intentText, recovery?)`. The owner exposes only `accept(text, identity, owned,
now)` and one-shot `finish(identity, owned, now)`. Receipts contain frozen
primitive model outcomes/counters, not writable handles, raw identifiers,
caller commands, callbacks or credentials. Every actual elevation/authentication/
account/file/process/cleanup/source-ownership/storage/wiring/execution claim is
false even on a complete model-positive path. This is not an actual consent API.

|Boundary|Fixed model rule|Not established|
|---|---|---|
|Intent|Canonical ASCII JSON with exactly version/mode/target/artifact/policy/parent in that order; six fixed fixture enums except proof/cleanup-only mode|Artifact provenance, human approval or protected bootstrap|
|Identity|Five private attempt/supervisor/initiator/consumer/generation symbols plus four distinct reserved object symbols; no global/well-known/aliased/reused proof IDs|Windows SID/token, filesystem object or logon authority|
|Events|Canonical seq/kind/value JSON, exact accepted-event sequence and finite stage transitions|OS call/result, physical denial or durable journal write|
|Proof model|Paired root/account/probe intent/results, six distinct ordered probe declarations, five unique terminal declarations, paired removals and journal disposition|Real source isolation, natural shutdown or cleanup|
|Recovery model|One sealed in-module receipt; same nine identities, original epoch/counters, known ownership and no pending intent; cleanup-only cannot restart a proof|Crash recovery, SID-atomic deletion, privilege or process containment|

Canonical matching rejects whitespace, duplicate/reordered/extra keys, escaped
aliases, non-ASCII/control bytes, alternate numeric spellings, unknown profiles/
targets/commands and credential/path/account fields; there is no normalization or
fallback. Config/context records must be plain own-data objects. Accessors,
proxies (including revoked), exotic prototypes and symbol-key additions refuse
without invoking supplied getters/coercion. Context/object checks precede event
parsing/charging; context descriptors themselves are not protected OS reads.

## Finite model budgets and lineage

- Intent text at most512 ASCII bytes; event text at most256 ASCII bytes.
- At most24 event attempts; the complete positive proof trace accepts23.
- Cumulative intent/event UTF-8 charge capped at2048, including failed attempts
  and the continuation's intent. Above-limit accounting saturates at2049; it is
  not an exact measurement of an arbitrarily large rejected caller allocation.
- Huge strings refuse from their length lower bound before UTF-8 scanning;
  bounded strings are UTF-8-charged, including malformed non-ASCII input.
- Virtual monotonic original20s half-open epoch; no wall clock, sleeps or reset.
  A clock fault permanently prevents creation of a recovery handoff even if a
  later caller offers a smaller finish timestamp.

These are DATA/model bounds, not native query/time/heap/transaction guarantees.
The caller already owns its input allocation, Reflect.ownKeys must inspect a
supplied context record, and engine metadata/scratch/GC costs are not measured.
No whole-process heap or adversarial native-ingress resource closure is claimed.
Existing physical query32/SQL4096/deadline policies are not changed or promoted
from these counters. A future adapter may not treat this fixture profile or a
private JS symbol as actual consent/source/cleanup authority.

## Quarantine and cleanup-only behavior

Fresh state has no owned journal. A modeled protected handoff establishes only
the model control-record flag. Every create/launch/remove intent must have its
paired result before ownership/removal is credited. Finishing with a pending
intent quarantines without a recoverable receipt: no prefix/name/nonce adoption
or guessed ownership. Pre-handoff absence cannot manufacture cleanup authority.

For known modeled owned state, a finished proof owner may produce a privately
registered receipt used once by a cleanup-only owner. Clones, JSON copies,
foreign module/object records, wrong identities, changed epochs and reused
receipts refuse. Event/byte counters and last virtual timestamp carry forward.
Live modeled processes require an explicit modeled containment/EOF declaration
before account/root removal; containment can only complete a cleanup model, not
a proof model. No second automatic recovery or renewed budget is possible.
Journal retention/removal is explicit; retained control records never count as
zero artifacts. None of these declarations performs or verifies physical cleanup.

## Source-scoped review and test inventory

Canonical Feature Light: new helper, fixture, three tests and owner/plan records;
Other/test-only DATA. No UI/UX or runtime surface. Measured helper111 lines,
fixture28, data tests151, guard tests95 and boundary tests34. Cohesive files stay
below the JS250 feature threshold; no meaningful Bloat split/cleanup trigger.
The toolkit's PHP/JS/CSS measurement does not cover MJS; no false MJS coverage or
canonical Bloat completion claimed. No existing tests removed or weakened.

Source-scoped canonical07 examined all12 categories. Applicable empty/boundary,
replay, corrupt-input, modeled identity/deletion and recovery risks are covered;
WordPress/browser/external-service UI behavior is outside this dormant model.
No additional model fix was justified after the implemented protections. This
does not refresh historical whole-plugin07 or Database/physical review rows.

|07/07A risk group|Tests exercising first-party helper|Layer/classification|Remaining handoff|
|---|---|---|---|
|Closed input and limits|Intent variants, JSON spellings, type/accessor/proxy/Unicode and byte/event checks|Pure, Strong|Actual request/OS authority absent|
|Role/object lineage|All context/object drifts, symbol reuse, distinctness and original time|Pure, Strong|Actual Windows identities/authentication unproven|
|Ordered ownership and terminals|Positive retained/removed-journal paths; every event omission and material adjacent swap; terminal permutation/duplication|Pure, Strong|No physical intent/result atomicity or natural teardown proof|
|Recovery/quarantine|Pending intents, forged/clone/one-use receipt, containment, no proof restart, cumulative costs and clock fault|Pure, Strong|No durable storage, cross-process recovery or real cleanup|
|Dormancy/receipt shape|Import allowlist, static runtime scans, primitive receipts and all actual flags false|Static plus pure, Useful within its layer|Not installed WordPress or runtime isolation acceptance|
|Guard sensitivity|25 source controls: actual assertions pass, each mutant imports then assertion-fails, including always-unavailable completion and authority promotion|Bounded in-memory semantic controls, Strong|Not mutation-score or physical security proof|

Scoped93 tests comprise65 DATA cases,25 semantic controls and3 boundary cases;
all pass, no skips. No fixture algorithm mocks the helper under test. Semantic
mutants are data-URL imports in memory; source files are never overwritten.
07A scoped test inventory is proportionate, with no further test edits justified;
broader Windows full-suite repeatability remains **Partially verified** as below.
Do not mark historical whole-plugin07A complete. Final focused Feature Security
found no confirmed executable security defect requiring a regression handoff;
actual OS/auth/storage/native/wiring gates remain closed. FULL is still due for
a coherent release candidate; no package, release, tag or deploy in this task.

## Verification evidence and honest limits

Initial targeted62 tests had three fixture failures: recovery attempted101 after
the retained finish stamp102. Corrected only the test fixture to later virtual
timestamps; clock guards were preserved. Added sticky-clock/forged-handoff/input
cases; final93/93 passed. No production/native defect or physical red-to-green
claim. Unchanged setup DATA/wire preservation and exact-head CI are recorded in
the source-scoped toolkit evidence.

First local full Node run failed with an exit assertion tail (actual null,
expected0); its failing test name was lost by a Last9 output filter. Two bounded
identical diagnostic repeats, with full output captured in memory, each passed
1987/1987 with no failures/skips and no code/timeout/assertion change between them.
Cause remains unknown and unreproduced: do not silently call that first run green
or claim a diagnosed Windows harness fix. Local repeatability is not conclusively
closed by later passes or Linux CI. No further repeat loop was run.

Uploader PHPCS255 and dashboard PHPCS138 passed; existing functionWhitelist
configuration deprecation reported, not suppressed. Dashboard structure passed;
local PHP299/1423 has two existing Sodium skips. Build/verify:runner passed with
unchanged7356eacd57ed67f4265db8198f8ca5809e07f4bd985b1d6d00a88cb915112117;
generated assets/runner diff empty. No full Windows uploader PHP or native proof
run. Restore OptionA checked clean777f784/existing external20261024-161220 archive;
no new backup/checkpoint/restore. Actual CI heads/counts belong in toolkit evidence,
not a claim that isolated models prove WordPress, SQL or Windows transactions.

Implementation-plan progress: approved finite intent/journal/recovery contracts
now have non-vacuous executable model protection. A1-A4/native GO stay Blocked.
Recommended next gated slice: **repository-only V2.3 Fixed Supervisor Windows
Adapter and Protected Bootstrap Integration Design**, design-only. Specify exact
native call/result ownership and protected human handoff without treating model
receipts as permission; no OS calls, account/ACL/storage writes, elevation, native
experiment or production wiring. Carry the unexplained local full-suite failure
as an explicit validation limitation, not a reason to weaken physical budgets.
