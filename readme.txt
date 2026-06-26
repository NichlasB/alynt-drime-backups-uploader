=== Alynt Drime Backups Uploader ===
Contributors: alynt
Tags: backup, wpvivid, drime
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Upload completed backup packages to Drime.

== Description ==

Alynt Drime Backups Uploader is a companion plugin that scans completed local backup packages, queues stable backup files, and uploads them to Drime.

The plugin includes Drime destination settings with workspace selection, folder browsing and read-only destination preview, WPvivid path detection, generic server-outbox scanning with sidecar uploads, direct and configurable multipart upload support, duplicate handling, retry tracking, active-upload recovery, manual remote-retention cleanup, optional failed-upload email notifications, scheduled-scan cron health tracking, and optional redacted diagnostics for support. Local deletion, remote retention, and failure emails are disabled by default.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/alynt-drime-backups-uploader/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Open Tools > Drime Backups.
4. Enter a Drime API token and destination settings.
5. Run Test Drime Connection before scanning or uploading.

== Frequently Asked Questions ==

= Does this delete local WPvivid backups? =

No. Local deletion is disabled by default and must be explicitly enabled in settings.

= What happens on uninstall? =

Uninstall removes plugin-owned WordPress options and scheduled cron hooks. It does not delete local backup packages, sidecars, restore staging folders, or manually installed server-runner directories.

= Does this permanently delete Drime files? =

No. Remote retention is disabled by default, runs only from manual admin actions, and moves eligible plugin-owned Drime uploads to trash. It does not permanently delete remote files.

= Does this upload incomplete backups? =

The scanner waits until files are old enough and their size is stable across scans. WPvivid-listed split sets are queued only when every listed part is present and stable. Generic outbox packages are queued only after the final archive is stable.

= How are server-runner packages verified before restore staging? =

The runner verifies the package checksum and manifest sidecar, then rejects unsafe archive member paths before extracting into a new restore directory. The current restore flow is inspection-only and does not import databases or overwrite live files.

= Are server-runner packages transactional snapshots? =

No. The server runner creates a logical WordPress backup from a WP-CLI database export plus a filesystem archive. High-write sites should use low-traffic windows or a future stricter consistency mode before production reliance.

= Can I restore if the original WordPress site is unavailable? =

The server runner can fetch a known Drime package plus matching manifest and checksum sidecars from CLI when you have the package ID, workspace, folder hash, and token. It then verifies and stages locally. wp-admin restore, database import, live file overwrite, and remote index support are not included.

= Can this run beside the old Alynt Drime WPvivid Uploader? =

During migration, yes, but do not leave both plugins automatically uploading the same WPvivid backup folder. The health summary warns when the old WPvivid-specific uploader is active and this plugin is configured to use the WPvivid source.

= Does this store diagnostics? =

Diagnostics are disabled by default. When enabled, diagnostics are redacted and stored in a bounded WordPress option.

= Can this detect a server cron? =

It records runtime evidence. A scheduled scan run from WP-CLI is shown as likely server-cron configured; HTTP WP-Cron is shown separately because WordPress cannot reliably tell whether that trigger came from visitor traffic, a server cron curl, or an external monitor.

= How are failed upload emails delivered? =

Failure emails are disabled by default and use WordPress mail, so the active site mail stack or SMTP plugin handles delivery. Emails are plain text and avoid tokens, signed URLs, raw request bodies, file contents, stack traces, and absolute server paths.

= How do the Drime base folder and relative path work? =

Select an existing Drime base folder, then enter the site folder or subpath in Relative Path. Browsing and previewing are read-only; missing subfolders are created only when an upload needs them.

= How does workspace selection work? =

Load Drime Workspaces retrieves the workspaces available to the saved API token. Choosing a workspace updates Workspace ID and clears the selected base folder so folders from another workspace are not reused accidentally. Save settings after choosing a workspace.

= Does this expose custom developer hooks? =

No public custom actions or filters are exposed.

== Changelog ==

= Unreleased =
* Initial Alynt Drime Backups Uploader plugin line with Drime settings, queue/registry storage, WPvivid source support, generic server-outbox support, direct and multipart uploads, duplicate handling, retry limits, diagnostics, uninstall cleanup, and build/test tooling.
* Added producer-adapter documentation for future backup-source support.
* Added a health warning for old WPvivid-specific uploader coexistence during migration.
* Added package-security documentation for server-runner package integrity and restore staging boundaries.
* Added logical backup consistency documentation and server-runner manifest timing fields.
* Added remote restore discovery notes for WordPress-unavailable disaster scenarios.
* Added CLI-only server-runner fetch support for known Drime packages and sidecars.
* Added generic-outbox sidecar uploads for server-runner manifest and checksum files.
* Added WP-CLI scan, upload, run, status, failed-upload, diagnostics, and restore-support commands for server-driven workflows.
* Changed server-runner archives to exclude symlink entries before restore staging.
* Clarified uninstall scope for plugin-owned WordPress state versus operator-managed local backup files.

= 0.1.0 =
* Initial development version for the new backup-producer-agnostic plugin line. Historical releases for the previous WPvivid-specific uploader remain in the old plugin repository.

== Upgrade Notice ==

= 0.1.0 =
Initial development version for the new Alynt Drime Backups Uploader plugin line.
