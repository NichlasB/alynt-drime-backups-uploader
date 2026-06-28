# Destructive Restore Automation Plan

This document tracks the separate gated project for restoring Alynt server-runner backups onto a live WordPress target.

It is intentionally separate from the current restore staging flow. The existing runner can fetch, verify, inspect, and stage a package for review. This project would add the ability to overwrite a staging site's files and import its database after strict gates pass.

## Current Approval

Approved planning assumptions:

- Test site: `alyntdrime.sitesmain.com`.
- SSH alias: `sites-main`.
- Site root: `/var/www/alyntdrime.sitesmain.com`.
- WordPress path: `/var/www/alyntdrime.sitesmain.com/htdocs`.
- Version 1 scope: files plus database.
- Version 1 environment: staging-only.
- Confirmation phrase: `--confirm=restore-staging-site`.
- Pre-restore backup evidence is required before any destructive action.
- Read-only GridPane restore/snapshot investigation is allowed before implementation.

## Core Principle

The command must never make destructive changes just because a package exists.

It must prove:

1. The package has already been fetched or is locally available.
2. The package has already been verified.
3. The package has already been staged for inspection.
4. The target is explicitly marked as a staging restore target.
5. A pre-restore backup exists and is recorded.
6. The operator passed the exact confirmation phrase.

## Non-Goals For Version 1

- No wp-admin restore UI.
- No production restore support.
- No restore from arbitrary third-party archives.
- No remote Drime deletion.
- No automatic rollback guarantee beyond pre-restore backup evidence and clear failure reporting.
- No host-level snapshot orchestration until GridPane behavior is proven.
- No unattended cron restore.

## Proposed Commands

Dry run:

```bash
php alynt-backup-runner.php restore-dry-run \
  --config=/var/www/alyntdrime.sitesmain.com/private/alynt-drime-backups/runner/config.json \
  --staged-path=/var/www/alyntdrime.sitesmain.com/restores/alynt-drime-backups/PACKAGE_ID \
  --scope=files-and-database
```

Apply:

```bash
php alynt-backup-runner.php restore-apply \
  --config=/var/www/alyntdrime.sitesmain.com/private/alynt-drime-backups/runner/config.json \
  --staged-path=/var/www/alyntdrime.sitesmain.com/restores/alynt-drime-backups/PACKAGE_ID \
  --scope=files-and-database \
  --confirm=restore-staging-site
```

Supported `--scope` values for version 1:

- `files`
- `database`
- `files-and-database`

Recommended first implementation path:

1. Build `restore-dry-run`. Current source status: implemented as a preflight.
2. Add report generation. Current source status: implemented as optional `--write-report=1` success evidence under configured `restore_reports_path`.
3. Add pre-restore backup evidence checks. Current source status: implemented as validation of a matching evidence JSON file and readable backup artifacts.
4. Add `restore-apply --scope=database`. Current source status: implemented for staging database imports after confirmation, dry-run checks, and pre-restore evidence validation.
5. Add `restore-apply --scope=files`.
6. Combine as `restore-apply --scope=files-and-database`.

## Required Config Additions

Add restore-specific config keys. These should be absent by default in normal generated runner configs.

Example:

```json
{
  "restore_apply_enabled": true,
  "restore_environment": "staging",
  "restore_target_wordpress_path": "/var/www/alyntdrime.sitesmain.com/htdocs",
  "restore_pre_backup_path": "/var/www/alyntdrime.sitesmain.com/private/alynt-drime-backups/pre-restore",
  "restore_pre_backup_evidence_path": "/var/www/alyntdrime.sitesmain.com/private/alynt-drime-backups/pre-restore/PRE_RESTORE_BACKUP_EVIDENCE-package-id.json",
  "restore_reports_path": "/var/www/alyntdrime.sitesmain.com/private/alynt-drime-backups/restore-reports"
}
```

Safety rules:

- `restore_apply_enabled` must be `true`.
- `restore_environment` must equal `staging` for version 1.
- `restore_target_wordpress_path` must match the intended WordPress path.
- The command must refuse when the target path is empty, `/`, `/var/www`, or another broad parent path.
- Production-like values must be blocked until a future production design is approved.

## Read-Only GridPane Investigation

Before implementation, perform read-only investigation on `sites-main`.

Questions to answer:

- Which user should run restore commands for `alyntdrime.sitesmain.com`?
- What are the exact ownership and permission expectations for `htdocs`?
- Does GridPane expose a native backup, snapshot, or restore command that should be required before destructive restore?
- Is there a safe GridPane-specific way to enable maintenance mode?
- Are there cache clear commands that should run after restore?
- Are PHP-FPM or Nginx reloads needed after file replacement?
- Does `wp db export` and `wp db import` work as the site user?
- Where should pre-restore backup artifacts be stored without exposing them publicly?

Read-only examples:

```bash
ssh sites-main "id"
ssh sites-main "ls -ld /var/www/alyntdrime.sitesmain.com /var/www/alyntdrime.sitesmain.com/htdocs"
ssh sites-main "find /var/www/alyntdrime.sitesmain.com/htdocs -maxdepth 1 -printf '%M %u %g %p\n' | head"
ssh sites-main "cd /var/www/alyntdrime.sitesmain.com/htdocs && wp --info"
ssh sites-main "cd /var/www/alyntdrime.sitesmain.com/htdocs && wp db size --human-readable"
```

Do not run destructive, backup-writing, import, move, delete, or permission-changing commands during this investigation without a separate explicit approval.

## Read-Only GridPane Investigation Findings

Investigation date: 2026-06-27.

Target confirmed:

- SSH alias lands as `root`.
- Site user/group: `alyntdrime-sitesmain-com`.
- Site root owner/group: `alyntdrime-sitesmain-com:alyntdrime-sitesmain-com`.
- WordPress path: `/var/www/alyntdrime.sitesmain.com/htdocs`.
- WordPress URL values:
  - `home`: `https://alyntdrime.sitesmain.com`
  - `siteurl`: `https://alyntdrime.sitesmain.com`
- WordPress version: `7.0`.
- PHP CLI version through WP-CLI: `8.2.31`.
- WP-CLI version: `2.12.0`.
- Database name/user observed through WP-CLI config reads: `YyF_alyntdrime_sitesmain_com`.
- Table prefix: `wp_`.
- Database size: about `192 MB`.
- Active theme: `blocksy-child`; template: `blocksy`.
- HTTP check returned `200` for `https://alyntdrime.sitesmain.com`.

Filesystem:

- `/var/www/alyntdrime.sitesmain.com/htdocs` is about `2.4G`.
- `/var/www/alyntdrime.sitesmain.com/private` is about `1.7G`.
- `/var/www/alyntdrime.sitesmain.com/restores` is currently small.
- Available filesystem space was about `13G` on `/dev/sda1`.
- `wp-config.php` lives outside `htdocs` at `/var/www/alyntdrime.sitesmain.com/wp-config.php`.
- File restore v1 must target `htdocs` only and must not overwrite the site root, `wp-config.php`, `private`, `nginx`, `logs`, `modsec`, `dns`, or `restores`.
- One symlink was found in `htdocs`: `wp-content/db.php` points into the Query Monitor plugin. Current server-runner archives exclude symlink entries, so destructive file restore must either recreate/validate expected drop-ins or document that excluded symlinked drop-ins may need regeneration.

Current runner/install state:

- Installed runner config matches the expected private outbox/work/restore layout.
- Installed runner script still reports `const VERSION = '0.1.0'`.
- Installed runner script does not contain newer source features such as remote catalog sidecars, cleanup execution, or richer restore guidance.
- Before destructive restore testing, install/update the staging runner from the current source and create a fresh package with current sidecars and restore reports.
- Current outbox contains one older package set, about `1.8G`, with archive, manifest, and checksum sidecar only.
- Restore staging directories under `/var/www/alyntdrime.sitesmain.com/restores/alynt-drime-backups` and `/var/www/alyntdrime.sitesmain.com/restores/alynt-drime-backups-fetch-validation` were empty at the time of inspection.

Cache/runtime:

- `redis-cache` is active and `wp redis status` reported connected.
- `nginx-helper` is active.
- Future apply flow should include explicit post-restore cache handling, probably WP object cache/Redis flush plus Nginx Helper or host cache purge after the destructive restore succeeds.
- Do not include Redis salts/password-like values in reports; the WP-CLI status command can print sensitive cache constants even when some fields are masked.

GridPane command caution:

- `/usr/local/bin/gp` exists.
- `/usr/local/bin/gpbup` exists.
- Running `gpbup` without arguments is not read-only on this host. It triggered backup logic, reported backups disabled for `alyntdrime.sitesmain.com`, then checked another site. It did not produce a backup for `alyntdrime.sitesmain.com`, but future investigation must not call `gpbup` casually.
- Do not rely on native GridPane backups for this staging target until a specific safe command or operator-provided procedure is identified.

Implications for version 1:

- Run restore commands as the site user where possible, not as root.
- Keep pre-restore database exports and file backups under a private path such as `/var/www/alyntdrime.sitesmain.com/private/alynt-drime-backups/pre-restore`.
- The disk budget is enough for one controlled rehearsal, but the command must preflight space carefully because current `htdocs` plus backup package plus staged copy can consume several GB.
- Version 1 should create its own pre-restore evidence instead of assuming GridPane native backups are enabled.
- Apply should refuse if the installed runner/config is stale or missing restore-specific staging gates.
- Post-restore checks should include `wp option get home`, `wp option get siteurl`, a WP-CLI core/plugin sanity check, and an HTTP `200` check.

## Pre-Restore Backup Evidence

Version 1 should require local pre-restore evidence before overwrite.

Minimum evidence:

- A fresh SQL export of the target database.
- A file backup or archive of the current target files.
- A JSON evidence file recording:
  - generated time
  - target site URL
  - target WordPress path
  - database export path
  - file backup path
  - package ID being restored
  - operator command scope

Potential command:

```bash
php alynt-backup-runner.php restore-preflight-backup \
  --config=/path/to/config.json \
  --scope=files-and-database
```

Alternative for version 1:

- `restore-apply` may create this pre-restore evidence automatically before any overwrite.
- If that automatic backup fails, restore must stop before touching live files or database.

## Restore Apply Flow

Dry run should verify:

1. Config allows staging restore only.
2. `--staged-path` exists and is under an approved restore path.
3. `RESTORE_REPORT.json` exists.
4. `RESTORE_REPORT.json` says:
   - `status: staged_for_inspection`
   - `package_verified: true`
   - `archive_members_safe: true`
   - `database_imported: false`
   - `live_files_overwritten: false`
   - `manual_restore_required: true`
5. Staged `htdocs/` exists when file restore is requested.
6. Staged `database.sql` exists when database restore is requested.
7. Target WordPress path exists and matches config.
8. Target database is reachable by WP-CLI.
9. Pre-restore backup path is writable.
10. Disk space is sufficient for pre-restore backup and restore work.

Current `restore-dry-run` behavior:

- Reads staged evidence and writes nothing by default.
- Supports `--scope=files`, `--scope=database`, and `--scope=files-and-database`.
- Supports `--format=json`.
- Supports `--pre-restore-evidence=/path/to/evidence.json` override, otherwise reads `restore_pre_backup_evidence_path` from config.
- Supports `--write-report=1` to write a successful dry-run evidence report under configured `restore_reports_path`.
- Refuses to write success evidence when the dry run fails.
- Requires pre-restore backup evidence to match package ID, scope, and target path.
- Requires scope-specific backup artifact files to be readable and under `restore_pre_backup_path`.
- Reports `destructive_actions_performed: false`, `database_imported: false`, `live_files_overwritten: false`, and `pre_restore_backup_created: false`. `restore_apply_command_available` is true only for `database` scope in the current partial implementation.
- Does not create pre-restore backups yet.
- Does not check target database connectivity yet.
- Does not import the database itself.

Current `restore-apply --scope=database` behavior:

- Requires `--confirm=restore-staging-site`.
- Refuses `files` and `files-and-database` scopes.
- Runs the existing dry-run/evidence checks with `database` scope.
- Refuses to import when any dry-run check fails.
- Imports staged `database.sql` with WP-CLI against the configured target WordPress path.
- Writes `RESTORE_APPLY_REPORT-*.json` under configured `restore_reports_path`.
- Reports database import attempt/success, dry-run checks, report path, failure step, and manual recovery notes.
- Does not create the pre-restore backup evidence automatically yet.
- Does not restore files yet.

Staging rehearsal status:

- Passed on `alyntdrime.sitesmain.com` on 2026-06-28.
- Updated runner was installed under `/var/www/alyntdrime.sitesmain.com/private/alynt-drime-backups/runner/alynt-backup-runner.php`, with the previous runner retained as a timestamped `.bak-*` file.
- Fresh package `alyntdrime-sitesmain-com-20260628-081212.tar.gz` was created, verified, and staged.
- Pre-restore database export evidence was created at `/var/www/alyntdrime.sitesmain.com/private/alynt-drime-backups/pre-restore/current-database-before-alyntdrime-sitesmain-com-20260628-081212.sql`.
- `restore-dry-run --scope=database --write-report=1` passed with `failure_count: 0`.
- `restore-apply --scope=database --confirm=restore-staging-site` succeeded, imported the staged `database.sql`, and wrote `RESTORE_APPLY_REPORT-alyntdrime-sitesmain-com-20260628-081212-20260628-081847.json`.
- Post-apply verification passed: WP-CLI returned the expected `home` and `siteurl`, WordPress core version `7.0`, `wp db check` succeeded, and HTTPS returned `200`.

Current dry-run report fields:

- `report_write_requested`
- `report_written`
- `report_path`
- `report_write_error`

Current pre-restore backup evidence shape:

```json
{
  "schema_version": 1,
  "evidence_type": "pre_restore_backup",
  "generated_at": "2026-06-27T15:30:00+00:00",
  "package_id": "example-com-YYYYmmdd-HHMMSS",
  "scope": "files-and-database",
  "target_wordpress_path": "/var/www/example.com/htdocs",
  "database_export_path": "/var/www/example.com/private/alynt-drime-backups/pre-restore/current-database.sql",
  "file_backup_path": "/var/www/example.com/private/alynt-drime-backups/pre-restore/current-files.tar.gz"
}
```

Feature-stage review status for the first dry-run slice:

- Feature Light Review: passed after tightening staged report path checks so malformed `file_root` or `database_dump` values fail the dry run.
- Feature Bloat And Structure Review: completed with explicit base ref `52380a4`; the standalone runner remains oversized and is deferred because splitting the portable CLI script is architecture-sensitive.
- Feature UI/UX Implementation Review: not applicable; no wp-admin UI, frontend UI, AJAX, REST, or browser-facing controls changed.
- Feature Security Review: passed; dry run validates scope, restore-path containment, staged report evidence, safe single-segment report paths, target path safety, and reports no destructive actions.
- Documentation Sync Audit: completed; README, readme.txt, changelog, server-runner docs, restore runbook, package security docs, pre-release checklist, and this plan now describe the dry-run boundary.
- Validation: PHP syntax checks, focused restore/security PHPUnit, full `npm.cmd test`, `npm.cmd run lint`, `git diff --check`, and feature bloat report passed. `git diff --check` reports only the existing `readme.txt` CRLF warning.

Feature-stage review status for the dry-run report-generation slice:

- Feature Light Review: passed; report writing is opt-in, writes only successful dry-run evidence, and leaves failed dry runs without success artifacts.
- Feature Bloat And Structure Review: completed with explicit base ref `65aef5a`; the new test file was trimmed under threshold, and the standalone runner remains oversized/deferred because splitting the portable CLI script is architecture-sensitive.
- Feature UI/UX Implementation Review: not applicable; no wp-admin UI, frontend UI, AJAX, REST, or browser-facing controls changed.
- Feature Security Review: passed; report writing is gated by `--write-report=1`, requires configured `restore_reports_path`, blocks broad report paths, and still exposes no `restore-apply` command.
- Documentation Sync Audit: completed; README, readme.txt, changelog, server-runner docs, restore runbook, package security docs, pre-release checklist, implementation plan, and this plan now describe optional dry-run evidence reports.
- Validation: PHP syntax checks, focused restore/security PHPUnit, full `npm.cmd test`, `npm.cmd run lint`, `git diff --check`, and feature bloat report passed. `git diff --check` reports only the existing `readme.txt` CRLF warning.

Feature-stage review status for the pre-restore backup evidence validation slice:

- Feature Light Review: passed; the slice validates existing evidence only and does not create backups or perform restore actions.
- Feature Bloat And Structure Review: completed with explicit base ref `3239a3a`; changed test files are under threshold, and the standalone runner remains oversized/deferred because splitting the portable CLI script is architecture-sensitive.
- Feature UI/UX Implementation Review: not applicable; no wp-admin UI, frontend UI, AJAX, REST, or browser-facing controls changed.
- Feature Security Review: passed; evidence file and artifact paths must live under `restore_pre_backup_path`, match package/scope/target, and be readable before dry run can pass.
- Documentation Sync Audit: completed; README, readme.txt, changelog, server-runner docs, restore runbook, package security docs, pre-release checklist, implementation plan, and this plan now describe pre-restore evidence validation.
- Validation: PHP syntax checks, focused restore/security PHPUnit, full `npm.cmd test`, `npm.cmd run lint`, `git diff --check`, and feature bloat report passed. `git diff --check` reports only the existing `readme.txt` CRLF warning.

Apply should:

1. Run the same dry-run checks.
2. Require `--confirm=restore-staging-site`.
3. Create pre-restore backup evidence.
4. Put the staging site into a maintenance-safe state when possible.
5. Restore database if requested.
6. Restore files if requested.
7. Restore or preserve expected ownership and permissions.
8. Flush caches or rewrite rules where safe.
9. Run post-restore WordPress checks.
10. Write `RESTORE_APPLY_REPORT.json`.
11. Leave enough evidence for manual recovery if any step fails.

## File Restore Strategy

Version 1 should avoid blind broad deletion where possible.

Preferred staging-only approach:

- Move current `htdocs` to a timestamped pre-restore directory only after pre-restore backup evidence exists.
- Move or copy staged `htdocs` into the target path.
- Preserve or re-apply ownership/group based on the original target path.
- Refuse if target path resolution is outside the configured site root.

Open implementation choice:

- Use atomic-ish directory swap for staging.
- Or use `rsync --delete` after pre-restore backup evidence.

This should be decided after read-only GridPane investigation.

## Database Restore Strategy

Preferred version 1 approach:

- Use WP-CLI from the target WordPress path.
- Export current DB first.
- Import staged `database.sql` only after export succeeds.
- Run a post-import `wp option get home` and `wp option get siteurl`.
- Do not run search-replace automatically in version 1 unless the package and target URL mismatch is explicitly approved.

Potential commands:

```bash
wp --path=/var/www/alyntdrime.sitesmain.com/htdocs db export /private/pre-restore/current.sql
wp --path=/var/www/alyntdrime.sitesmain.com/htdocs db import /path/to/staged/database.sql
```

## Reporting

Write a machine-readable apply report:

```text
RESTORE_APPLY_REPORT.json
```

Minimum fields:

- schema version
- generated time
- command
- scope
- target environment
- target WordPress path
- package ID
- staged path
- confirmation phrase accepted
- dry-run checks passed
- pre-restore database backup path
- pre-restore file backup path
- database import attempted
- database import succeeded
- file restore attempted
- file restore succeeded
- maintenance action attempted
- post-restore checks
- failure step
- manual recovery notes

The report must not include Drime tokens, database passwords, salts, cookies, signed URLs, or raw file contents.

## Acceptance Criteria

Version 1 is acceptable only when all of these pass on `alyntdrime.sitesmain.com`:

- `restore-dry-run` passes against a verified staged package.
- `restore-dry-run` fails for wrong target paths.
- `restore-dry-run` fails when `RESTORE_REPORT.json` is missing or unsafe.
- `restore-apply` refuses without `--confirm=restore-staging-site`.
- `restore-apply` refuses when restore config does not explicitly say staging.
- `restore-apply` creates pre-restore database and file backup evidence before overwrite.
- Database restore succeeds on staging.
- File restore succeeds on staging.
- Ownership/permissions are correct after restore.
- WordPress loads after restore.
- WP-CLI can read `home` and `siteurl` after restore.
- `RESTORE_APPLY_REPORT.json` records every attempted destructive action.
- A failed restore leaves enough evidence for manual recovery.

## Test Strategy

Local/unit coverage:

- Dry-run config guard tests.
- Staged path containment tests.
- Confirmation phrase tests.
- Report validation tests.
- Refusal tests for missing pre-restore evidence.
- Refusal tests for production-like config.

Server/staging coverage:

- Read-only GridPane investigation.
- Create known package A.
- Mutate staging site content/database to known state B.
- Stage package A.
- Run dry-run.
- Run apply.
- Confirm site returns to package A state.
- Confirm pre-restore evidence preserves state B.
- Repeat at least once to prove the flow is not a one-off.

## Approval Gates

This project needs explicit approval before each phase:

1. Read-only GridPane investigation.
2. Source implementation of dry-run/report-only commands.
3. Source implementation of destructive apply command.
4. First staging destructive test.
5. Repeated staging destructive test.
6. Any future production-capable design.

## Open Questions

- Should pre-restore backup be a separate command or always created by `restore-apply`?
- Should file restore use directory swap or `rsync --delete` for GridPane staging?
- Should maintenance mode be WordPress maintenance file based, plugin based, or GridPane/host based?
- Should post-restore checks include HTTP checks, WP-CLI checks, or both?
- Should version 1 support database-only and files-only apply paths, or keep them dry-run-only until the combined flow passes?

## Current Recommendation

Start with read-only GridPane investigation and a dry-run/reporting implementation. Do not implement the destructive `restore-apply` command until the dry-run report proves the target, package, pre-restore backup location, and staging-only gate are all reliable.
