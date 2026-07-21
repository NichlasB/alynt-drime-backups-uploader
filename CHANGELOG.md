# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [0.5.1] - 2026-07-21

### Fixed

- Fixed a production-simulation rollback retry that could collide with a retained rollback staging directory when both attempts occurred in the same second.
- Locked development dependencies to PHP 7.4-compatible versions so the declared GitHub Actions PHP support matrix can install and run.
- Fixed Linux fake WP-CLI test fixtures so shell arguments are preserved instead of being interpolated by PHP before the fixture runs.

## [0.5.0] - 2026-07-21

### Added

- Added a reusable GitHub Actions quality workflow covering PHPUnit and PHPCS on PHP 7.4 and 8.3 plus the Node build, generated-asset verification, and dependency audit. Release packaging now depends on that gate.
- Added an administrator-only failed-upload table with per-file retry actions when the original local backup remains readable.
- Added read-only `restore-production-preflight` checks for enrolled target identity, staged package identity, conservative disk headroom, active plugin/theme and filesystem markers, maintenance/write-control readiness, and fresh host-native backup evidence.
- Added optional redacted production preflight reports plus refusal coverage for target/package mismatch, missing recovery evidence, incomplete write-control review, and unsafe report paths.
- Added production-simulation pre-restore recovery evidence with private database/file SHA-256 records and a separately enabled explicit rollback foundation that accepts only a matching production-apply report and exact target confirmations.
- Added disabled-by-default production-simulation files-only, database-only, and combined apply commands with exact target confirmations, verified pre-restore evidence, target-drift checks, maintenance control, post-apply identity verification, and rollback-ready private reports. Combined apply replaces files before importing the database under one maintenance window.
- Advanced the standalone runner identity to `0.4.7` so successful-rollback extraction cleanup can be distinguished from earlier deployed runner scripts.
- Added deterministic SHA-256 integrity records for extracted WordPress files and database dumps. Production preflight verifies them, private pre-restore evidence binds them, and production apply recomputes them after maintenance activation immediately before target writes.
- Added exact enrolled-symlink target validation and automatic symlink recreation during production-simulation file apply, followed by complete plugin, theme, drop-in, symlink path, and symlink target verification before maintenance is removed.
- Added deterministic partial file-copy interruption coverage, conservative live-file overwrite reporting, emergency maintenance-marker recovery, and rollback preflight handling that permits only damage-induced runtime/inventory drift while retaining immutable safety gates.
- Added deterministic production database import-failure and rollback-import-failure coverage, including successful rollback and retry proofs plus conservative `database_may_be_modified` reporting after every attempted import.
- Added deterministic post-write WP-CLI identity-read failure coverage for apply and rollback, including successful recovery/retry proofs and truthful rollback availability as soon as destructive apply work begins.
- Added post-write WordPress root owner/group verification against private pre-restore evidence plus deterministic ownership and enrolled drop-in mismatch recovery coverage.
- Added deterministic rollback maintenance activation, reactivation, and deactivation failure coverage, including emergency-marker protection and successful recovery retries.
- Added combined production-simulation apply and rollback coverage, including successful end-to-end recovery and rollback after a database failure occurs following file replacement.
- Added report-first cleanup of successful production rollback extraction trees while retaining those trees after extraction, copy, database, verification, maintenance, or report failures.

### Changed

- Changed optional WPvivid local deletion so a listed split backup set is removed only after every listed part is confirmed uploaded.
- Changed admin workspace and destination requests to stop after 30 seconds and show retry guidance instead of waiting indefinitely.

### Fixed

- Fixed backup scans potentially appearing successful when the WordPress upload queue could not be persisted; the failure is now surfaced and logged.
- Fixed production-simulation file rollback so extracted symlinks are validated against exact private enrollment before target deletion, enrolled links are preserved during copy, maintenance remains active through post-rollback verification, and complete target identity is required before maintenance is removed.
- Fixed long multipart uploads potentially outliving the upload lock by renewing an owner-aware worker lease and refusing shared queue/retry mutations after lock ownership is lost.

## [0.4.0] - 2026-07-12

### Added

- Added optional `restore-apply --create-pre-restore-backup=1` support so staging restores can create matching pre-restore database/file backup evidence immediately before apply.

## [0.3.2] - 2026-07-12

### Added

- Added server-specific local retention for uploaded generic-outbox/server-runner packages, with a configurable newest-package keep count and safeguards that leave WPvivid files untouched.

## [0.3.1] - 2026-07-03

### Changed

- Changed generic outbox/server-runner uploads to create or reuse a Drime child folder named after the package ID, then upload the archive and recognized sidecars inside that package folder.

## [0.3.0] - 2026-06-30

### Added

- Added optional per-source Drime relative paths so generic outbox/server-runner packages and WPvivid packages can upload into separate subfolders while sharing the same workspace/base folder.

### Changed

- Changed default minimum file age from 900 seconds to 300 seconds.
- Changed default multipart chunk size from 32 MB to 128 MB for large-backup-oriented uploads, while keeping supported range validation.

## [0.2.1] - 2026-06-28

### Changed

- Streamlined the settings-page server-runner setup into guided single-line command blocks for install/update, first package verification, scan/upload, and cron review.
- Changed generated server-cron upload lines to run the plugin's scheduled scan/upload hooks through WP-CLI so cron health evidence stays accurate.

## [0.2.0] - 2026-06-28

### Added

- Added a restore rehearsal checklist and report template for documenting fetch, verify, inspect, stage-restore, and cleanup proof during onboarding or periodic restore confidence checks.
- Added a server backup automation guide covering the runner/upload job split, cron scheduling, multiple standalone site layout, local artifact retention, monitoring, and high-write-site boundaries.
- Added central dashboard readiness documentation that records the existing redacted status foundation and keeps the dashboard plugin, enrollment, endpoint, and remote-control work as a separate future project.
- Added a producer adapter backlog guide for deciding when a future backup source needs a dedicated adapter instead of the generic outbox.
- Added `server-runner` JSON package inventory output through `list --format=json` for local restore discovery and sidecar readiness checks.
- Added `RESTORE_REPORT.json` output after successful server-runner restore staging so restore rehearsals have machine-readable local evidence.
- Added clearer server-runner restore guidance after successful fetch, verify, inspect, and stage-restore commands.
- Added `server-runner cleanup-preview` for read-only local outbox and restore staging cleanup candidate reporting.
- Added `server-runner cleanup` for operator-confirmed deletion of old local outbox package sets and restore staging directories.
- Added Drime workspace destination guardrails that block workspace ID `0` and support optional `ALYNT_DRIME_ALLOWED_WORKSPACE_IDS` allowlisting.
- Added server-runner light consistency mode so packages can record database/archive timing, archive warning counts, and clean versus file-changes-detected status for high-write-site review.
- Added server-cron review commands to the settings screen so operators can build and diff a proposed crontab file before manually approving installation.
- Added multiple standalone site runner guidance for managing separate GridPane/VPS WordPress sites without sharing runner configs, outboxes, cron entries, or Drime site folders.
- Added package-level `.remote-index.json` sidecars for server-runner packages and generic-outbox uploads so each Drime package set carries restore discovery metadata.
- Added `.remote-catalog.json` folder catalog snapshot sidecars so each uploaded server-runner package can carry a non-secret catalog of the local outbox package set.
- Added `server-runner restore-dry-run` as a read-only preflight that checks staged restore evidence, staging-only config gates, target path safety, pre-restore backup path readiness, and scope-specific staged files before any destructive restore apply runs.
- Added optional `restore-dry-run --write-report=1` evidence reports under the configured `restore_reports_path` so successful dry runs can leave machine-readable proof for later restore gates.
- Added pre-restore backup evidence checks to `restore-dry-run`, requiring a matching evidence JSON file and readable database/file backup artifacts under the configured pre-restore backup path.
- Added `server-runner restore-apply --scope=database` for staging-only, confirmation-gated database imports after restore dry-run and pre-restore evidence checks pass.
- Added `server-runner restore-apply --scope=files` for staging-only, confirmation-gated file replacement after restore dry-run and pre-restore evidence checks pass.
- Added file-restore reporting for pre-restore symlinked drop-ins that are absent from staged files, including a manual-review flag in restore apply reports.
- Added `server-runner restore-apply --scope=files-and-database` for staging-only combined restores that replace files first, then import the staged database after the same confirmation and evidence gates pass.
- Added post-restore manual-review reporting for known symlinked drop-ins such as Query Monitor's `wp-content/db.php`, including whether broken-link cleanup needs operator review.

### Changed

- Expanded server-runner `RESTORE_NOTES.txt` output with archive format, file root, database dump, and manual inspection reminders so staged restores are easier to audit before any approved production recovery work.
- Changed newly generated server-runner configs to opt into light consistency metadata with `consistency_mode: "light"`.

## [0.1.1] - 2026-06-26

### Added

- Initial Alynt Drime Backups Uploader plugin line with clean plugin identity, settings, queue storage, upload registry storage, failed registry storage, active upload recovery, and uninstall cleanup.
- Added producer-adapter documentation for future backup-source support.
- Added WPvivid local backup source support with verified Free/Pro path detection, backup-list metadata, split-part completeness checks, and old WPvivid-specific uploader coexistence warnings.
- Added generic server-outbox source support for completed archive packages produced outside WordPress.
- Added scanner-level validation and defaulting for normalized producer package records.
- Added Drime direct upload, resumable multipart upload, duplicate handling, retry limits, destination folder browsing, workspace selection, destination preview, remote duplicate validation, and manual remote-retention cleanup.
- Added WP-CLI commands for server-driven scan, upload, run, and status workflows.
- Added a GridPane-oriented PHP CLI server runner that can create `.tar.gz` site packages with manifest and checksum sidecars.
- Added generic-outbox sidecar uploads so server-runner manifest and checksum files travel to Drime with the archive.
- Added CLI-only server-runner fetch support for known Drime packages and sidecars.
- Added restore staging archive-member safety validation before server-runner extraction.
- Added package-security documentation for server-runner package integrity and restore staging boundaries.
- Added logical backup consistency documentation and server-runner manifest timing fields.
- Added remote restore discovery notes for WordPress-unavailable disaster scenarios.
- Added status payload contract documentation and redaction guard tests for future dashboard readiness.
- Added Composer, npm, PHPCS/WPCS, PHPUnit, build script, POT generation script, and CI placeholders.

### Changed

- Split from the old Alynt Drime WPvivid Uploader into a backup-producer-agnostic plugin line.
- Split large runtime classes into focused traits for admin rendering, Drime upload APIs, uploader helpers, scanner metadata, CLI commands, producer adapters, restore helpers, and plugin admin actions.
- Moved text-domain loading to early `plugins_loaded`.
- Shared verified array-option storage through `Alynt_Drime_Backups_Uploader_Option_Storage`.
- Changed the server runner to exclude symlink entries from new archives before restore staging.
- Changed the server runner to treat GNU tar live file-change warnings as recoverable only when a non-empty archive was produced.
- Changed the build script to regenerate the served admin JavaScript and CSS files that WordPress enqueues.

### Fixed

- Settings, queue, registry, active-state, and upload-state writes now verify persisted WordPress option state before reporting success.
- Admin scan and upload failures now surface explicit notices and diagnostics.
- Diagnostics export now has a JSON fallback when encoding fails.
- Multipart signed upload URLs are validated before backup bytes are sent.
- Invalid-token uploads stop at connection preflight before duplicate checks or byte upload.
- HTTP `429` and malformed multipart response paths have regression coverage.
- Server Runner Status now uses the same admin table width as the other status panels.

### Security

- Sensitive diagnostics values are redacted, including bearer tokens, authorization headers, cookies, nonces, passwords, request bodies, presigned URLs, and HTTP URLs embedded in scalar values.
- Server-runner restore staging rejects unsafe archive paths and verifies manifest/checksum sidecars before extraction.
- Server-runner Drime fetch now validates HTTPS redirect targets and does not forward the bearer token to redirected download URLs.

### Notes

- This is a new plugin line. Historical releases for the previous WPvivid-specific uploader remain in the old plugin repository and are not carried forward as release versions here.
