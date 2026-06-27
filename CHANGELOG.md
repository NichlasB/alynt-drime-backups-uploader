# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added

- Added a restore rehearsal checklist and report template for documenting fetch, verify, inspect, stage-restore, and cleanup proof during onboarding or periodic restore confidence checks.
- Added a server backup automation guide covering the runner/upload job split, cron scheduling, multiple standalone site layout, local artifact retention, monitoring, and high-write-site boundaries.
- Added central dashboard readiness documentation that records the existing redacted status foundation and keeps the dashboard plugin, enrollment, endpoint, and remote-control work as a separate future project.
- Added a producer adapter backlog guide for deciding when a future backup source needs a dedicated adapter instead of the generic outbox.
- Added `server-runner` JSON package inventory output through `list --format=json` for local restore discovery and sidecar readiness checks.
- Added `RESTORE_REPORT.json` output after successful server-runner restore staging so restore rehearsals have machine-readable local evidence.
- Added `server-runner cleanup-preview` for read-only local outbox and restore staging cleanup candidate reporting.
- Added Drime workspace destination guardrails that block workspace ID `0` and support optional `ALYNT_DRIME_ALLOWED_WORKSPACE_IDS` allowlisting.
- Added server-runner light consistency mode so packages can record database/archive timing, archive warning counts, and clean versus file-changes-detected status for high-write-site review.
- Added server-cron review commands to the settings screen so operators can build and diff a proposed crontab file before manually approving installation.
- Added multiple standalone site runner guidance for managing separate GridPane/VPS WordPress sites without sharing runner configs, outboxes, cron entries, or Drime site folders.

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
