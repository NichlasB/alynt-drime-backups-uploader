# Remote Restore Discovery

This document defines how operators should think about finding restorable packages when the local WordPress site, plugin options, or uploaded registry are unavailable.

The current MVP can create, upload, fetch, verify, inspect, and stage packages. It does not maintain a remote restore index and does not perform destructive restores.

## Current MVP Path

If the original WordPress site is still available:

1. Use the plugin uploaded registry and diagnostics to identify the uploaded package.
2. Confirm the Drime workspace and destination folder from plugin settings.
3. Use the server-runner `fetch` command, or download the archive and both sidecars from Drime manually.
4. Place all three files on the server if using manual download.
5. Run the server-runner `verify`, `inspect`, and `stage-restore` commands.

If the original WordPress site is unavailable:

1. Use the Drime web UI to browse the known backup workspace and folder.
2. Locate the archive and matching `.manifest.json` and `.sha256` sidecars by filename.
3. Download all three files to a safe server path manually, or use `fetch` if the package ID, workspace, folder hash, and token are available.
4. Use the manifest sidecar to confirm site ID, site URL, package ID, producer, backup type, timing fields, file root, and database dump path.
5. Run local verification and staging restore from the server runner.

This manual path is acceptable for MVP testing, but it is not enough for a fast disaster workflow across many sites.

## Package Naming

Server-runner packages should keep a predictable basename:

```text
example-com-YYYYmmdd-HHMMSS.tar.gz
example-com-YYYYmmdd-HHMMSS.tar.gz.manifest.json
example-com-YYYYmmdd-HHMMSS.tar.gz.sha256
```

The archive, manifest sidecar, and checksum sidecar must travel together. A package without sidecars should not be staged unless the operator has a separate, approved recovery procedure.

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

## Remote Index Option

A future remote index can make discovery easier, especially for a central dashboard or a server where the original WordPress database is gone.

Possible shape:

```json
{
  "schema_version": 1,
  "site_id": "example.com",
  "site_url": "https://example.com",
  "generated_at": "2026-06-26T00:00:00+00:00",
  "packages": [
    {
      "package_id": "example-com-20260626-020000",
      "archive_name": "example-com-20260626-020000.tar.gz",
      "manifest_name": "example-com-20260626-020000.tar.gz.manifest.json",
      "checksum_name": "example-com-20260626-020000.tar.gz.sha256",
      "created_at": "2026-06-26T02:00:00+00:00",
      "backup_type": "logical_wordpress_backup",
      "size": 1234567890,
      "checksum_algorithm": "sha256"
    }
  ]
}
```

Remote index rules:

- Store only non-secret metadata.
- Treat the index as a convenience, not the source of truth.
- Prefer package sidecars for final verification.
- Write the index atomically if it is generated locally before upload.
- Keep old index versions readable if the schema evolves.
- Do not allow dashboard or remote index data to trigger production restore without local verification and human approval.

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
- Confirm the package can be discovered without relying on the original WordPress uploaded registry.
- Decide whether package-level sidecar discovery is sufficient for the first production rollout or whether a remote index must be implemented first.
