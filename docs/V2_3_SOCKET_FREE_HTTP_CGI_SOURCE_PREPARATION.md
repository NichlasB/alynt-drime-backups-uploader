# V2.3 Socket-Free Experimental HTTP/CGI Source Preparation (R2)

2026-10-23. Autonomous remaining-five batch;dashboard owns
docs/V2_3_DEDICATED_BUFFERED_HTTP_CGI_SUPERVISION_DESIGN.md. This is test-support
under scripts/tests only,not production admission,a general server/parser,another
product DATA authority or a qualified WordPress profile. The new feature tests
perform no HTTP/network,PHP/WordPress/authentication/storage/native execution,
temporary config/listener/process operation,site inspection or wiring change.
Pre-existing preservation suites retain their sandboxed CLI/pipes/watcher fixtures;
these are not new native HTTP/CGI or WordPress proofs. Versions0.5.25/0.1.77 unchanged;
entry_available=false,grants/activation/rollback off,no package/release/tag/deploy.

Coherent source-review excluded base:5f12f98630cc8cdf55aab77fa08bd3c6eaaf0af1,
before design plan commit fd9614f3f93713e20d818dd4bea3adbbf7b704e8. This boundary
will remain fixed for final integrated review,not move to a verification-only tail.

## Task5: original epoch and cleanup reserve

HttpCgiBudget holds one finite nonnegative monotonic origin. Headers/body/handoff/
cleanup cutoffs are strict1000/2000/4000/10000ms offsets,not fresh per-phase waits.
Ordered work stages,clock regression/nonfinite/type/coercion rejection and permanent
work poisoning. Cleanup can still be checked after refusal but cannot revive work,
rewind time or extend10000ms. Remaining durations are conservatively floored;zero
means do not begin another wait. No actual timer,promise,process,signal or IO call.
This accounts for a future fixture deadline;it does not interrupt blocking work or
prove physical settlement. Existing fixed owner10s/20s and native source unchanged.

Local7/7 virtual-clock tests include valid nonzero fractional epoch through all
stages,each exact cutoff,phase skip/regression,cleanup-to-work,clock rollback,
nonfinite/negative/overflow times and hostile values without coercion hooks.
An always-unavailable implementation fails the positive timeline. Canonical
Feature Light:2 source/test files plus this record/plan,Other/test accounting,
issues0. Bloat/Structure not indicated for this small cohesive module. Final
focused Feature Security:strict time types/order,non-resettable budget and refusal
reviewed,no confirmed defect or new regression handoff. Actual preservation/CI
counts and publication links are recorded in toolkit source-scoped closeout.

## Task6: fixed standard-parser header snapshot policy

The policy validates exact primitive method/target/version/raw-header snapshot
keys and own data properties,GET/POST /probe.php HTTP/1.1 only. Host owned.invalid,
Connection close,one canonical CL0..16384,exact POST form type,GET empty/no type.
Unknown/duplicate(case-insensitive)/control/folded/oversized/missing/type-confused
fields reject;no merged-header trust,TE/chunked reconciliation,query/absolute URL,
cookie/authentication/forwarding/compression domain. No body,URL or header decoding.
Positive GET and zero/small/max POST plus25 isolated tests;accessor fixtures prove
getters are not invoked. Fixed plain snapshots from a future standard-parser
adapter are the intended input,not hostile same-process proxies or wire bytes.

Canonical Feature Light:2 new source/test files,direct budget/design dependencies,
Other/test semantic framing;issues0. Bloat/Structure not indicated for these small
cohesive files. Final focused Feature Security:duplicate/raw evidence,exact method/
length/target/host/type,control/unknown-key and descriptor checks reviewed;no
confirmed scoped defect. It is not HTTP parser/framing or genuine auth proof.
No production import or native operator integration. Continue bounded body task7.

## Task7: bounded raw body and one-shot erasure

FixtureHttpBody creates its own original budget and validates headers before one
allocation of exactly declared0..16384 bytes. Nonempty Buffer chunks are copied
once into fixed storage;no chunk list,decoding,seek,spill,stream/socket or native
handle. One-byte fragmentation remains linear and bounded by total accepted bytes.
End requires exact length and own strict complete=true/aborted=false/error=false
facts;future runtime must earn those facts from Node's actual parser,not a client.
Malformed/truncated/extra/late/repeated data or facts poison and erase. Take requires
completed end and remaining original handoff budget;it copies once and erases the
private buffer. Its synthetic byte copy is not an authority/receipt and will be
consumed/erased by task8's fixed handoff. Cleanup cannot re-arm or reset time.

10 isolated tests include max16KiB at1/7/1024/16384-byte fragments,zero GET/POST,
caller mutation isolation,pre-end/repeated take,truncation/type/overrun/empty/extra
data,EOF/accessor facts,exact cutoffs and regressing clock. Canonical Feature Light:
2 new source/test files plus direct budget/header dependencies,Other/memory IO;
issues0. Final focused Feature Security:bounds before copy,method/raw preservation,
one-shot refusal and private erasure reviewed;no confirmed scoped defect. Final
task8 review adds cross-component/counterfactual erasure proof,not native claims.
No operational integration or change to old CGI operator. Continue fixed handoff.

## Task8: fixed byte handoff and canonical source-scoped checkpoint

FixtureCgiHandoff composes the actual header/body/budget modules with the existing
fixed FastCGI encoder. One prepare consumes the exact completed body and erases
its temporary copy in finally;one later take rechecks the original handoff cutoff
after encoding. No bytes escape on partial/end failure,late encoding,reentry or
bad builder output. Cleanup/refusal zeroes the owned wire Buffer,including its
returned alias;it cannot erase independent caller/kernel copies or undo bytes
already physically forwarded. There is no socket/native forwarding in this source.
Marked absolute /probe.php paths are lexical checks only;physical source/hash/
directory/process ownership must be earned in a future approved operator.

Review found two bounded harness defects,not production plugin defects:

- E1/Error Handling,Medium:non-Buffer builder output could break #fail's cleanup
  handling. Validate Buffer type before retaining a wire reference;fixed error
  code,permanent refusal and raw erasure tested with throwing/wrong-type encoder
  source doubles. No public callback/provider/injection seam was added.
- E2/Edge Case,Medium:the old CGI builder synthesized form CONTENT_TYPE even for
  GET,contrary to this candidate's empty GET policy. New actual-encoding regression
  RED failed an expected-undefined outcome assertion,not import/setup. Encoder now
  emits CONTENT_TYPE only for POST;unchanged regression GREEN. POST/incomplete
  STDIN and response decoder tests retained. This is a source correction,not a
  rerun/refresh of the historical native proof at5f12f98.

### Review inventory and outcomes

Explicit coherent base stays5f12f98630cc8cdf55aab77fa08bd3c6eaaf0af1 through this
entire source span,not the latest plan/verification commit. Changed sources:four
http-cgi modules,existing cgi-profile-codec and five new Node test files;direct
predecessor codec/process/transport tests and owning design/plan records inspected.
No production loader,WordPress hooks/auth/nonce domains/native source seal changed.

Canonical Feature Light:10 executable/test files plus owning docs,Other/test-only
framing/buffer/accounting;issues2,auto-fixed2,manual0(E1/E2). No UI;UI/UX N/A.
Canonical Feature Bloat/Structure:helper with explicit base reports0 because it
excludes .mjs,not because the feature is empty. Supplemental measured TOTAL:
budget19,headers26,body36,handoff34,codec38;tests24/20/29/25/49. No executable CODE
count inferred. All10 classified Consolidate(reviewed,keep cohesive);zero oversized,
split,defer/removal or Phase2 structure edits. No unrelated cleanup or test removal.
Direct references are confined to tests/test-support;no native operator or runtime
import of the new helpers. The codec's old operator caller is acknowledged above.

Canonical source-scoped03 Phase1 inventory:E1 only;loading/success/empty/form UI,
external-service/database confirmations and permission UX N/A to socket-free
test-support. Phase2 fixes E1 with fixed redacted error,permanent consumption and
finally erasure;no retry/fallback/logging or user/admin state added. Error-handler
failure is specifically tested,not silently ignored.

Canonical source-scoped06 Phase1:0 confirmed performance issues,No updates needed,
no Phase2 optimization. Body0..16384 in one fixed allocation,nonempty fragments
copy once with no retained chunk list;one-byte fragmentation remains linear and
bounded. Handoff adds bounded copy/encoding,not unbounded payload recursion. No
queries,network,assets,cache or background work. This is a complexity/resource
property plus representative memory tests,not physical HTTP timing qualification.

Canonical source-scoped07 Phase1:E2 only uncovered defect;empty GET/POST,exact max/
over-limit,types/controls/aliases,clock boundaries,reentry/truncation,failure and
cleanup inventories mapped to existing isolated coverage. WordPress/DB/permission/
real browser/deletion cascades N/A here,remain product/runtime handoffs. Phase2
fixes E2;permanent byte-level regression handed to07A. No nonce/auth policy change.

Canonical source-scoped07A Phase1 baseline58/58 related tests(after E1/E2 fixes,
including predecessor codec8),no skips/failures,Node assertions not separately
reported. Existing budget7/header25/body10/handoff8 groups are Strong for their
pure unit contracts;erasure observability Useful but improvable until capture
instrumentation. No misleading runtime/auth claim or consolidation candidate.
Phase2 recommendation Proceed:ten fixed in-memory semantic controls and explicit
retained-buffer/failing-encoder probes;no dependency/mutation-tool installation.

Phase2 adds11 tests:ten independent guard removals are assertion-killed after
successful imports and valid positive setup,not parse/bootstrap errors. Controls
cover original cutoff,duplicate fields,body ceiling,complete-message fact,cleanup
erasure,take erasure,pre-copy guard,post-encoding deadline,finally erasure and
builder type. The pre-copy spy proves refusal before typed-array RangeError could
mask a removed guard. Retained allocations and the actual builder argument are
observed only via labeled fixed-source instrumentation in memory;no test getter or
provider is installed in runtime source. Four controlled source SHA256s unchanged
on disk. Tests use <=16KiB synthetic buffers,virtual clocks and no external state.

Final07A status:Clean at this source-scoped automated layer;full Node935/935,
0 failures/skips,61 new tests over batch baseline874. Scoped61 plus unchanged
codec8;integration positives decode actual records independently,not duplicate the
encoder algorithm. No tests consolidated/removed,production defects0,harness
defects2. Physical HTTP/CGI,authentication and final whole-plugin security remain
outside this status. No coverage percentage fabricated or broad historical row
refreshed. Formal13/FULL_PRE_RELEASE remains for a coherent release candidate.

Final canonical focused Feature Security after all fixes and validation:strict
primitive/raw-header domains,original clocks,bounds-before-copy,one-shot outcomes,
lexical versus physical identity,buffer erasure,closed entry and lack of native
handles reviewed. No unresolved confirmed scoped security defect;E1/E2 have exact
permanent regression coverage. No WordPress sanitization/nonce guard is waived.

Local preservation:Node935/935,focused PHP141/1767,no failures/skips;uploader
WPCS255(existing ruleset metadata deprecation),build/runner verify pass. Dashboard
PHP299/1423 with2 existing Windows Sodium skips,WPCS138/structure/build pass.
Exact-head CI/status for every pushed task belongs in toolkit closeout. Source-only
evidence cannot certify actual standard-parser snapshots,worker termination,
configuration/Node version drift,wire framing,origin/HTTP trust or WP bootstrap.

Source-only canonical OptionB before this batch:12248 files at clean5f12f98,
toolkit-snapshots/alynt-drime-backups-uploader/
alynt-drime-backups-uploader-20261023-225259.zip plus matching.txt,SHA256
EAAFFD9069065589533F9CFF55A9FEE2DA83A103D0711925DD1F15611C3F482A.
No site/database backup,restore apply or checkpoint branch.

Implementation-plan progress:R2 gate B's planned source preparation now includes
all four distinct components and integrated adversarial checkpoint. The remaining
five tasks reach batch8/8 only when each exact pushed-head CI is verified;no extra
release/package or cleanup task is manufactured. Runtime profile and genuine HTTP
roots remain Blocked;source-only tests do not substitute for operational evidence.

Recommended next gated slice:**isolated disposable fixed HTTP-to-CGI supervision
no-fault/resource-limit and owned cleanup proof (R2)**. Obtain explicit owned
temporary configuration/listener/process/file scope before execution. Preserve
existing LocalWP services,WordPress/database/application state and wiring. Begin
with fresh no-fault/refusal,actual parser/one-slot/original-epoch/worker-settlement
and cleanup proof before controlled fault cases;unknown ownership or budget
overrun is Blocked. No actual WordPress/auth/native feature or production ingress.
