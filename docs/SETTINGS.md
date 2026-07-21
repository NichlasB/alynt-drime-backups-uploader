# Settings And Option Schema

## User Settings

Option name: `alynt_drime_backups_settings`

| Option Key | Type | Default | Sanitization | UI Area | Description |
| --- | --- | --- | --- | --- | --- |
| `api_token` | string | `''` | `sanitize_text_field`; masked value preserves existing token | Drime | Drime bearer token used for API requests. Stored with autoload disabled. |
| `workspace_id` | integer | `0` | `absint`; blank is allowed as the first-setup "not configured yet" state; submitted workspace ID `0` and IDs outside `ALYNT_DRIME_ALLOWED_WORKSPACE_IDS` are rejected; changing it clears selected base-folder metadata | Drime | Drime workspace ID for backup destinations. The personal/default workspace ID `0` is blocked by default. The workspace picker can populate this value from `GET /me/workspaces`. |
| `parent_folder_id` | string | `''` | empty string or `absint` string; cleared when workspace changes | Drime | Optional concrete Drime base folder ID, either manually entered or selected through the folder browser. |
| `parent_folder_hash` | string | `''` | alphanumeric, underscore, and hyphen only; cleared when `parent_folder_id` is empty or workspace changes | Drime | Optional non-secret Drime folder hash used for browsing children and previewing destinations. |
| `parent_folder_display_path` | string | `''` | `sanitize_text_field`, slash normalization; cleared when `parent_folder_id` is empty or workspace changes | Drime | Optional non-secret display breadcrumb for the selected base folder. |
| `relative_path` | string | `''` | `sanitize_text_field`, slash normalization, rejects `..` | Drime | Optional shared subpath under the selected base folder. Missing folders may be created only by the upload path when needed. |
| `backup_path_override` | string | `''` | `sanitize_text_field` | WPvivid Source | Optional local WPvivid backup path override. |
| `server_outbox_path` | string | `''` | `sanitize_text_field` | Generic Outbox Source | Optional local directory scanned for completed backup packages produced by the server runner or another backup producer. |
| `server_relative_path` | string | `''` | `sanitize_text_field`, slash normalization, rejects `..` | Generic Outbox Source | Optional Drime subpath used for generic outbox/server-runner packages. Falls back to `relative_path` when empty. |
| `wpvivid_relative_path` | string | `''` | `sanitize_text_field`, slash normalization, rejects `..` | WPvivid Source | Optional Drime subpath used for WPvivid packages. Falls back to `relative_path` when empty. |
| `site_uuid` | string | `''` | Generated internally and sanitized as UUID | Internal | Stable non-secret site identifier used in health/status payloads for future centralized monitoring. |
| `duplicate_mode` | string | `skip` | `sanitize_key`, allowlist `skip` or `rename` | Behavior | Controls whether existing Drime filenames are skipped or renamed. |
| `auto_scan_enabled` | boolean | `false` | boolean cast from checkbox presence | Behavior | Enables scheduled WP-Cron scanning. |
| `server_cron_expected` | boolean | `false` | boolean cast from checkbox presence | Behavior | Enables admin reminders when scheduled scans should be driven by WP-CLI but no WP-CLI scan evidence has been observed. |
| `scan_interval` | string | `fifteen_minutes` | internal fixed value | Internal | WP-Cron schedule key used by automatic scanning. |
| `min_file_age_seconds` | integer | `300` | `absint`, minimum `60` | Behavior | Minimum modified age before a file can be queued. |
| `multipart_chunk_size_mb` | integer | `128` | `absint`, range `5` to `256` | Behavior | Multipart upload part size in MB. `128` is recommended for large backups when PHP memory and the network path can support one part plus runtime overhead. |
| `delete_local_after_upload` | boolean | `false` | boolean cast from checkbox presence | Behavior | Deletes local backup files after confirmed upload when enabled. |
| `server_local_retention_enabled` | boolean | `false` | boolean cast from checkbox presence | Behavior | Enables automatic pruning of older uploaded generic-outbox/server-runner packages from the configured local outbox. WPvivid files are not affected. |
| `server_local_retention_keep` | integer | `2` | `absint`, range `1` to `30` | Behavior | Number of newest uploaded local server package sets to keep when server local retention is enabled. |
| `remote_retention_enabled` | boolean | `false` | boolean cast from checkbox presence | Behavior | Allows manual cleanup of old Drime files uploaded by this plugin. |
| `remote_retention_days` | integer | `60` | `absint`, range `1` to `365` | Behavior | Uploaded registry records older than this many days are eligible for manual remote cleanup. |
| `failure_email_enabled` | boolean | `false` | boolean cast from checkbox presence | Behavior | Sends plain-text failed upload notifications through WordPress mail. |
| `failure_email_recipients` | string | site admin email | comma/newline parsing, valid emails only, stored one per line | Behavior | Recipients for failed upload notifications and test emails. |
| `max_retries` | integer | `3` | `absint`, range `0` to `10` | Behavior | Failed upload attempts allowed before a queued item is removed. |
| `diagnostics_enabled` | boolean | `false` | boolean cast from checkbox presence | Behavior | Enables redacted diagnostics storage. |
| `diagnostics_min_level` | string | `warning` | `sanitize_key`, allowlist severity level | Behavior | Minimum diagnostics severity to store. |
| `diagnostics_retention` | integer | `100` | `absint`, range `25` to `500` | Behavior | Maximum diagnostics events retained locally. |

## Operational Options

These options are owned by the plugin and are removed on uninstall.

| Option Key | Type | Default | Writer | Description |
| --- | --- | --- | --- | --- |
| `alynt_drime_backups_upload_queue` | array | `array()` | `Alynt_Drime_Backups_Uploader_Queue` | Pending backup upload items keyed by signature. |
| `alynt_drime_backups_active_upload` | array | `array()` | `Alynt_Drime_Backups_Uploader_Queue` | Current multipart upload state used for resume and recovery. |
| `alynt_drime_backups_uploaded_files` | array | `array()` | `Alynt_Drime_Backups_Uploader_Backup_Registry` | Uploaded backup records keyed by signature. Remote retention preserves these records and records `remote_status`, `remote_updated`, and optional `remote_status_context`. |
| `alynt_drime_backups_failed_uploads` | array | `array()` | `Alynt_Drime_Backups_Uploader_Backup_Registry` | Failed upload records keyed by signature. |
| `alynt_drime_backups_drime_locations` | array | `array()` | `Alynt_Drime_Backups_Uploader_Backup_Registry` | Cached Drime parent folder IDs for configured relative paths. |
| `alynt_drime_backups_failure_notifications` | array | `array()` | `Alynt_Drime_Backups_Uploader_Failure_Notifier` | Sent-notification ledger keyed by backup signature and failure state to suppress duplicate failure emails. |
| `alynt_drime_backups_cron_health` | array | `array()` | `Alynt_Drime_Backups_Uploader_Cron_Health` | Scheduled-scan runner evidence used to report whether scans have been observed from WP-CLI, HTTP WP-Cron, manual admin actions, or an unknown runtime. |
| `alynt_drime_backups_file_snapshots` | array | `array()` | `Alynt_Drime_Backups_Uploader_Scanner` | File size/modified-time snapshots used to verify stability across scans. |
| `alynt_drime_backups_outbox_file_snapshots` | array | `array()` | `Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer` | File size/modified-time snapshots used to verify generic outbox package stability across scans. |
| `alynt_drime_backups_logs` | array | `array()` | `Alynt_Drime_Backups_Uploader_Logger` | Redacted diagnostics events when diagnostics are enabled. |
| `alynt_drime_backups_upload_lock` | array | `array()` | `Alynt_Drime_Backups_Uploader_Uploader` | Renewable owner-aware upload worker lease. Long multipart work renews the lease at major boundaries; only the owning worker may release it or mutate shared completion/retry state after ownership loss. |

Uninstall removes the plugin-owned options above and the plugin cron hooks from each site on multisite installs. It intentionally does not remove backup archives, sidecars, restore staging folders, or manually installed server-runner directories outside WordPress option storage.

The admin setup screen also generates read-only server-runner helper snippets from saved settings. The server setup area is organized into guided copy-paste blocks for runner install/update, first package verification, scan/upload, and cron review. The install command embeds the non-secret runner config JSON so the operator does not have to save `config.json` manually. The **Server Cron Review Commands** snippet is not persisted as a setting; it uses single-line shell commands to build and diff a proposed user crontab file and leaves the final install command commented until an operator approves it.

## Per-Source Drime Relative Paths

The shared `relative_path` remains the default destination subpath for all producers. When both server-runner/generic-outbox packages and WPvivid packages are enabled on the same site, optional per-source paths can keep uploads separated under the same workspace and base folder:

```text
relative_path: /example.com
server_relative_path: /example.com/server
wpvivid_relative_path: /example.com/wpvivid
```

If `server_relative_path` is empty, generic outbox/server-runner packages use `relative_path`. Generic outbox packages always append a package-ID folder under that effective path before uploading the archive and sidecars. For example, `server_relative_path: /example.com/server` and `package_id: example-com-20260702-010001` resolve to `/example.com/server/example-com-20260702-010001`.

If `wpvivid_relative_path` is empty, WPvivid packages use `relative_path`. WPvivid uploads do not receive the generic outbox package-folder treatment. Uploaded registry records include `destination_relative_path` for the effective path used by that package.

## Local Server Outbox Retention

There are two local deletion controls, and they intentionally solve different problems:

- `delete_local_after_upload` is broad immediate cleanup. When enabled, it deletes local files after confirmed upload and can affect WPvivid files.
- `server_local_retention_enabled` is server-package retention. When enabled, it prunes only uploaded generic-outbox/server-runner packages from `server_outbox_path` after they fall outside the newest retained package count.

Server-package retention uses uploaded registry records as the remote-confirmed evidence. It ignores files that are not marked uploaded, files outside the configured server outbox, missing paths, and WPvivid records. Recognized sidecars are deleted only when they belong to the deleted archive path. Uploaded registry records remain in WordPress as remote evidence after local files are pruned.

The setting is disabled by default so existing sites do not lose local backup copies during upgrade. Enable it only after Drime uploads and restore procedures are verified. A typical production value is:

```text
server_local_retention_enabled: true
server_local_retention_keep: 2
```

## Workspace Destination Guardrails

Workspace ID `0` is the personal/default Drime workspace and is not allowed for backup destinations. A blank workspace field is allowed only as the first-setup "not configured yet" state so the operator can save the token before loading workspaces.

Without a workspace allowlist, the workspace picker shows non-personal workspaces returned by Drime so the operator can discover and choose the intended workspace ID.

After discovery, lock the site to one approved workspace in `wp-config.php`:

```php
define( 'ALYNT_DRIME_ALLOWED_WORKSPACE_IDS', '12345' );
```

Multiple approved workspaces can be configured with comma-separated IDs:

```php
define( 'ALYNT_DRIME_ALLOWED_WORKSPACE_IDS', '12345,67890' );
```

When the constant is present, the workspace picker filters to those IDs. Settings save, folder browsing, destination preview, and upload processing also reject disallowed workspace IDs server-side.

## Health Payload Fields

See `docs/STATUS_PAYLOAD.md` for the full redacted status payload contract and future dashboard boundary.

The health summary includes these non-secret migration/coexistence fields:

| Field | Type | Description |
| --- | --- | --- |
| `old_wpvivid_uploader_active` | boolean | Whether the previous `alynt-drime-wpvivid-uploader` plugin line is currently active. When true and this plugin is configured to use the WPvivid source, the health summary adds an `old_wpvivid_uploader_active` warning to help avoid duplicate uploads during migration. |

## External Options Read

The plugin reads these WPvivid options and does not write them:

| Option Key | Purpose |
| --- | --- |
| `wpvivid_common_setting` | Detects custom local backup folder configuration. |
| `wpvivid_local_setting` | Detects Free/Pro local backup path and Pro outside-folder mode. |
| `wpvivid_backup_list` | Reads WPvivid backup-set metadata for complete-set scanning. |

The plugin also reads these WordPress core options for coexistence warnings and does not write them:

| Option Key | Purpose |
| --- | --- |
| `active_plugins` | Detects whether the previous Alynt Drime WPvivid Uploader plugin is active on the site. |
| `active_sitewide_plugins` | Detects whether the previous Alynt Drime WPvivid Uploader plugin is network-active on multisite. |
