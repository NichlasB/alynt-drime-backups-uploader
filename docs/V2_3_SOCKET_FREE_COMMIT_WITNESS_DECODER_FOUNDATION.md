# V2.3 Socket-Free Commit Witness Decoder Safety Foundation

2026-10-20. Approved R3 **repository-only, synthetic bytes only**. Two test-only
ES modules, two Node test files and one invented-byte fixture helper. No networking,
storage API, native execution, real authentication, WordPress/LocalWP bootstrap,
users, database fixtures, listeners, relay, production wiring, grants, observer
execution, activation, effects or rollback. No package/release/tag/deploy. Versions
remain uploader0.5.25/dashboard0.1.77; not a release candidate.

## Ownership, coherent review boundary and source protection

Uploader owns the five MJS files and this record. Dashboard owns two plan/design
pointers; toolkit owns source-scoped evidence. Start uploader/excluded source base
`ae8c0f4924eafd40fad1963b42596274615da09d`, dashboard
`1105fe9dd6aaa9f9cfdb0bc7c5abb3b84b72407f`, toolkit
`e214f367cc900e7e6bcc25c41ada550f3a7ec095`, clean/synced. Dashboard excluded
review base `89108c90a50d9d6649ad95cd81c1de8be85fb860` includes the coherent
feasibility design plus new foundation pointers, not merely a verification tail.
Parent: dashboard `docs/V2_3_OWNED_NATIVE_UNCERTAINTY_WITNESS_FEASIBILITY_DESIGN.md`;
its official MariaDB/PHP references govern the proposed grammar, not empirical proof.

Pristine uploader Option B source-only snapshot before edits:
`alynt-drime-backups-uploader-20261020-155706.zip` and matching `.txt`,10959 files,
SHA256 `C04CFEE4CE9FE4D7427D1F4043986FA3229F78651AA2CCA977D3A1312C721559`,
under `C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/`.
Not a site/database backup; no checkpoint branch, stash or rollback. No site target
operated on, so no LocalWP/NovaMira connection or prior target confirmation reused.

## Implemented selected-transcript contract

`scripts/tests/commit-witness-protocol.mjs` validates a deliberately narrow classic
protocol41 subset. `scripts/tests/commit-witness-decoder.mjs` owns incremental
framing, context/clock binding and the one-shot state machine. The only dependency
is that sibling module; neither module imports network/files/process/WordPress code,
creates IO handles, reads environment variables, logs or starts work on import.
Existing Node development test discovery finds the new tests without changing
package metadata, dependencies, CI configuration, PHP runtime or release exclusions.

```text
greeting -> hello -> auth -> ready --arm--> commit -> response -> observed --finish--> closed
any invalid input -> permanently failed --finish--> closed
```

| Boundary | Exact support and refusal |
|---|---|
| Framing | Owned four-byte header and copied payload; directional sequence0/1/2/0/1. Per-state payload caps256/256/7/64/7, whole selected transcript1KiB. Header validated before payload allocation; partial/truncated, zero length, 0xffffff continuation, replay/extra/coalesced duplicate frames refuse. Five frames maximum follows irreversible stages, not an independently testable generic frame counter. |
| Greeting | Protocol10, bounded ASCII10.6 MariaDB version, nonzero uint32 thread, collation45, idle AUTOCOMMIT status, required capabilities, strict reserved bytes, auth length21 and native-password plugin. No metadata/ack rewriting or fallback. |
| Client response | Exact capability mask0x0008a208, bounded identifier/database, collation45, reserved bytes and20 opaque invented auth bytes; plugin exact. TLS/compression/multi-statements/session-tracking/EOF-deprecation negotiation unsupported. A server merely advertising TLS is not a negotiated TLS connection. |
| OK | Exact seven-byte canonical zero affected/insert/warnings, status AUTOCOMMIT only. ERR/EOF/FE, leading-zero row-shaped data, noncanonical lenenc, active transaction, extra results/session flags, warnings/info/trailing bytes refuse. No broad packet interpretation. |
| COMMIT | Exact COM_QUERY byte plus `COMMIT AND NO CHAIN NO RELEASE`; no whitespace, case normalization, comments, semicolon/prefix/multi-statement acceptance. |
| Context | Exactly six ordinary own data fields, no accessor/extra symbol properties; distinct unregistered Symbol attempt/pair identities. Borrowed-auth/capture IDs are distinct valid uint32, and greeting writer excludes them. Arm matches exact writer ID once, not connection ordinal. |
| Clock | Caller-supplied synthetic BigInt monotonic timestamps, bounded signed64 start with room for10s deadline. Nonregression and original half-open10s window on feed/arm/finish; arm requires at least6s remaining. No real-clock provenance, timers or window reset. |
| Completion/privacy | Accepted fragments are not a witness. Only finish may return frozen `synthetic_match`; `synthetic_only` true and all authentication/native/forwarding/delivery/storage/retry/dispatch/execution claims false. No raw bytes/IDs/context exported. Buffers cleared on failure/finish; bindings released on finish. No cryptographic managed-memory erasure claim. |

This accepts a **selected synthetic handshake/COMMIT/OK transcript**, not all wire
traffic between pre-BEGIN arm and COMMIT. Intermediate session/BEGIN/prepared
statement/result traffic is deliberately unsupported here. A future transparent
relay must separately prove byte-exact pass-through, selection, native capability
compatibility and egress accounting; it cannot drop this helper into the existing
worker or silently skip unsupported traffic. Ordinary accessor rejection is not
a sandbox against malicious same-process JavaScript, Proxies or host monkeypatches.
Symbols/clock/byte inputs are synthetic context, never trusted native provenance.

## Canonical source-scoped 07 then 07A: inventory and routine fixes

Read-only scoped baseline after initial implementation: **80 Node tests**, exit0,
no skips/warnings. Existing Node runner/CI, package metadata, tests and fixtures
inspected; Node24.12.0 locally, CI24. No new tooling/dependency/coverage installer.
Twelve 07 categories considered: empty/boundary/corruption/input extremes/recovery,
plus sequential interleaving/identity analogues apply to this pure helper. External
services, WordPress variants, plugin registration conflicts, persistent deletion
cascades and browser behavior are outside this test-only source scope. No SQL,
permission/nonce/user/cron/lifecycle integration is inferred from these analogues.

| Finding / risk group | Evidence, handling and 07A disposition |
|---|---|
| W01 Medium: constructor callback/identity shape | Initial factory invoked an ordinary accessor and accepted symbol-key/registered identities. New constructor regression failed (Missing expected exception), then own-data descriptors/key domain and unregistered identities prevent it. Permanent pure-unit test, no production auth defect claim. |
| W02 Medium: unbounded start arithmetic | New bounded-clock test failed before signed64 start guard; exact upper valid start and ten-second arithmetic now pass. Prevent unsupported virtual clocks before deadline arithmetic. |
| W03 Medium: coupled expected profile | Fixture initially imported the decoder's capability constant. Fixture now specifies expected bytes independently; missing capability bits and exact exported profile asserted. No algorithm copied into the assertion and no production change. |
| Existing framing/identity/OK negatives | 35 stage cases, ten lifetime/arm cases, six exact command refusals, ten OK categories, five capability refusals protect distinct boundaries. Positive and nine fragmentation sizes reject an always-unavailable implementation. Strong pure-unit layer; no mocks of the decoder. |
| Resource/privacy/recovery | All truncation points, trailing bytes, wrong types/1KiB cap, copied-buffer aliasing, early/late frames, half-open/6s clocks, one-shot closure, private frozen result and false authority claims. Bounded synthetic inputs, no actual exhaustion, sleeps, sockets, processes or application storage. |

07 Phase2 routine fixes stayed inside the new helper. 07A Phase1 classified the
initial fixtures as Useful but improvable (W03), the negative/positive groups as
Strong at the pure-unit layer, and identified missing exhaustive truncation/feed/
finish/context cases. No consolidation/removal candidates; overlap across states
protects distinct sequence/phase guards. Phase2 adds seven tests and strengthens
the independent fixture, resulting **87 targeted tests**, no skips. Node does not
report assertion totals; none are invented. Two newly added regression tests were
red before helper fixes and green after, not syntax/bootstrap failures. No pre-
existing production defect or native bug is claimed from this new helper.

07A source-scoped final status is recorded with exact-head publication verification
in the toolkit entry. Full Node suite and Linux repository CI establish automated status,
not native compatibility or final whole-plugin security. Runtime handoff below
remains mandatory; whole-plugin release-readiness rows are not refreshed.

## Falsifiable semantic controls and restoration

Eight single-change controls were assertion-killed (exit1), then exact-SHA256
restored: always-unavailable finish (positive fails); removed attempt/pair check;
removed sequence check (all five states fail); removed writer match; relaxed
command equality (trailing/multi-statement cases fail); removed OK status guard;
removed half-open deadline; removed declared-frame cap (bounded257-byte cases).
They exercise only this new socket-free helper, not a native source/fault port.
Expected assertion mismatches, not syntax/import errors, killed every control.
No mutation dependency installed, no surviving/source control left in the repo.

Restored decoder SHA256
`1B3CCBB5AF73B89D5E9DA8E213D9238C0593EFA9B2A7246AFC044601E53B931D`;
protocol `6794FED82D7A62CB3A27A4CD1E697D61929DF5AF8F6FD07137454AFF7DA2DEB3`.
Restored targeted87 and configured full Node90 (87new plus three existing watcher
tests) pass. Existing watcher tests use only their pre-existing owned OS temporary
files and cleanup; no new decoder test or helper has filesystem fixtures or IO.

## Focused quality/security review and verification

Canonical Feature Light: five new MJS files, this record, two dashboard pointers
and direct parent/runner contracts. Backend test support only; W01-W03 resolved,
no new production/public/deployment behavior. Canonical Feature Bloat/Structure
uses explicitae8c0f4; helper reports zero changed PHP/JS/CSS because it excludes
MJS. That is NOT evidence no code was added. Supplemental measured MJS TOTAL:
decoder92, protocol52, main tests115, boundary tests64, fixture30; all below250.
All Consolidate/retain; separate protocol grammar/state and shared invented bytes
are cohesive, no cleanup/split/02 deferral. Direct/import/string references and
hook/filter/shortcode/AJAX/REST/cron loading checked; only test discovery imports.
No bloat Phase2 edits needed; same-boundary closeout remeasurement is unchanged.

Final focused Feature Security follows fixes/restored controls/validation: bounded
copy-before-retention, strict byte/state/identity/clock domains, no executable input,
native proof/authority export, IO import, raw diagnostics, callback/provider or
production hook. No confirmed production security defect/regression handoff.
No UI/UX workflow, physical Database Review or full pre-release claim. Full
pre-release suite remains required before a coherent RC is packaged/published.

Executed local regressions: targeted Node87/full Node90, no failures/skips; related
PHP DATA/API173/10171 no skips, WPCS235 (existing PHPCompatibility property-format
deprecation only). Runner lint/build/verify pass, generated outputs unchanged.
Dashboard299/1423/two existing local Sodium skips, WPCS138/structure pass. No full
Windows uploader PHPUnit rerun; exact pushed-head Linux full-suite/counts belong
in the toolkit publication entry. Syntax/import/reference/privacy and Git ownership
checks cover new files; no native proof or socket/storage operation was run.

Implementation-plan progress: the first concrete test-only witness decoder now
has falsifiable synthetic protection; this is not a new outcome-DATA authority
layer. **Recommended next gated slice: Plugin Tester local-only V2.3 Owned Relay
Supervisor and No-Fault Pass-Through Proof**, including fresh exact target and
owned disposable fixture/listener/socket/cleanup approval. First prove unchanged
native identity, byte-exact pass-through and positive admission; no response
suppression, lost-ack experiment, production wiring, grants/activation/rollback.
Any native protocol mismatch must stop for review, not weaken runtime guards.
