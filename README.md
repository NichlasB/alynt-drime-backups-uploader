# Alynt Drime Backups Uploader

Companion WordPress plugin that scans completed local backup packages and uploads them to Drime. It currently supports WPvivid backup archives and a generic server outbox for packages produced by a server-side runner or another backup producer.

## Features

- Scans configured backup producers through an adapter layer.
- Detects the local WPvivid backup folder, including verified Free/Pro path options.
- Scans a configured generic server outbox for completed archive packages with optional manifest and checksum sidecars.
- Scans only stable backup files so in-progress archives are not queued.
- Handles WPvivid-listed split archives such as `.part001.zip` and `.part002.zip` as complete sets.
- Queues uploads, tracks attempts, enforces retry limits, and prevents duplicate queue entries.
- Uploads small files through Drime direct upload and larger files through resumable multipart upload.
- Shows failed uploads with per-file retry actions when the local file is still readable.
- Lets administrators load Drime workspaces, browse existing Drime folders, and preview the resolved upload destination before backups run.
- Caches resolved Drime parent folder IDs so remote duplicate checks work after relative-path uploads.
- Supports duplicate handling by skipping existing remote files or asking Drime for an available filename.
- Provides manual admin actions for connection testing, scanning, upload, diagnostics export, diagnostics clearing, and active-upload recovery.
- Provides manual remote-retention preview and cleanup for plugin-owned Drime uploads, moving eligible remote files to Drime trash only.
- Sends optional plain-text failed upload notifications through WordPress mail with duplicate suppression.
- Tracks scheduled-scan cron health so administrators can see whether scans have run from WP-CLI or only from HTTP WP-Cron.
- Provides WP-CLI commands for server-driven scan/upload/status workflows.
- Includes a first-pass PHP CLI server runner that can create `.tar.gz` site packages for the generic outbox.
- Stores bounded, redacted diagnostics when diagnostics are explicitly enabled.
- Keeps local backups after upload by default; deletion requires explicit opt-in.

## Requirements

- WordPress 6.0 or later.
- PHP 7.4 or later.
- At least one local backup producer: WPvivid local backups, the included server runner, or another process that writes completed packages to the generic outbox.
- A Drime API token.

## Installation

1. Upload the plugin folder to `wp-content/plugins/alynt-drime-backups-uploader`.
2. Activate **Alynt Drime Backups Uploader** from the WordPress Plugins screen.
3. Open **Tools > Drime Backups**.
4. Enter a Drime API token and destination settings.
5. Configure the WPvivid path override and/or server outbox path for the backup producer in use.
6. Use **Test Drime Connection** before scanning or uploading.

For development and release validation, use the packaged zip and the documented LocalWP confirmation gate before touching `plugin-tester.local`.

## Updates

The plugin includes `GitHub Plugin URI: NichlasB/alynt-drime-backups-uploader` for Alynt Plugin Updater compatibility. Updates are distributed from public GitHub releases using the attached WordPress-installable zip asset.

## Configuration

The settings screen controls:

- Drime API token, workspace ID with optional workspace picker, selected or manually entered parent folder ID, and optional relative subpath.
- Optional WPvivid backup path override.
- Optional server outbox path for generic packages produced outside WordPress.
- Duplicate handling mode: skip existing files or rename new uploads.
- Automatic WP-Cron scanning.
- Optional server-cron expectation reminders for WP-CLI-driven scheduled scans.
- Minimum file age before queueing.
- Multipart chunk size for large Drime uploads.
- Optional local deletion after confirmed upload.
- Optional manual remote retention for old plugin-owned Drime uploads.
- Optional failed upload email notifications and recipient list.
- Maximum retry count.
- Diagnostics enablement, minimum severity, and retention.

See [docs/SETTINGS.md](docs/SETTINGS.md) for the full option schema.

## Producer Adapters

Backup sources are implemented as producer adapters. A producer discovers completed local packages and returns normalized package records; the shared queue and Drime uploader handle the upload lifecycle.

See [docs/PRODUCER_ADAPTERS.md](docs/PRODUCER_ADAPTERS.md) for the adapter contract, package record shape, stability rules, and test expectations for future producers.

## Server Runner

The `server-runner/` directory contains a standalone PHP CLI runner for GridPane-style servers. It exports the WordPress database with WP-CLI, archives the WordPress files with `tar`, writes manifest/checksum sidecars, and atomically places completed packages in the configured outbox.

See [server-runner/README.md](server-runner/README.md) for the runner config shape and commands.

The plugin settings screen generates a GridPane runner config, runner install commands, runner health command, and cron snippet for the current site. The config is non-secret and can be saved as `config.json` beside the runner script. The install commands create the private directories and copy the bundled runner script only; cron installation and backup runs stay separate. The cron snippet creates one daily server-runner package, scans/uploads completed packages every 15 minutes through WP-CLI, and includes a lightweight status check line.

For restore validation, see [docs/RESTORE_RUNBOOK.md](docs/RESTORE_RUNBOOK.md). The current restore flow is non-destructive: it verifies and stages packages for inspection, but it does not download from Drime, import databases, or overwrite live site files.

For package integrity, extraction safety, storage-path, and encryption boundaries, see [docs/PACKAGE_SECURITY.md](docs/PACKAGE_SECURITY.md).

## Diagnostics

Diagnostics are disabled by default. When enabled, the plugin stores a bounded event log in WordPress options and exposes a health summary, recent events table, JSON export, and clear action to administrators.

Diagnostics redact bearer tokens, authorization headers, cookies, nonces, passwords, request bodies, presigned URLs, and HTTP URLs embedded in scalar values.

See [docs/STATUS_PAYLOAD.md](docs/STATUS_PAYLOAD.md) for the redacted health/status payload contract prepared for future dashboard work.

## Cron Health

The Scan State panel shows the current UTC time, the next automated scan, the last scheduled scan, the last detected scan runner, whether `DISABLE_WP_CRON` is active, and a server-cron health summary. The plugin records runtime evidence from WordPress; it does not read server cron files such as `/etc/cron.d/wp-cron-sites`.

## Frequently Asked Questions

### Does this delete local WPvivid backups?

No. Local deletion is disabled by default and only runs after confirmed upload when the administrator enables **Delete Local Files**. For WPvivid-listed split backup sets, the plugin waits until every listed part has uploaded successfully before deleting the local parts.

### Does this permanently delete Drime files?

No. Remote retention is disabled by default, runs only from manual admin actions, and moves eligible plugin-owned Drime uploads to trash. It does not permanently delete remote files.

### How are failed upload emails delivered?

Failure emails are disabled by default and use WordPress `wp_mail()`, so the active site mail stack or SMTP plugin handles delivery. Emails are plain text and include only safe operational details such as site URL, backup filename, sanitized reason, attempt count, timestamp, and the admin page URL.

### Can a failed upload be retried?

Yes. Failed uploads appear in the admin status area with a retry action when the failed registry still points to a readable local backup file. Retrying puts that file back at the front of the queue with attempts reset to zero.

### How do the Drime base folder and relative path work?

Select an existing Drime base folder, then enter the site folder or subpath in **Relative Path**. For example, selecting `General/Files/Backups` and entering `site1.com` resolves uploads to `General/Files/Backups/site1.com`. Browsing and previewing are read-only; missing subfolders are created only when an upload needs them.

### How does workspace selection work?

Use **Load Drime Workspaces** to retrieve workspaces available to the saved API token. Choosing a workspace updates the numeric **Workspace ID** field and clears the selected base folder so folders from another workspace are not reused accidentally. Save settings after choosing a workspace.

### Does this upload incomplete WPvivid files?

The scanner waits until files are old enough and their size is stable across scans. WPvivid-listed split sets are queued only when every listed part is present and stable.

### Can this run beside the old Alynt Drime WPvivid Uploader?

During migration, yes, but do not leave both plugins automatically uploading the same WPvivid backup folder. The health summary warns when the old WPvivid-specific uploader is active and this plugin is configured to use the WPvivid source.

### Does this upload incomplete server-runner packages?

The generic outbox producer ignores temporary files and waits until archive size is stable across scans. The included server runner writes to temporary paths first and only renames completed package artifacts into the outbox.

### Does this expose developer hooks?

No public custom actions or filters are exposed. See [docs/HOOKS.md](docs/HOOKS.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
