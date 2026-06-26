# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added

- Added producer-adapter documentation for future backup-source support.

## [0.6.0] - 2026-06-22

### Changed

- Raised the configurable Drime multipart chunk-size validation range from 5-64 MB to 5-256 MB while keeping 32 MB as the conservative recommended value.
- Added a multipart upload memory guard so oversized chunk settings fail gracefully instead of exhausting PHP memory.
- Reused cached concrete Drime destination folder IDs before folder traversal so repeated selected-base relative-path uploads do not try to recreate an existing subfolder.

## [0.5.1] - 2026-06-22

### Added

- Added a failed uploads table with per-file retry actions when failed files still exist locally.

### Changed

- Increased Drime API control-request timeout to 180 seconds to better tolerate slow multipart signing and preflight responses.
- Local deletion for WPvivid-listed split backup sets now waits until every listed part has uploaded successfully, then cleans up the completed set together.

## [0.5.0] - 2026-06-22

### Added

- Added a read-only Drime workspace picker on the settings screen for loading workspaces available to the saved API token.

### Changed

- Changing the selected workspace now clears selected base-folder metadata so stale folder IDs are not reused across workspaces.
- Refined settings input widths and status table widths for a cleaner admin layout.

## [0.4.0] - 2026-06-22

### Added

- Added a read-only Drime folder browser on the settings screen for selecting an existing base folder without copying folder IDs from Drime URLs.
- Added a read-only destination preview that resolves the selected base folder plus relative subpath and reports existing or missing folder segments before uploads run.

### Changed

- Drime uploads now resolve or create the final selected-base-plus-relative-path folder during upload, then send backup bytes to that concrete parent folder.

## [0.3.0] - 2026-06-21

### Added

- Added cron health tracking for scheduled scans, including last runner evidence, WP-CLI scan evidence, WP-Cron disabled status, and server-cron health guidance.
- Added a Server Cron Expected setting that warns administrators when automatic scans should be driven by WP-CLI but no WP-CLI scheduled scan has been observed.

## [0.2.1] - 2026-06-21

### Added

- Added a dedicated Scan State section showing current UTC time, automatic scan status, next scheduled scan timing, last completed scan, and minimum file age in seconds.

### Changed

- Display Recent Events timestamps in explicit UTC format and add a current UTC time reference above the events table.

## [0.2.0] - 2026-06-21

### Added

- Added a configurable multipart chunk size setting for Drime uploads, defaulting to 32 MB with a supported 5-64 MB range.
- Added manual Remote Retention controls that preview old plugin-owned Drime uploads and move eligible remote files to Drime trash without permanent deletion.
- Added optional failed-upload email notifications through WordPress mail with recipient settings, duplicate suppression, and a test-email admin action.

### Changed

- Clarified production guidance for local WPvivid backup deletion and minimum file age settings.
- Added row numbers to the Recent Events diagnostics table.

## [0.1.1] - 2026-06-20

### Added

- Added `GitHub Plugin URI` metadata so Alynt Plugin Updater can detect the public GitHub release source.

### Changed

- Refreshed the GitHub Actions release packaging workflow to package only runtime plugin files and avoid development-only Composer/npm artifacts.

## [0.1.0] - 2026-06-20

### Added

- Initial WordPress plugin scaffold for uploading completed WPvivid local backups to Drime.
- Admin settings for Drime API token, workspace, destination folder, relative path, backup path override, duplicate handling, automatic scanning, retry limits, local deletion, and diagnostics.
- WPvivid local backup path detection for verified Free and Pro options.
- Stable-file scanner with WPvivid backup-list metadata and split-part completeness checks.
- Option-backed queue, uploaded registry, failed registry, active upload state, scan snapshots, resolved Drime folder cache, diagnostics log, and upload worker lock.
- Direct small-file upload and resumable multipart upload support for Drime.
- Remote duplicate validation using cached parent folder IDs after relative-path uploads.
- Manual admin actions for connection testing, scanning, uploading, diagnostics export, diagnostics clearing, and active-upload recovery.
- Redacted diagnostics logging with bounded retention.
- Multisite-aware uninstall cleanup for plugin-owned options and cron hooks.
- Composer, npm, PHPCS/WPCS, PHPUnit, build script, POT generation script, and CI placeholders.

### Changed

- Split large runtime classes into focused traits for admin rendering, Drime upload APIs, uploader helpers, scanner metadata, and plugin admin actions.
- Moved text-domain loading to early `plugins_loaded`.
- Shared verified array-option storage through `Alynt_Drime_Backups_Uploader_Option_Storage`.

### Fixed

- Settings, queue, registry, active-state, and upload-state writes now verify persisted WordPress option state before reporting success.
- Admin scan and upload failures now surface explicit notices and diagnostics.
- Diagnostics export now has a JSON fallback when encoding fails.
- Multipart signed upload URLs are validated before backup bytes are sent.
- Invalid-token uploads stop at connection preflight before duplicate checks or byte upload.
- HTTP `429` and malformed multipart response paths have regression coverage.

### Security

- Sensitive diagnostics values are redacted, including bearer tokens, authorization headers, cookies, nonces, passwords, request bodies, presigned URLs, and HTTP URLs embedded in scalar values.
