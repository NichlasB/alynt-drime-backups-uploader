# V2.3 Closed Entry Ingress and Request-Policy Safety Foundation (R2)

2026-10-22. Approved repository-only dormant foundation. Synthetic bytes and
isolated policy API tests only;no actual authentication,WordPress,storage,native
execution,public hook/loader registration,grants,activation or rollback. Versions
remain uploader0.5.25/dashboard0.1.77. No release/package/tag/deploy.

Dashboard owns docs/V2_3_REAL_AUTHENTICATED_CLOSED_ENTRY_REQUEST_POLICY_DESIGN.md.
This record owns its first implementation,not completion of production R2.
Explicit excluded review baseline:uploader6bfea54ec2e708a76fbd0e8b4a90792b25f6661a;
dashboardbdd7de0f9194978429d8a32e15247d10f8e99de0;toolkit8ad0d030f97bc3c4150b5f779cb1b4cffea97f3a.
Review boundary is the complete new ingress/policy/tests plus these related plan
records and directly inspected grammar/loader dependencies,not the latest docs
commit and not whole-plugin readiness. Historical R1 native source seals/proofs
and unavailable production facades remain unchanged.

## Implementation and limits

- includes/class-registration-entry-ingress.php is explicitly test-loaded. Exact
  POST,single form content type with optional sole UTF-8 charset,empty query/GET/
  files/method-override sources and a strictly complete synthetic body are
  required. No superglobals,body IO,Content-Length trust or downstream callback.
- Four unique once-decoded allowlisted keys only;malformed escapes,PHP aliases,
  arrays,duplicates,controls including Unicode C1,invalid UTF-8 and unknown
  purposes refuse. Ampersand only;semicolon is opaque value data,not a separator.
  Nonce remains opaque bounded bytes,not a claim that core accepts it.
- Encoded input<=16384;content type<=128;decoded and original slashed aggregate/
  JSON<=4096;value<=2048. Exact key allowlist also bounds keys. The decoded/value
  ceilings dominate the maximum valid encoded form;there is no manufactured
  valid16384-byte example. Tests reject16384/16385 garbage and exercise exact
 2048/2049 values and4096/4097 JSON.
- Compare decoded bytes to a separately once-unslashed POST inspection,then
  return exactly the ORIGINAL slashed three domain strings. Existing issuance
  parser owns its single unslash. Missing explicitly loaded contract refuses
  without autoload. Existing domain grammar/nonce builders are not modified.
- includes/class-registration-entry-policy.php is a fixed synthetic event model,
  NOT a request owner,authentication adapter,capability or native factory. Separate
  exact GET issuer and POST policy sequences contain no callbacks or IO. Event
  tokens and cleanup/completeness inputs are forgeable DATA,never authoritative
  facts a future production owner may trust. No generic controller/provider API.
- Ordered synthetic auth/capability/nonce/context/writer-release/erasure/fresh-read/
  barrier/post-close steps,original nondecreasing virtual clocks (20s root,10s
  admission/read),consume-on-refusal/completion and erasure are exercised. The
  model does not compare actual actor/cookie/blog identity or validate nonces.
  GET completion issues no nonce. Model consumption is not cross-HTTP replay
  protection;there is no persistence,retry,deduplication or continuation.
- POST DATA projection permits only closed_receipt_observed or
  not_observed_at_barrier after the entire model and strict cleanup acknowledgement.
  Committed/uncertain/held/refused/native objects are not accepted as diagnostic
  success. Fixed400/403/405/503 refusal projections,constant no-store metadata,
  <=1024-byte envelope and all execution/dispatch/retry flags false. No response
  headers/JSON are actually emitted;no IDs,origin,nonce,exceptions or handles.

The future bounded raw reader must establish completion and inspect actual
override/header/source metadata;passing synthetic facts here proves no HTTP
framing. A genuine owner must perform fresh fixed core guards,not replay these
event labels or treat policy_complete/HTTP-shaped DATA as permission. Existing
native_entry_available remains false;runtime loaders omit both new classes.

## Canonical source-scoped reviews

Used actual wp-plugin-toolkit canonical Feature Light,Feature Bloat/Structure,
07 Edge Cases then07A Adversarial Test-Suite,and final focused Feature Security.
Scope stayed backend-only;UI/UX not applicable. No formal Database Review,
physical transaction proof,whole-plugin security audit or FULL_PRE_RELEASE claim.
FULL_PRE_RELEASE remains required at the next coherent RC before publication.

### Feature Light and structure

Feature scope:dormant wire grammar and policy DATA. Files reviewed:five new
source/test files,four related plan/design records and this owning review record;direct contract and
loader dependencies inspected read-only. Risk Other/input/auth-policy modeling,
not installed authorization. Explicit user approval covers this dormant scope.

One parser defect found/fixed:ASCII-only control rejection missed valid UTF-8
C1 controls. One test weakness fixed:malformed nonce bytes originally disagreed
with POST,so rejection alone did not establish the control check. Matching POST
regressions now expose the defect. Method-override facts and absent-dependency
refusal were made explicit;header work is bounded. Mechanical PHPDoc/array style
issues corrected without behavior changes. No remaining scoped manual decision.

Canonical bloat helper against the explicit uploader baseline reports four PHP
files,zero oversized files. Runtime classes remain below300 lines;no split or
cleanup justified. Helper CODE column incorrectly reports1 for these PHP files;
TOTAL is used as instructed,not treated as credible code-line counts. The helper
omits.mjs;the small new control file is inspected separately. No stale debug,
fallback,duplicate controller or loader/hook references. No Phase2 bloat edits
required;no architecture-sensitive split deferred.

### 07 discovery and handling,source-scoped

All12 categories considered at this boundary. Five high-priority prevention
groups below;no data-loss/critical live operation is present. Empty/malformed
data,resource boundaries,repeat/model state,input corruption,permission-order
modeling and recovery refusal apply. Actual concurrent storage,external-service
failure,WP installation variations,plugin conflicts,deletion cascades and
browser rendering require future layers or are not introduced here.

| ID | Risk/trigger | Handling and permanent regression handoff |
|---|---|---|
| E1 | Duplicate/aliased keys,mixed sources,malformed encoding,control/invalid UTF-8 | Prevent in ingress;pure matching raw/POST tests,including C1 regression;actual HTTP truncation/headers remain a genuine ingress handoff. |
| E2 | Double unslash,raw/POST disagreement,resource caps/reference values | Exact comparison and original output,transport-inclusive caps;pure boundary and reference tests. |
| E3 | Missing/reordered capability/auth/nonce domain,protected work before guards | Fixed DATA sequence refuses,positive flow plus each omitted step;not evidence of actual cookie/capability/nonce enforcement. |
| E4 | Context-change token,reentry,backward/expired virtual time,early finalize | Model consumes/erases,no restart;pure state/clock tests,not actual callback or concurrent HTTP evidence. |
| E5 | Unknown cleanup or arbitrary downstream result promoted/leaked | Exact diagnostic allowlist,false flags,erasure and strict cleanup;pure result tests. Genuine release/barrier proof remains outside scope. |

Strategies:prevent E1/E2;detect-and-refuse E3-E5. No retry,transient,normalization,
new UI,logging,state repair or database operation added. Second-order effect of
one unslash is protected by original-slash positive fixtures and downstream
existing issuance parsing. No entity/deletion cascade introduced.

### 07A inventory,fixes and non-vacuity

Baseline PHP9.6.35 RegistrationRequestContractTest:3 tests/94 assertions,clean.
Existing pure domain tests protect ordered full nonce domains and raw/unslashed
limits;they cannot protect a nonexistent ingress or genuine request integration.
No consolidation/removal candidate;new first-party behavior merits narrow tests.

| New group | Layer/first-party code | Assertion value / classification |
|---|---|---|
| RegistrationEntryIngressTest,valid/slash case | Pure ingress+existing issuance | Exact original three strings and once-unslashed nonce;Strong. Always-unavailable fails. |
| Ingress malformed/aligned controls/transport/types/imports/limits | Pure ingress | Exact false,matching POST,C1,2048/2049,4096/4097,references/override denial;Strong. Simulated downstream counters are illustrative only,not real protected-read instrumentation. |
| RegistrationEntryPolicyTest,GET/POST positive | Isolated DATA API | Independent fixed events,exact result,false flags,no issued nonce;Strong for model only. |
| Policy each omitted step/context/clock/reentry | Isolated DATA API | Each event/order plus terminal erasure and strict virtual deadlines;Strong for model,not genuine guard proof. |
| Policy downstream/cleanup/refusal | Isolated DATA API | Strict tokens/types,all output keys,unknown cleanup refusal;Strong. No synthetic native success. |
| Node entry controls | Actual PHP source in memory,isolated CLI | Twelve semantic bypasses assertion-killed AFTER valid loading and successful unchanged-source exercises;disk SHA256 unchanged. One source-only loader/native-disable check. |

RED:E1 aligned C1 U+0080 test fails against the first ASCII-only implementation
(one test,four assertions,one intended array-versus-false failure). GREEN:same
test passes after Unicode Cc rejection,including both C1 endpoints. PHP8.5 test
reflection deprecation corrected by enabling accessibility only on PHP<8.1.
Node control harness initial eval/bootstrap/target mistakes were test-harness
failures,corrected before evidence;they are NOT semantic kills or parser REDs.

Twelve controls:always-unavailable,method,body completeness,raw/POST agreement,
duplicate-collapsing parse_str replacement,C1 guard,double unslash,cleanup,
guard ordering,nonce role substitution,admission expiry,terminal consumption.
No mutation is written to source;baseline and altered evaluation each required.
No line-coverage percentage used or dependency installed. No tests weakened,
removed,skipped or excluded. Full configured Linux PHP/Node CI is the final
whole-suite execution evidence,not a claim that this scoped review audited all
historical tests. Local full Windows PHP suite is not rerun;its historical
platform limitations are not refreshed by targeted tests.

### Final focused Feature Security

After fixes/tests,reviewed strict type/length/encoding/source contracts,one
unslash,policy versus authority,nonce role separation,erasure/replay limits and
fixed redacted output. No remaining confirmed scoped security defect. E1 has
permanent pure regression protection. Strict validation refuses malformed
input;sanitize/coerce would change intent and is deliberately not substituted.
No output HTML,SQL,file operation,actual auth/nonce API or registered surface
exists in this delta. Test-only eval consumes fixed repository source,never
request bytes. No public owner/factory/native availability bypass introduced.

## Executed local QA and publication evidence

Targeted new PHP11 tests/899 assertions;related PHP17/1037,no failures/skips/
warnings. New Node13/13 (twelve controls plus disabled-wiring/source check).
Configured uploader WPCS250 files passes;existing PHPCompatibility ruleset
deprecation notice remains tooling metadata,not a new source violation.
Build,deterministic runner verification and portable runner syntax pass without
generated source changes. Full final Node851/851,no failures/skips,and configured
npm audit0 vulnerabilities. Dashboard preservation PHP299/1423 with two existing
Windows Sodium skips,WPCS138/structure/build pass. Full final Node and exact-head
CI counts/run links are recorded in toolkit's scoped evidence after completion.

Canonical source OptionB before edits:12115 files from clean uploader baseline,
alynt-drime-backups-uploader-20261022-222916.zip and matching.txt under
Toolkits/toolkit-snapshots/alynt-drime-backups-uploader;SHA256
094701983CC22586281C8D688F7B9D027BD65CAE976F3B00BCF508E22240E93A.
No site/database backup or checkpoint branch;no rollback applied. Read-only
OptionD closeout:0 missing,3 changed(plan,Git index and ignored PHPUnit cache),
6 new approved artifacts. No restore/deletion performed. Source-only restore
protection does not establish WordPress state.

Implementation-plan progress:R2 strict ingress and isolated policy DATA advance;
genuine raw-reader/authenticated owner/issuer integration is NOT proved. R1
historical fixed-local evidence retained;production NO-GO,R3-R7 still gated.

Recommended next gated slice:local-only V2.3 Fixed Genuine Closed-Entry Request
Owner and Bounded Raw Reader Integration Design (R2),design-only. Define the
fixed authenticating owner and earned transport facts without trusting model
tokens,before genuine WordPress/owned proof. Keep storage/native execution,
hook/loader/wiring,grants,activation and rollback disabled.
