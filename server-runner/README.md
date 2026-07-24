# Alynt Server Backup Runner

The server runner creates backup packages that the WordPress plugin can detect through the generic outbox producer.

This first runner is intentionally conservative:

- It is site-local and config-driven.
- It writes packages to a configured outbox directory.
- It uses WP-CLI for database exports.
- It uses `tar` for WordPress file archives.
- It writes manifest, SHA-256, package-level remote-index, and folder catalog snapshot sidecars.
- It can record light consistency metadata for high-write-site review.
- It writes temporary files first, then atomically renames completed artifacts into the outbox.

The current standalone runner identity is `0.4.8`. Production apply and rollback reports carry that runner identity so older deployed copies can be identified before an approved rehearsal. This runner-only identity is independent of the WordPress plugin release version.

## Source And Generated Artifact

`server-runner/alynt-backup-runner.php` is the generated, single-file deployment artifact. Edit the authoritative source under `server-runner/src/` and run:

```bash
npm run build:runner
npm run verify:runner
npm run lint:runner
```

Generation uses an explicit source manifest, LF line endings, no timestamps, and one final newline. CI rebuilds the runner and fails when the committed artifact is stale. Release packages include the generated runner and exclude the modular source and build scripts, so existing GridPane installation and rollback commands continue to copy one portable file.

## Commands

```bash
php alynt-backup-runner.php health --config=/path/to/config.json
php alynt-backup-runner.php run --config=/path/to/config.json
php alynt-backup-runner.php list --config=/path/to/config.json
php alynt-backup-runner.php list --config=/path/to/config.json --format=json
php alynt-backup-runner.php cleanup-preview --config=/path/to/config.json --older-than-days=14
php alynt-backup-runner.php cleanup-preview --config=/path/to/config.json --older-than-days=14 --format=json
php alynt-backup-runner.php cleanup --config=/path/to/config.json --older-than-days=14 --confirm=delete-local-artifacts --format=json
php alynt-backup-runner.php verify --config=/path/to/config.json --package=/path/to/package.tar.gz
php alynt-backup-runner.php inspect --config=/path/to/config.json --package=/path/to/package.tar.gz
php alynt-backup-runner.php fetch --config=/path/to/config.json --package-id=package-id --workspace-id=0 --folder-hash=hash --download-path=/path/to/downloads
php alynt-backup-runner.php stage-restore --config=/path/to/config.json --package=/path/to/package.tar.gz
php alynt-backup-runner.php restore-production-preflight --config=/path/to/config.json --staged-path=/path/to/restores/package-id --scope=files-and-database --target-site=example.com --format=json
php alynt-backup-runner.php restore-production-preflight --config=/path/to/config.json --staged-path=/path/to/restores/package-id --scope=files-and-database --target-site=example.com --write-report=1 --format=json
php alynt-backup-runner.php restore-production-create-pre-backup --config=/path/to/config.json --staged-path=/path/to/restores/package-id --scope=files-and-database --target-site=example.com --confirm=create-production-pre-restore-backup --format=json
php alynt-backup-runner.php restore-production-apply --config=/path/to/config.json --staged-path=/path/to/restores/package-id --scope=files --pre-restore-evidence=/path/to/PRODUCTION_PRE_RESTORE_EVIDENCE.json --target-site=example.com --confirm=restore-production-site --confirm-site=example.com --format=json
php alynt-backup-runner.php restore-production-apply --config=/path/to/config.json --staged-path=/path/to/restores/package-id --scope=database --pre-restore-evidence=/path/to/PRODUCTION_PRE_RESTORE_EVIDENCE.json --target-site=example.com --confirm=restore-production-site --confirm-site=example.com --format=json
php alynt-backup-runner.php restore-production-apply --config=/path/to/config.json --staged-path=/path/to/restores/package-id --scope=files-and-database --pre-restore-evidence=/path/to/PRODUCTION_PRE_RESTORE_EVIDENCE.json --target-site=example.com --confirm=restore-production-site --confirm-site=example.com --format=json
php alynt-backup-runner.php restore-production-rollback --config=/path/to/config.json --apply-report=/path/to/RESTORE_PRODUCTION_APPLY_REPORT.json --target-site=example.com --confirm=rollback-production-site --confirm-site=example.com --format=json
php alynt-backup-runner.php restore-dry-run --config=/path/to/config.json --staged-path=/path/to/restores/package-id --scope=files-and-database --format=json
php alynt-backup-runner.php restore-dry-run --config=/path/to/config.json --staged-path=/path/to/restores/package-id --scope=files-and-database --pre-restore-evidence=/path/to/pre-restore/evidence.json --format=json
php alynt-backup-runner.php restore-dry-run --config=/path/to/config.json --staged-path=/path/to/restores/package-id --scope=files-and-database --write-report=1 --format=json
php alynt-backup-runner.php restore-apply --config=/path/to/config.json --staged-path=/path/to/restores/package-id --scope=database --pre-restore-evidence=/path/to/pre-restore/evidence.json --confirm=restore-staging-site --format=json
php alynt-backup-runner.php restore-apply --config=/path/to/config.json --staged-path=/path/to/restores/package-id --scope=files --pre-restore-evidence=/path/to/pre-restore/evidence.json --confirm=restore-staging-site --format=json
php alynt-backup-runner.php restore-apply --config=/path/to/config.json --staged-path=/path/to/restores/package-id --scope=files-and-database --create-pre-restore-backup=1 --confirm=restore-staging-site --format=json
```

`list` keeps the original plain-text path output by default. Use `--format=json` to print a local package inventory with package IDs, archive names, sidecar names, manifest/checksum/remote-index validity flags, checksum metadata, manifest metadata, and `verification_ready` flags. The JSON inventory is read-only and does not verify hashes or contact Drime.

`cleanup-preview` is also read-only. It reports outbox packages and restore staging directories older than the selected threshold, defaults to 14 days, and sets `destructive_actions_performed` to `false` in JSON output. It does not delete archives, sidecars, or restore staging folders.

`cleanup` deletes only old candidates discovered from the configured outbox and restore staging paths. It refuses to run unless the exact `--confirm=delete-local-artifacts` flag is present, deletes known package sidecars beside a selected archive, and reports `destructive_actions_performed` as `true` in JSON output.

Use server cron to call `run`, then call the plugin WP-CLI workflow to scan/upload the resulting package, for example:

```bash
php /path/to/alynt-backup-runner.php run --config=/path/to/config.json
wp --path=/var/www/example.com/htdocs alynt-drime-backups run --max-uploads=1
```

## GridPane Shape

For a GridPane site, keep the outbox outside the public web root when possible:

```text
/var/www/example.com/private/alynt-drime-backups/outbox
/var/www/example.com/private/alynt-drime-backups/work
/var/www/example.com/restores/alynt-drime-backups
```

The WordPress plugin setting `server_outbox_path` should point at the same outbox path.

The plugin settings screen generates guided single-line commands for runner install/update, first package verification, scan/upload, and cron review. The install command embeds the non-secret `config.json` content, writes it beside `alynt-backup-runner.php`, installs the runner, sets permissions, and runs health. Use the generated review commands to build and diff a proposed crontab file before manually approving the final `crontab` install command.

See [docs/SERVER_BACKUP_AUTOMATION.md](../docs/SERVER_BACKUP_AUTOMATION.md) for the broader automation model, including scheduling guidance, multiple standalone site layout, disk retention policy, and high-write-site boundaries. For several separate WordPress sites on one server, see [docs/MULTIPLE_STANDALONE_SITE_RUNNER_GUIDANCE.md](../docs/MULTIPLE_STANDALONE_SITE_RUNNER_GUIDANCE.md).

This runner currently supports `tar.gz` only. Additional archive formats should be added after live GridPane validation proves the first flow.

## Consistency Mode

Generated configs include:

```json
"consistency_mode": "light"
```

Light mode does not put WordPress into maintenance mode and does not pause writes. It records non-secret consistency evidence in the completed manifest sidecar:

- `consistency_mode`
- `consistency_status`
- database dump timing
- file archive start/finish timing
- archive exit code
- archive warning counts
- file-change warning counts
- up to five warning samples

`consistency_status` is `clean` when the archive completed without warnings, `file_changes_detected` when tar reported files changing while they were read, and `warnings_detected` when other archive warnings were captured. Existing configs without this key behave as `standard` and report `not_checked` for final consistency status.

The runner excludes symlink entries from new archives. This keeps restore staging focused on regular files and directories, and avoids extracting links that point outside the staged restore directory.

## Resource Safety

The runner health check verifies that `work_path`, `outbox_path`, and `restore_path` are writable and have at least `minimum_free_space_bytes` available. The default minimum is 1 GB when the setting is omitted.

The health check also verifies that `work_path` and `outbox_path` are on the same filesystem device, because completed archives are renamed from the work directory into the outbox. Keep both paths on the same mounted filesystem for atomic completion.

## Package Artifacts

A completed package writes:

```text
example-com-YYYYmmdd-HHMMSS.tar.gz
example-com-YYYYmmdd-HHMMSS.tar.gz.manifest.json
example-com-YYYYmmdd-HHMMSS.tar.gz.sha256
example-com-YYYYmmdd-HHMMSS.tar.gz.remote-index.json
example-com-YYYYmmdd-HHMMSS.tar.gz.remote-catalog.json
```

The plugin can detect the archive and read the manifest/checksum/remote-index/remote-catalog sidecars. The remote-index sidecar describes the single package. The remote-catalog sidecar is a folder catalog snapshot that describes the local outbox package set at package creation time. Both travel to Drime with the archive; the catalog snapshot is not a mutable singleton remote file.

The runner can also print a local inventory for the outbox:

```bash
php alynt-backup-runner.php list --config=/path/to/config.json --format=json
```

Use this before restore discovery or cleanup decisions to confirm which package sets have archive, manifest, checksum, remote-index, and remote-catalog files locally. `verification_ready` means the manifest has a package ID and the checksum sidecar has a parseable SHA-256 value; still run `verify` before restore staging.

The runner can preview old local artifacts before an operator-approved cleanup:

```bash
php alynt-backup-runner.php cleanup-preview --config=/path/to/config.json --older-than-days=14 --format=json
```

Use this after upload and restore rehearsal proof to see which local outbox packages and restore staging directories are old enough to review. The command does not remove anything.

After the preview has been reviewed and local retention policy allows cleanup, run the explicit cleanup command:

```bash
php alynt-backup-runner.php cleanup --config=/path/to/config.json --older-than-days=14 --confirm=delete-local-artifacts --format=json
```

The cleanup command deletes matching local outbox archives plus `.manifest.json`, `.sha256`, `.sha256sum`, `.remote-index.json`, and `.remote-catalog.json` sidecars when present. It also deletes matching restore staging directories. It does not contact Drime, delete remote files, or touch arbitrary paths outside the configured outbox and restore directories.

## Restore Staging

The first restore flow is intentionally non-destructive. `fetch` can download a known Drime package plus sidecars into a local download directory, and `stage-restore` verifies the package and sidecars, creates a new directory under `restore_path`, extracts the archive there, and writes `RESTORE_NOTES.txt` plus `RESTORE_REPORT.json`. The report includes deterministic SHA-256 integrity records for the extracted WordPress tree and database dump.

`restore-dry-run` reads a staged restore directory and reports whether a future gated restore command would have enough evidence to proceed. It checks the staging-only restore config flags, confirms `--staged-path` is inside `restore_path`, validates `RESTORE_REPORT.json`, verifies required staged `htdocs/` and/or `database.sql` content for the selected scope, checks the configured target WordPress path, checks pre-restore backup path readiness, validates pre-restore backup evidence, and confirms the target filesystem has the configured minimum free space.

`restore-production-preflight` is a separate production-simulation planning command. It reads target identity through fixed WP-CLI queries, checks the enrolled URL and site UUID, validates staged package identity and scope, calculates a conservative disk budget, checks operator-reviewed maintenance/write-control settings, and requires fresh GridPane native-backup evidence. It cannot import a database, replace files, or change maintenance state. By default it prints JSON only; add `--write-report=1` to write a redacted audit report under `production_reports_path`, which must remain outside the WordPress target.

`restore-production-create-pre-backup` runs the same full preflight and then writes fresh private database/file recovery artifacts plus immutable evidence with SHA-256 values. It also binds the current staged-input integrity record into private evidence. It requires `--confirm=create-production-pre-restore-backup`, stays outside `htdocs`, and does not restore anything. `restore-production-apply` is implemented for `files`, `database`, and `files-and-database` scopes, remains disabled unless both production apply and rollback are explicitly enabled in private config, and requires matching evidence within `production_pre_backup_max_age_seconds` (one hour by default), unchanged target fingerprint, unchanged staged package identity, exact action and target confirmations, maintenance control, and post-apply identity checks. After maintenance activation, apply recomputes every staged digest required by the selected scope immediately before target writes and refuses on any mismatch while leaving maintenance active for inspection. File apply snapshots every enrolled symlink and its exact configured target before replacement, recreates those links before verification, and re-establishes the core maintenance marker if file copying fails after destructive replacement starts. Combined apply completes file replacement and symlink restoration before importing the database under the same maintenance window; a database failure after file replacement remains rollback-ready. `restore-production-rollback` consumes the resulting private apply report and verified recovery evidence after its own exact confirmations. Its safety preflight continues to require enrolled paths, package integrity, private recovery evidence, configuration, disk headroom, and native backup evidence, while allowing current WP-CLI and filesystem inventory drift that can be caused by a failed apply. Before deleting target files, rollback extracts privately, validates every source symlink against exact enrollment, and refuses unknown or changed links. Successful rollback keeps maintenance active through complete plugin/theme/drop-in/symlink verification, writes its private mode-`0640` report, and only then removes the exact generated `.rollback-*` extraction tree. Any extraction, copy, database, verification, maintenance, or report-write failure retains that tree for operator recovery. Production apply reports are also written with private mode `0640`.

Production database reports distinguish a completed import from possible partial modification. `database_imported` is true only after WP-CLI reports success; `database_may_be_modified` becomes true as soon as an import attempt starts because a failed SQL import can still have changed data. A failed apply import retains maintenance and can use its apply report for rollback. A failed rollback import retains maintenance and can be retried against the original verified recovery evidence after the underlying import problem is resolved.

Post-write WP-CLI identity checks are fail-closed. If required home, site URL, UUID, plugin, theme, database, or core reads fail after apply or rollback, maintenance remains active and the private report records a verification failure. Apply reports expose `production_rollback_available: true` as soon as destructive work starts, including failed or partially verified applies whose reports and recovery evidence can drive rollback.

Post-write filesystem verification also requires the WordPress root owner and group IDs to match the private pre-restore fingerprint, alongside the exact enrolled drop-in and symlink inventories. An ownership or drop-in mismatch keeps maintenance active and writes a recovery report; failed apply verification can be rolled back, while failed rollback verification can be retried against the original verified recovery evidence.

Rollback maintenance control is fail-closed at every boundary. Total activation failure stops before rollback writes; reactivation failure after file replacement uses the emergency core marker to keep the site protected; and deactivation failure leaves maintenance active after successful verification. Each private failure report remains suitable for an operator-controlled retry after the underlying maintenance fault is resolved.

Version 1 keeps public HTTP health verification as a mandatory operator or agent check after maintenance is removed. The runner does not make a public HTTP response a destructive-action gate because CDN, WAF, DNS, cache, maintenance-response, and server-egress behavior can differ from actual WordPress recovery state. A future optional configured probe may supplement, but must not replace, WP-CLI identity and filesystem verification.

Production preflight requires the `staged_integrity` object generated by runner `0.4.4` or newer. Run `stage-restore` again with the current runner for any staged directory created by an older runner before creating new production pre-restore evidence.

The preflight reads only the plugin `site_uuid` field instead of loading the full WordPress settings option. Database names are emitted only as SHA-256 fingerprints, raw WP-CLI output is not included, and report data redacts secret-like keys, bearer values, and signed query parameters. A passing preflight is evidence that Phase 2 checks passed at that moment; it is not authorization or a command path for production restore.

By default, dry run prints output only. Add `--write-report=1` to write a successful dry-run evidence report under configured `restore_reports_path`. The command writes that report only after the dry run passes; failed dry runs do not create success evidence. It does not create a pre-restore backup, import a database, overwrite files, delete files, or run shell restore commands.

`restore-apply --scope=database` can import the staged `database.sql` into the configured staging target after the exact `--confirm=restore-staging-site` phrase is provided. `restore-apply --scope=files` can replace the configured staging target files from staged `htdocs/` after the same confirmation phrase. `restore-apply --scope=files-and-database` runs file replacement first and database import second. All apply commands first run the existing dry-run/evidence checks for their scope and refuse to apply if any check fails. Add `--create-pre-restore-backup=1` to create matching pre-restore backup evidence immediately before the dry-run/apply gates; if `--pre-restore-evidence` is also supplied, that output path must not already exist. File and combined apply also report pre-restore symlinked drop-ins that are absent from the staged files so operators can manually inspect or regenerate them after apply. A successful or attempted apply writes a `RESTORE_APPLY_REPORT-*.json` file under `restore_reports_path`.

Pre-restore backup evidence can come from the config key `restore_pre_backup_evidence_path` or from `--pre-restore-evidence=/path/to/evidence.json`. The evidence file must live under `restore_pre_backup_path`, be readable JSON, match the staged package ID, match the dry-run scope, match `restore_target_wordpress_path`, and point to readable backup artifacts under `restore_pre_backup_path`.

Evidence JSON shape:

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

The restore commands now print operator guidance after successful `fetch`, `verify`, `inspect`, and `stage-restore` runs. This guidance points to the next inspection step, names the staged path after extraction, and repeats that database imports and live file replacement remain separately approved manual work.

`fetch` reads the Drime bearer token from `ALYNT_DRIME_TOKEN` by default, or from the environment variable named by `--token-env`. It requires exact filename matches for the archive, manifest sidecar, and checksum sidecar, refuses local overwrites unless `--overwrite=1` is provided, and verifies the package immediately after download. Each downloaded file has a bounded timeout controlled by `drime_download_timeout_seconds`; the default is `21600` seconds (six hours), with accepted values clamped between five minutes and 24 hours.

Before extraction, `stage-restore` lists the archive and rejects unsafe member paths, including absolute paths, parent-directory traversal, empty path segments, and archive links. This keeps restore staging focused on runner-created packages and prevents a malformed archive from writing outside the new staging directory.

`stage-restore` does not import `database.sql`, does not overwrite the live WordPress path, and refuses to use an existing restore directory. `RESTORE_REPORT.json` records the package ID, site identity, archive and sidecar names, checksum metadata, manifest fields, and explicit booleans confirming that no database import or live file overwrite was performed. The final `stage-restore` output tells operators to review `RESTORE_NOTES.txt`, `RESTORE_REPORT.json`, `htdocs/`, and `database.sql` before any separately approved recovery work. `restore-dry-run` can be run after that inspection to produce a machine-readable staging preflight result and, when explicitly requested, a persistent dry-run evidence report. `restore-production-preflight` performs the stricter read-only production-simulation checks. Production apply remains disabled by default and limited to separately approved files-only, database-only, or combined simulations; `restore-apply --scope=database`, `restore-apply --scope=files`, and `restore-apply --scope=files-and-database` remain the staging-target command family.

See [docs/RESTORE_RUNBOOK.md](../docs/RESTORE_RUNBOOK.md) for the operator runbook, GridPane staging checks, and the currently gated manual disaster restore outline. See [docs/PACKAGE_SECURITY.md](../docs/PACKAGE_SECURITY.md) for the package integrity, extraction safety, storage-path, and encryption boundaries.

See [docs/RESTORE_REHEARSAL_CHECKLIST.md](../docs/RESTORE_REHEARSAL_CHECKLIST.md) for the onboarding and periodic restore proof checklist.

See [docs/CONSISTENCY_MODEL.md](../docs/CONSISTENCY_MODEL.md) for the logical backup timing model, manifest timing fields, and high-write site caveats.
