# V2.3 Fixed Closed-Entry Owner and Bounded Reader Safety Foundation (R2)

2026-10-23. Approved local-only source foundation; isolated API/IO tests only.
Dashboard owns docs/V2_3_FIXED_CLOSED_ENTRY_OWNER_RAW_READER_DESIGN.md. Versions
remain uploader 0.5.25 / dashboard 0.1.77. No WordPress, LocalWP, real auth/cache,
database, storage, sockets, native experiment, fixture user or site operation.
No public hook/loader, production wiring, grants, activation, rollback, HTTP
emitter, package, release, tag or deployment. No installed HTTP support claim.

## Exact source boundary and ownership

Excluded review bases: uploader 6cfab6b15fb59bc3c90424969bc17dae2689df41,
dashboard 10743cebdc8dcae0e98a127f2174abf02b7bd4e1, toolkit
faaccce97c65961f970e1bb39a11195e09ccf612. These cover the coherent new owner/
reader feature, not a trailing verification commit or whole-plugin release.

New uploader sources are the final Registration_Closed_Entry_Request_Owner and
four private transport, reader, scope and GET-auth traits (full uploader prefix).
New tests: ClosedEntryOwnerApiIoTest and four ClosedEntry* support files. Direct
dependencies reviewed: existing Entry_Ingress, Request_Contract, unchanged
Real_Closed_Writer_Core_Guard, unavailable request owners, native lifecycle root,
admin Tools slug, plugin loader and root file. Existing source seals, protected
participants, native owner/guards and production contracts remain unchanged.

The new class is explicitly test-loaded only. Zero-argument create performs no
IO; it exposes no operational public method. Fixed private inspect_post and
issue_get consume a static request-wide latch before dependencies/IO. A new
instance cannot retry a refusal; reentry poisons the outer request, including
cleanup. Clone/serialization/unserialization cannot export an owner. Finally
erases original input, identity, clocks and owned callback references.

entry_available remains literally false: no reviewed server/SAPI profile exists.
The existing public unavailable facades and native_entry_available=false remain
unchanged. No production reflection, test-enable property or injected stream/
provider/actor/clock/action parameter. Private positive checkpoints are not HTTP
success, public auth certificates or write-capable continuations.

## Implemented private protocol

POST snapshots exact bounded original method/type/length/maps and rejects query,
files, override/encoding/framing aliases, references and extra fields. The fixed
reader opens php://input/rb once, requests <=1024 bytes/read, <=17 reads and <=16385
bytes total. It rejects byte16385, timeout/warning/throw/false/unknown metadata,
empty non-EOF stall, call exhaustion, declared-length mismatch and uncertain
close. Short reads continue; only consistent clean EOF/metadata/length and owned
close allow the original bytes into the existing ingress parser. Content-Length
is canonical positive <=16384 and consistency only, not completion authority.

The original monotonic root precedes acquisition. Both UTC and monotonic samples
must not regress; the initial10s stays under the original20s root. No reset or
native read phase is added. Checks do not interrupt a blocking syscall. Metadata
shapes, buffer behavior, duplicate network headers/framing and hard IO limits
remain exact server/SAPI feasibility gates, not proven by fake stream metadata.

After grammar, original exact core db/blog/prefix, actor and bounded cookie are
pinned. POST uses the unchanged POST-only core authentication with outer original
identity/transport/time checks. Fresh session/current manage_options precedes
the exact blog/register_closed small nonce; only integer1/2 is accepted. A second
fresh current authentication/capability check follows it. Original slashed three
strings survive; existing issuance grammar owns the single domain unslash.

GET has separate fixed method/Tools page/screen/admin-context and authentication
procedures; it never calls POST-only helpers, rewrites SERVER method or reads a
body. It creates only the small blog/register_closed nonce between fresh current
auth/capability and unchanged original context checks. No guest issuer, UUID/full
nonce, eligibility/protected capture, lifecycle call or native connection.

Owned alloptions/query fences precede callback-bearing auth. Query allowance is
deliberately narrow: exact role read and fixed core user/session SELECT shapes
for the original actor/cookie login, not arbitrary users/options or writes.
Parsing the cookie login here narrows SQL allowance only, never authenticates.
Core compatibility, including other valid username/query shapes, is unproved;
do not broaden it to make a future runtime proof pass. Original temporary roles
and only owned filters/error handlers are restored. Unknown cleanup overrides a
checkpoint; no retry or removal of foreign callbacks to fabricate success.

## Canonical source-scoped review checkpoint

Actual toolkit prompts used: Feature Light, Feature Bloat/Structure, DS3 07 Edge
Cases then 07A Adversarial Test-Suite, final focused Feature Security. No UI/UX,
formal Database Review, physical transaction or whole-plugin security/readiness
claim. Full pre-release remains required for the next coherent RC before package.

Feature Light: backend auth protocol/file-IO risk, ten new PHP files plus direct
callers above and plan/design pointers. Deep nesting in the private root is
bounded and retains a final cleanup override; GET duplication intentionally
does not weaken/rewrite the source-sealed POST guard. No generic controller,
fallback, debug output or public surface added. Three draft issues fixed below;
no remaining scoped implementation decision. Genuine profile/auth remains gated.

Bloat Phase1 uses canonical feature-bloat-report.ps1 with the explicit uploader
base above: ten changed PHP files, zero >300 TOTAL. Each classified Consolidate
(reviewed; no additional cleanup/split justified); zero Remove/Split-now/deferred
items. Owner187, GET78, reader83, scope164, transport78; test59, cases91,
fixture70, worker98, IO47 TOTAL at first measurement (before final test additions).
Helper CODE=1 is a known reporting limitation, not executable line evidence.
Scope/GET/transport/IO/fixture responsibilities are cohesive; no further splitting
or consolidation is warranted. No WordPress registrations reference the new
owner; only its own dependency loads and isolated source-backed tests do.
Final same-base measurement is recorded in toolkit evidence.

### 07 / 07A Phase1 discovery and plan

Baseline command: PHP vendor/bin/phpunit --do-not-cache-result --filter
'RegistrationEntry|RegistrationRequestContract|RegistrationObservationRequest|RegistrationNativeLifecycle|RegistrationLifecycleRequest'.
Clean17 tests/1037 assertions, no failures/skips. PHPUnit9.6.35, isolated bootstrap;
Node node:test; CI PHP7.4/8.3 plus Node. No new dependencies/configuration. No
coverage percentage or mutation package installed. Existing grammar/policy tests
are Strong for pure logic, not owner provenance. Runtime/core behavior cannot be
inferred from them. No redundancy/removal candidate.

| Risk ID | Behavior / impact | Previous protection | Approved narrow layer / Phase2 work |
|---|---|---|---|
| R2-IO | False completion, capped prefix, unbounded reads | Parser supplied completeness only | Actual private reader with synthetic chunks/metadata/errors and virtual clocks |
| R2-AUTH | Missing/reordered guards, stale/wrong-domain nonce | Existing POST guard API tests, no new owner | Actual private owner/core guard protocol with isolated core doubles |
| R2-PIN | New valid actor/session/transport adopted during callbacks | No request-wide owner tests | Original-pin mutations; GET screen/context and POST metadata changes |
| R2-OWN | New-instance retry, nested GET/POST, cleanup poison | Object-local/model tests only | Independent child per request; actual static latch and owned cleanup |
| R2-DOWN | Protected capture/UUID/native handle before approval | Existing unavailable facades | Catch-resistant traps, exact private result/public API and unloaded native classes |

All are high-impact future-boundary risks, currently dormant. Phase2 approved:
add actual-branch isolated tests, preserve fail-closed behavior, routine scoped
fixes only. Genuine WordPress auth/HTTP and any database/native proof need a
separate target/scope approval. No concurrent database or production resource test.

### 07 / 07A Phase2 findings and test quality

Three confirmed draft defects have permanent RED→GREEN evidence (same3 tests
first fail after valid loading, then pass3/43 after the precise source fixes):

| Finding | Draft defect / fix | Layer / regression |
|---|---|---|
| E1 Medium | Monotonic clock could fall while still above start; retain/compare last sample | Synthetic read/close clock advances then regresses |
| E2 Medium | Reentry during filter cleanup occurred after the initial poison check; final cleanup rechecks poison/deadline | Nested fresh owner during owned remove callback |
| E3 Medium | Login SELECT allowance could read another user's row; narrow to original cookie-login syntax | Known required shapes pass; another-login/protected/write/ambiguous SQL refuses |

These were unpublished dormant-source defects, not evidence of a live exploit.
Harness failures were separately classified/fixed: PHPUnit discovers support
files, so core-double definitions are conditional on the explicit child marker;
Reflection setAccessible is only called on PHP<8.1 (8.5 deprecates the redundant
call). Neither bootstrap/parse failure counts as a semantic kill.

New tests use a fresh extension-free PHP child per request, preserving true
request-local static lifetime. PHP7.4 JSON extension preflight/fallback follows
existing tooling. They load the actual new source bodies and unchanged core
guard; only fixed external IO/time/drop-in/core APIs are doubled. The unavailable
gate is replaced in memory only after fixed core/IO doubles are installed, never
on disk or via a production port. Both original closed GET/POST gates are tested
for zero dependency/clock/input calls. Positive reader, parsed slashed form and
GET nonce branches defeat an always-unavailable implementation.

Deferred assertions survive caught exceptions. Exact protocol/outcome, fixed
paths and budgets, original roles/foreign filter/handler identity, erased state,
no protected IO/UUID/query/native calls are checked. No test expects a successful
real auth/storage/native result. The synthetic GET nonce is not WP issuance.
Thirteen bounded in-memory semantic controls are assertion-killed after valid
loading: always-unavailable, nonce bypass, each first/second POST/GET auth phase,
length/short-read/close, consumed retry, reentry poison, transport and GET context.
Disk source SHA256 equality is checked. No guard/expectation weakened, no skips,
test removals, artificial public success, production enable switch or native run.

Local new110/1447; related127/2484 and broader related suite recorded in toolkit;
full Node851/851, no failures/skips. Full uploader Linux suites run on exact
pushed-head CI; actual counts/links belong to toolkit's scoped closeout, not
fabricated from local subsets. Dashboard local299/1423 has two pre-existing
Windows Sodium skips; no dashboard runtime change. Its CI must prove both PHP
versions without those environmental skips. 07A remains Partially verified until
the full exact-head CI suites succeed; completion is source-scoped, not runtime.

Final focused Feature Security after fixes/QA checks type/byte/reference bounds,
fixed input path, guard order/current capability, four distinct nonce domains,
original identity and request-wide poisoning, fences, cleanup and no protected/
native handoff. No remaining confirmed scoped security defect. E1-E3 have actual
private-branch regressions. Exact raw/slashed validation is intentional (blanket
sanitize_* would corrupt provenance); no HTML/output. SQL strings are a deny-by-
default fence grammar, never executed queries. Test eval uses repository source
and hardcoded substitutions only, never request bytes. No user data unserialize.

## Safety record and remaining gate

Canonical source-only OptionB before edits:12138 files, clean uploader review
base; toolkit-snapshots/alynt-drime-backups-uploader/
alynt-drime-backups-uploader-20261023-182941.zip and matching.txt. SHA256
DDCBB0E490DF9DD4569BD932C5AAAACE08E124624F4DA048B50736BA53D51F7F.
No site/database backup, checkpoint branch or restore. OptionD is read-only.

Implementation-plan progress:R2 actual fixed owner/reader source and isolated
protocol coverage advance; genuine authentication/HTTP input-profile support
remain unproved. Production NO-GO; R3-R7 still separately gated.

Recommended next gated slice:Plugin Tester local-only V2.3 Fixed Closed-Entry
Owner Genuine Authentication and Input-Profile Feasibility / Owned Cleanup Proof
(R2). Require exact target and owned disposable user/fixture confirmation. Prove
genuine core guard/nonce behavior and assess exact HTTP/SAPI header, buffering,
metadata and bounded-input feasibility without enabling production ingress. If
profile evidence is insufficient, report it blocked rather than waive checks.
No protected/native composition, public wiring, grants, activation or rollback.
