# WPvivid Source Verification

Verified: 2026-07-14

Targets: `unconditionyou.com` (`live-only` backup/upload source) and `plugin-tester.local` (`local-only` restore target)

Installed source/runtime inspected:

- WPvivid Backup Plugin Free `0.9.130`
- WPvivid Plugins Pro `2.2.47`
- Alynt Drime Backups Uploader `0.4.0`

## Backup Directory

WPvivid Free defines `WPVIVID_DEFAULT_BACKUP_DIR` as `wpvividbackups` and stores local backup settings in `wpvivid_local_setting`.

Relevant Free behavior:

- `WPvivid_Setting::set_default_local_option()` writes `wpvivid_local_setting['path'] = 'wpvividbackups'`.
- `WPvivid_Setting::get_backupdir()` reads `wpvivid_local_setting['path']` and treats it as relative to `WP_CONTENT_DIR`.

Relevant Pro behavior:

- `WPvivid_Custom_Interface_addon::wpvivid_get_local_backup_abspath()` reads:
  - `wpvivid_common_setting['local_backup_folder']`
  - `wpvivid_local_setting['path']`
  - `wpvivid_local_setting['outside_path']`
- When `local_backup_folder` is `outside_folder`, Pro uses `outside_path` as the absolute backup directory.
- Otherwise Pro uses `WP_CONTENT_DIR . DIRECTORY_SEPARATOR . wpvivid_local_setting['path']`.

Runtime snapshot on `plugin-tester.local`:

- `wpvivid_local_setting` keys: `path`, `save_local`
- `wpvivid_local_setting['path']`: `wpvividbackups`
- `wpvivid_common_setting['local_backup_folder']`: not set
- `wpvivid_backup_list`: empty array at inspection time

Plugin impact:

- `Alynt_Drime_Backups_Uploader_WPvivid_Detector` now reads the specific WPvivid path options instead of recursively sniffing unrelated options.
- Remote-storage option values are not inspected because they may contain credentials and are not needed for local backup path detection.

## Filename And Split Archive Patterns

WPvivid recognizes local backup ZIP files with patterns equivalent to:

- `wpvivid-.*_.*_.*.zip`
- Pro/white-label prefix variants through `wpvivid_white_label_file_prefix`
- Pro also accepts broader `.*-.*_.*_.*.zip` patterns.

Observed naming examples and source patterns include:

- `wpvivid-{id}_{date}_backup_all.zip`
- `wpvivid-{id}_{date}_backup_db.zip`
- `wpvivid-{id}_{date}_backup_themes.zip`
- `wpvivid-{id}_{date}_backup_plugin.zip`
- `wpvivid-{id}_{date}_backup_uploads.zip`
- `wpvivid-{id}_{date}_backup_content.zip`
- Split archive parts use a `.partNNN.zip` suffix in current source/runtime list data, for example `wpvivid-..._backup_db.part001.zip` and `wpvivid-..._backup_db.part002.zip`.

Temporary or incomplete upload markers:

- Free upload cleanup removes `.tmp` and `.part`.
- Pro upload writes external upload temp files as `.part` before final rename.
- Pro cleanup also checks `wpvivid-.*_.*_.*.tmp`.

Plugin impact:

- The scanner ignores `.tmp`, raw `.part`, `temp`, `partial`, and `incomplete` filename markers, but preserves valid WPvivid split archives matching `.partNNN.zip`.
- The scanner now adds WPvivid metadata per candidate:
  - `backup_id`
  - `backup_type`
  - `set_signature`
  - `set_file_count`
  - `from_list`
- The scanner queues WPvivid backup-list-backed files only after every listed file is present and stable.
- The scanner skips orphaned `.partNNN.zip` split archive files when no completed WPvivid backup-list entry is available.

## Backup List And Completion Signals

WPvivid stores completed local backup records in `wpvivid_backup_list`.

Relevant structures:

- `wpvivid_backup_list[$backup_id]['backup']['files']` contains backup file metadata.
- `wpvivid_backup_list[$backup_id]['local']['path']` stores the local backup folder.
- Free and Pro call `do_action('wpvivid_update_backup', $id, $key, $data)` when updating backup records.
- WPvivid upload/rescan flows use the action `wpvivid_rebuild_backup_list`.
- Free supports filters around list names:
  - `wpvivid_get_backuplist_name`
  - `get_wpvivid_backup_list_name`

Plugin impact:

- The scanner uses `wpvivid_backup_list` as metadata when present, but does not require it because fresh installs, imported local files, or manually rescanned folders may have an empty list.
- A future hardening pass should use WPvivid's list filters or completion hooks if the uploader needs stronger set-level gating than age/size stability.

## Split Archive Runtime Validation

A LocalWP split-fixture validation was run on `plugin-tester.local` using WPvivid's real `.part001.zip` / `.part002.zip` naming and a temporary `wpvivid_backup_list` entry.

Validated behavior:

- Complete listed split set: the first scan captured the stability snapshot and queued zero files; the second scan queued both listed parts after age/size stability.
- Missing listed split part: the first scan captured the stability snapshot and queued zero files; the second scan still queued zero files because the listed set was incomplete.
- Cleanup verification after the fixture restored plugin state: queue count `0`, failed count `0`, active upload empty, uploaded registry count `1`, cached Drime parent folder ID still present, split-fixture scan snapshot absent, and the temporary fixture directory removed.

## Backup-Engine Split Archive E2E Validation

A real WPvivid files-and-database backup rehearsal was completed on 2026-07-14. The live source was `unconditionyou.com`; the destructive restore target was the non-production LocalWP site `plugin-tester.local`.

Creation and upload proof:

- WPvivid backup ID: `wpvivid-cf7afa1a987c6`.
- WPvivid's backup engine produced six valid `.part001.zip` through `.part006.zip` files totaling `96,082,149` bytes.
- While one final part still used WPvivid's temporary name, Alynt found candidates but queued zero files.
- After all six final filenames existed, the initial stability scan still queued zero files.
- After the configured 300-second minimum age, Alynt queued and uploaded exactly six files.
- Final Alynt status returned queue `0`, failed `0`, and no active upload.
- The effective Drime destination was `/unconditionyou.com/wpvivid`.

Drime retrieval and restore proof:

- All six files were downloaded through Drime's file-entry download endpoint.
- Every downloaded file matched the corresponding live source size and SHA-256 hash.
- WPvivid rescanned the six files on `plugin-tester.local` as one complete backup with no incomplete parts.
- A full local filesystem and database restore point was created and verified before the destructive restore.
- WPvivid restored database, themes, plugins, uploads, general `wp-content`, and WordPress core.
- The migrated target retained `home` and `siteurl` as `http://plugin-tester.local` while restoring the `UnconditionYou` site identity.
- Post-restore verification found 85 database tables, three users, HTTP `200`, the restored Genesis/fox-diamond theme pair, and no browser console errors.
- WPvivid Free `0.9.130`, WPvivid Pro `2.2.47`, and Alynt Drime Backups Uploader `0.4.0` were present and active after restoration.

Approved cleanup removed the large source, download, imported, and filesystem-restore archive copies. The remote Drime backup, small evidence files, and the `2.2 MB` pre-restore database export were retained.

## Remaining Boundaries

- Pro filtered backup-list names beyond `wpvivid_backup_list` were not enumerated at runtime.
- The destructive restore proof used a disposable LocalWP target. It does not authorize or validate unattended production restoration.
