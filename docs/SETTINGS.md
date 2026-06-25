# Settings And Option Schema

## User Settings

Option name: `alynt_drime_backups_settings`

| Option Key | Type | Default | Sanitization | UI Area | Description |
| --- | --- | --- | --- | --- | --- |
| `api_token` | string | `''` | `sanitize_text_field`; masked value preserves existing token | Drime | Drime bearer token used for API requests. Stored with autoload disabled. |
| `workspace_id` | integer | `0` | `absint`, minimum `0`; changing it clears selected base-folder metadata | Drime | Drime workspace ID. `0` means the personal/default workspace. The workspace picker can populate this value from `GET /me/workspaces`. |
| `parent_folder_id` | string | `''` | empty string or `absint` string; cleared when workspace changes | Drime | Optional concrete Drime base folder ID, either manually entered or selected through the folder browser. |
| `parent_folder_hash` | string | `''` | alphanumeric, underscore, and hyphen only; cleared when `parent_folder_id` is empty or workspace changes | Drime | Optional non-secret Drime folder hash used for browsing children and previewing destinations. |
| `parent_folder_display_path` | string | `''` | `sanitize_text_field`, slash normalization; cleared when `parent_folder_id` is empty or workspace changes | Drime | Optional non-secret display breadcrumb for the selected base folder. |
| `relative_path` | string | `''` | `sanitize_text_field`, slash normalization, rejects `..` | Drime | Optional subpath under the selected base folder. Missing folders may be created only by the upload path when needed. |
| `backup_path_override` | string | `''` | `sanitize_text_field` | WPvivid Source | Optional local WPvivid backup path override. |
| `duplicate_mode` | string | `skip` | `sanitize_key`, allowlist `skip` or `rename` | Behavior | Controls whether existing Drime filenames are skipped or renamed. |
| `auto_scan_enabled` | boolean | `false` | boolean cast from checkbox presence | Behavior | Enables scheduled WP-Cron scanning. |
| `server_cron_expected` | boolean | `false` | boolean cast from checkbox presence | Behavior | Enables admin reminders when scheduled scans should be driven by WP-CLI but no WP-CLI scan evidence has been observed. |
| `scan_interval` | string | `fifteen_minutes` | internal fixed value | Internal | WP-Cron schedule key used by automatic scanning. |
| `min_file_age_seconds` | integer | `900` | `absint`, minimum `60` | Behavior | Minimum modified age before a file can be queued. |
| `multipart_chunk_size_mb` | integer | `32` | `absint`, range `5` to `256` | Behavior | Multipart upload part size in MB. `32` is recommended for large backups; larger values require enough PHP memory for one part plus runtime overhead and should be validated against the current Drime account and network path before production use. |
| `delete_local_after_upload` | boolean | `false` | boolean cast from checkbox presence | Behavior | Deletes local backup files after confirmed upload when enabled. |
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
| `alynt_drime_backups_logs` | array | `array()` | `Alynt_Drime_Backups_Uploader_Logger` | Redacted diagnostics events when diagnostics are enabled. |
| `alynt_drime_backups_upload_lock` | array | `array()` | `Alynt_Drime_Backups_Uploader_Uploader` | Short-lived upload worker lock to prevent concurrent queue processing. |

## External Options Read

The plugin reads these WPvivid options and does not write them:

| Option Key | Purpose |
| --- | --- |
| `wpvivid_common_setting` | Detects custom local backup folder configuration. |
| `wpvivid_local_setting` | Detects Free/Pro local backup path and Pro outside-folder mode. |
| `wpvivid_backup_list` | Reads WPvivid backup-set metadata for complete-set scanning. |
