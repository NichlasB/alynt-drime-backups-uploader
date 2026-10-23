# V2.3 Fixed Closed-Entry Owner Genuine Authentication and Input-Profile Feasibility / Owned Cleanup Proof (R2)

2026-10-23. Approved and separately confirmed Plugin Tester local-only target:
http://plugin-tester.local, C:/Users/Captain/Local Sites/plugin-tester/app/public,
database127.0.0.1:10004/local, resolved from LocalWP sites.json. No live counterpart
or production operation. Uploader0.5.25/dashboard0.1.77 unchanged. Production
ingress, grants, activation, rollback, protected/native composition and wiring off.
No package/release/tag/deploy, service/configuration or credential changes.

Review bases excluded: uploader329b6d45eb17a28668ced477db92116c6fc02709,
dashboardee0f9c2d0a6c7c688d9cfdef82028f218fa3aea0,
toolkit8d48a5d218fbc973d35689522974d6b1a6109c36. Coherent new operator proof/test
helpers and related records; existing source/native seals and runtime unchanged.
Parent: [fixed owner/reader foundation](V2_3_FIXED_CLOSED_ENTRY_OWNER_READER_FOUNDATION.md).
Dashboard owns the fixed owner/raw-reader design and R1-R7 readiness decisions.

## Outcome and exact proof limits

**Genuine core helper/authentication and cleanup proof passed. HTTP input-profile
support remains Blocked; the actual reader correctly refuses this profile.**
The successful operator command means measurements/auth cases/cleanup completed,
not that a supported HTTP ingress profile or integrated owner root is approved.

17 fresh core processes,8963 combined authentication/isolation/source checks on
genuine WordPress7.1.2 / bundled PHP8.5.1. Reflection verifies auth/nonce/capability/
screen functions originate in core, not shims. Each process uses a fresh owned
options/users/usermeta triple with a private ownership marker and three disposable
users. No actual wp-config, site salts, browser cookie, plugins/themes or actual
application rows are imported into the core process. No auth/IO APIs are stubbed,
no source is rewritten and no unavailable bit is switched on.

The actual private start/pin/fences and POST/GET authentication helpers run with
genuine core cookies, sessions, roles, users and small nonces. Positive helper
cases require exact authenticated/capability -> small nonce -> authenticated/
capability order. Negative cases cover subscriber, forged/expired cookie, raw
session/role/capability revocation despite warm caches, post-nonce role/session/
valid actor/cookie changes, wrong-domain/invalid small nonce, GET capability loss
and changed core screen. Post-nonce cases require a genuine first-auth/nonce
positive control. An unexpected exception cannot count as a qualified refusal.
Raw fixture-row revocations model another writer in owned storage only; they do
not bypass an application authorization check or permit application writes.

This is **private helper composition**, not positive execution of inspect_post or
issue_get: their entry_available gate stays false. The harness brackets genuine
nonce calls explicitly; it does not claim the disabled owner root performed them.
The GET WP_Screen is genuine core but CLI-established, not a genuine rendered
Tools/admin GET routing proof. CLI transport maps are fixture data, not HTTP
acquisition. No protected capture, UUID/full command nonce, native transaction,
receipt qualification, dispatch or feature execution. Genuine WP6.0/PHP7.4 runtime
acceptance is not established by the single LocalWP core version or isolated CI.

## HTTP observations: four complete, bounded loopback requests

A121-byte owned root PHP stub required only the input-only repo probe, not
WordPress. Its exact canonical directory, filename, loopback origin/Host, POST,
empty query and fixed mode are checked. It has no auth, database or storage path,
caller stream/provider or production hook. Local's auto-prepend bootstrap was
inspected; no auto-login input was used. No server/PHP configuration changed.

| Request | Actual input observation | Interpretation |
|---|---|---|
| Metadata/small |101 bytes,2 reads,EOF/close true | Synthetic bytes conserved; not framing certification |
| Actual private reader/small |false,0 accepted bytes,owned cleanup true | Current metadata predicate refuses, no permissive fallback |
| Metadata/16384 |16384 bytes,17 reads,EOF/close true | Observed byte/call boundary only |
| Metadata/16385 |16385 bytes,17 reads,EOF false,close true | Overflow sentinel is not a complete/accepted capped body |

Actual cgi-fcgi metadata:PHP/Input,rb,php://input,timed_out=false,blocked=true,
unread_bytes=0,**seekable=true**. The existing predicate requires seekable=false,
so owner_metadata_accepted=false in every metadata sample. No seek/reopen was
performed. Do not simply flip that predicate to obtain a green HTTP proof.

Read-only LocalWP configuration inspection also finds PHP max_execution_time1200,
max_input_time600,post_max_size1000M,max_input_vars4000 and nginx
fastcgi_read_timeout1200s. These broad values are not evidence of the design's
10s initial/20s original-root hard blocking-IO bound. Application clock checks
cannot interrupt a blocking syscall. No slow-client, malformed/conflicting
framing, request-smuggling or server exhaustion experiment was attempted.
Collapsed SERVER metadata/clean sample EOF alone cannot certify original headers,
error absence, buffering guarantees or all input limits. Profile support is
unproved, not waived by successful small/body-boundary observations.

## Cleanup and application preservation

Bundled mysql.exe established the target before any PHP/database bootstrap.
Before/after independent MySQL CHECKSUM TABLE EXTENDED maps cover all77
application tables; every checksum and inventory entry is unchanged. Supervisor
also compares actual options/users/usermeta row digests privately, never outputs
user/option payloads or their hashes. All17 worker exits0/stderr empty; ownership
and bounded worker settlement precede exact fixture cleanup. No feature/native
transactions were opened. No claim of physical transaction/Database Review.

51 exact disposable tables were row-count-previewed, dropped and independently
verified absent; no table was reused or application data deleted. The root stub
was content/path-verified and removed after the run, independently confirmed
absent. HTTP request hashes cover synthetic test bytes only and are stripped from
published receipts. Cookies, nonces, salts, session tokens and passwords stay in
process memory/environment and are not published. Existing users/options/cron/
plugins/themes/configuration remain untouched; standard HTTP service logs are
not a claimed filesystem-wide checksum boundary.

Novamira HQ was available and listed the exact local origin, but doctor reported
expired authorization. Normal approved LocalWP access was used; no reconnect or
credential mutation. Initial sandbox SQL denial was resolved by scoped execution,
not a change to database access or server settings.

## Source-scoped review and validation

New six operator helpers separate supervisor/core bootstrap/core cases/input
observer/HTTP client/independent audit; two new CI-safe boundary/preflight tests. No runtime
include, loader, hook, dependency, asset, version or source-seal change. Historical
helpers are reused read-only for core fixtures/fences/digests, not edited.

Canonical Feature Light: DB/File Ops/Other(auth,local HTTP) risk, eight new helper/
test files plus direct dependencies and plan records. Bounded case lifetimes,
owned-only query fences, redacted failure outcomes and cleanup inspected. Positive
controls prevent always-unavailable greenwashing. No significant scoped quality
issue remains; current HTTP profile is explicitly unsupported, not silently fixed.
P1 operator routing risk: a future changed HTTP port or missing stub could send a
proof request to the wrong route. Post-measurement review tightened current
metadata/port/exact-stub preflight (no auth/reader/body-protocol change). Read-only
actual metadata preflight passed with zero requests; six source-backed isolated
driver tests prove valid progression and refusal of port/profile/root/stub drift.
No WordPress/HTTP proof was rerun after this preflight-only tightening.

Canonical Bloat/Structure uses the excluded uploader base: five PHP files, zero
>300 TOTAL. Bootstrap38,core worker72,HTTP probe46,supervisor73,test31. Supplemental
measurement:HTTP.mjs51,audit.ps1 73,Node test39 (canonical helper omits those extensions).
Helper CODE=1 is a reporting limitation, not executable-line evidence. All
classified Consolidate(reviewed,no further edit); no removal/split/defer needed.
Source references confirmed, no production registration/callback references.
No Phase2 cleanup required after the scoped no-update analysis.

Final focused Feature Security: validated fixed target/metadata/markers, bounded
requests/process output/time, original guard call protocol and actual outcomes,
private auth material, exact file cleanup and fixture-only SQL reviewed. Values
are prepared; interpolated table identifiers are generated/strictly allowlisted.
The proof-only role unserialize uses owned data and allowed_classes=false. No
untrusted eval, arbitrary path/SQL/provider, public auth or execution port. No
confirmed new scoped security defect. No UI/UX, formal DS3/Database/physical proof,
whole-plugin security or full pre-release claim. Full pre-release still required
before packaging a coherent RC containing this work.

Local new9/36 operator refusal/syntax tests run without WordPress/database/HTTP;
combined with existing owner API/IO tests119/1483,no failures/skips. They are not
genuine-core tests; actual owned run above supplies that separate evidence.
Node new6/6,full857/857,no failures/skips;uploader WPCS255/build/runner verify pass,
broader related PHP279/7124. Existing PHPCompatibility metadata deprecation remains.
Dashboard preservation PHP299/1423 with two existing Windows Sodium skips,
WPCS138/structure/build pass;not new runtime support or an HTTP acceptance claim.
Remaining local QA and exact pushed-head full CI/counts are recorded in toolkit's
source-scoped closeout. Do not infer whole-runtime support from CI success.

Canonical source-only OptionB before edits:12167 files,clean uploader review
base; toolkit-snapshots/alynt-drime-backups-uploader/
alynt-drime-backups-uploader-20261023-202246.zip and matching.txt,SHA256
F59702CA04C564C8ACF83F0706E4C0915694DA60B7A79A3ACD4DB3E131E0EA42.
No site/database backup, checkpoint branch, restore, release or deployment.

Implementation-plan progress:R2 genuine core helper protocol and owned cleanup
advance; integrated HTTP root, real Tools request context and supported bounded
input profile remain blocked. Production NO-GO; R3-R7 remain separately gated.

Recommended next gated slice:design-only V2.3 Buffered Input Profile and Genuine
Closed-Entry HTTP Integration Design (R2). Resolve seekable-buffer evidence,
no-seek/reopen invariants, hard request/IO ceilings, original framing and genuine
Tools/POST ownership before any profile acceptance or integrated HTTP proof.
No source guard relaxation, server changes, WordPress/native experiment, writes,
public registration or production ingress activation in that design slice.
