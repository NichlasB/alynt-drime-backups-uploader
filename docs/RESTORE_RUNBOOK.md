# Restore Runbook

This runbook covers the first restore workflow for Alynt Drime Backups Uploader server-runner packages.

The restore support is intentionally gated. The runner can fetch a known package from Drime, verify it, stage it for inspection, run read-only dry-run checks, and apply database-only, files-only, or combined files-and-database restores to a staging target after explicit confirmation and pre-restore evidence checks. `restore-apply` can also create the required staging pre-restore evidence itself when `--create-pre-restore-backup=1` is explicitly supplied.

The runner prints next-step guidance after successful `fetch`, `verify`, `inspect`, and `stage-restore` commands. Treat that output as an operator checklist, not as approval to perform a live restore.

## Scope

Supported now:

- Verify a local `.tar.gz` package and its sidecars.
- List local outbox package inventory as JSON for package discovery.
- Use package-level `.remote-index.json` and folder catalog `.remote-catalog.json` sidecars as restore discovery helpers for server-runner package sets.
- Fetch a known Drime package and its sidecars into a local download directory.
- Inspect package metadata and archive contents.
- Extract a verified package into a separate restore staging directory.
- Write a local `RESTORE_REPORT.json` evidence file after successful restore staging.
- Run a read-only `restore-dry-run` preflight against staged evidence.
- Apply database-only, files-only, or combined files-and-database restores to a staging target after explicit gates pass.
- Create staging pre-restore database/file backup evidence immediately before `restore-apply` when `--create-pre-restore-backup=1` is explicitly supplied.
- Review restored files and `database.sql` without touching production.

Not supported yet:

- Running an automated production restore command.

Any production restore must remain a manual, explicitly approved operation until the separate production-capable workflow is designed and tested. Staging-only apply is tracked in [DESTRUCTIVE_RESTORE_AUTOMATION_PLAN.md](DESTRUCTIVE_RESTORE_AUTOMATION_PLAN.md); future production capability is tracked in [PRODUCTION_RESTORE_AUTOMATION_PLAN.md](PRODUCTION_RESTORE_AUTOMATION_PLAN.md).

## Package Layout

A complete current server-runner package has four local artifacts:

```text
example-com-YYYYmmdd-HHMMSS.tar.gz
example-com-YYYYmmdd-HHMMSS.tar.gz.manifest.json
example-com-YYYYmmdd-HHMMSS.tar.gz.sha256
example-com-YYYYmmdd-HHMMSS.tar.gz.remote-index.json
example-com-YYYYmmdd-HHMMSS.tar.gz.remote-catalog.json
```

The archive contains:

```text
htdocs/
database.sql
manifest.json
```

`database.sql` is present only when database exports are enabled in the runner config.

## Pre-Restore Checklist

Before running a restore inspection:

- Confirm the exact site and server target.
- Confirm the package path and sidecars are local to the server.
- Confirm there is enough free disk space for at least one extracted copy of the package.
- Confirm `restore_path` points outside the public web root.
- Confirm the action is inspection-only unless a separate manual restore approval is given.
- Run the runner `health` command and confirm all resource checks pass.

To review local outbox packages before choosing one:

```bash
php /path/to/alynt-backup-runner.php list \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --format=json
```

Use the JSON inventory to confirm the package ID, archive name, sidecar names, manifest/checksum/remote-index validity, checksum metadata, and whether `verification_ready` is true. The remote catalog snapshot can help compare package sets when the original local inventory is unavailable. These are discovery helpers only; still run `verify` before inspection or staging.

For GridPane sites, the preferred restore staging path is:

```text
/var/www/example.com/restores/alynt-drime-backups
```

## Verify A Package

If the only copy is in Drime, fetch the package and sidecars first:

```bash
export ALYNT_DRIME_TOKEN='drime-bearer-token'

php /path/to/alynt-backup-runner.php fetch \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --package-id=example-com-YYYYmmdd-HHMMSS \
  --workspace-id=0 \
  --folder-hash=DRIME_FOLDER_HASH \
  --download-path=/var/www/example.com/private/alynt-drime-backups/downloads
```

`fetch` requires exact remote matches for the archive, `.manifest.json`, and `.sha256` sidecar. It downloads into temporary files, refuses to overwrite existing files unless `--overwrite=1` is supplied, and verifies the package immediately after download. The Drime token must come from the environment variable named by `--token-env`, defaulting to `ALYNT_DRIME_TOKEN`.

For server-runner packages uploaded through the generic outbox producer, the plugin creates or reuses a Drime package folder named after the package ID, then uploads the archive, manifest, checksum, package-level remote-index, and folder catalog snapshot sidecars into that package folder. If `fetch` reports a missing required manifest/checksum sidecar, stop and repair the remote package set before treating the backup as restorable.

Run verification before inspecting or extracting:

```bash
php /path/to/alynt-backup-runner.php verify \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --package=/var/www/example.com/private/alynt-drime-backups/outbox/example-com-YYYYmmdd-HHMMSS.tar.gz
```

Expected result:

```text
Package verified: /path/to/package.tar.gz
Restore guidance:
- Package is intact: package.tar.gz
- Next: run inspect to review metadata, timing, and archive preview.
```

Stop if the command reports missing sidecars, an invalid manifest, or a checksum mismatch.

## Inspect A Package

Inspect prints manifest details and a short archive preview without extracting files:

```bash
php /path/to/alynt-backup-runner.php inspect \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --package=/var/www/example.com/private/alynt-drime-backups/outbox/example-com-YYYYmmdd-HHMMSS.tar.gz
```

Confirm the output matches the expected site URL, archive format, file root, and database dump path.

For server-runner packages, also review backup type and timing fields. See [CONSISTENCY_MODEL.md](CONSISTENCY_MODEL.md) for how to interpret logical backup timing.

The `inspect` command ends with guidance to run `stage-restore` only when the package ID, site URL, timing, and archive preview match the intended recovery target.

## Stage A Restore

Stage the package into a new restore directory:

```bash
php /path/to/alynt-backup-runner.php stage-restore \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --package=/var/www/example.com/private/alynt-drime-backups/outbox/example-com-YYYYmmdd-HHMMSS.tar.gz
```

The runner creates:

```text
/var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS/
/var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS/RESTORE_NOTES.txt
/var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS/RESTORE_REPORT.json
```

It refuses to overwrite an existing restore directory with the same package ID.

After a successful run, `stage-restore` prints the staged path and a compact review checklist:

```text
Restore staging completed.
Review next:
1. Open RESTORE_NOTES.txt.
2. Open RESTORE_REPORT.json.
3. Inspect htdocs/ before any file replacement.
4. Inspect database.sql before any database import.
5. Keep production restore steps manual until separately approved.
```

Before extracting, the runner validates archive member names and refuses packages with absolute paths, parent-directory traversal, empty path segments, or symlink/hardlink entries. If this safety validation fails, stop and treat the package as unsuitable for restore staging until its source is understood.

New server-runner packages exclude symlinks during archive creation. If an older package fails validation because it contains a link entry, create a fresh package with the current runner or handle that package through a separate approved manual recovery procedure.

After staging, check:

```bash
du -sh /var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS
ls -la /var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS
cat /var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS/RESTORE_NOTES.txt
```

`RESTORE_NOTES.txt` should state that no database import was performed and no live WordPress files were overwritten.

`RESTORE_REPORT.json` is a machine-readable local evidence file. It records package ID, archive and sidecar names, checksum metadata, non-secret manifest fields, `package_verified`, `archive_members_safe`, `extracted_for_inspection`, `database_imported: false`, `live_files_overwritten: false`, and `manual_restore_required: true`.

## Restore Dry Run

After staging and inspection, run a read-only dry run if you are preparing for the separate destructive restore project:

```bash
php /path/to/alynt-backup-runner.php restore-dry-run \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --staged-path=/var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS \
  --pre-restore-evidence=/var/www/example.com/private/alynt-drime-backups/pre-restore/PRE_RESTORE_BACKUP_EVIDENCE-example-com-YYYYmmdd-HHMMSS.json \
  --scope=files-and-database \
  --format=json
```

The evidence path can also be saved in runner config as `restore_pre_backup_evidence_path`.

Minimum evidence JSON shape:

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

For `database` scope, `database_export_path` must be present, under `restore_pre_backup_path`, and readable. For `files` scope, `file_backup_path` must be present, under `restore_pre_backup_path`, and readable. For `files-and-database`, both are required.

To write a persistent evidence report after a passing dry run, add `--write-report=1` and configure `restore_reports_path`:

```json
{
  "restore_reports_path": "/var/www/example.com/private/alynt-drime-backups/restore-reports"
}
```

```bash
php /path/to/alynt-backup-runner.php restore-dry-run \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --staged-path=/var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS \
  --scope=files-and-database \
  --write-report=1 \
  --format=json
```

Supported scope values are:

- `files`
- `database`
- `files-and-database`

The dry run reads config and staged evidence only. It checks:

- `restore_apply_enabled` is explicitly enabled in config.
- `restore_environment` is `staging`.
- `--staged-path` is inside the configured `restore_path`.
- `RESTORE_REPORT.json` exists, is valid JSON, and records staged inspection evidence.
- Staged `htdocs/` exists when file restore is requested.
- Staged `database.sql` exists when database restore is requested.
- `restore_target_wordpress_path` matches the runner `wordpress_path` and is not a broad system path.
- `restore_pre_backup_path` exists or its parent is writable.
- Pre-restore evidence exists, is valid JSON, matches package/scope/target, and points to readable backup artifacts under `restore_pre_backup_path`.
- The target filesystem has the configured minimum free space.

The dry run reports `destructive_actions_performed: false`, `database_imported: false`, `live_files_overwritten: false`, and `pre_restore_backup_created: false`. `restore_apply_command_available` is true for `database`, `files`, and `files-and-database` scopes in this release. By default it writes nothing. With `--write-report=1`, it writes only a successful dry-run evidence report under `restore_reports_path`; failed dry runs do not create success evidence. It does not create a pre-restore backup, import a database, overwrite files, delete files, or run shell restore commands.

## Staging Database Apply

After staging and inspection, a staging database restore can be applied with the explicit confirmation phrase. Existing evidence may be supplied with `--pre-restore-evidence`, or the runner can create fresh database evidence immediately before apply with `--create-pre-restore-backup=1`:

```bash
php /path/to/alynt-backup-runner.php restore-apply \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --staged-path=/var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS \
  --pre-restore-evidence=/var/www/example.com/private/alynt-drime-backups/pre-restore/PRE_RESTORE_BACKUP_EVIDENCE-example-com-YYYYmmdd-HHMMSS.json \
  --scope=database \
  --create-pre-restore-backup=1 \
  --confirm=restore-staging-site \
  --format=json
```

This command is intentionally narrow:

- It only imports the database for `--scope=database`.
- It refuses to run without the exact `--confirm=restore-staging-site` phrase.
- It runs the existing dry-run/evidence checks before importing anything.
- It creates matching pre-restore database evidence first when `--create-pre-restore-backup=1` is supplied.
- It imports only the staged `database.sql` through WP-CLI.
- It writes a `RESTORE_APPLY_REPORT-*.json` file under `restore_reports_path`.
- It does not restore files in this scope, combine files plus database, or support production restore.

Before running it without `--create-pre-restore-backup=1`, confirm that the pre-restore evidence points to a readable database export created before the restore attempt. If apply fails, stop and inspect the apply report plus the pre-restore evidence before attempting manual recovery.

## Staging File Apply

After staging and inspection, staging files can be replaced with the explicit confirmation phrase. Existing evidence may be supplied with `--pre-restore-evidence`, or the runner can create a fresh file backup and evidence immediately before apply with `--create-pre-restore-backup=1`:

```bash
php /path/to/alynt-backup-runner.php restore-apply \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --staged-path=/var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS \
  --pre-restore-evidence=/var/www/example.com/private/alynt-drime-backups/pre-restore/PRE_RESTORE_BACKUP_EVIDENCE-example-com-YYYYmmdd-HHMMSS.json \
  --scope=files \
  --create-pre-restore-backup=1 \
  --confirm=restore-staging-site \
  --format=json
```

This command is also narrow:

- It only replaces files for `--scope=files`.
- It refuses to run without the exact `--confirm=restore-staging-site` phrase.
- It runs the existing dry-run/evidence checks before replacing files.
- It creates matching pre-restore file backup evidence first when `--create-pre-restore-backup=1` is supplied.
- It replaces the configured staging WordPress path from staged `htdocs/`.
- It writes a `RESTORE_APPLY_REPORT-*.json` file under `restore_reports_path`.
- It reports pre-restore symlinked drop-ins that are absent from the staged files as `file_restore_missing_symlink_count`, `file_restore_missing_symlink_samples`, and `file_restore_manual_review_required`.
- It reports known post-restore drop-in review items such as `wp-content/db.php` as `post_restore_manual_review_items`.
- It does not import the database in this scope, combine files plus database, or support production restore.

Before running it without `--create-pre-restore-backup=1`, confirm that the pre-restore evidence points to a readable file backup created before the restore attempt. After apply, inspect the apply report for missing symlink/drop-in warnings and `post_restore_manual_review_items`. If warnings exist, check files such as `wp-content/db.php` and regenerate or restore plugin-owned drop-ins manually as needed. If `post_restore_cleanup_required` is true, remove or regenerate broken links only after operator review. If apply fails, stop and inspect the apply report plus the pre-restore evidence before attempting manual recovery.

## Staging Combined Apply

After staging and inspection, staging files and database can be restored in one gated command. Existing evidence may be supplied with `--pre-restore-evidence`, or the runner can create fresh database and file backup evidence immediately before apply with `--create-pre-restore-backup=1`:

```bash
php /path/to/alynt-backup-runner.php restore-apply \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --staged-path=/var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS \
  --pre-restore-evidence=/var/www/example.com/private/alynt-drime-backups/pre-restore/PRE_RESTORE_BACKUP_EVIDENCE-example-com-YYYYmmdd-HHMMSS.json \
  --scope=files-and-database \
  --create-pre-restore-backup=1 \
  --confirm=restore-staging-site \
  --format=json
```

The command creates matching pre-restore database and file backup evidence first when `--create-pre-restore-backup=1` is supplied, then runs the same dry-run/evidence checks with `files-and-database` scope, replaces staged files first, and imports the staged database second. If file replacement fails, database import is not attempted. If database import fails after files are restored, the apply report marks the database phase failed and records manual recovery notes.

The apply report includes `combined_restore_order: ["files", "database"]`, the existing file/database phase booleans, the same symlink/drop-in warning fields used by file-only apply, and `post_restore_manual_review_items` for known drop-ins such as Query Monitor's `wp-content/db.php`.

## Manual Disaster Restore Outline

This outline is not an automated production restore command. Use it only after a human has approved a real restore and the staged files have been inspected.

1. Put the site into a maintenance-safe state.
2. Create a fresh pre-restore snapshot through the host or server-level tooling.
3. Verify and stage the package.
4. Inspect `htdocs/`, `database.sql`, and `manifest.json`.
5. Confirm the target database name, user, and table prefix from the live `wp-config.php`.
6. Import `database.sql` only after a database backup exists and the target database has been confirmed.
7. Replace files only after a filesystem backup exists and the target path has been confirmed.
8. Run WordPress URL, permalink, cache, and login checks.
9. Remove maintenance mode only after runtime checks pass.

The runner now supports staging-only database, file, and combined files-and-database apply commands after confirmation gates, dry-run output, pre-restore backup evidence, and staging evidence. `restore-apply` can create that pre-restore evidence itself for staging targets when explicitly requested. Production restore remains outside the automated scope.

For the package integrity, extraction safety, storage-path, and encryption boundaries behind this runbook, see [PACKAGE_SECURITY.md](PACKAGE_SECURITY.md).

## Drime Download

If the only copy is in Drime, prefer the CLI `fetch` command above when the package ID, workspace ID, package-folder hash, and token are available. Otherwise, open the package folder in Drime, download the package plus required manifest/checksum sidecars manually to the server first, then run the local verification workflow. Download the `.remote-index.json` and `.remote-catalog.json` sidecars too when they are available so discovery notes stay with the package set.

See [REMOTE_RESTORE_DISCOVERY.md](REMOTE_RESTORE_DISCOVERY.md) for the manual disaster discovery path, CLI fetch behavior, package-level remote-index sidecar, and folder catalog snapshot sidecar.

## Restore Rehearsal Report

For onboarding or periodic confidence checks, use [RESTORE_REHEARSAL_CHECKLIST.md](RESTORE_REHEARSAL_CHECKLIST.md) after this runbook. It gives operators a compact checklist and report template for recording package ID, Drime location, verification result, staging path, `RESTORE_NOTES.txt` review, `RESTORE_REPORT.json` evidence, and cleanup decision.

The report is evidence that a backup can be fetched, verified, inspected, and staged. It is not approval to import a database or overwrite live files.
