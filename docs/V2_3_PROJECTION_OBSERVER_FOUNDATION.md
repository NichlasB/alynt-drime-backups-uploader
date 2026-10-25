# V2.3 Socket-Free Projection Observer Composition Foundation

2026-10-25. R2 synthetic-only safe-local batch. Dashboard owner/design:
docs/V2_3_PROJECTION_OBSERVER_ADAPTER_DESIGN.md. Excluded coherent bases uploader
33b1e0a0d95ada4227597be938ac45ae243d4a07, dashboard
b68a704d6934ed1ed7cdbc258346266c2d46c097; toolkit
bb9c5b660969c8c09dedb568a2ef6c5f961cd2ae. Versions0.5.25/0.1.77 unchanged.
Actual worker/storage/native execution/production wiring unchanged. This is not
an attachment to the real relay, a credential path or a physical proof.

## Incremental task record

Task1: dashboard design fixes the finite eight-task contract and next native gate.

Task2: `projection-observer-owner.mjs` owns bounded detached input snapshots,
opaque one-use FIFO credits and direct calls to the unchanged zero-row decoder.
Only fixed Node proxy classifier/decoder imports; no arbitrary observer/provider.
Capture never returns bytes; input mutation after capture cannot change decoding.
Private snapshots are zeroed and released on observation/refusal/finish, including
pending or invalid finish. Original20s clock,16KiB cumulative/pending byte cap,
512 pending-item ceiling and sticky refusal. No changes to existing native limits.
At this intermediate stage session, acknowledgement and terminal EOF admission
are intentionally not yet composed; the receipt already denies all actual proof.
Targeted11 tests pass, including positive actual composition, bytewise fragments,
mutation, replay/order, pending/malformed closure, getters/shared input, foreign
identities, original clock and copy ceiling. No native/mock-auth success claim.

Each task's commit/exact-head CI is recorded in toolkit source-scoped evidence,
not inferred from a later head. Initial canonical Feature Light/focused Security
find no outstanding issue in this test-only boundary; pending-finish decoder
disposal was tightened before publication. No UI/UX or meaningful Bloat trigger.
No whole-plugin/FULL/Database/physical readiness refresh or package/release.

Task3: fixed synthetic three-packet session profile now precedes capture. Same
seed-derived limited username/schema, exact client flags/collation/max packet,
bounded greeting/OK/sequence/plugin and one-time binding. Root/foreign/malformed/
unsupported/replayed input poisons; temporary copies are zeroed. Authentication
response bytes are opaque and unreported; a positive only correlates invented
bytes, never proves credentials/grants or genuine installed compatibility.
Layout reference: [MariaDB connection](https://mariadb.com/docs/server/reference/clientserver-protocol/1-connecting/connection).
No actual handshake/session, account or driver operation, and old profiles remain
unchanged. Permanent tests include the real new parser, not an injected provider.

Task4: the oldest opaque capture credit now needs a one-time exact-length/equal-
byte synthetic forwarding acknowledgement before observation. Missing, duplicate,
partial, changed, foreign, out-of-order, stale and expired completion poison.
Detached bytes remain private; acknowledgement buffers cannot overwrite them.
This closes the modeled capture/credit/result ordering gap, NOT real forwarding
or delivery proof and never a transport-control instruction. Existing positive
fixtures now perform the actual new acknowledgement path; negative cases bypass
it deliberately to ensure the guard fails closed rather than mocking the owner.

Implementation-plan progress: fixed observer ownership is being modeled, not real
provenance. Final synthetic reconciliation must precede any proposed native proof.
