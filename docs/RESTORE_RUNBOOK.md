# Restore Runbook

This runbook covers the first restore workflow for Alynt Drime Backups Uploader server-runner packages.

The current restore support is intentionally non-destructive. The runner can fetch a known package from Drime, verify it, and stage it for inspection, but it does not import the database or overwrite live WordPress files.

## Scope

Supported now:

- Verify a local `.tar.gz` package and its sidecars.
- Fetch a known Drime package and its sidecars into a local download directory.
- Inspect package metadata and archive contents.
- Extract a verified package into a separate restore staging directory.
- Review restored files and `database.sql` without touching production.

Not supported yet:

- Importing `database.sql` into WordPress.
- Replacing the live `htdocs` directory.
- Running an automated production restore command.

Any production restore must remain a manual, explicitly approved operation until a separate destructive restore workflow is designed and tested.

## Package Layout

A complete server-runner package has three local artifacts:

```text
example-com-YYYYmmdd-HHMMSS.tar.gz
example-com-YYYYmmdd-HHMMSS.tar.gz.manifest.json
example-com-YYYYmmdd-HHMMSS.tar.gz.sha256
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

For server-runner packages uploaded through the generic outbox producer, the plugin uploads the manifest and checksum sidecars to the same Drime folder as the archive. If `fetch` reports a missing sidecar, stop and repair the remote package set before treating the backup as restorable.

Run verification before inspecting or extracting:

```bash
php /path/to/alynt-backup-runner.php verify \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --package=/var/www/example.com/private/alynt-drime-backups/outbox/example-com-YYYYmmdd-HHMMSS.tar.gz
```

Expected result:

```text
Package verified: /path/to/package.tar.gz
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
```

It refuses to overwrite an existing restore directory with the same package ID.

Before extracting, the runner validates archive member names and refuses packages with absolute paths, parent-directory traversal, empty path segments, or symlink/hardlink entries. If this safety validation fails, stop and treat the package as unsuitable for restore staging until its source is understood.

After staging, check:

```bash
du -sh /var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS
ls -la /var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS
cat /var/www/example.com/restores/alynt-drime-backups/example-com-YYYYmmdd-HHMMSS/RESTORE_NOTES.txt
```

`RESTORE_NOTES.txt` should state that no database import was performed and no live WordPress files were overwritten.

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

The plugin and runner should not perform steps 6 or 7 automatically until a destructive restore design has its own confirmation gates, dry-run output, and staging evidence.

For the package integrity, extraction safety, storage-path, and encryption boundaries behind this runbook, see [PACKAGE_SECURITY.md](PACKAGE_SECURITY.md).

## Drime Download

If the only copy is in Drime, prefer the CLI `fetch` command above when the package ID, workspace ID, destination folder hash, and token are available. Otherwise, download the package and both sidecars manually to the server first, then run the local verification workflow.

See [REMOTE_RESTORE_DISCOVERY.md](REMOTE_RESTORE_DISCOVERY.md) for the manual disaster discovery path, CLI fetch behavior, and remote index option.
