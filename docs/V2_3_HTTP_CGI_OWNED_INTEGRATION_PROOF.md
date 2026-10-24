# V2.3 Fixed HTTP/CGI Owned Integration Proof (R2)

2026-10-24. User authorized continuation after the explicitly described disposable
HTTP-to-CGI proof gate. Scope is standalone owned temporary configurations,
listeners, processes, files and cleanup only. No WordPress, SQL, application state,
production ingress/wiring, grants, activation, rollback, package or release.
Coherent excluded source base:66418eae3bcfcfb0fe3bfe22fd229fe9bf80700e;
dashboard:c917b2d9c892672583c39c3918e04dc077715632. Versions unchanged.

Parent:dashboard docs/V2_3_DEDICATED_BUFFERED_HTTP_CGI_SUPERVISION_DESIGN.md.
This batch advances its runtime-proof prerequisite, not genuine HTTP authentication
or R3-R7. The requested eight tasks are a ceiling, not permission for filler or a
new operational boundary. Complete necessary source integration and isolated
regressions, then ordered no-fault/refusal/resource/failure experiments with
independent owned settlement. Stop on failed verification or unknown ownership.

## Task 1: remaining-budget process inspection and settlement

The existing explicit CGI owner accepts an optional remaining-credit reader.
Fresh identity inspection, child kill/join and console/listener absence inspection
each sample it anew and clamp to their unchanged fixed caps. Invalid/expired
credit refuses before the next operation. Default callers retain previous caps;
identity/executable/parent/console/listener guards are unchanged. This is not an
actual timer or cleanup proof; the future operator must supply the original epoch.

Isolated actual-source process-double regressions:10/10, no skips, including expiry
during inspection refusing before kill, shrinking credits, fixed cap preservation,
foreign/console refusal and malformed timeout rejection. No PHP/process/network
operation. Publication evidence is recorded after exact-head CI completes.

## Task 2: transport work/cleanup credit

The existing fixed loopback CGI transport optionally samples the same original
epoch before socket creation, again before forwarding after connect, and before
cleanup join. Fixed four-second work/one-second join caps cannot expand. Expired
cleanup still destroys its owned socket but never reports unobserved settlement.
Default callers retain their limits. Actual-source EventEmitter socket regressions
cover zero operations on invalid credit, delayed connect without writes, valid
forwarding and expired cleanup refusal. These tests do not open real sockets.

## Task 3: one-connection standard-parser boundary

The explicit test-support HTTP owner composes the existing header/body/handoff
with Node HTTP events. It closes its listener after one accepted connection,
retains untruncated rawHeaders, rejects alternate ingress events and reuse,
uses actual message.complete/aborted facts, and forwards at most once. Absolute
connection timers at one/two/four seconds share the original epoch; cleanup
cannot extend ten seconds. Refusal erases buffered bytes immediately. Private
parent observation is not a general HTTP response proxy or authenticated entry.
Actual-source isolated event/clock regressions prove valid handoff and refusals;
real parser/timer/socket/worker evidence remains pending the explicit operator.

## Task 4: shared marked fixture and exact cleanup

Extracted the existing CGI fixture configuration/source and nonrecursive cleanup
into one owner shared by the old explicit operator and the new candidate. Every
allowed file is now lstat/size checked before reading through it or deleting any
file. Random temporary root, exact owner/source/config hashes, file allowlist and
absence proof remain mandatory. Owned disposable filesystem regressions exercise
tampering, unknown entries, directory substitution, oversize and successful cleanup;
they do not launch PHP or access a site. The historical operator was not rerun.

## Task 5: explicit ordered operator

New operator is inert without the exact Windows-only flag. It requires a warm
independently identified owned worker before accepting HTTP, separately bounds
preflight, and supplies the original remaining epoch to every subsequent process
and transport wait. It orders no-fault/refusal before body/parser/header/time/
disconnect/pipeline/fatal cases and stops at the first failed case. Every case
requires physical worker/console/listener/socket/file cleanup and before/after
existing configuration/service identity preservation. Unknown cleanup retains
the exact marked root and reports Blocked; no blind retry. Syntax/CLI refusal
validation is not native proof. Actual ordered execution is the next task.

## Task 6: canonical source-scoped review and regression hardening

Explicit excluded baseline remains66418eae3bcfcfb0fe3bfe22fd229fe9bf80700e,
not the latest verification commit. Scope:11 changed executable source/test files,
plus directly used budget/header/body/handoff/codec/lifetime/inspection/PHP fixture
dependencies and this record. No production plugin code or public contract.

Feature Light:Other/file operations/process/loopback HTTP,2 Medium error-handling
findings,2 fixed,0 manual decisions. UI/UX/WP hooks/translations/SQL/third-party
service categories N/A:standalone test support only. Bloat/Structure unnecessary:
small cohesive helpers,shared fixture replaces duplicate ownership logic,none near
300 lines,no additional splitting justified. FULL_PRE_RELEASE remains deferred.

Source-scoped03 Error Handling,06 Performance,07 Edge Cases then07A Adversarial
were run using the canonical prompts. Phase1 was read-only;Phase2 routine fixes
and tests remained within approved standalone scope. Inventory/disposition:

| ID | Severity / category | Evidence and action | Regression layer |
|---|---|---|---|
| E1 | Medium / early refusal error recovery | Request/response error listeners were attached after header acceptance;rejected input could leave late errors unhandled. Moved handlers before every return. RED:doesNotThrow failed on `owned late error`;same regression GREEN after fix. |Actual-source HTTP event doubles;native parser behavior remains separate|
| E2 | Medium / partial fixture creation recovery | Failed write after mkdir could lose the retained root from operator output. Preserve exact owned root on typed creation error;no blind deletion. Fault-injected actual-source regression proves root and cause retained without delete. |Isolated filesystem API failure;not physical disk-fault proof|
| W1 | Low / environment-sensitive predecessor tests | Default-concurrency full Windows run963/965,two unchanged3s positive pipe timeouts. Both files unchanged from baseline;isolated18/18 and full concurrency2 run965/965. No assertions,limits,source or CI configuration changed. |Windows scheduling limitation,not a new HTTP defect|

Performance inventory:0 additional scoped defects. One connection/request policy,
4KiB headers,16KiB body,18KiB CGI request,16KiB CGI response and original-epoch
wait caps are bounded;no DB/assets/cache/production background work. OS suspension
is failure,not hard-real-time qualification. File ownership checks are not cached
across deletion. No machine/service exhaustion experiment is included.

Edge inventory covers empty/exact/oversize inputs,framing,duplicate requests,
disconnect/late errors,expiry during cleanup,tampered/unknown/oversized files,
partial creation and fail-closed recovery. WordPress/user/permission/schema and
plugin-conflict/cascade categories are not exercised or claimed by this scope.
07A classification:43 targeted checks pass/no skips;actual-source owner/event
doubles are Strong for local contracts,not real parser/process/auth evidence.
Owned file tests are real temporary filesystem tests,not application storage.
No redundant deletion or suite restructuring. Three in-memory semantic removals
(actual complete fact,request reuse,immediate erasure) import successfully then
are assertion-killed. Required positive handoff uses real composition and bytes;
an always-refusing owner cannot pass. Mutation copies never edit on-disk source.
Full concurrency2 suite is rerun after these fixes;CI and native evidence follow.

Final focused Feature Security after fixes/targeted validation:fixed loopback
destination,exact script/root/hash/parent/executable/console guards,strict ingress,
no protected/authenticated reads or write-capable application handles,no secrets
in output,nonrecursive bounded cleanup and refusal on uncertain ownership. No
remaining confirmed scoped security defect;no new regression handoff. This is
not prompt13 whole-plugin audit,Database Review or authenticated HTTP proof.

## Task 7: ordered physical qualification and truthful outcome accounting

First ordered attempt:10 cases passed,then pipeline accounting failed. Node's
real parser rejected the fixed illegal pipelining after Connection:close before
request#2 existed:parser_error,requests1,forwarded0. All11 attempted cases had
verified worker/console/listener/socket/file cleanup;existing service/configuration
identities were unchanged. Native fatal was NOT RUN. This is not a12-case pass.
Completed original connection-to-cleanup observations ranged3564..6615ms;
startup/preflight was separate. No retained fixture or uncertain cleanup.

Harness-only correction accepts exactly that early parser rejection or exactly
the guarded second-request refusal with at most one handoff. Generic disconnect,
deadline,success,wrong counts and multiple handoffs still fail. Source-backed
predicate negatives cover every other tested reason/count combination. CGI fatal
diagnostics additionally retain only a specific one-second fatal boolean from
FastCGI STDERR,not a raw trace or generic-refusal-as-timeout inference.21 related
checks pass/no skips. Final focused security rerun:fixed scope and guard/budget
limits unchanged,no remaining confirmed defect in this correction. One fresh
ordered attempt with fresh no-fault/refusal prerequisites follows;no blind retry.
