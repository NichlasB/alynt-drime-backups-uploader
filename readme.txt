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

Alynt Drime Backups Uploader is a companion plugin that scans completed local WPvivid backup archives, queues stable backup files, and uploads them to Drime.

The plugin includes Drime destination settings with workspace selection, folder browsing and read-only destination preview, WPvivid path detection, direct and configurable multipart upload support, duplicate handling, retry tracking, active-upload recovery, manual remote-retention cleanup, optional failed-upload email notifications, scheduled-scan cron health tracking, and optional redacted diagnostics for support. Local deletion, remote retention, and failure emails are disabled by default.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/alynt-drime-backups-uploader/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Open Tools > Drime Backups.
4. Enter a Drime API token and destination settings.
5. Run Test Drime Connection before scanning or uploading.

== Frequently Asked Questions ==

= Does this delete local WPvivid backups? =

No. Local deletion is disabled by default and must be explicitly enabled in settings.

= Does this permanently delete Drime files? =

No. Remote retention is disabled by default, runs only from manual admin actions, and moves eligible plugin-owned Drime uploads to trash. It does not permanently delete remote files.

= Does this upload incomplete backups? =

The scanner waits until files are old enough and their size is stable across scans. WPvivid-listed split sets are queued only when every listed part is present and stable.

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

= 0.6.0 =
* Changed Drime multipart chunk-size validation to allow values from 5 MB through 256 MB while keeping the conservative recommendation unchanged.
* Added a memory guard so oversized multipart chunk settings fail gracefully under constrained PHP memory.
* Improved selected-base relative-path uploads so cached concrete Drime destination folders are reused before creating missing folders.

= 0.5.1 =
* Added a Failed Uploads table with per-file retry actions for readable local backup files.
* Increased Drime API control-request timeout for slower multipart preflight/signing responses.
* Changed local deletion for WPvivid-listed split backup sets so cleanup waits until every listed part is uploaded.

= 0.5.0 =
* Added a read-only Drime workspace picker for loading workspaces available to the saved API token.
* Changed workspace changes to clear selected base-folder metadata so stale folder IDs are not reused across workspaces.
* Refined settings input widths and status table widths for a cleaner admin layout.

= 0.4.0 =
* Added a read-only Drime folder browser and destination preview for selecting an existing base folder and checking the resolved upload path.
* Changed uploads so a selected base folder can be combined with a typed relative subpath, creating the final subfolder during upload when needed.

= 0.3.0 =
* Added cron health tracking for scheduled scans, including last runner evidence, WP-CLI scan evidence, WP-Cron disabled status, and server-cron health guidance.
* Added a Server Cron Expected setting for reminders when automatic scans should be driven by WP-CLI but no WP-CLI scheduled scan has been observed.

= 0.2.1 =
* Added a Scan State section with current UTC time, automatic scan status, next scheduled scan timing, last completed scan, and minimum file age in seconds.
* Displayed Recent Events timestamps in explicit UTC format and added a current UTC time reference above the events table.

= 0.2.0 =
* Added a configurable multipart chunk size setting for Drime uploads.
* Added manual Remote Retention controls for plugin-owned Drime uploads.
* Added optional failed-upload email notifications through WordPress mail with recipient settings, duplicate suppression, and a test-email admin action.
* Clarified production guidance for local deletion and minimum file age settings.
* Added row numbers to the Recent Events diagnostics table.

= 0.1.1 =
* Added Alynt Plugin Updater compatibility metadata and refreshed release packaging workflow for public GitHub release updates.

= 0.1.0 =
* Initial development release with Drime settings, WPvivid local scanner, queue/registry storage, direct and multipart uploads, duplicate handling, retry limits, diagnostics, uninstall cleanup, and build/test tooling.

== Upgrade Notice ==

= 0.6.0 =
Allows larger Drime multipart chunk sizes with a PHP memory guard. Keep 32 MB unless larger chunks have been validated in the target environment.

= 0.5.1 =
Improves large multipart upload resilience and failed-upload recovery. No breaking changes.

= 0.5.0 =
Adds Drime workspace selection from the settings screen. No breaking changes.

= 0.4.0 =
Adds Drime folder browsing and read-only destination preview for safer backup destination setup. No breaking changes.

= 0.3.0 =
Adds cron-health evidence and server-cron expectation reminders for automatic scan reliability. No breaking changes.

= 0.2.1 =
Adds clearer UTC scan timing and Recent Events context for backup monitoring. No breaking changes.

= 0.2.0 =
Adds Drime backup controls, failed-upload email notifications, and admin UI clarity improvements. No breaking changes.

= 0.1.1 =
Adds GitHub release update compatibility.

= 0.1.0 =
Initial development release.
