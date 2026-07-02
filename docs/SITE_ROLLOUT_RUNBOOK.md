# Site-By-Site GridPane Rollout Runbook

This runbook describes how to add one GridPane-hosted WordPress site to the Alynt Drime Backups Uploader flow.

The goal is to make each rollout repeatable: install the plugin, configure Drime, install the site-local server runner when needed, prove one backup upload, and prove one non-destructive restore staging pass before relying on the site flow.

## Scope

Use this runbook for GridPane VPS sites where backups should be uploaded to Drime through Alynt Drime Backups Uploader.

Supported producer choices:

- WPvivid local backups only.
- Server-side runner packages through the generic outbox.
- Both sources, when they intentionally produce different backup sets.

The fetch, verify, inspect, and stage-restore commands in this runbook apply to server-runner `.tar.gz` packages. WPvivid-only sites need a separate WPvivid restore proof before they are considered restore-ready.

For the broader server-side automation policy, including scheduling, multiple standalone site layout, disk retention, and high-write-site boundaries, see `docs/SERVER_BACKUP_AUTOMATION.md`.

For multiple separate WordPress sites on the same GridPane or VPS server, see `docs/MULTIPLE_STANDALONE_SITE_RUNNER_GUIDANCE.md`. That guide is not WordPress Multisite guidance; it covers isolated runner configs and paths for separate standalone sites.

Do not leave two tools uploading the same backup folder automatically. During migration, the old WPvivid-specific Alynt uploader may be active briefly, but the new plugin health summary should be checked and duplicate automation should be disabled before production use.

## Safety Rules

- Confirm the exact site, SSH alias, site user, WordPress path, and Drime destination before changing anything.
- Treat server writes, cron edits, package deletion, restore staging, and remote Drime cleanup as approval-required operations.
- Keep runner paths outside the public web root.
- Keep local backups after upload by default until the create, upload, fetch, verify, and stage-restore loop has been proven.
- Do not enable destructive restore automation. The current restore flow is inspection-only.
- Do not store Drime tokens in runner config files or cron snippets. The upload plugin stores the upload token in WordPress settings; the runner fetch command reads a token from an environment variable only when a restore download is needed.

## Information To Collect

Record these values before starting:

```text
Site label:
Public URL:
GridPane SSH alias:
GridPane site user:
WordPress path:
Site root:
Plugin source/version:
Drime workspace:
Drime base folder:
Shared Drime relative path/site folder:
Server Drime relative path:
WPvivid Drime relative path:
Producer choice: WPvivid / server runner / both
Server outbox path:
Runner path:
Restore staging path:
Cron owner:
First backup package ID:
First Drime upload location:
First restore staging path:
```

Typical GridPane paths:

```text
/var/www/example.com/htdocs
/var/www/example.com/private/alynt-drime-backups/outbox
/var/www/example.com/private/alynt-drime-backups/work
/var/www/example.com/private/alynt-drime-backups/runner
/var/www/example.com/restores/alynt-drime-backups
```

## Phase 1: Install Or Update The Plugin

1. Confirm the target site and current plugin state.
2. Install the accepted release zip or update through Alynt Plugin Updater.
3. Activate **Alynt Drime Backups Uploader**.
4. Open **Tools > Drime Backups**.
5. Confirm the admin page loads without PHP errors.
6. If the old Alynt Drime WPvivid Uploader is active, decide whether it should be deactivated now or left temporarily for migration.

Acceptance:

- The plugin is active.
- The admin screen loads.
- The displayed plugin version matches the expected release.
- No queue, failed upload, or active upload state exists unless this is an intentional migration.

Useful WP-CLI check:

```bash
wp --path=/var/www/example.com/htdocs plugin list --status=active
wp --path=/var/www/example.com/htdocs alynt-drime-backups status --format=json
```

## Phase 2: Configure Drime And Backup Sources

1. Enter the Drime API token.
2. Use **Test Drime Connection**.
3. Load Drime workspaces if the site should upload to a team workspace.
4. Select or enter the Drime base folder.
5. Enter the shared relative path for this site, usually the domain or site slug.
6. If both server-runner/generic-outbox packages and WPvivid packages should upload to separate Drime folders, enter source-specific relative paths such as `/example.com/server` and `/example.com/wpvivid`. Leave a source-specific path empty when that source should use the shared relative path.
7. Preview the destination.
8. Choose duplicate behavior:
   - `skip` for conservative production behavior.
   - `rename` only when duplicate retention is intentional.
9. Decide whether the site uses WPvivid, server runner, or both.
10. Configure the source paths:
   - Leave WPvivid override blank unless WPvivid stores backups outside the detected path.
   - Set **Server Outbox Path** when using the server runner or another server-side backup producer.
11. Enable **Server Cron Expected** when scan/upload will be driven by WP-CLI cron.
12. Keep **Delete Local Files** disabled until restore rehearsal has passed.
13. Keep remote retention disabled until upload and restore behavior is proven.

Acceptance:

- Drime connection succeeds.
- Destination preview identifies the intended target.
- Producer paths are configured intentionally.
- Status output reflects the expected source configuration.

Useful WP-CLI check:

```bash
wp --path=/var/www/example.com/htdocs alynt-drime-backups status --format=json
```

## Phase 3: Install The Server Runner

Skip this phase if the site will only upload WPvivid backups.

1. In **Tools > Drime Backups**, review the generated **Install / Update Server Runner** command.
2. Run the command as the GridPane site user.
3. Fix any health failure before creating a backup package.

The generated install command embeds the non-secret config JSON, writes it as `config.json` beside the runner, creates private directories, copies the runner script, sets permissions, and runs health. The config includes site URL, WordPress path, outbox path, work path, restore path, archive format, package prefix, WP-CLI path, database export setting, and exclude paths.

The generated install command does not add cron and does not run a backup.

Acceptance:

- Runner script exists and is executable by the site user.
- `config.json` exists and is readable by the site user.
- Outbox, work, and restore paths exist outside the public web root.
- Runner health check passes.

Useful commands, using the generated paths for the actual site:

```bash
php /var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php health \
  --config=/var/www/example.com/private/alynt-drime-backups/runner/config.json
```

## Phase 4: Create And Upload The First Backup

For server-runner sites:

1. Run the generated **Create First Test Backup** command.
2. Run the generated **Scan And Upload Completed Packages** command after the package is old enough to pass the minimum file age and stability checks.
3. Check the plugin status payload.
4. Confirm the plugin created a Drime child folder named after the package ID under the intended server destination path, and that the archive, `.manifest.json`, `.sha256`, `.remote-index.json`, and `.remote-catalog.json` sidecars are visible inside that package folder.

Example commands:

```bash
PACKAGE=$(php /var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php run --config=/var/www/example.com/private/alynt-drime-backups/runner/config.json | tee /dev/stderr | awk '/^Created package:/ {print $3}') && if test -n "$PACKAGE"; then php /var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php verify --config=/var/www/example.com/private/alynt-drime-backups/runner/config.json --package="$PACKAGE"; else echo 'Could not detect created package path from runner output.' >&2; exit 1; fi

wp --path=/var/www/example.com/htdocs alynt-drime-backups run --max-uploads=1
wp --path=/var/www/example.com/htdocs alynt-drime-backups status --format=json
```

For WPvivid-only sites:

1. Create or confirm a completed local WPvivid backup.
2. Run one scan.
3. If the file was just created, run a second scan after the minimum file age and unchanged-size requirement can pass.
4. Run upload.
5. Confirm the uploaded registry and Drime destination.

Acceptance:

- The first backup package is created or detected.
- The package is queued only after it is stable.
- Upload succeeds.
- Queue count returns to `0`.
- Failed count remains `0`.
- Active upload is `false`.
- Uploaded registry count increases.
- Drime contains the expected backup artifacts.

## Phase 5: Add Server Cron

Add cron only after the manual runner health check and first manual package/upload pass succeed.

Use the generated **Review And Install Cron** commands from the plugin settings screen. The cron snippet should include:

- one daily server-runner package creation line;
- one frequent WP-CLI scheduled scan/upload hook line;
- one optional status log line.

Typical shape:

```cron
# Alynt Drime Backups: create one server-side package daily.
17 2 * * * php '/var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php' run --config='/var/www/example.com/private/alynt-drime-backups/runner/config.json'
# Alynt Drime Backups: scan/upload completed packages every 15 minutes.
*/15 * * * * wp --path='/var/www/example.com/htdocs' cron event run alynt_drime_backups_scan_event alynt_drime_backups_upload_event
# Alynt Drime Backups: optional status log check.
7 3 * * * wp --path='/var/www/example.com/htdocs' alynt-drime-backups status --format=json
```

Recommended install flow:

1. Copy the generated **Review And Install Cron** commands.
2. Run them as the intended site user.
3. Review the diff between the current crontab and proposed crontab.
4. Confirm the schedule, paths, and site user are correct.
5. Run the commented final `crontab` install command only after approval.

Acceptance:

- Cron is installed for the intended site user.
- Cron paths match the current site.
- The proposed crontab diff was reviewed before install.
- The plugin status eventually reports WP-CLI scan evidence when scheduled scans run.
- The admin health panel no longer warns that server cron is expected but unobserved.

## Phase 6: Observe The First Automated Cycle

After cron is installed, wait for the next scheduled scan/upload window or temporarily trigger a safe manual equivalent.

Check:

```bash
wp --path=/var/www/example.com/htdocs alynt-drime-backups status --format=json
```

Confirm:

- Last WP-CLI scan is recorded.
- Cron health is healthy or expected.
- Queue count is stable.
- Failed count is `0`.
- No stale active upload remains.
- Drime receives the expected files.

If the package creation cron runs daily, the first full automated create/upload proof may require waiting until the next scheduled package time.

## Phase 7: Prove Restore Staging

Run this before treating a server-runner site as fully onboarded. For WPvivid-only sites, record a separate WPvivid restore proof instead; the server runner does not stage WPvivid ZIP packages.

1. Identify the first uploaded server-runner package ID.
2. Confirm the archive, `.manifest.json`, and `.sha256` sidecar are present in Drime.
3. Fetch the package and sidecars from Drime, or manually download the archive plus manifest/checksum sidecars to a safe server path. Download the `.remote-index.json` and `.remote-catalog.json` sidecars too for current server-runner packages.
4. Verify the package locally.
5. Inspect the package metadata.
6. Stage the package into the restore directory.
7. Confirm `RESTORE_NOTES.txt` says no database import and no live file overwrite occurred.

Example commands:

```bash
export ALYNT_DRIME_TOKEN='drime-bearer-token'

php /var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php fetch \
  --config=/var/www/example.com/private/alynt-drime-backups/runner/config.json \
  --package-id=example-com-YYYYmmdd-HHMMSS \
  --workspace-id=0 \
  --folder-hash=DRIME_FOLDER_HASH \
  --download-path=/var/www/example.com/private/alynt-drime-backups/downloads

php /var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php inspect \
  --config=/var/www/example.com/private/alynt-drime-backups/runner/config.json \
  --package=/var/www/example.com/private/alynt-drime-backups/downloads/example-com-YYYYmmdd-HHMMSS.tar.gz

php /var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php stage-restore \
  --config=/var/www/example.com/private/alynt-drime-backups/runner/config.json \
  --package=/var/www/example.com/private/alynt-drime-backups/downloads/example-com-YYYYmmdd-HHMMSS.tar.gz
```

Acceptance:

- Fetch or manual download includes the archive plus required manifest/checksum sidecars, and the `.remote-index.json` plus `.remote-catalog.json` sidecars for current server-runner packages.
- Verification passes.
- Inspect output matches the expected site URL and package metadata.
- Stage restore creates a new restore directory outside the public web root.
- No live WordPress files are changed.
- No database import occurs.

See `docs/RESTORE_RUNBOOK.md` for the detailed restore procedure.

## Phase 8: Post-Onboarding Settings

After the first create, upload, and restore staging proof:

- Decide whether to keep both WPvivid and server-runner producers enabled.
- Decide whether local deletion is appropriate. Keep it disabled if local packages are part of the operational retention plan.
- Decide whether manual remote retention should be enabled.
- Decide whether failed-upload email notifications should be enabled.
- Record the site in any external operational tracker.
- Record the Drime destination and restore discovery notes.

Do not enable local deletion or remote retention merely because upload succeeded. Enable them only after restore confidence and retention policy are clear.

## Ongoing Checks

Suggested regular checks:

- Admin status panel shows healthy cron state.
- WP-CLI status payload shows expected queue/upload/failure counts.
- Drime server destination contains recent package-ID child folders, and each package folder contains the archive plus matching sidecars.
- Restore staging rehearsal has been repeated after meaningful plugin or server-runner changes.
- Diagnostics do not contain secrets.
- Server disk usage stays within the site's retention expectations.

Useful status command:

```bash
wp --path=/var/www/example.com/htdocs alynt-drime-backups status --format=json
```

## Troubleshooting Quick Map

| Symptom | First Checks |
| --- | --- |
| Server outbox not readable | Confirm `server_outbox_path`, ownership, permissions, and public-root separation. |
| Runner health fails | Check path existence, write permissions, free space, same-device work/outbox paths, `tar`, WP-CLI, and WordPress path. |
| Package is not queued | Confirm file age, unchanged size across scans, supported extension, readable sidecars, and scan timing. |
| Upload fails | Check Drime token, workspace, destination preview, network errors, retry state, and failed-upload table. |
| Server cron warning remains | Confirm cron is installed for the site user and that the WP-CLI `run` command has executed. |
| Restore fetch cannot find sidecars | Confirm the Drime artifacts share the same package basename and are inside the package folder named after the package ID. Manifest/checksum are required for verification; `.remote-index.json` and `.remote-catalog.json` are expected for current server-runner packages. |
| Staging restore refuses extraction | Inspect archive member paths; unsafe paths or link entries should stop the restore. |

## Rollout Acceptance Checklist

A site is onboarded only when all applicable items are true:

- Plugin is active at the expected version.
- Drime connection test passes.
- Drime workspace/base folder/relative paths are confirmed.
- Producer choice is intentional and duplicate automation is avoided.
- Server outbox path is configured when using the generic outbox.
- Server runner health passes when using the server runner.
- First backup package is created or detected.
- First upload succeeds.
- Queue returns to empty.
- Failed upload registry remains empty.
- WP-CLI status payload is healthy.
- Cron is installed and WP-CLI scan evidence is observed when server cron is expected.
- For server-runner sites, restore fetch or manual download has been proven for the first package.
- For server-runner sites, package verification passes.
- For server-runner sites, restore staging succeeds outside the public web root.
- For WPvivid-only sites, a separate WPvivid restore proof is recorded.
- Operator notes record package ID, Drime location, and restore staging path.

## Related Docs

- `server-runner/README.md`
- `docs/RESTORE_RUNBOOK.md`
- `docs/REMOTE_RESTORE_DISCOVERY.md`
- `docs/PACKAGE_SECURITY.md`
- `docs/CONSISTENCY_MODEL.md`
- `docs/STATUS_PAYLOAD.md`
- `docs/PRODUCER_ADAPTERS.md`
