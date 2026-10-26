# Repository Organization Migration — 2026-10-26

## Approved project and main-work checkpoint

User approved D2/U1/U2/U3 as one bounded path-only project before returning to
dashboard main work. This record owns the34 uploader moves; dashboard owns its
[57-file D2 record](https://github.com/NichlasB/alynt-drime-backups-dashboard/blob/6dca720deb47ce28cec243387c0cd00030f37e94/docs/REPOSITORY_ORGANIZATION_MIGRATION.md)
and historical15-file D1. All106 moves in the audited plan are now implemented;
91 belong to this continuation. Kept/deferred categories are not an implied next
migration queue. No logic/architecture/symbol/API/compatibility change, new
autoloader/shim, dependency install, protected-source rebaseline, actual site/SQL/
native proof, schedule/lifecycle operation, package/release/tag or deployment.

Fresh uploader baseline `29b2848b805a2c775fe037d9ae878487c6126126`, master, clean/
synced. Dashboard main checkpoint `8aa20fc10afc9f9876c13a3d82c465d80708c7ea`,
exact-head CI40410394509 successful. Main product progress remains4/8; organization
and evidence publication earn no product-task credit. Deferred V2.3 remains parked.

External source-folder restore ZIP (not a site backup or installable package):
`C:/Users/Captain/Documents/AI Workflows/Toolkits/toolkit-snapshots/alynt-drime-backups-uploader/alynt-drime-backups-uploader-20261026-232126.zip`,
matching .txt,13009 files, SHA256
`E1B5948BF1D1E19BBCF5D23A84ECD67ED1C7DE88AA4C31EB430C6D5C079F600C`.
Created before uploader edits and independently hash-verified. Same project
restore plus clean published intermediate commits protect the incremental batches;
no source restore or checkpoint branch applied.

## Source batches and exact-head publication

| Batch | Source head | Successful Quality run |
|---|---|---|
| U1 Drime11 | `21293008947760601172315a96ca6e97c35b4155` | [40412785811](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/40412785811) |
| U2 admin13 | `2fb348945667a5a588bfd9b8fa174d7143a0b027` | [40413498188](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/40413498188) |
| U3 producers10 | `24fed61e991fc37771fd7a10f368b741dda202ee` | [40414117502](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/40414117502) |

Each preceding exact source-head CI passed before starting the next source batch.
Actual logs for every run: PHP7.4=2311 tests/55847 assertions; PHP8.3=2311/55598;
no skips/failures. Node1987/1987, fail0/skip0. All configured Quality steps passed,
including PHPCS, fixed manual-proof syntax-only globs, deterministic build/
freshness and dependency audit. CI never runs the genuine native/site proofs.
PHP-version assertion counts differ; they are not substituted for one another.

## Exact move maps

### U1 — Drime adapters

| Historical path | Current path |
|---|---|
| `includes/class-drime-client.php` | `includes/drime/class-drime-client.php` |
| `includes/class-folder-browser.php` | `includes/drime/class-folder-browser.php` |
| `includes/class-workspace-browser.php` | `includes/drime/class-workspace-browser.php` |
| `includes/trait-drime-client-direct-upload-response.php` | `includes/drime/trait-drime-client-direct-upload-response.php` |
| `includes/trait-drime-client-direct-upload.php` | `includes/drime/trait-drime-client-direct-upload.php` |
| `includes/trait-drime-client-multipart.php` | `includes/drime/trait-drime-client-multipart.php` |
| `includes/trait-drime-client-response-helpers.php` | `includes/drime/trait-drime-client-response-helpers.php` |
| `includes/trait-drime-client-transport.php` | `includes/drime/trait-drime-client-transport.php` |
| `includes/trait-folder-browser-normalization.php` | `includes/drime/trait-folder-browser-normalization.php` |
| `includes/trait-folder-browser-path-helpers.php` | `includes/drime/trait-folder-browser-path-helpers.php` |
| `includes/trait-folder-browser-preview.php` | `includes/drime/trait-folder-browser-preview.php` |

### U2 — Admin owner and traits

| Historical path | Current path |
|---|---|
| `includes/class-admin-page.php` | `includes/admin/class-admin-page.php` |
| `includes/trait-admin-page-cron-health.php` | `includes/admin/trait-admin-page-cron-health.php` |
| `includes/trait-admin-page-dashboard-settings.php` | `includes/admin/trait-admin-page-dashboard-settings.php` |
| `includes/trait-admin-page-diagnostics-status.php` | `includes/admin/trait-admin-page-diagnostics-status.php` |
| `includes/trait-admin-page-drime-settings.php` | `includes/admin/trait-admin-page-drime-settings.php` |
| `includes/trait-admin-page-failed-uploads.php` | `includes/admin/trait-admin-page-failed-uploads.php` |
| `includes/trait-admin-page-notices.php` | `includes/admin/trait-admin-page-notices.php` |
| `includes/trait-admin-page-notification-settings.php` | `includes/admin/trait-admin-page-notification-settings.php` |
| `includes/trait-admin-page-runner-guidance.php` | `includes/admin/trait-admin-page-runner-guidance.php` |
| `includes/trait-admin-page-settings.php` | `includes/admin/trait-admin-page-settings.php` |
| `includes/trait-admin-page-source-settings.php` | `includes/admin/trait-admin-page-source-settings.php` |
| `includes/trait-admin-page-status.php` | `includes/admin/trait-admin-page-status.php` |
| `includes/trait-admin-page-upload-settings.php` | `includes/admin/trait-admin-page-upload-settings.php` |

### U3 — Producer/interface/integration family

| Historical path | Current path |
|---|---|
| `includes/class-generic-outbox-producer.php` | `includes/producers/class-generic-outbox-producer.php` |
| `includes/class-wpvivid-detector.php` | `includes/producers/class-wpvivid-detector.php` |
| `includes/class-wpvivid-producer.php` | `includes/producers/class-wpvivid-producer.php` |
| `includes/interface-producer.php` | `includes/producers/interface-producer.php` |
| `includes/trait-generic-outbox-producer-archive-helpers.php` | `includes/producers/trait-generic-outbox-producer-archive-helpers.php` |
| `includes/trait-generic-outbox-producer-diagnostics.php` | `includes/producers/trait-generic-outbox-producer-diagnostics.php` |
| `includes/trait-generic-outbox-producer-normalization.php` | `includes/producers/trait-generic-outbox-producer-normalization.php` |
| `includes/trait-generic-outbox-producer-scan.php` | `includes/producers/trait-generic-outbox-producer-scan.php` |
| `includes/trait-generic-outbox-producer-sidecars.php` | `includes/producers/trait-generic-outbox-producer-sidecars.php` |
| `includes/trait-wpvivid-producer-scan-helpers.php` | `includes/producers/trait-wpvivid-producer-scan-helpers.php` |

## Dependency closure and offline validation

Every moved file retains its raw SHA256, original basename/symbols and complete
body. Only the root loader's34 path literals changed at their original positions.
All1180 original tracked source paths accounted for,125 unique loader entries
retain exact order;35 affected PHP syntax checks pass. No stale executable old
path or case collision. No producer/Drime/admin method uses a source-location
magic constant; dirname(input/ABSPATH) in runner guidance is location independent.

Tests/Support casing/discovery, scripts/tests proof families, source-identity
JSON/group/reflection pins, allowlist grammar, safety budgets/flags, CI/lint/build
configuration, dependencies, assets and portable runner source/output all remain
unchanged. No expected digest, assertion, skip or path grammar was loosened.
Full offline Node tests retain positive source closures and existing assertion-
killed controls; focused PHP includes actual QualificationSourceManifest positive/
descriptor-negative checks. These are source/API checks, not native attestation.

Local before and after each batch: focused76 PHP tests/452 assertions, no skips;
full Node1987/1987 with no failures/skips. PHPCS255, full deterministic build,
runner/source syntax, strict Composer validation, Composer/npm audits (zero
advisories), generated-output freshness and diff hygiene pass. Composer PHAR is
the existing dashboard tool invoked with uploader cwd; no dependency installation.
Existing PHPCS array-config deprecation and Git line-ending notices are unchanged.
Full Windows PHP was not rerun or claimed; full PHP7.4/8.3 is the exact-head CI
evidence above, not a filtered CI suite. No browser/runtime acceptance is claimed.

Runner digest stays
`7356eacd57ed67f4265db8198f8ca5809e07f4bd985b1d6d00a88cb915112117`.
No tracked generated artifact changed. Recursive unchanged rsync release rules
include all new includes/drime, includes/admin and includes/producers leaves;
a static rule check is not a generated/released ZIP audit.

Existing WP-CLI2.12.0 extracted source only, before WordPress load, with owned
empty config, --skip-packages and build/docs/scripts exclusions. Full baseline/
current extractions each505 messages/header have identical non-location content
and exactly mapped locations. Cumulative tracked projections touch17/331/335
active message blocks after U1/U2/U3 respectively, zero historical-only blocks.
Actual source references/lines come from extraction; unrelated pre-existing
missing-entry/metadata drift is retained. No msgid added/deleted or broad refresh.

## Canonical source-scoped reviews

FEATURE_LIGHT_REVIEW: scope path-only existing adapter/UI/producer organization;
files reviewed13/15/12 source/consumer/POT paths for U1/U2/U3. Risk areas File Ops,
loading and translation locations; no changed UI, request, DB or remote behavior.
Issues found0, auto-fixed0, manual decisions0. Feature passes light review; no
significant introduced non-security issue. No new runtime code/file count,
abstraction or oversized-file split; bloat workflow unnecessary. UI bytes/output
unchanged, so no UI/UX workflow or acceptance refresh.

Final FEATURE_SECURITY_REVIEW after validation: feature passes security review;
no introduced issue found. Fixed internal mapped paths stay beneath the resolved
repo, load order/guards unchanged; no new input, SQL, nonce/capability, escaping,
redaction, credential, unsafe file or external-call boundary. Regression handoff:
no confirmed security defect needs new coverage. This is scoped differential
review, not whole-plugin02/13 or FULL release-readiness completion.

## Evidence revalidation and cross-plugin navigation

Historical source/path/proof references in docs/IMPLEMENTATION_PLAN.md and local
PRE_RELEASE_CHECKLIST.md remain intact; current pointers resolve through this
map. No historical checkbox/review date or acceptance claim is refreshed.
Shared toolkit PRE_RELEASE_CHECKLIST.md is untouched.

Dashboard V2_3_FIXED_CLOSED_ENTRY_OWNER_RAW_READER_DESIGN and
V2_3_REAL_AUTHENTICATED_CLOSED_ENTRY_REQUEST_POLICY_DESIGN cite uploader admin;
their current target is includes/admin/class-admin-page.php, not dashboard admin.
Dashboard V2_3_CACHE_CONVERGENCE_SCAN_STOP_ADMISSION_DESIGN and
V2_3_NATIVE_SCAN_EFFECT_STORAGE_PARTICIPANT_DESIGN cite uploader producer paths;
resolve those through U3 above. Navigation-only notes preserve historical text.

Covered pinned native files/groups remain byte-identical and existing offline
identity controls pass without rebaselining. This does not renew old physical
receipts, unproven A1-A4/native GO, installed lifecycle, authentication, transaction,
concurrency, browser or package acceptance. Future release qualification must
explicitly use the new recursive source tree/fixture manifest and repeat the
relevant bootstrap/lifecycle/package checks under separate authority. No transfer
of old proof into new execution permission. Existing deployed copies, ignored
operator tooling and third-party direct includes were not inspected or changed;
their absence is not inferred from first-party searches.

## Organization completion and return gate

All approved source batches and exact-head CI gates are complete; this docs-only
reconciliation receives independent publication/CI verification at handoff. Final layout:219 remaining
flat includes kept/deferred, Drime11, admin13, producers10; tests/proof tools/docs/
runner layouts kept as audited. No additional migration, cleanup or deferred
research is recommended merely to reduce counts.

Return to dashboard canonical05 at **Plugin Tester local-only Retention Cleanup,
Latest-Snapshot Preservation and Failure-Reporting Owned Proof**. It remains
unapproved: obtain separate owned disposable table/options scope approval and
fresh exact-target confirmation; preserve existing application state/wiring.
Main product count4/8 and pending05/FULL remain unchanged. Do not run that proof,
release or deploy under this organization approval.
