# Alynt Drime Backups Uploader Implementation Plan

Updated: 2026-06-27

## Purpose

This document tracks the current implementation state and future roadmap for the new plugin line only: `Alynt Drime Backups Uploader`.

The previous `alynt-drime-wpvivid-uploader` plugin line is considered complete and is not the focus of this plan except for migration/coexistence notes.

## Current Stable Baseline

- Current accepted stable release: `v0.1.1`.
- Accepted as stable on 2026-06-26.
- Development repo: `C:\Development\WordPress\Plugins\alynt-drime-backups-uploader`.
- GitHub release/update flow has been validated with Alynt Plugin Updater.
- A real WordPress Plugins-screen update rehearsal passed by downgrading the LocalWP test copy to `0.1.0`, then updating to `0.1.1` through the rendered WordPress plugin UI.
- The working tree was clean before the operator-approved local cleanup execution slice.

## What Is Complete

### Plugin Foundation

- Clean plugin scaffold, settings, queue, registry, uploader, Drime client, cron, admin page, WP-CLI command class, uninstall cleanup, README, changelog, settings docs, and hook docs are in place.
- Build tooling is installed and working.
- Observability and diagnostics are implemented.
- Admin diagnostics export and recent-event views are available.
- Admin tables and status sections have been accessibility-reviewed.
- Uninstall removes plugin-owned options and cron hooks while intentionally leaving backup archives, runner files, and restore staging folders untouched.

### Producer-Agnostic Backup Source Architecture

- The plugin is no longer WPvivid-specific.
- Backup sources are represented through `Alynt_Drime_Backups_Uploader_Producer_Interface`.
- The scanner combines normalized candidates from registered producers.
- Producers do not contain Drime upload logic.
- Current producers:
  - `wpvivid`: scans WPvivid local backup archives.
  - `generic_outbox`: scans a configured server-side outbox for completed backup packages from the included runner or another backup producer.
- Producer documentation is maintained in `docs/PRODUCER_ADAPTERS.md`.

### WPvivid Producer

- WPvivid Free/Pro source and runtime option shapes were reviewed.
- Real single-file database backup fixture was tested.
- WPvivid-list-backed split-part fixture validated `.part001.zip` / `.part002.zip` scanner gating.
- Full backup-engine-generated split backup remains a confidence gap, not a blocker for the new generic-outbox/server-runner focus.

### Generic Server Outbox Producer

- Scans a configured local outbox directory.
- Ignores temporary and unsupported files.
- Requires readable, non-empty, stable files before queueing.
- Uses age and unchanged-size checks before queueing.
- Reads manifest, checksum, package-level remote-index, and folder catalog snapshot sidecars after the main archive is stable.
- Supports server-runner packages and future producer outputs as long as they follow the normalized candidate shape.

### Server-Side Backup Runner

- First-pass PHP CLI runner is included under `server-runner/`.
- Designed for GridPane-style WordPress servers.
- Creates `.tar.gz` packages containing WordPress files plus optional `database.sql`.
- Uses WP-CLI for database export.
- Uses `tar` for filesystem archive creation.
- Writes manifest, SHA-256, package-level remote-index, and folder catalog snapshot sidecars.
- Writes temporary archive files first, then atomically moves completed packages into the outbox.
- Uses a runner-level lock to avoid overlapping package creation.
- Health checks verify required paths, free space, `tar`, WP-CLI, and WordPress path assumptions.
- Handles GNU tar live-file churn warnings conservatively when a usable archive was created.
- Settings screen generates non-secret runner config, install commands, health command, cron snippet, and server-cron review commands.

### Upload Flow

- Direct small-file upload and multipart upload are implemented.
- Duplicate handling supports skip and rename behavior.
- Destination folder preview, workspace selection, and relative-path upload handling are implemented.
- Multipart resume, stale active upload clearing, retry handling, and failure registry behavior are implemented.
- Manifest/checksum/remote-index/remote-catalog sidecars are uploaded with server-runner packages.
- Manual remote retention is implemented conservatively:
  - registry-owned uploads only;
  - disabled by default;
  - 60-day default;
  - Drime trash only;
  - no permanent remote deletion path.

### Restore Flow Documentation And Runner Support

- Restore documentation exists in `docs/RESTORE_RUNBOOK.md`.
- Restore rehearsal checklist/report template exists in `docs/RESTORE_REHEARSAL_CHECKLIST.md`.
- Destructive restore automation planning exists in `docs/DESTRUCTIVE_RESTORE_AUTOMATION_PLAN.md` as a separate gated future project.
- First read-only GridPane investigation findings for `alyntdrime.sitesmain.com` are recorded in that plan.
- Remote discovery notes exist in `docs/REMOTE_RESTORE_DISCOVERY.md`.
- Package security boundaries are documented in `docs/PACKAGE_SECURITY.md`.
- Current restore support is intentionally non-destructive:
  - fetch a known Drime package plus sidecars;
  - verify manifest/checksum sidecars;
  - inspect package metadata and archive contents;
  - stage the package into a separate restore directory;
  - write `RESTORE_NOTES.txt`.
- The runner does not import the database or overwrite live WordPress files.
- Destructive restore remains a manual, explicitly approved operator process until the separate gated design in `docs/DESTRUCTIVE_RESTORE_AUTOMATION_PLAN.md` is implemented and proven on staging.

### Operator-Approved Local Cleanup

- The server runner supports `cleanup-preview` for read-only review of old local outbox package sets and restore staging directories.
- The server runner supports `cleanup` only when the operator passes `--confirm=delete-local-artifacts`.
- Cleanup execution deletes only candidates discovered from the configured outbox and restore paths.
- Cleanup execution removes selected local archives plus known sidecars (`.manifest.json`, `.sha256`, `.sha256sum`, `.remote-index.json`, and `.remote-catalog.json`) and selected restore staging directories.
- Cleanup execution does not contact Drime, delete remote files, import databases, overwrite WordPress files, or run automatically from plugin uninstall.

### Dashboard Readiness Foundation

- A redacted status payload contract exists in `docs/STATUS_PAYLOAD.md`.
- The status payload is usable for wp-admin, WP-CLI, diagnostics, and future centralized monitoring design.
- No public dashboard REST endpoint is enabled by default.
- Any future dashboard endpoint must add explicit pairing/enrollment, scoped authentication, and redaction enforcement.

### Release And Validation Workflows

- Pre-release review workflows have been run where they made sense for the completed baseline.
- The plugin has been added to the release/checklist tracking flow through the wp-plugin-toolkit process.
- GitHub release asset install/update validation passed for `v0.1.1`.
- Alynt Plugin Updater compatibility passed for `v0.1.1`.

## Latest Validation Snapshot

Latest read-only readiness review and local validation pass: 2026-06-27.

Passed locally:

- `npm.cmd test`: 121 tests, 589 assertions.
- `npm.cmd run lint`: PHPCS passed across configured files.
- `npm.cmd run build`: build completed.
- `npm.cmd audit`: 0 vulnerabilities.
- PHP syntax sweep across plugin PHP files excluding `vendor` and `node_modules`: no syntax errors.
- Git worktree remained clean after validation.

Historical E2E validation recorded in this plan and supporting docs:

- LocalWP `plugin-tester.local` admin/settings/package/update validation.
- GridPane staging site `alyntdrime.sitesmain.com` server-runner package creation, manifest/checksum sidecars, package inspection, plugin scan/queue, retry upload to Drime, uploaded registry recording, and clean queue/failed state afterward.
- Staging package verified after GridPane tar-warning hardening: `alyntdrime-sitesmain-com-20260626-165727.tar.gz`.
- Site-by-site rollout runbook dry run passed on GridPane staging `alyntdrime.sitesmain.com` on 2026-06-27: plugin updated to `0.1.1`, runner health passed, package `alyntdrime-sitesmain-com-20260627-103909.tar.gz` was created, verified, scanned after the configured 900-second stability window, uploaded to Drime with manifest/checksum sidecars, fetched back from Drime, verified, inspected, and staged to `/var/www/alyntdrime.sitesmain.com/restores/alynt-drime-backups/alyntdrime-sitesmain-com-20260627-103909` with no database import or live file overwrite.
- Approved local cleanup after the dry run removed the `alyntdrime-sitesmain-com-20260627-103909` outbox package/sidecars, fetched download copy/sidecars, and restore staging directory. Staging disk usage returned from 82% to 66%; the Drime uploaded copy remains available.

## Test Targets

### LocalWP

- Site key: `plugin-tester`.
- Mode: `local-only` unless a future task says otherwise.
- LocalWP site name: `Plugin Tester`.
- LocalWP domain: `plugin-tester.local`.
- WordPress path: `C:\Users\Captain\Local Sites\plugin-tester\app\public`.
- Follow the Site Operations confirmation gate before installing, testing, or changing the LocalWP runtime copy.
- Check Novamira MCP availability before LocalWP runtime testing.

### GridPane Staging

- SSH alias: `sites-main`.
- Staging site path: `/var/www/alyntdrime.sitesmain.com`.
- Keep this target as the primary server-side backup automation staging site until replaced.
- Treat server writes, cron edits, restore staging, remote Drime cleanup, and package deletion as approval-required operations.

## Known Boundaries And Confidence Gaps

- The new plugin is production-ready for the validated baseline, but broad rollout should still be staged site-by-site.
- Server-runner packages are logical WordPress backups, not transactional filesystem snapshots.
- High-write sites may need maintenance windows or stricter future consistency modes.
- Full backup-engine-generated WPvivid split backup remains untested.
- Live rate-limit induction remains intentionally untested to avoid abusive Drime API traffic.
- Composer audit has previously been blocked locally when Composer was unavailable; rerun when Composer is available in the relevant environment.
- The first restore flow is inspection/staging only; production database import and file replacement are intentionally manual.
- Centralized dashboard work is not implemented yet; only the redacted status payload foundation exists.
- Third-party producer adapters beyond WPvivid and generic outbox are not implemented yet.
- Drime workspace destination guardrails are implemented in source/docs and passed feature-stage review workflows.

## Active Roadmap / Backlog

### 1. Plan And Documentation Hygiene

Status: active now.

Goal:

- Keep this plan aligned with the new plugin baseline.
- Keep old plugin-line history out of the active implementation roadmap.
- Preserve only the history that helps future release, support, or rollout decisions.

Acceptance:

- Current stable baseline is obvious.
- Completed features are separated from future backlog.
- Future items are scoped to the new plugin only.
- Validation status is current and not mixed with older copied feature-slice notes.

### 2. Site-By-Site Rollout Runbook

Status: drafted in `docs/SITE_ROLLOUT_RUNBOOK.md` and dry-run validated on GridPane staging.

Goal:

- Create a practical operator runbook for adding a new GridPane-hosted site to the backup flow.

Expected coverage:

- Install or update the plugin.
- Configure Drime credentials and destination.
- Choose WPvivid producer, generic outbox producer, or both with clear duplication warnings.
- Configure server outbox path.
- Generate and review server-runner config.
- Install runner files as the site user.
- Run runner health check.
- Add server cron.
- Confirm WP-CLI scheduled scans are observed.
- Run first package creation.
- Confirm scan, queue, upload, registry, and status payload.
- Run fetch/verify/stage restore inspection for server-runner packages before relying on that site flow.

Current artifact:

- `docs/SITE_ROLLOUT_RUNBOOK.md` now provides the operator checklist and GridPane rollout phases.
- Dry-run validation passed on `alyntdrime.sitesmain.com` using package `alyntdrime-sitesmain-com-20260627-103909.tar.gz`.

### 3. Broader Server-Side Backup Automation Support

Status: documentation, local package inventory, package-level remote-index sidecars, folder catalog snapshot sidecars, cleanup preview/execution, light consistency mode, multiple standalone site guidance, and server-cron review UX implemented.

Goal:

- Make the server-side backup producer story stronger beyond the first GridPane `.tar.gz` runner.

Implemented first slice:

- Added `docs/SERVER_BACKUP_AUTOMATION.md` to document the split between package creation and plugin upload, typical cron shape, site-local multiple standalone site layout, disk retention policy, high-write-site boundaries, monitoring commands, and current automation boundaries.
- Linked the guide from `README.md`, `server-runner/README.md`, and `docs/SITE_ROLLOUT_RUNBOOK.md`.
- Kept automatic cron mutation and ungated automatic local artifact deletion out of scope.
- Added `server-runner` JSON package inventory output through `list --format=json` so operators can review local package IDs, archive names, sidecar names, manifest/checksum validity, checksum metadata, manifest metadata, and `verification_ready` flags before restore discovery or cleanup decisions.
- Added `server-runner cleanup-preview` so operators can review old local outbox packages and restore staging folders before disk cleanup. The command is read-only, supports JSON output, and records `destructive_actions_performed` as `false`.
- Added `server-runner cleanup` so operators can delete old local outbox package sets and restore staging directories only after passing `--confirm=delete-local-artifacts`. The command reports `destructive_actions_performed` as `true`, deletes only paths reconstructed from configured local cleanup roots, and leaves Drime untouched.
- Added light consistency mode for high-write-site review. Newly generated configs include `consistency_mode: "light"`, and completed manifest sidecars record database/archive timing, archive warning counts, live file-change warning counts, and `consistency_status` (`clean`, `file_changes_detected`, or `warnings_detected`).
- Added generated **Server Cron Review Commands** so operators can capture the current crontab, build a proposed crontab file with the generated snippet, review a diff, and manually approve the final `crontab` install command.
- Added `docs/MULTIPLE_STANDALONE_SITE_RUNNER_GUIDANCE.md` to clarify that this backlog item meant several separate WordPress sites on one server, not WordPress Multisite. The guide documents isolated runner configs, outboxes, work paths, restore paths, cron entries, Drime site folders, monitoring, cleanup-preview use, and common mistakes.
- Added package-level `.remote-index.json` sidecars for server-runner packages. The generic outbox producer reads and uploads those sidecars with the archive, manifest, and checksum so each Drime package set carries non-secret restore discovery metadata.
- Added `.remote-catalog.json` folder catalog snapshot sidecars for server-runner packages. The generic outbox producer reads and uploads those sidecars with the package set so the latest uploaded package can carry a non-secret catalog of the local outbox package set.

Remaining possible future work:

- Additional archive formats after real server validation.
- Stronger consistency modes for higher-write sites, such as maintenance-window runbooks, temporary maintenance mode, or host-level snapshots.
- Mutable singleton remote package catalog if future Drime API validation proves safe in-place replacement semantics.
- Optional stronger restore/operator helpers after more rollout evidence.

Light consistency mode validation:

- Local focused tests passed for generated config output and clean package metadata.
- Staging validation target: `sites-main:/var/www/alyntdrime.sitesmain.com`.
- Staging free-space precheck on 2026-06-27 showed `/` with about 13 GB available and the site directory at about 4.1 GB, enough for one controlled package run.
- Staging health check passed using a separate test runner/config pair, leaving the existing installed runner/config untouched.
- Staging package creation passed on 2026-06-27 with package `alyntdrime-sitesmain-com-20260627-173914.tar.gz`.
- Staging verification passed for that package checksum and sidecars.
- Staging inventory reported `consistency_mode: light`, `consistency_status: clean`, and `verification_ready: true`.
- Staging manifest recorded `file_archive_exit_code: 0`, `file_archive_warning_count: 0`, and `file_archive_live_change_warning_count: 0`.
- Temporary staging test runner/config files and the 1.8 GB test package plus sidecars were removed after validation; final staging free-space check returned `/` to about 13 GB available.

Light consistency mode feature workflow results:

- Feature Light Review: passed; change is limited to runner manifest/inventory/report metadata, generated config output, tests, and documentation.
- Feature Bloat And Structure Review: completed with explicit base ref `origin/master`; the standalone runner remains oversized as an existing architecture-sensitive file, while changed admin/test files remain under threshold. No feature-stage split was safe or necessary.
- Feature UI/UX Implementation Review: not required beyond confirming generated admin config output remains read-only and no new control or workflow state was added.
- Feature Security Review: passed; the new metadata is non-secret timing/count/status data, uses existing local runner config, performs no new remote calls, and does not add destructive actions.
- Documentation Sync Audit: completed; README, readme.txt, server-runner docs, server automation docs, consistency model docs, changelog, and this plan now describe light consistency mode.
- Final validation: `npm.cmd test` passed with 135 tests and 702 assertions; `npm.cmd run lint` passed; `git diff --check` passed with only existing line-ending warnings for generated/text files.

Server cron review UX feature workflow results:

- Feature Light Review: passed; change is limited to a read-only generated admin textarea, one helper method that builds conservative crontab review commands, focused regression coverage, and documentation.
- Feature Bloat And Structure Review: completed with explicit base ref `origin/master`; `includes/trait-admin-page-source-settings.php` is now 24 lines over the 300-line guideline, but a feature-stage split was deferred because the helper remains tightly coupled to the existing server-runner snippet generation and a cosmetic split would add churn without reducing risk. Existing oversized architecture files remain unchanged by this slice.
- Feature UI/UX Implementation Review: passed; the new field uses the existing WordPress form-table pattern with a matching label, readonly textarea, associated help text, and no destructive button or automatic cron install action.
- Feature Security Review: passed; no new POST/GET/REST/AJAX surface was added, output is escaped through the existing admin rendering pattern, generated commands do not include Drime tokens, and the final `crontab` install command remains commented for operator approval.
- Documentation Sync Audit: completed; README, readme.txt, changelog, server-runner docs, settings docs, server automation guide, rollout runbook, POT file, and this plan now describe the server-cron review workflow.
- Final validation: `php -l` passed for the changed admin trait; focused `AdminPageSettingsTest` passed with 7 tests and 35 assertions; focused PHPCS passed; POT regeneration passed with WP-CLI deprecation warnings from the phar; `npm.cmd test` passed with 136 tests and 709 assertions; `npm.cmd run lint` passed; `npm.cmd run build` passed; `git diff --check` passed with only existing line-ending warnings for `readme.txt` and the generated POT file.

Multiple standalone site runner guidance feature workflow results:

- Feature Light Review: passed; change is documentation-only and clarifies the operator model for several separate WordPress sites on the same GridPane/VPS server.
- Feature Bloat And Structure Review: not applicable; no production PHP, JavaScript, CSS, or file structure changed.
- Feature UI/UX Implementation Review: not applicable; no admin UI or frontend UI changed.
- Feature Security Review: completed as a boundary review; the guidance keeps runner configs, outboxes, restore paths, cron entries, and Drime site folders isolated per standalone site and warns against broad cleanup across several sites.
- Documentation Sync Audit: completed; README, readme.txt, changelog, server-runner docs, server automation docs, rollout runbook, release checklist, and this plan now use "multiple standalone sites" wording and link the focused guidance.

Package-level remote index feature workflow results:

- Feature Light Review: passed; change is limited to runner package sidecar generation, generic-outbox sidecar discovery/upload, local inventory metadata, focused tests, and documentation.
- Feature Bloat And Structure Review: completed with explicit base ref `origin/master`; measurement reported 13 changed PHP files and 4 oversized existing files (`server-runner/alynt-backup-runner.php`, `tests/UploaderTest.php`, `includes/class-uploader.php`, and `includes/class-generic-outbox-producer.php`). The standalone runner and uploader paths remain existing architecture-sensitive files, and the new code is confined to small helper methods plus existing sidecar upload paths.
- Feature UI/UX Implementation Review: not applicable; no admin UI or frontend UI changed.
- Feature Security Review: passed; the remote-index sidecar stores non-secret package metadata only, does not include Drime tokens or signed URLs, omits archive absolute paths, and does not approve restore or destructive actions.
- Documentation Sync Audit: completed; README, readme.txt, changelog, server-runner docs, restore docs, remote discovery docs, automation docs, package security docs, rollout/rehearsal docs, and this plan now document package-level remote-index sidecars while keeping mutable singleton remote catalog work as future.
- Final validation: `php -l` passed for changed PHP files; `npm.cmd test` passed with 136 tests and 734 assertions; `npm.cmd run lint` passed across 53 files; `git diff --check` passed with only the existing `readme.txt` CRLF warning.

Folder catalog snapshot feature workflow results:

- Feature Light Review: passed; change is limited to server-runner catalog snapshot generation, generic-outbox catalog sidecar discovery/upload, queue/retry context preservation, focused tests, and documentation.
- Feature Bloat And Structure Review: completed with explicit base ref `631a684`; measurement reported 13 changed PHP files and 4 oversized existing files (`server-runner/alynt-backup-runner.php`, `tests/UploaderTest.php`, `includes/class-uploader.php`, and `includes/class-generic-outbox-producer.php`). The new duplicate JSON sidecar reader was consolidated, and any further split of the standalone runner/uploader/generic producer remains architecture-sensitive rather than a feature-stage cleanup.
- Feature UI/UX Implementation Review: not applicable; no admin UI or frontend UI changed.
- Feature Security Review: passed; the catalog snapshot stores non-secret package metadata, omits archive absolute paths, does not include tokens or signed URLs, follows the same package-basename sidecar safety rule as other sidecars, and does not approve restore or destructive actions.
- Documentation Sync Audit: completed; README, readme.txt, changelog, server-runner docs, restore docs, remote discovery docs, automation docs, package security docs, rollout/rehearsal docs, and this plan now document folder catalog snapshot sidecars while keeping mutable singleton remote catalog replacement as future optional work.
- Final validation: `npm.cmd test` passed with 136 tests and 754 assertions; `npm.cmd run lint` passed across 53 files; `git diff --check` passed with only the existing `readme.txt` CRLF warning.

### 4. Restore Flow Improvements

Status: restore rehearsal, restore report, and richer server-runner CLI guidance slices implemented in source/docs.

Goal:

- Improve operator confidence after backups are in Drime.

Implemented first slice:

- Added `docs/RESTORE_REHEARSAL_CHECKLIST.md` with a guided restore rehearsal checklist, report template, and cleanup decision guidance.
- Linked the rehearsal checklist from `docs/RESTORE_RUNBOOK.md`, `docs/REMOTE_RESTORE_DISCOVERY.md`, and `README.md`.
- Enriched server-runner `RESTORE_NOTES.txt` output with archive format, file root, database dump, and manual inspection reminders.
- Added regression coverage that restore staging notes keep database import and live file overwrite as manual, separately approved operations.
- Added `RESTORE_REPORT.json` output after successful `stage-restore` so restore rehearsals have machine-readable local evidence for package metadata, checksum metadata, verification state, extraction state, and destructive-action boundaries.

Implemented CLI guidance slice:

- Add clearer server-runner CLI guidance after `fetch`, `verify`, `inspect`, and especially `stage-restore`.
- Keep the guidance server/operator focused rather than adding a wp-admin restore UI.
- Keep all restore actions non-destructive.
- Record destructive restore automation as a separate future gated project, not part of this slice.

Feature-stage workflow results:

- Feature Light Review: passed; change is limited to restore docs, runner restore notes, and a regression test.
- Feature Bloat And Structure Review: passed; measurement reported two changed PHP files, with the standalone runner still oversized as an existing file and no new split required for this slice.
- Feature UI/UX Implementation Review: not applicable; no admin UI or frontend UI changed.
- Feature Security Review: passed; no new user input, database writes, REST/AJAX endpoints, or credential exposure added. Restore notes keep destructive restore manual.
- Documentation Sync Audit: completed; README, restore docs, server-runner docs, and changelog now reference the restore rehearsal checklist and unreleased restore-note enhancement.

Server automation documentation slice workflow results:

- Feature Light Review: passed; change is documentation-only and narrows operational expectations without changing runtime behavior.
- Feature Bloat And Structure Review: not applicable; no production code or file structure changed.
- Feature UI/UX Implementation Review: not applicable; no admin UI or frontend UI changed.
- Feature Security Review: not applicable as a code review; the documentation explicitly keeps automatic cron mutation, local deletion, and destructive restore outside the current implementation.
- Documentation Sync Audit: completed; README, server-runner docs, rollout runbook, changelog, and this plan now reference the server backup automation guide.

Local package inventory feature workflow results:

- Feature Light Review: passed after tightening `verification_ready` so it requires a manifest package ID and parseable SHA-256 sidecar value, not just sidecar file presence.
- Feature Bloat And Structure Review: passed; measurement reported `server-runner/alynt-backup-runner.php` as an existing oversized standalone runner and both changed test files under threshold. No feature-stage split was needed.
- Feature UI/UX Implementation Review: not applicable; no admin UI or frontend UI changed.
- Feature Security Review: passed; inventory output is local CLI-only, read-only, does not contact Drime, does not include tokens or signed URLs, and omits archive absolute paths from JSON.
- Documentation Sync Audit: completed; `README.md`, `readme.txt`, server-runner docs, restore docs, remote discovery docs, automation docs, changelog, and this plan now document the local inventory helper.
- Final validation: `npm.cmd test` passed with 124 tests and 617 assertions; `npm.cmd run lint` passed.

Restore validation report feature workflow results:

- Feature Light Review: passed; change is limited to `stage-restore` report output, runner-level restore report coverage, and restore documentation.
- Feature Bloat And Structure Review: passed; measurement reported `server-runner/alynt-backup-runner.php` as an existing oversized standalone runner, `tests/ServerRunnerInventoryTest.php` under threshold, and `tests/ServerRunnerSecurityTest.php` under threshold. No feature-stage split was needed.
- Feature UI/UX Implementation Review: not applicable; no admin UI or frontend UI changed.
- Feature Security Review: passed; `RESTORE_REPORT.json` is local, written only after verified non-destructive restore staging, records explicit destructive-action false states, and does not include Drime tokens, signed URLs, or archive absolute paths.
- Documentation Sync Audit: completed; `README.md`, `readme.txt`, server-runner docs, restore runbook, restore rehearsal checklist, changelog, and this plan now document `RESTORE_REPORT.json`.
- Final validation: `npm.cmd test` passed with 125 tests and 635 assertions; `npm.cmd run lint` passed.

Richer server-runner restore guidance feature workflow results:

- Feature Light Review: passed; change is limited to CLI output guidance for `fetch`, `verify`, `inspect`, and `stage-restore`, focused runner tests, and documentation.
- Feature Bloat And Structure Review: completed with explicit base ref `d15bbd8`; measurement reported 2 changed PHP files and 1 oversized existing file (`server-runner/alynt-backup-runner.php`). The changed test file remains under threshold, and splitting the standalone runner is deferred as architecture-sensitive rather than forced in this feature slice.
- Feature UI/UX Implementation Review: not applicable; no admin UI or frontend UI changed.
- Feature Security Review: passed; the guidance adds no new inputs, writes, remote calls, or restore authority. It repeats that database imports and live file replacement remain separately approved manual work.
- Documentation Sync Audit: completed; `README.md`, `readme.txt`, `CHANGELOG.md`, server-runner docs, restore runbook, restore rehearsal checklist, remote discovery docs, package security docs, and this plan now document the restore guidance boundary.
- Final validation: `php -l server-runner/alynt-backup-runner.php` passed; focused restore/security PHPUnit passed with 9 tests and 118 assertions; `npm.cmd test` passed with 140 tests and 799 assertions; `npm.cmd run lint` passed across 53 files; `git diff --check` passed with only the existing `readme.txt` CRLF warning.

Local cleanup preview feature workflow results:

- Feature Light Review: passed; change is limited to a read-only `server-runner cleanup-preview` command, focused CLI tests, and documentation.
- Feature Bloat And Structure Review: passed; measurement reported `server-runner/alynt-backup-runner.php` as an existing oversized standalone runner. New/changed test files remain under threshold, and shared runner CLI test helpers were extracted to avoid growing the inventory test file.
- Feature UI/UX Implementation Review: not applicable; no admin UI or frontend UI changed.
- Feature Security Review: passed; cleanup preview reads configured local outbox and restore staging paths, outputs basenames and sidecar readiness only, performs no deletion or writes, and records `destructive_actions_performed` as `false`.
- Documentation Sync Audit: completed; `README.md`, `readme.txt`, server-runner docs, server automation docs, changelog, and this plan documented cleanup preview as read-only for that slice. Cleanup execution was added later behind an explicit operator confirmation gate.
- Final validation: `npm.cmd test` passed with 127 tests and 667 assertions; `npm.cmd run lint` passed.

Operator-approved local cleanup execution feature workflow results:

- Feature Light Review: passed; change is limited to a CLI-only cleanup command, path-contained file operations, focused runner tests, and operator documentation.
- Feature Bloat And Structure Review: completed with explicit base ref `728e52f`; measurement reported 3 changed PHP files and 1 oversized existing file (`server-runner/alynt-backup-runner.php`). The new tests remain under threshold, and splitting the standalone runner is deferred as architecture-sensitive rather than forced in this feature slice.
- Feature UI/UX Implementation Review: not applicable; no admin UI or frontend UI changed.
- Feature Security Review: passed; cleanup execution requires `--confirm=delete-local-artifacts`, reconstructs deletion paths from configured outbox/restore roots and basenames, rejects slash-containing names, leaves `cleanup-preview` read-only, and does not contact Drime or perform restore actions.
- Documentation Sync Audit: completed; `README.md`, `readme.txt`, `CHANGELOG.md`, server-runner docs, server automation docs, multiple standalone site guidance, restore rehearsal docs, and this plan now document cleanup preview versus confirmed cleanup execution.
- Final validation: `php -l server-runner/alynt-backup-runner.php` passed; focused cleanup/security PHPUnit passed with 8 tests and 72 assertions; `npm.cmd test` passed with 139 tests and 782 assertions; `npm.cmd run lint` passed across 53 files; `git diff --check` passed with only the existing `readme.txt` CRLF warning.

Remaining possible future work:

- Mutable singleton remote package catalog only if future Drime API validation proves safe in-place replacement semantics.
- Guided staging-restore wp-admin UI only if operator experience later proves that the browser is the right surface.
- Destructive restore automation only as the separate gated project documented in `docs/DESTRUCTIVE_RESTORE_AUTOMATION_PLAN.md`, with dry-run, confirmation, pre-restore safety evidence, and staging evidence.

### 5. Central Dashboard Plugin Preparation

Status: uploader-side readiness documented; dashboard plugin remains a separate future project.

Goal:

- Build a separate control-center plugin that monitors multiple sites running Alynt Drime Backups Uploader.

Current uploader-side preparation:

- Stable non-secret `site_uuid` setting exists.
- Redacted status payload contract exists.
- WP-CLI status output exists.
- `docs/CENTRAL_DASHBOARD_READINESS.md` documents the future dashboard boundary, safe first-version fields, endpoint requirements, and explicit non-goals.

Future dashboard boundaries:

- First version should be read-only monitoring.
- Require explicit pairing/enrollment.
- Require scoped authentication.
- Do not expose local paths or secrets by default.
- Do not trigger remote restore, remote delete, backup execution, settings changes, or credential changes in the first dashboard version.

Feature-stage workflow results:

- Feature Light Review: passed; change is documentation-only and confirms existing uploader-side readiness without starting the separate dashboard plugin.
- Feature Bloat And Structure Review: not applicable; no production code or file structure changed beyond one focused docs page.
- Feature UI/UX Implementation Review: not applicable; no admin UI or dashboard UI changed.
- Feature Security Review: completed as a boundary review; the docs explicitly require opt-in pairing, scoped authentication, redacted payloads, and no remote-control actions for any future endpoint.
- Documentation Sync Audit: completed; README, status payload docs, package security docs, changelog, and this plan now reference the central dashboard readiness boundary.

### 6. Additional Producer Adapters

Status: readiness/backlog guidance documented; no additional third-party adapter selected.

Goal:

- Add adapters for other backup producers only when there is a real need.

Notes:

- The current architecture is ready for more producers.
- Do not spend implementation effort on a specific third-party producer until the target is confirmed.
- Future adapters should follow `docs/PRODUCER_ADAPTERS.md`, keep producer logic separate from Drime upload logic, and add fixture coverage for complete/incomplete backup states.
- `docs/PRODUCER_ADAPTER_BACKLOG.md` now records the decision criteria, evidence requirements, implementation checklist, redaction rules, and target-selection template for future adapters.

Feature-stage workflow results:

- Feature Light Review: passed; change is documentation-only and avoids selecting or implementing a third-party adapter prematurely.
- Feature Bloat And Structure Review: not applicable; no production code changed.
- Feature UI/UX Implementation Review: not applicable; no admin UI or frontend UI changed.
- Feature Security Review: completed as a boundary review; the docs keep producer diagnostics redacted and require package evidence before implementation.
- Documentation Sync Audit: completed; README, producer adapter docs, changelog, and this plan now reference the future adapter decision guide.

### 7. Drime Workspace Destination Guardrails

Status: implemented in source/docs; feature-stage review workflows completed.

Goal:

- Prevent backups from being accidentally configured into the wrong Drime workspace.
- Keep the first-install discovery flow usable, so the operator can still identify the correct workspace ID before locking the site.
- Prefer server/operator-controlled configuration over a separate plugin password.

Implemented behavior:

- Hide and reject Drime workspace ID `0` by default so the personal/general workspace is not selectable for backup destinations.
- Keep blank workspace ID saveable as the first-setup "not configured yet" state so the operator can save the Drime token before loading workspaces.
- Show workspace IDs in the workspace picker labels so operators can identify the correct workspace during setup.
- Add an optional `wp-config.php` allowlist constant:

```php
define( 'ALYNT_DRIME_ALLOWED_WORKSPACE_IDS', '12345' );
```

- Support comma-separated values for future flexibility:

```php
define( 'ALYNT_DRIME_ALLOWED_WORKSPACE_IDS', '12345,67890' );
```

- When the allowlist is absent, show non-personal workspaces so the operator can discover the intended workspace ID.
- When the allowlist is present, show only allowed workspaces in the picker.
- Validate saved settings server-side so a disallowed workspace ID cannot be saved or used even if the UI is bypassed.
- Clear or block folder selections when the saved workspace is no longer allowed, so folders from another workspace are not reused accidentally.
- Add admin copy that explains when workspace selection is restricted by server configuration and shows the constant format.
- Reject disallowed workspace IDs at upload time so stale saved settings from before this feature cannot silently upload to the wrong workspace.

Acceptance:

- Workspace ID `0` is excluded from loaded workspace options by default.
- Existing and newly saved settings cannot use workspace ID `0` as a backup destination.
- A blank first-setup workspace value can be saved, but folder browsing, destination preview, and uploads remain blocked until an allowed non-personal workspace is configured.
- A single allowed workspace constant makes only that workspace selectable.
- Multiple allowed workspace IDs work when explicitly configured.
- Disallowed workspace IDs fail validation with a clear admin-facing message.
- Folder browsing and destination preview respect the allowed workspace rules.
- Documentation explains the setup flow:

```text
Install plugin
Enter Drime token
Load workspaces
See non-personal workspace names with IDs
Choose the intended workspace
Add ALYNT_DRIME_ALLOWED_WORKSPACE_IDS to wp-config.php
Confirm only the allowed workspace remains selectable
Continue folder selection and backup setup
```

Changed files:

- Drime workspace loading/filtering logic.
- Settings sanitization/validation logic.
- Admin workspace picker rendering and JavaScript labels.
- Folder browser, destination preview, and upload worker workspace guards.
- Tests for default ID `0` exclusion, allowlist filtering, save validation, folder/destination guardrails, and upload-time refusal.
- README/readme/settings/security/changelog/plan updates.

Feature-stage workflow results:

- Feature Light Review: passed after the first-setup blank-workspace edge case was fixed; the feature touches admin UI, AJAX-backed workspace loading, settings persistence, Drime folder browsing, destination preview, upload guardrails, and docs.
- Feature Bloat And Structure Review: completed with explicit base ref `origin/master`; dedicated guardrail tests were split out to keep changed test files under threshold. Remaining oversized files are existing architecture-sensitive files (`server-runner/alynt-backup-runner.php`, `includes/class-uploader.php`, `includes/class-settings.php`, and `includes/trait-plugin-admin-actions.php`) and no feature-stage split was safe or necessary.
- Feature UI/UX Implementation Review: passed after preserving the first-setup flow, keeping the workspace field blank until selected, adding actionable validation notice copy, and keeping labels/help text/live status regions translatable and accessible.
- Feature Security Review: passed; inputs are sanitized/validated, outputs are escaped, admin actions remain nonce/capability gated, and destination enforcement occurs server-side at save, folder browse, preview, and upload time.
- Documentation Sync Audit: completed; README, readme.txt, settings docs, package security docs, changelog, POT file, and this plan now describe the workspace guardrail behavior.
- Validation: `npm.cmd run build`, `npm.cmd test`, `npm.cmd run lint`, `git diff --check`, POT regeneration, and the feature bloat report passed. `git diff --check` reports only line-ending warnings for generated/text files.

### 8. Future Release Workflow

Status: repeat as needed.

After each new feature slice is added, run the applicable feature-stage workflows from `wp-plugin-toolkit` in this order, whichever are necessary for the actual change:

1. `C:\Users\Captain\Documents\AI Workflows\Toolkits\wp-plugin-toolkit\d4-prompts\ds2-feature\FEATURE_LIGHT_REVIEW_PROMPT.md`
2. `C:\Users\Captain\Documents\AI Workflows\Toolkits\wp-plugin-toolkit\d4-prompts\ds2-feature\FEATURE_BLOAT_AND_STRUCTURE_REVIEW_PROMPT.md`
3. `C:\Users\Captain\Documents\AI Workflows\Toolkits\wp-plugin-toolkit\d4-prompts\ds2-feature\FEATURE_UI_UX_IMPLEMENTATION_PROMPT.md`
4. `C:\Users\Captain\Documents\AI Workflows\Toolkits\wp-plugin-toolkit\d4-prompts\ds2-feature\FEATURE_SECURITY_REVIEW_PROMPT.md`
5. `C:\Users\Captain\Documents\AI Workflows\Toolkits\wp-plugin-toolkit\d4-prompts\ds6-maintenance\DOCUMENTATION_SYNC_AUDIT_PROMPT.md`

Then run:

- `C:\Users\Captain\Documents\AI Workflows\Toolkits\wp-plugin-toolkit\d4-prompts\ds5-git\GIT_OPERATIONS_PROMPT.md` option C.

Do not run workflow prompts mechanically when their work is already complete and the current change does not touch that area. Record applicable workflow results in the pre-release checklist before release approval.

## Current Recommendation

The plugin baseline is effectively complete for the validated MVP/new-plugin scope.

The destructive restore automation project is tracked separately in [DESTRUCTIVE_RESTORE_AUTOMATION_PLAN.md](DESTRUCTIVE_RESTORE_AUTOMATION_PLAN.md). The current implemented slices are `restore-dry-run`, optional `--write-report=1` dry-run evidence reports, pre-restore backup evidence validation, `restore-apply --scope=database`, `restore-apply --scope=files`, file restore symlink/drop-in reporting, and `restore-apply --scope=files-and-database`. The server runner checks staged restore evidence, staging-only gates, matching pre-restore backup evidence, and readable pre-restore backup artifacts before any apply. Database apply requires `--confirm=restore-staging-site`, imports only the staged `database.sql` through WP-CLI, and writes a restore apply report under configured `restore_reports_path`. File apply requires the same confirmation and evidence gates, replaces the configured staging WordPress path from staged `htdocs/`, reports missing pre-restore symlinked drop-ins for manual review, and writes a restore apply report. Combined apply runs file replacement first and database import second after the same gates pass. Automatic pre-restore backup creation and production restore remain future gated slices.
