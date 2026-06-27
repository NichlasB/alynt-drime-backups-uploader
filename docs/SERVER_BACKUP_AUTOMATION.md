# Server Backup Automation Guide

This guide describes the server-side automation model for Alynt Drime Backups Uploader.

Use it with the site rollout runbook when a WordPress site should produce backup packages on the server and let the plugin upload completed packages to Drime.

## Automation Model

The flow has two separate jobs:

1. A server runner creates a local backup package.
2. WordPress scans the generic outbox and uploads completed packages to Drime.

Keeping these jobs separate makes failures easier to understand. Package creation can fail without touching Drime, and Drime upload can fail without rebuilding the package.

Typical cron shape:

```cron
# Create one server-side package during a quiet window.
15 2 * * * php /var/www/example.com/private/alynt-drime-backups/runner/alynt-backup-runner.php run --config=/var/www/example.com/private/alynt-drime-backups/runner/config.json

# Scan and upload completed packages regularly.
*/15 * * * * wp --path=/var/www/example.com/htdocs alynt-drime-backups run --max-uploads=1

# Optional lightweight status capture for server logs.
5 * * * * wp --path=/var/www/example.com/htdocs alynt-drime-backups status --format=json
```

The plugin settings screen can generate the site-specific config, install commands, health command, cron snippet, and server-cron review commands. The review commands save the current user crontab, append the generated snippet to a proposed crontab file, and show a diff before install. Cron installation is still an operator step; the final `crontab` install command is commented until an operator approves it.

## Adding Another Site

Each site should have its own runner config, work directory, outbox directory, restore directory, and cron entries.

Recommended site-local layout:

```text
/var/www/example.com/private/alynt-drime-backups/runner
/var/www/example.com/private/alynt-drime-backups/work
/var/www/example.com/private/alynt-drime-backups/outbox
/var/www/example.com/restores/alynt-drime-backups
```

Use `docs/SITE_ROLLOUT_RUNBOOK.md` for the full onboarding checklist. For several separate WordPress sites on the same server, use `docs/MULTIPLE_STANDALONE_SITE_RUNNER_GUIDANCE.md`. Do not share one outbox across multiple standalone sites unless a future inventory format is built to make that safe and easy to audit.

## Scheduling Guidance

Run package creation during the quietest window for the site.

For most small sites, one daily package creation job plus a 15-minute scan/upload job is enough. For larger sites, increase the upload scan interval only after confirming package creation time, package size, server load, and Drime upload duration.

Avoid overlapping package creation jobs. The runner has a lock, but cron should still be scheduled so normal runs finish before the next package creation attempt.

Use the generated **Server Cron Review Commands** during setup instead of pasting directly into an active crontab. Run them as the intended site user, inspect the diff, then uncomment and run the final install command only after the proposed schedule and paths are correct.

## Disk And Retention Policy

The local outbox is intentionally not deleted by uninstall and is not automatically cleaned by the plugin. Those files may be the only local backup copy until the Drime upload and restore rehearsal have been proven.

Recommended lifecycle:

1. Create the package and sidecars.
2. Upload the archive, manifest, and checksum to Drime.
3. Fetch the same package back from Drime.
4. Verify the package and sidecars.
5. Stage the restore into a separate restore directory.
6. Record the restore rehearsal result.
7. Run `cleanup-preview` to see old local outbox and restore staging candidates without deleting anything.
8. Only then consider removing local outbox, download, or restore staging artifacts.

For production sites, keep at least one recently verified local package when disk space allows. If disk space is tight, cleanup should be an operator-approved server process, not an automatic plugin side effect.

Preview candidates with:

```bash
php /path/to/alynt-backup-runner.php cleanup-preview --config=/path/to/config.json --older-than-days=14 --format=json
```

The command reports candidate archives and restore staging directories, includes sidecar readiness fields for outbox packages, and sets `destructive_actions_performed` to `false`. It does not delete local files.

## High-Write Sites

The current runner produces logical WordPress backups. It is not a filesystem snapshot system.

For sites with heavy writes, ecommerce activity, membership actions, LMS activity, or frequent media uploads, use a quiet window or maintenance window for package creation.

The current stricter mode is light consistency mode. It is configured with:

```json
"consistency_mode": "light"
```

Light mode does not pause writes and does not enable WordPress maintenance mode. It records package evidence so operators can review risk:

- database export start/finish time
- file archive start/finish time
- archive exit code
- archive warning counts
- live file-change warning counts
- `consistency_status`, such as `clean`, `file_changes_detected`, or `warnings_detected`

This is useful because a package can still be created successfully while the runner records that files changed during archive creation. Treat `file_changes_detected` as a reason to inspect more carefully, rerun during a quieter window, or move to a stricter operational process for that site.

If light mode is not enough, future stricter modes may still be considered, such as:

- A maintenance-window runbook.
- A host-level filesystem snapshot producer.
- Temporary WordPress maintenance mode during package creation.
- Database replica or snapshot-based package creation.

Do not present server-runner packages as transactional snapshots.

## Monitoring

Useful checks:

```bash
php /path/to/alynt-backup-runner.php health --config=/path/to/config.json
php /path/to/alynt-backup-runner.php list --config=/path/to/config.json --format=json
wp --path=/var/www/example.com/htdocs alynt-drime-backups status --format=json
wp --path=/var/www/example.com/htdocs alynt-drime-backups run --max-uploads=1
```

Review:

- Last server-runner package time.
- Local inventory package count and `verification_ready` flags.
- Local inventory `consistency_status` for recent server-runner packages.
- Cleanup-preview candidate count before operator-approved disk cleanup.
- Last WP-CLI scan time.
- Queue count.
- Failed upload count.
- Active upload state.
- Server outbox readable state.
- Free disk space around `work_path`, `outbox_path`, and `restore_path`.

## Current Boundaries

The current implementation does not yet include:

- Automatic cron installation.
- Automatic local outbox cleanup. A read-only cleanup preview exists, but deletion remains manual.
- A remote package inventory/index. The runner has a local read-only JSON inventory, but it does not upload or maintain a remote index.
- Alternative archive formats beyond runner-created `.tar.gz` packages.
- Automatic maintenance mode or write-pausing for high-write sites.
- Automatic destructive restore.

Those are backlog candidates. Add them only when the operational policy is clear and the staging evidence supports the extra automation.
