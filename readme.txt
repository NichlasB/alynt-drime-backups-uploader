=== Alynt Drime Backups Uploader ===
Contributors: alynt
Tags: backup, wpvivid, drime
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Upload completed backup packages to Drime.

== Description ==

Alynt Drime Backups Uploader is a companion plugin that scans completed local backup packages, queues stable backup files, and uploads them to Drime.

The plugin includes Drime destination settings with workspace selection guardrails, folder browsing and read-only destination preview, per-source Drime relative paths, WPvivid path detection, generic server-outbox scanning with per-package Drime folders and sidecar uploads, guided single-line server setup commands, server-runner local package inventory, package-level remote-index sidecars, folder catalog snapshot sidecars, light consistency metadata, cleanup-preview output, operator-confirmed local cleanup execution, uploaded server-package local retention, read-only restore dry runs, server-cron review commands, direct and configurable multipart upload support, duplicate handling, retry tracking, active-upload recovery, manual remote-retention cleanup, optional failed-upload email notifications, scheduled-scan cron health tracking, and optional redacted diagnostics for support. Broad local deletion, server-package local retention, remote retention, and failure emails are disabled by default.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/alynt-drime-backups-uploader/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Open Tools > Drime Backups.
4. Enter a Drime API token and destination settings.
5. Run Test Drime Connection before scanning or uploading.

== Frequently Asked Questions ==

= Does this delete local WPvivid backups? =

No. Local deletion is disabled by default and must be explicitly enabled in settings.

= Does this delete local server-runner packages? =

Not by default. When Prune Uploaded Server Packages is enabled, the plugin prunes only uploaded generic outbox/server-runner packages from the configured server outbox after they fall outside the newest retained package count. WPvivid files are not affected by that setting.

= What happens on uninstall? =

Uninstall removes plugin-owned WordPress options and scheduled cron hooks. It does not delete local backup packages, sidecars, restore staging folders, or manually installed server-runner directories.

= Does this permanently delete Drime files? =

No. Remote retention is disabled by default, runs only from manual admin actions, and moves eligible plugin-owned Drime uploads to trash. It does not permanently delete remote files.

= Does this upload incomplete backups? =

The scanner waits until files are old enough and their size is stable across scans. WPvivid-listed split sets are queued only when every listed part is present and stable. Generic outbox packages are queued only after the final archive is stable.

= How are server-runner packages verified before restore staging? =

The runner verifies the package checksum and manifest sidecar, prints next-step guidance for operators, then rejects unsafe archive member paths before extracting into a new restore directory. It writes `RESTORE_NOTES.txt` and `RESTORE_REPORT.json` as local evidence. Restore staging is inspection-only and does not import databases or overwrite live files.

= Can I dry-run a future restore after staging a package? =

Yes. The runner supports `restore-dry-run --staged-path=/path/to/staged/package --scope=files-and-database --format=json`. It reads config, staged restore evidence, and pre-restore backup evidence, then reports whether staging-only restore gates, target path safety, pre-restore backup artifacts, and required staged files are present. Add `--write-report=1` to write a successful dry-run evidence report under configured `restore_reports_path`. It does not import databases, overwrite files, or create backups.

= Can the server runner apply a staged restore? =

Yes, for staging restores only. `restore-apply --scope=database --confirm=restore-staging-site` first runs the existing dry-run/evidence checks, then imports the staged `database.sql` with WP-CLI and writes a restore apply report. `restore-apply --scope=files --confirm=restore-staging-site` first runs the file-scope dry-run/evidence checks, then replaces the staging target files from staged `htdocs/` and writes a restore apply report. `restore-apply --scope=files-and-database --confirm=restore-staging-site` replaces files first and imports the database second after the same gates pass. Add `--create-pre-restore-backup=1` to a staging apply command to create matching pre-restore database/file backup evidence immediately before apply. Production restore is not included yet.

= Can I list local server-runner packages before choosing one to restore? =

Yes. The runner supports `list --format=json` to print a local, read-only inventory with package IDs, archive names, sidecar names, manifest/checksum/remote-index validity, checksum metadata, and `verification_ready` flags. Still run `verify` before restore staging.

= Can I preview old local artifacts before deleting anything? =

Yes. The runner supports `cleanup-preview --older-than-days=14 --format=json` to report old outbox packages and restore staging directories. It is read-only and reports `destructive_actions_performed` as `false`. After upload and restore proof, an operator can run `cleanup --confirm=delete-local-artifacts --format=json` to delete only the selected local outbox package sets and restore staging directories.

= Are server-runner packages transactional snapshots? =

No. The server runner creates a logical WordPress backup from a WP-CLI database export plus a filesystem archive. Generated runner configs use light consistency mode, which records archive timing and warning metadata, but high-write sites should still use low-traffic windows or a maintenance-window process before production reliance.

= Can I restore if the original WordPress site is unavailable? =

The server runner can fetch a known Drime package plus matching manifest and checksum sidecars from CLI when you have the package ID, workspace, package-folder hash, and token. Server-runner packages also upload `.remote-index.json` and `.remote-catalog.json` sidecars for remote discovery. It then verifies, inspects, prints review guidance, and stages locally. Staging-only database, file, and combined file/database apply are available through gated CLI commands. wp-admin restore, production restore, and a mutable singleton remote catalog are not included.

= Can this run beside the old Alynt Drime WPvivid Uploader? =

During migration, yes, but do not leave both plugins automatically uploading the same WPvivid backup folder. The health summary warns when the old WPvivid-specific uploader is active and this plugin is configured to use the WPvivid source.

= Does this store diagnostics? =

Diagnostics are disabled by default. When enabled, diagnostics are redacted and stored in a bounded WordPress option.

= Can this detect a server cron? =

It records runtime evidence. A scheduled scan run from WP-CLI is shown as likely server-cron configured; HTTP WP-Cron is shown separately because WordPress cannot reliably tell whether that trigger came from visitor traffic, a server cron curl, or an external monitor.

= Does this install server cron automatically? =

No. The settings screen generates single-line review commands that build a proposed crontab file and show a diff. The final crontab install command is commented until an operator reviews and approves it.

= How are failed upload emails delivered? =

Failure emails are disabled by default and use WordPress mail, so the active site mail stack or SMTP plugin handles delivery. Emails are plain text and avoid tokens, signed URLs, raw request bodies, file contents, stack traces, and absolute server paths.

= How do the Drime base folder and relative path work? =

Select an existing Drime base folder, then enter the site folder or subpath in Relative Path. Browsing and previewing are read-only; missing subfolders are created only when an upload needs them.

When multiple producers are enabled, source-specific Drime relative paths can keep package types separate. For example, use `/site1.com/server` for server-runner packages and `/site1.com/wpvivid` for WPvivid packages. Empty source-specific fields fall back to the shared Relative Path.

= How does workspace selection work? =

Load Drime Workspaces retrieves allowed non-personal workspaces available to the saved API token. During first setup, Workspace ID may stay blank while you save the token and discover the correct destination. Choosing a workspace updates Workspace ID and clears the selected base folder so folders from another workspace are not reused accidentally. Workspace ID `0` is blocked by default. To lock a site to approved workspace IDs, add `define( 'ALYNT_DRIME_ALLOWED_WORKSPACE_IDS', '12345' );` to `wp-config.php`.

= Does this expose custom developer hooks? =

No public custom actions or filters are exposed.

== Changelog ==

= Unreleased =
* Added optional `restore-apply --create-pre-restore-backup=1` support so staging restores can create matching pre-restore database/file backup evidence immediately before apply.

= 0.3.2 =
* Added optional server-specific local retention for uploaded generic outbox/server-runner packages, with a configurable newest-package keep count.

= 0.3.1 =
* Changed generic server-outbox uploads to create or reuse a Drime child folder named after the package ID, then upload the archive and recognized sidecars inside that package folder.

= 0.3.0 =
* Added optional source-specific Drime relative paths so server-runner/generic-outbox uploads and WPvivid uploads can be stored in separate folders while sharing the same workspace and base folder.
* Changed the default minimum file age from 900 seconds to 300 seconds.
* Changed the default multipart chunk size from 32 MB to 128 MB for large-backup-oriented uploads, while keeping supported range validation.

= 0.2.1 =
* Streamlined server-runner setup into guided single-line command blocks for install/update, first package verification, scan/upload, and cron review.
* Changed generated server-cron upload lines to run the plugin's scheduled scan/upload hooks through WP-CLI so cron health evidence stays accurate.

= 0.2.0 =
* Added Drime workspace destination guardrails so workspace ID 0 is blocked and optional wp-config.php allowlisting can restrict selectable backup workspaces.
* Added server-runner light consistency metadata for database/archive timing, archive warning counts, and clean versus file-changes-detected status.
* Added server-cron review commands that build and diff a proposed crontab file without installing it automatically.
* Added multiple standalone site runner guidance for separate GridPane/VPS WordPress sites.
* Added package-level remote-index sidecars for server-runner packages uploaded through the generic outbox.
* Added folder catalog snapshot sidecars for server-runner packages uploaded through the generic outbox.
* Added operator-confirmed server-runner local cleanup execution for old outbox package sets and restore staging directories.
* Added clearer server-runner restore guidance after fetch, verify, inspect, and stage-restore commands.
* Added read-only server-runner restore dry-run checks for staged restore evidence, staging-only config gates, target path safety, and scope-specific staged files.
* Added optional server-runner restore dry-run evidence reports with `--write-report=1` and configured `restore_reports_path`.
* Added server-runner pre-restore backup evidence checks for a matching evidence JSON file and readable database/file backup artifacts.
* Added staging-only `restore-apply` support for database, files, and combined files-and-database restores behind explicit confirmation and pre-restore evidence gates.
* Added restore apply reports for missing symlinked drop-ins and known post-restore manual-review items such as Query Monitor's `wp-content/db.php`.
* Changed generated server-runner configs to use light consistency mode by default.
* Changed admin JavaScript and CSS internals to use the new `alynt-drime-backups` namespace instead of the old WPvivid-specific namespace.

= 0.1.1 =
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
* Changed server-runner archive creation to recover from live file-change warnings only when a non-empty archive was produced.
* Changed the build script to regenerate the served admin JavaScript and CSS assets that WordPress enqueues.
* Fixed the Server Runner Status admin table width so it matches the other status panels.
* Clarified uninstall scope for plugin-owned WordPress state versus operator-managed local backup files.

= 0.1.0 =
* Initial development version for the new backup-producer-agnostic plugin line. Historical releases for the previous WPvivid-specific uploader remain in the old plugin repository.

== Upgrade Notice ==

= 0.3.2 =
No breaking changes. Server-runner/generic-outbox packages can now be pruned locally after confirmed upload while preserving the newest configured package sets.

= 0.3.1 =
No breaking changes. Server-runner/generic-outbox package files now upload into a dedicated Drime package folder for each backup package.

= 0.3.0 =
No breaking changes. New installs use more production-oriented upload defaults, and sites can separate server-runner and WPvivid uploads into different Drime relative paths.

= 0.2.1 =
No breaking changes. Improves the guided server setup commands and cron scan/upload evidence alignment.

= 0.2.0 =
No breaking changes. Adds staging-only restore apply commands, stronger restore evidence gates, server-runner package discovery sidecars, operator-approved local cleanup, workspace guardrails, and server-cron review guidance.

= 0.1.1 =
No breaking changes. Includes server-runner hardening, admin UI polish, release validation, and documentation sync updates.

= 0.1.0 =
Initial development version for the new Alynt Drime Backups Uploader plugin line.
