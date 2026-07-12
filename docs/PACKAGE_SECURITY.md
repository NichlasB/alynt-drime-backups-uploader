# Package Security Model

This document describes the current security model for packages created by the Alynt server backup runner and uploaded by Alynt Drime Backups Uploader.

The goal is to make the first restore loop trustworthy enough to test and operate cautiously, without pretending the MVP already solves every backup-security problem.

## Scope

Covered now:

- Local package completion rules.
- Manifest, checksum, remote-index, and remote-catalog sidecars.
- Restore-stage archive safety validation.
- Recommended private server paths.
- CLI fetch and restore-staging boundaries.
- Restore dry-run boundaries.
- Drime upload and retention boundaries.
- Encryption status for the MVP.

Not covered yet:

- Automated production restore.
- End-to-end package signing.
- Backup encryption and key recovery.
- Mutable singleton remote disaster catalog for a WordPress-unavailable site.

## Trust Boundaries

The server runner is the trusted package producer for GridPane-style server backups. It runs on the same server as the WordPress site and writes packages to a configured outbox.

The WordPress plugin is the trusted uploader. It scans local completed packages, queues stable files, uploads bytes to Drime, and records upload state.

Drime is the remote storage provider. The plugin can upload packages and move plugin-owned remote files to trash through manual retention controls, but the MVP does not treat Drime as a restore orchestration system.

The current model assumes the server filesystem, WordPress administrator account, Drime API token, and Drime account access are sensitive operational boundaries. A compromise of any of those boundaries can compromise backup confidentiality or availability.

## Package Completion

The server runner writes archives in a work directory first, then renames completed archives into the configured outbox. This avoids exposing a partially written archive as a completed package.

The generic outbox producer ignores temporary and partial files, then waits until a discovered archive is old enough and stable before queueing it.

The server runner excludes symlink entries from new archives. If a site relies on a symlinked WordPress drop-in or plugin-owned link, the restored staging package will not recreate that link automatically; the operator should inspect the source site and recreate any required link manually after approving a real restore. Restore apply reports known missing or broken drop-ins such as `wp-content/db.php` in `post_restore_manual_review_items` so operators have an explicit cleanup/regeneration checklist.

A completed server-runner package has:

```text
example-com-YYYYmmdd-HHMMSS.tar.gz
example-com-YYYYmmdd-HHMMSS.tar.gz.manifest.json
example-com-YYYYmmdd-HHMMSS.tar.gz.sha256
example-com-YYYYmmdd-HHMMSS.tar.gz.remote-index.json
example-com-YYYYmmdd-HHMMSS.tar.gz.remote-catalog.json
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

The current restore flow is intentionally non-destructive. `stage-restore` extracts a verified package into a new restore directory for inspection. Successful restore commands print operator guidance, but that guidance is limited to review steps and does not approve database imports or live file replacement.

Before extraction, `stage-restore` lists the archive and rejects unsafe members:

- Absolute paths.
- Windows drive-letter absolute paths.
- Parent-directory traversal such as `../`.
- Empty or `.` path segments.
- Symlink entries.
- Hardlink entries.

If archive safety validation fails, the runner removes the newly created empty restore directory and exits before extraction.

This validation is a staging guard. It does not make an unknown third-party package trusted. Operators should stage and inspect packages before any manual database import or file replacement.

## Restore Dry-Run Boundary

`restore-dry-run` is a preflight for the separate destructive restore project. It reads the runner config, a staged restore directory, and `RESTORE_REPORT.json`, then reports whether staging-only gates and evidence are present.

The command checks that restore apply is explicitly enabled in config, the target environment is `staging`, the staged path is under `restore_path`, the staged report still says no database import or live file overwrite happened, the requested scope has the required staged files, the configured target WordPress path is not a broad system path, the pre-restore backup path is available, pre-restore backup evidence matches the package/scope/target, required pre-restore artifacts are readable under `restore_pre_backup_path`, and minimum free space is present.

By default, the command writes nothing. With `--write-report=1`, it writes only a successful dry-run evidence report under the configured `restore_reports_path`; failed dry runs do not create success evidence. The command validates pre-restore backup evidence, but it does not create pre-restore backups, import databases, replace files, delete local files, contact Drime, or run shell restore commands.

## Restore Apply Boundary

`restore-apply` is destructive-capable and intentionally scope-limited. It is staging-only, requires `--confirm=restore-staging-site`, reruns the dry-run/evidence checks, and refuses to apply if any preflight check fails.

`restore-apply --scope=database` imports only the staged `database.sql` through WP-CLI. `restore-apply --scope=files` replaces only the configured staging WordPress path from staged `htdocs/`. `restore-apply --scope=files-and-database` replaces staged files first, then imports the staged database. All apply scopes write a `RESTORE_APPLY_REPORT-*.json` file under `restore_reports_path`.

By default, these commands require existing pre-restore evidence. When `--create-pre-restore-backup=1` is explicitly supplied, the runner creates the required staging pre-restore database export and/or file backup evidence before the dry-run/apply gates. If that evidence creation fails, apply stops before importing the database or replacing files. These commands do not contact Drime or support production restore. Because runner archives exclude symlink entries, file and combined apply inspect the pre-restore file backup for symlinked drop-ins that are absent from the staged files and report them in `file_restore_missing_symlink_count`, `file_restore_missing_symlink_samples`, and `file_restore_manual_review_required`. They also add known drop-ins such as Query Monitor's `wp-content/db.php` to `post_restore_manual_review_items` when the path is missing or the restored symlink is broken. Operators should inspect post-restore drop-ins carefully; symlinked drop-ins may need host/plugin regeneration after a file restore. The runner does not automatically remove or recreate these files.

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

The plugin uploads package bytes to the configured Drime workspace and destination folder. For generic server-runner packages, it creates or reuses a Drime child folder named after the package ID under the effective server destination path, then uploads the archive, manifest, checksum, package-level remote-index, and folder catalog snapshot sidecars into that package folder. The remote-index and remote-catalog sidecars contain non-secret restore discovery metadata and are convenience files, not authenticity guarantees or approval to restore. The plugin does not currently maintain a separate signed inventory or mutable singleton folder-level restore catalog.

Workspace ID `0`, the personal/default Drime workspace, is blocked for backup destinations. A blank workspace is allowed only as an initial "not configured yet" setup state and cannot be used for folder browsing, destination preview, or uploads. Operators can also define `ALYNT_DRIME_ALLOWED_WORKSPACE_IDS` in `wp-config.php` to restrict the site to one or more approved workspace IDs. The workspace picker, settings save, folder browser, destination preview, and upload worker enforce the same workspace rules so bypassing the dropdown does not allow uploads into a disallowed workspace.

See [REMOTE_RESTORE_DISCOVERY.md](REMOTE_RESTORE_DISCOVERY.md) for the current manual discovery path, package-level remote-index sidecar, and folder catalog snapshot sidecar.

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

No automated production restore command should ship. The current destructive-capable path is limited to staging database import, staging file replacement, or combined staging file/database apply after explicit confirmation, dry-run checks, and pre-restore evidence validation. Staging pre-restore backup evidence can be created by `restore-apply` only when explicitly requested. Production restore still needs separate gated implementation and proof. See [DESTRUCTIVE_RESTORE_AUTOMATION_PLAN.md](DESTRUCTIVE_RESTORE_AUTOMATION_PLAN.md) for the separate gated project plan.
