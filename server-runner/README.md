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

The plugin settings screen can generate a non-secret `config.json` snippet, conservative install commands, a health command, a cron snippet, and server-cron review commands for the current site. Review the generated paths, run the install commands as the site user, save the config beside `alynt-backup-runner.php`, then run the generated health command before adding cron. Use the generated review commands to build and diff a proposed crontab file before manually approving the final `crontab` install command.

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

The first restore flow is intentionally non-destructive. `fetch` can download a known Drime package plus sidecars into a local download directory, and `stage-restore` verifies the package and sidecars, creates a new directory under `restore_path`, extracts the archive there, and writes `RESTORE_NOTES.txt` plus `RESTORE_REPORT.json`.

`fetch` reads the Drime bearer token from `ALYNT_DRIME_TOKEN` by default, or from the environment variable named by `--token-env`. It requires exact filename matches for the archive, manifest sidecar, and checksum sidecar, refuses local overwrites unless `--overwrite=1` is provided, and verifies the package immediately after download.

Before extraction, `stage-restore` lists the archive and rejects unsafe member paths, including absolute paths, parent-directory traversal, empty path segments, and archive links. This keeps restore staging focused on runner-created packages and prevents a malformed archive from writing outside the new staging directory.

It does not import `database.sql`, does not overwrite the live WordPress path, and refuses to use an existing restore directory. `RESTORE_REPORT.json` records the package ID, archive and sidecar names, checksum metadata, manifest fields, and explicit booleans confirming that no database import or live file overwrite was performed.

See [docs/RESTORE_RUNBOOK.md](../docs/RESTORE_RUNBOOK.md) for the operator runbook, GridPane staging checks, and the currently gated manual disaster restore outline. See [docs/PACKAGE_SECURITY.md](../docs/PACKAGE_SECURITY.md) for the package integrity, extraction safety, storage-path, and encryption boundaries.

See [docs/RESTORE_REHEARSAL_CHECKLIST.md](../docs/RESTORE_REHEARSAL_CHECKLIST.md) for the onboarding and periodic restore proof checklist.

See [docs/CONSISTENCY_MODEL.md](../docs/CONSISTENCY_MODEL.md) for the logical backup timing model, manifest timing fields, and high-write site caveats.
