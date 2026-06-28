# Multiple Standalone Site Runner Guidance

This guide explains how to run Alynt Drime Backups Uploader across multiple separate WordPress sites on the same GridPane or VPS server.

This is not WordPress Multisite guidance. It applies to standalone sites such as:

```text
/var/www/site-a.com
/var/www/site-b.com
/var/www/site-c.com
```

Each standalone site should have its own plugin settings, runner config, backup paths, cron entries, Drime destination, and restore proof.

## Core Rule

Do not share one runner config, one outbox, one work directory, or one restore staging directory across multiple standalone sites.

The safe pattern is:

```text
one WordPress site
one plugin configuration
one runner config
one outbox
one work directory
one restore staging directory
one set of cron entries
one Drime site folder
```

Keeping each site isolated makes scanning, upload history, cleanup, restore rehearsal, and incident review much easier to trust.

## Recommended Layout

For `site-a.com`:

```text
/var/www/site-a.com/htdocs
/var/www/site-a.com/private/alynt-drime-backups/runner/config.json
/var/www/site-a.com/private/alynt-drime-backups/runner/alynt-backup-runner.php
/var/www/site-a.com/private/alynt-drime-backups/work
/var/www/site-a.com/private/alynt-drime-backups/outbox
/var/www/site-a.com/restores/alynt-drime-backups
```

For `site-b.com`:

```text
/var/www/site-b.com/htdocs
/var/www/site-b.com/private/alynt-drime-backups/runner/config.json
/var/www/site-b.com/private/alynt-drime-backups/runner/alynt-backup-runner.php
/var/www/site-b.com/private/alynt-drime-backups/work
/var/www/site-b.com/private/alynt-drime-backups/outbox
/var/www/site-b.com/restores/alynt-drime-backups
```

The plugin setting **Server Outbox Path** for each site should point only to that site's own outbox.

## Drime Destination Pattern

Use a separate Drime relative path or folder for each standalone site.

Recommended:

```text
Backups/site-a.com
Backups/site-b.com
Backups/site-c.com
```

Avoid sending multiple sites into the same Drime folder unless there is a deliberate naming and restore-discovery policy. Separate folders make restore selection and cleanup safer.

If workspace guardrails are enabled with `ALYNT_DRIME_ALLOWED_WORKSPACE_IDS`, keep the same approved workspace policy across the related sites unless one site intentionally belongs in a different Drime workspace.

## Cron Pattern

Each standalone site should have its own cron lines with that site's exact paths.

Example for `site-a.com`:

```cron
# Alynt Drime Backups: create one server-side package daily for site-a.com.
17 2 * * * php '/var/www/site-a.com/private/alynt-drime-backups/runner/alynt-backup-runner.php' run --config='/var/www/site-a.com/private/alynt-drime-backups/runner/config.json'
# Alynt Drime Backups: scan/upload completed packages for site-a.com.
*/15 * * * * wp --path='/var/www/site-a.com/htdocs' cron event run alynt_drime_backups_scan_event alynt_drime_backups_upload_event
```

Example for `site-b.com`:

```cron
# Alynt Drime Backups: create one server-side package daily for site-b.com.
47 2 * * * php '/var/www/site-b.com/private/alynt-drime-backups/runner/alynt-backup-runner.php' run --config='/var/www/site-b.com/private/alynt-drime-backups/runner/config.json'
# Alynt Drime Backups: scan/upload completed packages for site-b.com.
7,22,37,52 * * * * wp --path='/var/www/site-b.com/htdocs' cron event run alynt_drime_backups_scan_event alynt_drime_backups_upload_event
```

Stagger package creation times so multiple sites do not start database exports and file archives at the same minute. The runner has a per-site lock, but it does not coordinate load across other standalone sites on the server.

Use the generated **Review And Install Cron** commands from each site's plugin settings screen. Review the crontab diff before installing the proposed cron lines.

## Onboarding Checklist

For each standalone site:

1. Install or update the plugin.
2. Configure Drime token, workspace, base folder, and relative path for that site.
3. Configure that site's **Server Outbox Path**.
4. Run that site's generated install/update runner command from the settings screen.
5. Confirm runner health passes for that site.
6. Create and verify one manual package for that site.
7. Scan and upload the package through that site's scheduled WP-CLI hook command.
8. Confirm the archive and sidecars land in the intended Drime folder.
9. Fetch, verify, and stage a restore proof for that package.
10. Add that site's cron only after the manual package, upload, and restore proof pass.

Do not copy a working `config.json` from one site to another unless every path, URL, package prefix, and restore path is reviewed and changed.

## Naming Guidance

Use package prefixes that identify the source site clearly.

Recommended examples:

```text
site-a-com
shop-example-com
members-example-com
```

Avoid generic prefixes such as:

```text
wordpress
backup
site
production
```

Clear prefixes make Drime searches, local inventory output, cleanup-preview reports, and restore evidence easier to audit.

## Monitoring Multiple Sites

Check each site independently:

```bash
wp --path=/var/www/site-a.com/htdocs alynt-drime-backups status --format=json
php /var/www/site-a.com/private/alynt-drime-backups/runner/alynt-backup-runner.php list --config=/var/www/site-a.com/private/alynt-drime-backups/runner/config.json --format=json

wp --path=/var/www/site-b.com/htdocs alynt-drime-backups status --format=json
php /var/www/site-b.com/private/alynt-drime-backups/runner/alynt-backup-runner.php list --config=/var/www/site-b.com/private/alynt-drime-backups/runner/config.json --format=json
```

Review each site's:

- last WP-CLI scan time
- queue count
- failed upload count
- active upload state
- last package time
- local inventory package IDs
- `verification_ready` flags
- `consistency_status`
- cleanup-preview candidates
- free disk space

## Cleanup Guidance

Run cleanup preview per standalone site:

```bash
php /var/www/site-a.com/private/alynt-drime-backups/runner/alynt-backup-runner.php cleanup-preview --config=/var/www/site-a.com/private/alynt-drime-backups/runner/config.json --older-than-days=14 --format=json
```

After that site's upload and restore proof has been reviewed, run cleanup per standalone site only when approved:

```bash
php /var/www/site-a.com/private/alynt-drime-backups/runner/alynt-backup-runner.php cleanup --config=/var/www/site-a.com/private/alynt-drime-backups/runner/config.json --older-than-days=14 --confirm=delete-local-artifacts --format=json
```

Do not run broad server-level deletion commands across every site's backup folders unless each site's upload and restore proof has been reviewed. Cleanup should remain operator-approved and site-by-site.

## Common Mistakes To Avoid

- One shared outbox for several standalone sites.
- One shared `config.json` reused without changing every site-specific path.
- Cron lines pasted from one site into another site's crontab without updating `--path` and `--config`.
- Multiple large sites all starting package creation at the same minute.
- Multiple sites writing into the same Drime folder without a clear restore-discovery policy.
- Cleaning local packages for every site before each site has a verified Drime upload and restore rehearsal.

## Acceptance Criteria

A multiple-standalone-site setup is ready only when each site has:

- its own runner config
- its own outbox, work, and restore paths
- its own Drime relative path or folder
- its own cron entries
- one successful manual package run
- one successful upload to the intended Drime destination
- one successful fetch, verify, and restore staging proof
- clear status output with no unexpected queue, failed upload, or active upload state
