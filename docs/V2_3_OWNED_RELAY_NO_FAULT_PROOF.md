# V2.3 Owned Relay Supervisor and No-Fault Pass-Through Proof

2026-10-20. Approved R3 **Plugin Tester local-only**, exact target confirmed anew:
`plugin-tester`, LocalWP “Plugin Tester”, `http://plugin-tester.local`,
`C:/Users/Captain/Local Sites/plugin-tester/app/public`. Resolved metadata
XPa4sCxmk: MariaDB10.6.23/backend10004, web10005, PHP8.5.1; genuine core7.1.2.
Owned disposable users/storage fixtures and listener/socket/process cleanup only.
Existing application state, actual users/options/cron and production wiring remain
untouched. No response suppression, arming, native uncertainty/failure experiment,
grant, observer execution, activation/effect/rollback/package/release/tag/deploy.
Uploader0.5.25 and dashboard0.1.77 unchanged.

## Boundary and protection

Explicit excluded feature bases uploader `47f7e68127e2a47db9ad0b658e1739f72b9b62a5`
and dashboard `4f0750dfdb19f583d36f1278e89d7262b6574350` precede this complete relay
implementation/proof, not a verification tail. Toolkit starts
`b010309cd010a8d38a6186c2a7bf6c889890facf`. All began clean/synced. Directly reviewed:
native session/profile/transaction/guard owners, existing three-table fixture
supervisor/direct worker, registration join fixtures/cases, synthetic decoder and
parent feasibility design. Source review is not a whole-plugin readiness refresh.

Before edits, Option B pristine source-only restore point:
`alynt-drime-backups-uploader-20261020-162145.zip` plus matching `.txt`,10979 files,
SHA256 `E0E4EEC6928E6F63CE8A81016BAE61DA96B6A923F078DD35E44083760C506FA2`,
under `C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/`.
No checkpoint/stash/rollback; not a site/database backup. Master router, toolkit
runner, restore workflow and LocalWP SQL workflow followed. NovaMira complete
inventory matched exact origin; doctor reported expired OAuth. No reconnection or
credential change. Initial SQL used resolved bundled mysql.exe directly with
temporary MYSQL_PWD from the registered local source, never printed/persisted.

## Implementation and ownership

- Explicit `relay-no-fault` audit mode reuses the exact three-table owner and
  independent full-application checksum audit. It selects a **new** Node relay
  supervisor and separate full-core PHP worker; existing direct worker unchanged.
- Supervisor independently revalidates root/domain/backend/runtime/fixture syntax,
  binds exclusively127.0.0.1 on an OS-assigned port distinct from backend, and
  spawns only the fixed owned PHP worker with inherited private stdio. No public
  control listener, arbitrary upstream, route/firewall/service/config mutation.
- Separate bootstrap deliberately mirrors the existing fixed isolation guards:
  direct-backend ownership marker first, fresh fixture prefix/salts, no actual
  wp-config, site plugins/themes/drop-ins, HTTP, cron or updater execution. Only
  isolated DB_HOST selects the owned listener; runtime profile/identity checks
  remain unmodified. Duplication preserves the approved fixed worker boundary.
- Three genuine sessions (borrowed authentication, read-only capture, writer)
  match the **exact** handshake-ID/socket lifetime set, never connection ordinal
  or a subset allowing a foreign fourth pair. Worker reflects the actual writer
  before BEGIN/DML solely for test observation. IDs travel only through inherited
  child/supervisor pipes and are removed before the operator receipt.
- Each direction assembles bounded classic frames and sends their original bytes
  through one egress path with write-callback backpressure. Independent ingress/
  emitted SHA256 accumulators, lengths, complete-frame and write-success checks
  must agree. Buffers are zeroed/released after forwarding or finalization; no
  packet files, payload/SQL/auth/cookie/nonce logging or managed-memory erase claim.
- Limits:2MiB payload/frame;8MiB shared pending frame reservations;64MiB forwarded
  aggregate/attempt;4096 frames/pair;4 live pairs/8 accepted
  lifetimes, but final proof requires exactly the three expected roles. Node/TCP
  bounded internal chunk/write buffers are additional to the8MiB frame budget.
  Existing runtime <10s, connect3/read5 and native session policy unchanged.
  Worker has24s outer watchdog; relay sockets6s inactivity bound, no reconnect or
  retry. Only its owned child may be terminated on watchdog; that path was not
  experimentally exercised here. Existing outer fixture owner remains90s bounded.
- Close stops the exclusive listener and drains/closes only its own pairs. The
  direct-backend parent independently waits up to3s for all recorded owned server
  sessions to disappear **before** count-preview/exact DROP of owned tables.
  Missing/invalid cleanup identity or unresolved listener/session blocks DROP;
  exact retained fixture identity is reported for review, never broad cleanup.

## Native compatibility findings and fixes

Read-only development preflights and early harness runs exposed real differences:

1. Server greeting advertises collation8 and MariaDB extended capability word,
   not the earlier synthetic collation45/zero-reserved profile. New transparent
   relay validates bounded protocol10/MariaDB10.6/native-password shape, separate
   six reserved bytes and applicable extended word; no fake server metadata.
2. mysqlnd advertises LOCAL_FILES; wpdb authenticates before selecting DB. Relay
   permits those advertised/handshake variants, but refuses server0xfb local-file
   requests (including ambiguous NULL-leading rows), TLS/compression/zstd,
   multi-statements, expired-password mode and authentication switches. No
   capability rewriting, client/server policy change or runtime guard relaxation.
3. Default Node socket async iteration destroyed the writable half with ABORT_ERR
   on orderly EOF. Explicit non-destroying iterator leaves pair cleanup to the
   supervisor. ABORT_ERR is still a failure, not ignored. Only reset/premature EOF
   **after a forwarded exact COM_QUIT** can be ordinary teardown; incomplete or
   unequal streams still fail independently.

These are new proof-harness defects/assumptions, not production defects. Earlier
development failures are not success evidence; every owned fixture was removed,
actual options/users/usermeta digests and all77 table checksums reconciled. No
unknown COMMIT or lost acknowledgement was induced. Previous socket-free decoder
and its narrow grammar remain byte-identical; they are **not** native-compatible
merely because this separate transparent relay is.

Protocol references crosschecked: [MariaDB connection](https://mariadb.com/docs/server/reference/clientserver-protocol/1-connecting/connection)
and [framing](https://mariadb.com/docs/server/reference/clientserver-protocol/0-packet).
Documentation is structural context; actual native evidence is below.

## Canonical source-scoped review checkpoint

Feature scope: development-only owned relay/native positive proof, fixture owner,
CI syntax discovery and records; risk areas Other/DB/File Ops/local networking.
Feature Light reviewed all changed files/direct callers. Fixed compatibility,
EOF lifetime and exact-role-set issues above; no open production defect. No UI,
AJAX, REST, cron or public hook change; UI/UX workflows not applicable.

Bloat/Structure used explicit47f7e68 before/after. Canonical helper reports three
PHP files, TOTAL85/38/50, none over300; CODE1 is its dense proof-style tokenization,
not a claim of one executable line. MJS is omitted by that helper: supplemental
TOTAL framing75/receipt6/server53/supervisor45/tests96, below250. Each classified
Consolidate/retain; no dead code/removal/split/02 deferral justified. Direct,
string, hook/filter/shortcode/AJAX/REST/cron references checked. Separate bootstrap
duplication is intentional target isolation, not a general bootstrap refactor.

07 considered all12 categories, source-scoped: empty/truncated streams;
zero/2MiB/continuation/frame-count/64MiB caps; multiple/wrong/duplicate lifetimes;
fixed backend refusal/timeout; genuine WP versus stubs and native flag variants;
site-code/drop-in isolation; malformed receipt/frame; stream backpressure;
authorization-before-I/O and disjoint nonces; owned-only deletion cascade;
no-retry/unknown cleanup; browser irrelevant. UI/content/export/migration/Drime/
multisite behavior is unchanged/outside scope. Highest proof risks are foreign
session qualification or DROP with unresolved ownership: strict exact-set match
and cleanup gate prevent these. No production policy change. Resource-bound and
role-matching regression candidates handed to07A; failure injection against native
transactions/sessions remains outside this no-fault proof.

07A Phase1 baseline20 meaningful pure tests; missing native-profile/role-set/
resource coverage identified. Phase2 added five permanent tests, final25 targeted,
full Node115, no skips. Exact byte arrays/error outcomes, not mock call counts;
no tests removed, assertions weakened, skips or artificial native success paths.
Pure crypto/framing tests do not prove SQL/authentication/socket cleanup. Separate
native225/2 verifies actual core, transaction and cleanup; direct predecessors
cover broader genuine guards/failures. No broad coverage percentage claimed.

Six one-change synthetic helper controls were assertion-killed (not syntax failures):
always-unavailable equality, removed sequence, capability, frame cap, server0xfb
refusal and exact-role binding. Every control was restored before native work;
exact SHA256 frames `F176331DBBBF231343384852873279F83EACAEF83F25B23C4FC541FF09FEFC72`,
receipt `8251FB455CA38C121B4FCE752C1F8C3BF07161CC39EA007CDE4C16635F0D2FAB`.
No production/native source mutation or failure switch was used for these controls.

Final focused Feature Security after fixes/restoration/validation: fixed path/
backend/process ownership, strict integer SQL IDs and allowlisted fixture names,
genuine auth/nonce guards, no raw diagnostics or public lookup IDs, no storage/
write-capable handle export, bounded buffer/egress/cleanup paths reviewed. No open
confirmed production security defect; permanent helper regressions above are the
handoff disposition. Not a full-plugin security or formal Database Review.

## Executed evidence and limits

- `fixed-core-proof-audit.ps1 -Proof relay-no-fault`: final225 checks/2 groups;
  real positive original proposal/all-ten readback/sole owned ledger CAS/known
  native COMMIT, exact10 nonce guard order and distinct domains, malformed intents
  zero protected I/O, closed capture/writer, borrowed auth unchanged, NOWAIT lock
  availability, root erasure/replay refusal. Three authenticated relay pairs,
  six complete/equal directional streams,256878 forwarded bytes, three COM_QUIT,
  zero queued bytes/live pairs, listener closed and server sessions absent before
  exact owned fixture cleanup. All77 application checksums unchanged.
- Serial unchanged direct proofs: guard266/11; joined admission5756/27. Same
  independent application audit and all owned fixture absence confirmed.
- Targeted Node25, full Node115; related PHP173/10171 no skips; WPCS235 pass
  (existing PHPCompatibility functionWhitelist format deprecation only); runner
  lint/build/verify pass. Generated assets/runner unchanged. New proof PHP syntax
  checked locally and added to CI syntax sweep; native proof is never run in CI.
- Dashboard local299/1423/two existing Windows Sodium skips, WPCS138/structure pass.
  Exact pushed-head Linux full-suite/Node evidence and toolkit publication are
  recorded in the source-scoped toolkit checklist after CI, not inferred here.

This proves a bounded **no-fault compatibility control**, not selected COMMIT/OK
wire classification, zero response delivery, lost-ack uncertainty, power-loss
durability, global quiescence, safe retry or private qualification count/result.
Hashes/write callbacks are reviewed relay accounting, not packet capture or PHP
receipt attestation. Same-process malicious JS/local privileged interference is
not sandboxed. No runtime/public/native authority is obtained from relay receipts.
No physical failure/forced process termination/resource exhaustion experiment ran.
Full pre-release suite remains reserved for the next coherent RC before packaging.

Implementation-plan progress: the owned transparent native transport is now
proved without modifying the runtime; next work is a gated witness integration.
**Recommended next gated slice: local-only V2.3 Native-Profile Witness Selection
and Private Control-Channel Integration Design**, design-only. Specify adaptation
from the synthetic grammar to observed native negotiation and pre-BEGIN original
writer/pair binding over a private inherited channel before implementing arming or
any response suppression. Keep storage writes/native experiments/wiring disabled.
