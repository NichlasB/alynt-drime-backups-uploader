# V2.3 Genuine wpdb Setup Wire Grammar and Profile Residuals

2026-10-25. R1/R2 repository-only batch; synthetic bytes/private contexts/virtual
clocks only. Excluded coherent bases uploader02f14552af6de38b9a41b55d232e92b8d0b8b936,
dashboardb5575de8823c0f0aa9801fa2ee56f4ebaf95fbfa,
toolkit3c666a26d0c9c0a23803b6f5383d973bec3fed3f. Parent:
[setup DATA foundation](V2_3_WPDB_SETUP_DATA_FOUNDATION.md). Versions remain
uploader0.5.25/dashboard0.1.77; public PHP7.4/WP6.0 contracts unchanged.

## Decision and finite tasks

Do not infer genuine negotiated capabilities from the DATA model. A separately
named synthetic wire subset can exercise exact frame/result/request/provenance
obligations without native authority. No old capability mask, observer context
translation or worker attachment. The new reader will not export a native profile.

1. Specify wire subset, evidence boundaries and native-profile residuals.
2. Bounded detached complete-packet framing; reject unsupported continuation.
3. Exact warning-free ordinary OK completion, not header-only success.
4. Exact one-column/one-value mode-result grammar with typed fixture branches.
5. Exact command/request/result pairing for mode read and later schema selection.
6. Private command owner, original epoch, sticky refusal and bounded copy cleanup.
7. Non-vacuous adversarial cases and assertion-killed source guard controls.
8. Dormancy/claim contracts, focused reviews and readiness reconciliation.

Each task gets targeted validation, explicit commit/push and exact-checkout CI.
The batch ceiling is not permission to create filler or cross a site/native gate.

## Primary protocol references and chosen subset

The [packet reference](https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_packets.html)
describes a three-byte payload length and one-byte sequence. The candidate accepts
only complete, ordinary packets; no continuation, wrap, compression or TLS parsing.
Sequence zero belongs to a new command and server responses start at one.

The [OK layout](https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_ok_packet.html)
has length-encoded affected/insert values, status, warnings and capability-dependent
suffixes. Our fixture subset requires exactly seven bytes: header0, zero affected/
insert counts, AUTOCOMMIT-only status2, zero warnings; no session-state/info suffix.
This deliberately rejects many valid protocol messages, not a server compatibility
claim. ERR/LOCAL INFILE/auth-switch/unknown markers are never success.

The [text result layout](https://dev.mysql.com/doc/dev/mysql-server/9.7.2/page_protocol_com_query_response_text_resultset.html),
[column layout](https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_text_resultset_column_definition.html),
[row layout](https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_text_resultset_row.html)
and [EOF layout](https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basic_eof_packet.html)
define separate metadata/rows and capability-dependent termination. Select a
synthetic ordinary-EOF five-response sequence only: column-count1, fixed invented
ColumnDefinition41, metadata EOF, one row, terminal EOF. Fixed metadata has empty
schema/table/original names, name `@@SESSION.sql_mode`, charset45/typeVAR_STRING;
it is not an observed installed descriptor. Require exact warning-free/status2
EOFs; missing/extra/NULL/error rows, more-results or alternate terminators refuse.
Only empty bytes or fixed fixture `STRICT_TRANS_TABLES` may classify; no dynamic
mode filtering or raw value output. These enums cannot authorize a mode write.

[MariaDB COM_INIT_DB](https://mariadb.com/docs/server/reference/clientserver-protocol/2-text-protocol/com_init_db)
selects a connection's schema and receives OK or ERR. The synthetic request pair
uses exact command2 plus `owned_synthetic`; mode read uses command3 plus fixed
`SELECT @@SESSION.sql_mode`. These bytes are never transmitted/executed. An OK
alone must not establish selected schema, nor a schema in the old handshake.

Limits: packet payload1..4096 bytes, total owned copies at most16384 bytes and
at most six packets per command; virtual original20s half-open epoch. Header and
sequence checks precede allocation; ordinary non-shared Buffers only. No generic
SQL/parser framework, arbitrary metadata/expected-byte provider or tracing layer.
Allocation/copy counters describe these snapshots only, not whole-request heap.

## Residuals — native NO-GO

This ordinary-EOF subset does not establish actual driver/server negotiation,
auth plugin/result, installed build/SAPI/options, source/config immutable ownership,
genuine charset API-to-wire multiplicity, actual mode metadata/filter semantics,
session-track/deprecate-EOF/optional metadata behavior or reconnect/replay coverage.
Do not strip flags or patch genuine behavior to fit it. Public protocol references
are not an installed7.1.3/8.5.12 profile qualification or renewed LocalWP inspection.

Keep genuine setup, selected data-result grammar and full shutdown/engine/process/
both-EOF ownership independent. One command's synthetic match is not full setup,
DATA lifecycle, forwarding, native resource closure or storage/execution admission.
A1-A4 remain Blocked. Existing DATA model, old decoder/observer, worker32-query/
4096-SQL-byte fences, P29/N8 and all runtime contracts remain unchanged.
No actual SQL/authentication, WordPress/LocalWP/native runs, fixtures/accounts/
permissions/listeners/processes/services, storage/wiring, package/release/tag/deploy.
Restore OptionA checked clean02f1455/existing external20261024-161220 archive;
no new backup/checkpoint/restore created. Static site workflow/registry only.

Task2 implements only complete-packet validation and detached payload copying.
Header/sequence/ordinary non-shared Buffer checks precede allocation; payload
1..4096, exact total length, sequence0..8. Fragment/continuation/concatenation,
exotic/proxy/getter/shared inputs refuse. Returned bytes are caller-owned test
data, not a storage/connection handle or provenance receipt. Owner cleanup and
cumulative limits remain separate obligations in task6.

Task3 requires exact seven-byte OK payload/response sequence1, zero affected/
insert counts/warnings and status2 only. Every payload bit mutation, noncanonical
length, error/infile/EOF/alternate OK, wrong sequence and suffix refuses. Temporary
copies clear in finally without altering caller bytes. This is a fixed synthetic
completion predicate, not general authentication/COMMIT or schema-selection proof.

Task4 adds exact five-packet one-column/one-row ordinary-EOF grammar. Every
metadata byte and sequence matters; EOF status/warnings and canonical row bytes
are checked. Positive empty and fixed nonempty values classify independently;
NULL/zero/dynamic/missing/extra/error/alternate terminators refuse. Plain array
descriptors prevent getter/proxy reads; total copies bounded and cleared finally.
No actual SQL-mode semantics or installed metadata/profile qualification claimed.

Task5 joins exact sequence0 request bytes with only the corresponding fixed
response family. Schema success requires command2/exact owned_synthetic plus
OK sequence1; mode success requires fixed command3/query plus five responses.
Cross-family/dynamic query/foreign schema/extra/error packets refuse. Request
validation precedes response inspection, without accessor invocation. These
stateless predicates supply no source/context/native authority or actual writes.

Task6 owns one fixed synthetic command only: six private original identities,
fresh command symbol, exact profile/kind, original20s epoch, FIFO direction and
sequence. Frame/16KiB/count checks precede snapshot allocation. Caller mutation
cannot alter captured bytes; refusal/finish zero and release all owned copies.
Frozen outcome/copy counters contain no callable/storage/connection handle; all
physical/source/native claims stay false. Counters cover retained frame snapshots,
not parser scratch space, caller buffers, process/heap closure or actual forwarding.
This owner cannot be attached to the DATA model or native worker by this batch.

Task7 adds fourteen assertion-killed in-memory source controls: length/sequence,
complete OK, metadata/row/EOF, request, profile/context/deadline/refusal, snapshot
budget/release and an always-unavailable success mutant. Each assertion first
passes against the actual helper; mutant imports must succeed before assertions
kill semantic weakening. Every single-byte mutation of a complete positive
nonempty exchange is also rejected. No on-disk source mutation or native proof.

## Task8 source-scoped checkpoint and next boundary

Static imports/runtime scans, exact frozen receipt shape and old-profile/mask/
handshake negatives enforce dormant synthetic scope. Caller-selected labels do
not establish installed source/session/native authority. All previous DATA/
observer/worker/runtime files stay unchanged; no new production contract.

Canonical Feature Light: eight uploader files (one114-line cohesive byte/owner
helper, fixture, four focused tests, owner/plan) plus dashboard plan; explicit
bases above, not merely the latest docs commit. Other/test-only. Reviewed fixed
byte grammar and sequence, result/metadata/warning boundaries, branch/request
pairing, copied buffers, original private epoch/refusal/cleanup, receipt claims,
test sensitivity and scope. No confirmed scoped non-security defect remains.
No meaningful Bloat trigger: small cohesive helper/test files, no duplicated
implementation/debug debris or unnecessary split. No UI/UX trigger.

Final focused Feature Security after validation: no confirmed scoped issue or
security-fix regression handoff. Inputs have exact bounded bytes/descriptors/
enums; unsupported cases refuse, temporaries clear finally and receipts cannot
grant execution. No actual endpoint/SQL/file/network/credential operation or
protected read/write handle. Not a sandbox against same-process monkey-patching,
not native heap/resource qualification. Ordinary adversarial tests do not claim
canonical DS3 07/07A, Database Review or physical transaction/authentication proof.
Keep historical whole-plugin checklist rows unchanged. FULL remains due before
packaging a coherent RC; no release is warranted for this synthetic-only batch.

Implementation-plan progress: fixed complete-packet setup result/request ownership
subset now exercises byte obligations omitted by the primitive DATA model. Native
source/build/negotiation/ownership and A1-A4 are still unresolved, not maintenance
items to mask with more synthetic success. Target ceiling8 reached, not a claim
that every safe-local task is exhausted. Preferred next action: **repository-only
V2.3 Fixed Genuine Setup Observation Admission and Source-Ownership Closure
Design**. Specify how actual negotiated/session/source evidence would be admitted
without changing driver behavior or treating this fixture profile as compatible,
before seeking separate exact-target/owned-fixture/native observation approval.
