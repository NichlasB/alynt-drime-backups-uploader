# Package Security Model

This document describes the current security model for packages created by the Alynt server backup runner and uploaded by Alynt Drime Backups Uploader.

The goal is to make the first restore loop trustworthy enough to test and operate cautiously, without pretending the MVP already solves every backup-security problem.

## Scope

Covered now:

- Local package completion rules.
- Manifest and checksum sidecars.
- Restore-stage archive safety validation.
- Recommended private server paths.
- CLI fetch and restore-staging boundaries.
- Drime upload and retention boundaries.
- Encryption status for the MVP.

Not covered yet:

- Automated production restore.
- End-to-end package signing.
- Backup encryption and key recovery.
- Automated remote disaster inventory for a WordPress-unavailable site.

## Trust Boundaries

The server runner is the trusted package producer for GridPane-style server backups. It runs on the same server as the WordPress site and writes packages to a configured outbox.

The WordPress plugin is the trusted uploader. It scans local completed packages, queues stable files, uploads bytes to Drime, and records upload state.

Drime is the remote storage provider. The plugin can upload packages and move plugin-owned remote files to trash through manual retention controls, but the MVP does not treat Drime as a restore orchestration system.

The current model assumes the server filesystem, WordPress administrator account, Drime API token, and Drime account access are sensitive operational boundaries. A compromise of any of those boundaries can compromise backup confidentiality or availability.

## Package Completion

The server runner writes archives in a work directory first, then renames completed archives into the configured outbox. This avoids exposing a partially written archive as a completed package.

The generic outbox producer ignores temporary and partial files, then waits until a discovered archive is old enough and stable before queueing it.

The server runner excludes symlink entries from new archives. If a site relies on a symlinked WordPress drop-in or plugin-owned link, the restored staging package will not recreate that link automatically; the operator should inspect the source site and recreate any required link manually after approving a real restore.

A completed server-runner package has:

```text
example-com-YYYYmmdd-HHMMSS.tar.gz
example-com-YYYYmmdd-HHMMSS.tar.gz.manifest.json
example-com-YYYYmmdd-HHMMSS.tar.gz.sha256
```

The manifest identifies the package, producer, site, archive format, file root, database dump path, and runner version. It must not contain Drime API tokens, WordPress salts, database passwords, SSH keys, cookies, or other secrets.

See [CONSISTENCY_MODEL.md](CONSISTENCY_MODEL.md) for the logical backup timing fields and high-write site caveats.

## Integrity Model

The `.sha256` sidecar lets the runner verify that the local archive bytes match the package recorded at creation time.

This is an integrity check, not an authenticity guarantee. SHA-256 proves that the archive matches the sidecar, but it does not prove who created the archive if an attacker can alter both the archive and sidecar.

Before restore staging, the runner verifies:

- The archive is readable.
- The manifest sidecar exists and contains a package ID.
- The checksum sidecar exists and is parseable.
- The archive SHA-256 matches the checksum sidecar.

Future package signing can be added after the key model, rotation policy, and disaster recovery process are designed.

## Restore Extraction Safety

The current restore flow is intentionally non-destructive. `stage-restore` extracts a verified package into a new restore directory for inspection.

Before extraction, `stage-restore` lists the archive and rejects unsafe members:

- Absolute paths.
- Windows drive-letter absolute paths.
- Parent-directory traversal such as `../`.
- Empty or `.` path segments.
- Symlink entries.
- Hardlink entries.

If archive safety validation fails, the runner removes the newly created empty restore directory and exits before extraction.

This validation is a staging guard. It does not make an unknown third-party package trusted. Operators should stage and inspect packages before any manual database import or file replacement.

## Recommended Server Paths

For GridPane sites, keep package work and outbox paths outside the public web root:

```text
/var/www/example.com/private/alynt-drime-backups/outbox
/var/www/example.com/private/alynt-drime-backups/work
/var/www/example.com/restores/alynt-drime-backups
```

The restore path should also be outside the public web root. If the host exposes `/restores` publicly in a specific environment, choose a private restore path instead and update the runner config.

The runner health check verifies writable paths, minimum free disk space, and same-filesystem work/outbox placement for atomic completion.

## Drime Upload Boundary

The plugin uploads package bytes to the configured Drime workspace and destination folder. For generic server-runner packages, it also uploads the manifest and checksum sidecars to the same Drime folder. It does not currently upload a separate signed inventory or restore index.

Workspace ID `0`, the personal/default Drime workspace, is blocked for backup destinations. A blank workspace is allowed only as an initial "not configured yet" setup state and cannot be used for folder browsing, destination preview, or uploads. Operators can also define `ALYNT_DRIME_ALLOWED_WORKSPACE_IDS` in `wp-config.php` to restrict the site to one or more approved workspace IDs. The workspace picker, settings save, folder browser, destination preview, and upload worker enforce the same workspace rules so bypassing the dropdown does not allow uploads into a disallowed workspace.

See [REMOTE_RESTORE_DISCOVERY.md](REMOTE_RESTORE_DISCOVERY.md) for the current manual discovery path and future remote index option.

The server runner's CLI `fetch` command reads the Drime bearer token from an environment variable, downloads exact package/sidecar matches, and verifies the package before restore staging. If Drime returns a redirected download URL, the runner validates that redirect target as HTTPS and repeats the download without forwarding the bearer token.

Remote retention is disabled by default and only runs from manual administrator actions. It moves eligible plugin-owned remote files to Drime trash and does not permanently delete files.

Diagnostics must not expose Drime tokens, signed upload URLs, raw request bodies, package contents, or absolute server paths in remote/dashboard-safe payloads.

See [CENTRAL_DASHBOARD_READINESS.md](CENTRAL_DASHBOARD_READINESS.md) for the future central monitoring boundary. The uploader should stay read-only for a first dashboard integration and must not expose restore, deletion, credential, or settings mutation controls as part of dashboard preparation.

## Encryption Status

The MVP does not encrypt packages before upload.

This is intentional for the first restore-tested loop. Backup encryption should not be added until there is a tested key storage, key rotation, and key recovery process. An encrypted backup without recoverable keys is not a usable backup.

Until encryption is designed, treat Drime account access and package downloads as sensitive. Store packages only in the intended Drime workspace/folder, restrict API token access, use `ALYNT_DRIME_ALLOWED_WORKSPACE_IDS` when a site should be locked to a specific workspace, and avoid sharing package links outside the restore operators.

## Release Gates

Before production rollout of the server-runner producer:

- Create a package on a non-critical GridPane site.
- Upload the package to Drime.
- Confirm the local uploaded registry and diagnostics are redacted.
- Download or otherwise obtain the package and sidecars locally.
- Verify the checksum.
- Stage the restore into a non-public restore directory.
- Inspect `htdocs/`, `database.sql`, `manifest.json`, and `RESTORE_NOTES.txt`.

No automated production restore command should ship until destructive restore has its own dry-run output, confirmation gates, pre-restore snapshot requirement, and staging evidence.
