# Remote Restore Discovery

This document defines how operators should think about finding restorable packages when the local WordPress site, plugin options, or uploaded registry are unavailable.

The current MVP can create, upload, list local package inventory, upload package-level remote-index sidecars, upload folder catalog snapshot sidecars, fetch, verify, inspect, print restore guidance, and stage packages. It does not maintain a mutable singleton remote restore catalog and does not perform destructive restores.

## Current MVP Path

If the original WordPress site is still available:

1. Use the plugin uploaded registry and diagnostics to identify the uploaded package.
2. Confirm the Drime workspace and destination folder from plugin settings.
3. Use `php alynt-backup-runner.php list --format=json` against the site runner config to review local package IDs, archive names, sidecar names, checksum metadata, remote-index presence, and `verification_ready` flags.
4. Use the server-runner `fetch` command, or download the archive and required sidecars from Drime manually.
5. Place the archive, `.manifest.json`, and `.sha256` files on the server if using manual download. Keep the `.remote-index.json` and `.remote-catalog.json` sidecars nearby when available for discovery notes.
6. Run the server-runner `verify`, `inspect`, and `stage-restore` commands.

If the original WordPress site is unavailable:

1. Use the Drime web UI to browse the known backup workspace and folder.
2. Locate the archive and matching `.manifest.json`, `.sha256`, optional `.remote-index.json`, and optional `.remote-catalog.json` sidecars by filename.
3. Download the archive plus required manifest/checksum sidecars to a safe server path manually, or use `fetch` if the package ID, workspace, folder hash, and token are available.
4. Use the manifest sidecar to confirm site ID, site URL, package ID, producer, backup type, timing fields, file root, and database dump path.
5. Run local verification and staging restore from the server runner.

This manual path is acceptable for MVP testing, but it is not enough for a fast disaster workflow across many sites.

## Local Inventory Command

The server runner `list` command can print a package inventory from the configured outbox:

```bash
php /path/to/alynt-backup-runner.php list \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --format=json
```

The JSON output includes:

- Schema version and generation time.
- Package count.
- Archive filename and size.
- Package ID from the manifest when available.
- Manifest sidecar name, presence, and validity.
- Checksum sidecar name, presence, validity, algorithm, and value.
- Remote-index sidecar name, presence, and validity.
- Non-secret manifest fields such as site URL, created time, producer, backup type, archive format, file root, and database dump name.
- `verification_ready` when both required sidecars are present.

The inventory is local and read-only. It does not hash package bytes, contact Drime, delete files, or approve restore work. `verification_ready` means the manifest has a package ID and the checksum sidecar has a parseable SHA-256 value; use `verify` before trusting a package for restore staging.

## Package Naming

Server-runner packages should keep a predictable basename:

```text
example-com-YYYYmmdd-HHMMSS.tar.gz
example-com-YYYYmmdd-HHMMSS.tar.gz.manifest.json
example-com-YYYYmmdd-HHMMSS.tar.gz.sha256
example-com-YYYYmmdd-HHMMSS.tar.gz.remote-index.json
example-com-YYYYmmdd-HHMMSS.tar.gz.remote-catalog.json
```

The archive, manifest sidecar, and checksum sidecar must travel together. The remote-index and remote-catalog sidecars should travel with them when available because they make Drime-side discovery easier, but verification still depends on the manifest and checksum sidecars. A package without required sidecars should not be staged unless the operator has a separate, approved recovery procedure.

## Self-Describing Packages

Each package must remain useful even if the WordPress database is lost.

The manifest sidecar should identify:

- Site ID.
- Site URL.
- Package ID.
- Producer key and version.
- Backup type.
- Created/timing fields.
- Archive format.
- File root.
- Database dump name.
- Exclusion list.

The manifest must not store Drime API tokens, database passwords, WordPress salts, SSH keys, cookies, or recovery secrets.

## CLI Fetch Command

The server-runner includes a first CLI-only `fetch` command for downloading a known package and its sidecars from Drime:

```bash
export ALYNT_DRIME_TOKEN='drime-bearer-token'

php /path/to/alynt-backup-runner.php fetch \
  --config=/var/www/example.com/private/alynt-drime-backups/config.json \
  --package-id=example-com-YYYYmmdd-HHMMSS \
  --workspace-id=0 \
  --folder-hash=DRIME_FOLDER_HASH \
  --download-path=/var/www/example.com/private/alynt-drime-backups/downloads
```

Fetch behavior:

- Requires `--package-id`, `--folder-hash`, and `--download-path`.
- Reads the Drime bearer token from an environment variable only. The default is `ALYNT_DRIME_TOKEN`; override with `--token-env=NAME` if needed.
- Uses Drime file listing to find exact filename matches for the archive, `.manifest.json`, and `.sha256` sidecar before downloading bytes.
- Refuses to overwrite existing local files unless `--overwrite=1` is supplied.
- Downloads to temporary files, then promotes completed files into the download path.
- Verifies the fetched package checksum and manifest sidecar immediately after download.
- Prints filenames and verification status only; it must not print tokens, signed URLs, cookies, request bodies, or raw API payloads.
- Exits non-zero if any required package part is missing or fails verification.

The first `fetch` implementation is intentionally not exposed as a wp-admin remote restore action.

## Package-Level Remote Index

Server-runner packages now write a package-level remote-index sidecar beside the archive:

```text
example-com-YYYYmmdd-HHMMSS.tar.gz.remote-index.json
```

The generic outbox uploader sends this sidecar to the same Drime folder as the archive, manifest, checksum, and catalog snapshot. This gives each package set a small, non-secret discovery file even when the original WordPress uploaded registry is unavailable.

Current shape:

```json
{
  "schema_version": 1,
  "index_type": "single_package_restore_index",
  "generated_at": "2026-06-26T00:00:00+00:00",
  "archive_format": "tar.gz",
  "package_count": 1,
  "packages": [
    {
      "package_id": "example-com-20260626-020000",
      "archive_name": "example-com-20260626-020000.tar.gz",
      "manifest_name": "example-com-20260626-020000.tar.gz.manifest.json",
      "checksum_name": "example-com-20260626-020000.tar.gz.sha256",
      "remote_index_name": "example-com-20260626-020000.tar.gz.remote-index.json",
      "created_at": "2026-06-26T02:00:00+00:00",
      "backup_type": "logical_wordpress_backup",
      "archive_size": 1234567890,
      "checksum_algorithm": "sha256"
    }
  ],
  "restore_policy": {
    "requires_archive_manifest_checksum": true,
    "destructive_restore_automated": false,
    "manual_restore_required": true
  }
}
```

## Folder Catalog Snapshot

Server-runner packages also write a folder catalog snapshot beside the archive:

```text
example-com-YYYYmmdd-HHMMSS.tar.gz.remote-catalog.json
```

The catalog snapshot lists the local outbox packages known when the runner created the package. It is intentionally named after the package, uploaded as a sidecar, and not treated as a mutable singleton remote file. In a disaster, use the latest catalog snapshot you can identify in Drime, then verify the specific package before staging.

Current shape:

```json
{
  "schema_version": 1,
  "catalog_type": "folder_package_catalog_snapshot",
  "generated_at": "2026-06-26T00:00:00+00:00",
  "archive_format": "tar.gz",
  "package_count": 2,
  "packages": [
    {
      "package_id": "example-com-20260625-020000",
      "archive_name": "example-com-20260625-020000.tar.gz",
      "manifest_name": "example-com-20260625-020000.tar.gz.manifest.json",
      "checksum_name": "example-com-20260625-020000.tar.gz.sha256",
      "remote_index_name": "example-com-20260625-020000.tar.gz.remote-index.json",
      "created_at": "2026-06-25T02:00:00+00:00",
      "backup_type": "logical_wordpress_backup",
      "archive_size": 1234567890,
      "checksum_algorithm": "sha256"
    }
  ],
  "restore_policy": {
    "requires_archive_manifest_checksum": true,
    "destructive_restore_automated": false,
    "manual_restore_required": true
  }
}
```

Remote discovery rules:

- Store only non-secret metadata.
- Treat the index and catalog snapshot as conveniences, not the source of truth.
- Prefer package sidecars for final verification.
- Write discovery sidecars atomically if they are generated locally before upload.
- Keep old index versions readable if the schema evolves.
- Do not allow dashboard, remote index, or catalog data to trigger production restore without local verification and human approval.

The plugin does not maintain a mutable singleton catalog file in Drime. If operators need one canonical file that is overwritten in place, that remains a future dashboard/automation feature and should wait for verified Drime replacement semantics.

## Dashboard Relationship

The future central dashboard can show restore discovery status, but it should not perform destructive restore actions in the first dashboard version.

Safe dashboard fields:

- Site ID or display label.
- Last uploaded package time.
- Last package size.
- Package count.
- Missing sidecar warnings.
- Last verification status.

Unsafe dashboard fields for the first version:

- Drime API tokens.
- Signed download URLs.
- Local server absolute paths unless path mode is explicitly authorized.
- Database names, table names, salts, cookies, or package contents.
- Remote delete, restore, or credential mutation controls.

## Release Gate

Before claiming disaster restore readiness:

- Prove manual Drime download of archive plus sidecars.
- Prove CLI fetch of archive plus sidecars from Drime.
- Verify the downloaded package locally.
- Stage the downloaded package into a restore directory.
- Complete the restore rehearsal report in `RESTORE_REHEARSAL_CHECKLIST.md`.
- Confirm the package can be discovered without relying on the original WordPress uploaded registry.
- Confirm the `.remote-index.json` and `.remote-catalog.json` sidecars are present for new server-runner package sets, or document why an older package predates those sidecars.
