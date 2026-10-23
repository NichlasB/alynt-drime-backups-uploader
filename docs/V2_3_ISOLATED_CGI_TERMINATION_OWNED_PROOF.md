# V2.3 Isolated Disposable CGI Bounded-Termination and Cleanup Proof (R2)

2026-10-23. Explicit user approval covers owned temporary configurations,
listeners/processes and cleanup only. This is safe-local batch task3, not eight
invented maintenance slices. Uploader0.5.25/dashboard0.1.77 unchanged. Excluded
coherent review bases:uploader6135afb9da78b0135cb6288dde2b170c58661d61,
dashboard8ed209d386e4635bd84f1c27841a128f4a1c7457,
toolkit0310583f0e8ccf70bfecebc1ae8252220cc7297b.
Dashboard owns docs/V2_3_BUFFERED_INPUT_PROFILE_FEASIBILITY_REVIEW.md and the
parent buffered-input/HTTP design. No WordPress/authentication/database/native
feature execution, production code/loader, grants, activation or rollback change.
entry_available=false. No package/release/tag/deploy.

## Scope, process ownership and accounting

Borrow only the resolved LocalWP PHP8.5.1 NTS cgi-fcgi executable. The standalone
fixture lives under a fresh random OS-temp directory, never the WordPress root or
LocalWP generated configuration. No existing CGI/nginx/database process is started,
stopped, restarted or connected to. Novamira HQ availability/exact profile was
established in the parent read-only analysis; no WordPress Ability is invoked.

The explicit Windows operator requires --run-owned-cgi-profile. CI never runs it.
Each case uses a new marked configuration/script, minimal child environment with
no inherited credentials, empty scanned-ini path, one ephemeral127.0.0.1 listener
outside10002-10005, and a single PHP worker. Independent CIM/listener inspection
checks exact child/parent PID, executable, marked temp ini/command line and listener
identity before sending any FastCGI bytes or terminating a live owned child.
Windows may attach one conhost.exe:exact system executable/name, direct parent,
non-older creation and no descendants required. Any other child refuses. Console
PID absence is independently checked after direct child termination and pipe join;
no broad process-name kill or tree-kill command is used.

Owned configuration:execution1s,input5s,post16K,memory32M,input-vars16;no prepend,
append,user ini,session autostart,URL IO or process-launch functions. Logging is
enabled only in this disposable configuration to observe the synthetic fatal;
output/diagnostic caps apply. No existing ini/environment/service is modified.
Fixed single-ID FastCGI records have aggregate16KiB/record8KiB/64-record response
bounds and4s client deadline. Request/settlement elapsed is measured from one
monotonic epoch, never reset; each case must settle inside9.5s including cleanup.
Inspection itself is separately bounded5s,child join2s and client closure1s.
This is observed bounded experimental settlement, not a hard-real-time OS SLA or
the entire original10s/20s HTTP-root contract.

Cleanup validates exact temp parent, marker, original source/configuration hashes,
regular-file allowlist/no links/no directories and file-size ceilings. It removes
only verified files and the now-empty directory, never a recursive broad target.
Unknown identity/cleanup retains the owned directory and fails;no retry can turn
that result into proof. An independent absence/hash/inventory check settled each
retained startup attempt before the next fresh prerequisite.

## Actual final ordered proof

| Case | Actual result | Request plus cleanup |
|---|---|---|
| No fault |15 synthetic body bytes conserved;buffered/seekable input,EOF/read/close and owned ini values verified;no WP or mysqli loaded.|2927ms|
| Refusal |GET405 before fixture body read/stage;clean FastCGI completion.|2931ms|
| Native execution timeout |Actual1s fatal observed;request END status0,shutdown fatal witness ran;the PHP worker was still alive. Explicit supervisor cleanup then settled it.|4299ms|
| Client disconnect |Client socket closed;script demonstrably continued and completed its delayed stage;worker still alive. Explicit cleanup settled it.|3636ms|
| Incomplete STDIN |Worker alive,fixture execution stage absent after700ms;external termination required. Worker terminated before releasing stalled socket;stage remained absent.|3657ms|

All5 cases passed with exit+close, destroyed pipes, absent owned console/listener,
closed client socket and removed fixture directory. Existing LocalWP preservation:
before/after SHA256 identity of10 generated .ini/.conf files plus5 service listener
identities unchanged. No SQL/checksum query was made:do not refresh the historical
77 application checksums or claim database transaction/storage proof.

Native fatal and client disconnection are explicitly **not worker exit**. The
shutdown witness is not proof of normal finally/role/filter cleanup. Incomplete
input was observed only for700ms and externally stopped,not allowed to run to
max_input_time. No claim that PHP's timer interrupts every blocking input call.
No ordinary LocalWP/production profile,HTTP front door,framing/slow-client ceiling,
pre-root reception/bootstrap containment,genuine Tools/admin-post auth or minimum
WordPress/PHP runtime is qualified by this standalone FastCGI experiment.

## Failed prerequisites and corrections, not erased history

Initial PS5 inspection failed before fixture creation because inherited PS7 module
paths prevented Get-FileHash. Corrected only the helper child's PSModulePath.
Four fresh no-fault startups then refused before requests:the single-process guard
detected a Windows console host. Each retained directory was independently settled
after all marked CGI/console processes and exact listener were absent;only its
verified files/empty directory were removed,no process terminated during recovery.
The first two checks did not capture the child identity;later diagnostics established
conhost.exe rather than an assumed PHP worker or fast startup exit.

Three no-fault requests then refused on a decoder assumption:PHP emitted nonzero
reserved END_REQUEST octets. They are not protocol/application status;the decoder
now ignores only those3 octets and retains exact length,version,ID,type,status,
aggregate/record/trailing/duplicate guards. No response bytes are published in final
diagnostics. See [FastCGI END_REQUEST specification](https://fastcgi-archives.github.io/FastCGI_Specification.html#s5.5).
Next ordered no-fault/refusal passed but timeout proof refused because disposable
logging was off;no fatal was inferred from a completed response. Enabling bounded
fatal logging only in the owned config allowed the final full ordered run above.
All failed request attempts settled their owned processes/listeners/files;no
ordinary service or application state changed. No blind continuation past an
unsettled ownership failure or skipped prerequisite.

## Source-scoped reviews and validation

Canonical Feature Light:11 new test/operator source files plus directly reused
owned-stdio runtime and selected-native lifetime support. File Ops/Other(loopback
CGI/process supervision);not UI,REST,AJAX,DB,cron or third-party API. Startup,
console identity,reserved-byte and fatal-observation issues corrected as described.
Final review also added explicit upper-port/service-port rejection and prevents an
IO-error flag from skipping live-child identity verification. A final transport
review found that early END could settle success before a later trailing fragment:
completion now awaits actual socket closure;three new source-backed socket-double
tests cover late bytes,complete/incomplete closure and argument refusal. Fresh full
ordered prerequisites/fault/cleanup proof after that fix passed as tabulated above.
No production change or new fault authority.

Canonical Feature Bloat/Structure:explicit uploader base above,canonical helper
reports2 PHP files TOTAL18/11,none oversized. Its CODE=1 reporting limitation is
not executable-line evidence. Supplemental actual .mjs/.ps1 TOTAL counts38/40/42/
80/34/16/10/35/13;none oversized. All11 classified Consolidate(reviewed,keep cohesive);
no removal/split/defer, no Phase2 structure edits necessary. Reference search keeps
operator/fixture code test-only;no plugin registration or CI invocation of native
operator. UI/UX N/A:backend-only,not a rendered/admin user surface.

Permanent isolated Node17/17 includes byte fragmentation/truncation/poisoning and
actual owner-source positive/negative process doubles:foreign identity,console,
listener and residual closure cannot pass;each missing required check breaks its
negative assertion. PS5 actual inspection source with shadowed CIM/network APIs:
28 checks cover legitimate zero/one console and wrong child/path/name/age/count/
descendant/parent/executable/command/listener/closure. No native inspection in that
fixture run. PHP CLI-boundary1/4 refuses before fixture operations. These doubles
are not native proof;the separately executed5-case run is the actual CGI evidence.

Local focused PHP141/1767,full Node874/874,no failures/skips;uploader
WPCS255(existing ruleset metadata deprecation),build/runner verification pass.
Dashboard preservation and exact-head CI evidence belong in the toolkit's new
source-scoped record. No formal DS3/Database/whole-plugin readiness refresh.
Final focused Feature Security:fixed destinations/paths/minimal environment,
ownership-before-request/kill,capped parser/output/IO,failed settlement,source hashes
and exact cleanup reviewed;no remaining confirmed scoped security defect. No
security regression handoff beyond the new owner/codec/inspection regressions.
FULL_PRE_RELEASE remains required before packaging a coherent candidate.

Source-only restore point before edits:toolkit-snapshots/alynt-drime-backups-uploader/
alynt-drime-backups-uploader-20261023-220446.zip plus matching.txt;12216 files,
SHA256 B75D41B32EB1858CDB6A47681203E15CF4C6D650A38A58C68CF1C44E0F0E55BF.
Not a site/database backup,restore apply or checkpoint branch.

Implementation-plan progress:R2 gate B gains concrete standalone worker-settlement
feasibility and failure distinctions;ordinary profile/HTTP roots remain Blocked.
The next meaningful item changes category to an explicit resource/worker ownership
architecture decision,not filler maintenance to reach8. Production NO-GO,R3-R7 gated.

Recommended next gated slice:**design-only V2.3 Dedicated Buffered HTTP/CGI
Supervision and Pre-Root Resource Profile Design (R2)**. Select a fixed candidate's
absolute reception/worker/cleanup epoch and owned-worker isolation before any new
HTTP/configuration experiment. Existing shared LocalWP workers cannot be killed to
enforce an individual request. No watchdog/proxy/profile selector is installed or
authorized by this proof;storage,native feature execution and production ingress
remain disabled.
