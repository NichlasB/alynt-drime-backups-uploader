# V2.3 Blocker Rollback Acknowledgement Correction and Owned Proof

2026-10-22. Approved Plugin Tester local-only harness repair and ONE fresh ordered
proof completed successfully. Uploader0.5.25/dashboard0.1.77 unchanged. Existing
application state,production wiring,grants,activation and rollback execution stay
untouched. No package,release,tag,deploy or retry.

## Scope and provenance

Confirmed exact `plugin-tester`,local-only,LocalWP “Plugin Tester”,
`http://plugin-tester.local`,WordPress at
`C:\Users\Captain\Local Sites\plugin-tester\app\public`,database10004/web10005.
Owned disposable users/storage/listeners/processes and cleanup only. Initial and
final bundled mysql show77 application tables/0 fixtures/other sessions/active
transactions. Novamira complete inventory resolves profile;doctor reports expired
saved OAuth,no credential repair. No live-site access.

Explicit excluded review bases:uploader2f2e364e6ebf397118819c576205618f459d6b35,
dashboard72fe63d07b9ba9ddf7b0e365ddd22e07ee317a57,
toolkitf506bc75ecef011b9325e3fe28645060eafe7b68. Review covers the coherent helper
correction,private diagnostic grammar/source seals,actual-branch tests and owning
plan records. Parent [failed attempt](V2_3_CACHE_AWARE_EXACT_WAIT_OWNED_PROOF.md)
remains historical;its failure/held-not-run evidence is not rewritten as success.

Canonical OptionB source restore point before edits:12092 files,clean uploader
master at baseline;archive
`C:\Users\Captain\Documents\AI Workflows\Toolkits\toolkit-snapshots\alynt-drime-backups-uploader\alynt-drime-backups-uploader-20261022-214152.zip`
and matching `.txt` sidecar;SHA256
`0903FE197C341A170EA5722267D5AB7EADF4539C66AA45FFFC000A4EC81B2DF4`.
No checkpoint branch,site/database backup or applied rollback.

## Diagnosis and bounded correction

Read-only MariaDB10.6.23 query reproduces the projection mismatch:
`CAST(@@autocommit AS CHAR)` is `ON`,while `@@autocommit+0` is1.
The old strict pair expected string1/string0 and refused the valid closed state.
Correct the fixed query to
`SELECT CAST(@@autocommit+0 AS CHAR),CAST(@@in_transaction+0 AS CHAR)`.
Fresh bundled mysql confirms string1/string0. No coercion of PHP return values,
no accepted ON/false/null/integer pair,no SET/autocommit change and no guard removal.

Require raw ROLLBACK result===true before the state query;require exact two-string
closed pair afterward and free the result. Bounded private failure labels now
separate rollback_ack from rollback_state;no raw SQL/session payload exported.
Original zero-DML checks,owned transaction finalizer and closure remain mandatory.

Source-backed regression executes the trusted actual helper branch with query
doubles. It fails on the old projection with RuntimeException rollback,then the
same positive passes after correction. Thirteen cases/52 assertions include five
non-true acknowledgements stopping before state read and seven malformed/active/
noncanonical states refusing. This is isolated PHP,not physical mysqli proof.
Eval is limited to a fixed repository slice inside the test,never input/DB code.
Node protocol tests admit only bounded new labels and reject added raw facts.

New corrected-attempt source seal frozen before native execution:
`31481ac16832920ccca35a118a0c7b3c77c18ef017c93abc90e5b930c98d4137`.
This is a new approved source revision,not a reinterpretation of seal6e5101b4
at the previous Git head. Original66-file/seven-group source and no-fault
successor remain unchanged. Native owner/primitives/authentication/nonce domains,
sole relay,original worker/control,limits and production code unchanged.

## One fresh ordered physical result

Bundled-PHP source/composition preflight passed without WordPress bootstrap.
ONE ordered invocation:39983ms,exit0,all cases passed;no native rerun:

| Layer | Actual evidence |
|---|---|
| Containment |10 checks/2 groups;owned sockets/listener close,unresolved job stays unresolved |
| Genuine guards |266 checks/11 groups;owned users/storage removed |
| Original no-fault |960 checks/24 guards/10 genuine denials;committed history,original writer/barrier drains |
| Integrated cache no-fault |960/24;actual write credit,18 empty cache-spaced queries,honest expiry;no selected loss or actual wait |
| Selected uncertain control |960/24;uncertain history,slots erased,terminal observation;original successful barrier drain |
| Released |960/24;exact actual owned wait on query1,acknowledged blocker rollback,zero DML and monitor closure;uncertain history,slots erased,terminal observation,successful original barrier COMMIT/drain |
| Held |936 checks/22 guards;exact actual owned wait on query1,blocker retained through original1205/same-physical-session rollback/validation/QUIT suffix;uncertain history,slots erased,expected refused/rolled_back terminal,NOT successful barrier |

Both direct helpers in released/held exit0/close,pipes destroyed,no stderr,
invalid frame or timeout. Each selected transport conserves8 streams/4 pairs,
7 equal,exact11 writer response bytes withheld before0 target write attempts/
completions;barrier responses never suppressed. Listener closed,live pairs0,
queued bytes0,no passive observation failure. No whole-writer-equality claim on
the deliberately withheld stream. Held does not synthesize successful read rows
or a successful barrier zero-DML receipt.

Each lifetime independently rejects false unresolved-transport cleanup claims,
then verifies known sessions absent,10 locks available and all77 application
checksums unchanged. Owned9/3/41 rows previewed before dropping3 owned tables.
Final independent mysql77 application tables/0 probe tables/other sessions/active
transactions;no owned proof PHP/Node process remains. Debug.log unchanged:
3244712 bytes,SHA256
`A4705A06939E53D4DDAA7CE8BF6796D8F679F5B92516017C9AAC377E2223E644`.

All budgets unchanged:root20s/read10s,writer4s,ACK1s,connect3s/read5s,row-lock2s/
metadata3s,monitor2.5s/125 queries/125ms quiet gap after execute/previous query END,
parent3s/join3.25s,worker24s/helper28s/outer90s;4096-byte private frame/32768-byte
traffic,four relay roles/eight streams,4096 frames/pair,2MiB packet/8MiB queue/
64MiB attempt. No source/guard/budget edit after the frozen attempt.

## Source-scoped checkpoint and proof limits

Canonical Feature Light:7 code/test/seal artifacts plus owning plans;test-only
DB/process/Other risk,no UI/AJAX/REST/cron. One confirmed harness projection defect
fixed,zero production defects/remaining manual decisions in this bounded repair.
Canonical bloat measurement uses the explicit uploader base:PHP113/61/24 TOTAL,
0 oversized. Helper omits.mjs/CODE parsing limitation remains explicit;small
protocol/identity/test deltas need no cleanup or splitting. No bloat Phase2 edit
or unnecessary new runtime abstraction. UI/UX not applicable.

Focused edge/adversarial follow-through retains previous26 positive/negative
Node controls and adds13 actual-branch PHP cases. Guard-removal protection comes
from exact exception/query-prefix assertions,not an always-unavailable oracle.
Query doubles cannot prove authentication,real rollback acknowledgement or
physical locking;the separate fresh native evidence above establishes those
bounded facts. No new skip or weakened assertion. Full Node838/838 passes;
targeted source/rollback PHP16/81 passes. Exact-head Linux full-suite results
and final lint/build/preservation checks are owned by the scoped toolkit entry.
Prior whole Windows suite remains historical,not new-head evidence.
Related isolated PHP131/8912,no failures/skips;configured WPCS248,runner lint,
build and deterministic runner verification pass. Existing PHPCS configuration
deprecation is a tooling warning,not a new source error. Dashboard preservation
PHP299/1423 has two existing Windows Sodium skips;WPCS138/structure/build pass.
All62 selected local Markdown links resolve;generated assets unchanged.

Final canonical focused Feature Security checks fixed input/path/SQL,strict raw
ack/state,private label allowlist,source verification and cleanup ownership.
No confirmed scoped security defect or regression handoff outstanding. Test eval
accepts only trusted first-party source,never caller data. Original guards before
protected reads,nonce separation,unavailable public surfaces and no handle export
unchanged. No formal Database Review/global atomicity,durability,server quiescence,
safe retry,revocation linearization or public production integration claim.
FULL_PRE_RELEASE remains required at a coherent RC before packaging/publishing.

Implementation-plan progress:R1 combined uncertain-admission contention/failure
acceptance is satisfied for this fixed Plugin Tester composition. Production
readiness is still NO-GO;R2-R7 remain separate gates. No maintenance-only batch or
patch release is appropriate at this boundary.

**Recommended next gated slice:local-only V2.3 Real Authenticated Closed Entry
Surface and Request Policy Design (R2).** Design-only:choose the fixed local
entry/method/body/capability/nonce contract and fail-closed handoff/dependencies
on R3 storage coherence and R5 lifecycle policy. Keep public registration,wiring,
storage writes,native execution,grants,activation and rollback disabled. Obtain
explicit approval before opening that new authentication/product-policy slice.
