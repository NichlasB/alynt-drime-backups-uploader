# Restore Rehearsal Checklist

Use this checklist after a server-runner package has uploaded to Drime and before relying on that backup flow for a site.

This is an inspection workflow only. It does not approve database imports, file replacement, or automated production restore.

## Rehearsal Inputs

Record these values before starting:

```text
Site:
Server:
WordPress path:
Package ID:
Drime workspace:
Drime folder:
Package source: local outbox / Drime fetch / manual Drime download
Runner config path:
Download path:
Restore staging path:
Operator:
Date:
```

## Preflight

- Confirm the exact site and server.
- Confirm the package ID and filename.
- Confirm the archive, `.manifest.json`, and `.sha256` sidecar are available together. For current server-runner packages, also confirm the `.remote-index.json` and `.remote-catalog.json` sidecars are present or document why the package predates them.
- Confirm the restore staging path is outside the public web root.
- Confirm there is enough disk space for the downloaded archive and extracted copy.
- Confirm this rehearsal is inspection-only.

## Fetch Or Place Package

If fetching from Drime with the runner:

```bash
export ALYNT_DRIME_TOKEN='drime-bearer-token'

php /path/to/alynt-backup-runner.php fetch \
  --config=/var/www/example.com/private/alynt-drime-backups/runner/config.json \
  --package-id=example-com-YYYYmmdd-HHMMSS \
  --workspace-id=0 \
  --folder-hash=DRIME_FOLDER_HASH \
  --download-path=/var/www/example.com/private/alynt-drime-backups/downloads
```

If downloading manually from Drime, download the package set into a private server path:

```text
example-com-YYYYmmdd-HHMMSS.tar.gz
example-com-YYYYmmdd-HHMMSS.tar.gz.manifest.json
example-com-YYYYmmdd-HHMMSS.tar.gz.sha256
example-com-YYYYmmdd-HHMMSS.tar.gz.remote-index.json
example-com-YYYYmmdd-HHMMSS.tar.gz.remote-catalog.json
```

Stop if the required manifest or checksum sidecar is missing.

## Verify

```bash
php /path/to/alynt-backup-runner.php verify \
  --config=/var/www/example.com/private/alynt-drime-backups/runner/config.json \
  --package=/var/www/example.com/private/alynt-drime-backups/downloads/example-com-YYYYmmdd-HHMMSS.tar.gz
```

Expected result:

```text
Package verified: /path/to/package.tar.gz
```

Stop if verification fails.

## Inspect

```bash
php /path/to/alynt-backup-runner.php inspect \
  --config=/var/www/example.com/private/alynt-drime-backups/runner/config.json \
  --package=/var/www/example.com/private/alynt-drime-backups/downloads/example-com-YYYYmmdd-HHMMSS.tar.gz
```

Confirm:

- Package ID matches the selected backup.
- Site URL matches the intended site.
- Archive format is `tar.gz`.
- File root is `htdocs`.
- Database dump is `database.sql` when database export was enabled.
- Timing fields make sense for the site activity window.

## Stage

```bash
php /path/to/alynt-backup-runner.php stage-restore \
  --config=/var/www/example.com/private/alynt-drime-backups/runner/config.json \
  --package=/var/www/example.com/private/alynt-drime-backups/downloads/example-com-YYYYmmdd-HHMMSS.tar.gz
```

Confirm:

- A new restore directory was created.
- `RESTORE_NOTES.txt` exists.
- `RESTORE_NOTES.txt` says no database import was performed.
- `RESTORE_NOTES.txt` says no live WordPress files were overwritten.
- `RESTORE_REPORT.json` exists.
- `RESTORE_REPORT.json` has `package_verified: true`.
- `RESTORE_REPORT.json` has `database_imported: false` and `live_files_overwritten: false`.
- `htdocs/`, `database.sql`, and `manifest.json` are present when expected.

## Report Template

Copy this block into the site rollout notes or operational tracker:

```text
Restore rehearsal result:
Site:
Package ID:
Package created at:
Drime workspace/folder:
Fetch method: runner fetch / manual download / local outbox
Verification: passed / failed
Inspection: passed / failed
Stage restore: passed / failed
Restore staging path:
RESTORE_NOTES reviewed: yes / no
RESTORE_REPORT reviewed: yes / no
No database import performed: yes / no
No live files overwritten: yes / no
Disk cleanup performed: yes / no / not applicable
Open issues:
Operator:
Date:
```

## Cleanup Decision

After the rehearsal:

- Keep the Drime package and sidecars unless retention policy says otherwise.
- Remove local fetched downloads when they are no longer needed.
- Remove restore staging directories when inspection is complete.
- Remove local outbox packages only when the site retention policy allows it and Drime restore proof has passed.

Do not clean up the only known-good local copy unless the Drime copy and sidecars have been verified.
