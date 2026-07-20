# Production-Capable Restore Automation Plan

Updated: 2026-07-20

## At A Glance

| Item | Decision |
|---|---|
| Status | Phase 0 through Phase 2 implemented; the first read-only rehearsal safely refused on `alyntdrime.sitesmain.com` disk capacity; `hbf-staging` is approved as the replacement production-simulation candidate |
| Goal | Operator-supervised files-and-database restoration from a verified Alynt server-runner package |
| Existing baseline | Staging-only `restore-apply` version 1 is implemented and rehearsed |
| Current test target | Approved: `staging.handcraftedbotanicalformulas.com` as a production-simulation target |
| Historical test target | `alyntdrime.sitesmain.com`; retained as Phase 1/2 evidence, but no longer the preferred target because of disk capacity |
| Actual production target | None selected or approved |
| First implementation surface | Read-only production preflight and reporting |
| Required recovery protection | Verified Alynt pre-restore files/database evidence plus an independently verified host-native restore point when available |
| Confirmation model | Separate production command plus exact action and target-domain confirmations |
| Automation level | Supervised only; no cron, wp-admin button, or unattended restore |
| Next implementation step | Enroll `hbf-staging`, create fresh native backup evidence, and rerun the Phase 2 preflight before Phase 3 |

## Phase 0 Approval Record

Approved by the user on 2026-07-14:

- Use `alyntdrime.sitesmain.com` as the production-simulation target.
- Keep version 1 scope as files plus database.
- Temporary downtime is acceptable during simulation rehearsals.
- Keep production restoration operator-supervised.
- Require verified GridPane/host rollback evidence in addition to Alynt pre-restore evidence when supported.

This approval authorized the plan and Phase 1 read-only investigation only. It did not authorize backup creation, runtime updates, maintenance activation, configuration changes, restore apply, rollback, or cleanup.

Target change approved by the user on 2026-07-20:

- Replace `alyntdrime.sitesmain.com` with `staging.handcraftedbotanicalformulas.com` as the current production-simulation candidate.
- Keep the completed `alyntdrime.sitesmain.com` investigation and safe disk-refusal rehearsal as historical evidence.
- Refresh `hbf-staging` read-only before enrollment because several days had passed.
- Prepare the exact enrollment checklist before making server configuration, runner, package, cron, or cleanup changes.

This target-change approval covers the read-only refresh and plan/checklist update. It does not by itself authorize server runner installation, plugin setting changes, package creation, restore staging, cron changes, restore apply, rollback, or cleanup.

## Purpose

This plan defines a separate high-risk extension for restoring Alynt server-runner packages onto a production WordPress target. It builds on the completed staging-only restore flow without weakening its staging gate or reusing a staging confirmation phrase for production.

The project should make an agent-assisted production recovery predictable:

1. Locate the requested package in Drime.
2. Fetch, verify, inspect, and stage it.
3. Prove the production target identity and recovery prerequisites.
4. Create and verify rollback evidence.
5. Pause writes through an approved site-specific method.
6. Stop for explicit operator confirmation.
7. Apply files and database.
8. Run runtime checks.
9. Keep the site in a controlled state if checks fail and offer the tested rollback path.
10. Leave complete, redacted evidence of every attempted action.

This plan does not authorize any real production restore. Every implementation and runtime phase retains a separate approval gate.

## Relationship To Staging Restore Version 1

The existing staging flow already provides:

- package fetch, checksum verification, archive inspection, and restore staging;
- staged-path containment and safe archive-member checks;
- files-only, database-only, and combined dry runs;
- optional pre-restore database/file backup creation;
- explicit `--confirm=restore-staging-site` apply gating;
- staging file replacement and WP-CLI database import;
- machine-readable dry-run and apply reports;
- symlink/drop-in review reporting;
- successful repeated rehearsals on `alyntdrime.sitesmain.com`.

Production capability must be additive. The staging command must continue refusing production-like configurations.

## Approved Target Classification

`staging.handcraftedbotanicalformulas.com` remains a staging site in the site registry and in all operator-facing reports. It is the current **production-simulation target** because it has a GridPane layout, isolated site paths, a much larger storage margin, and successful host-native backup capability. `alyntdrime.sitesmain.com` remains the historical Phase 1/2 target and must not be silently substituted into new commands.

Production simulation should run the same core backup, apply, verification, and rollback implementation intended for production, but under an explicit `production-simulation` environment. It must not be relabeled as actual production and cannot prove behavior under customer traffic, commerce activity, external webhooks, or other real writes.

Before the first simulation write, confirm:

- site key: `hbf-staging`;
- environment: production simulation on a registered staging site;
- SSH alias: `drm-1`;
- site user: `handcraftedbotanicalformulas-com`;
- site root: `/var/www/staging.handcraftedbotanicalformulas.com`;
- WordPress path: `/var/www/staging.handcraftedbotanicalformulas.com/htdocs`;
- deployment/access method: SSH and `scp`;
- files plus database scope;
- temporary downtime is acceptable;
- data sensitivity and staging-refresh lineage have been reviewed; treat copied customer, order, form, and account data as production-sensitive when present.

## HBF Staging Read-Only Readiness Snapshot

Refreshed on 2026-07-20 through SSH alias `drm-1`; no site or server state was changed.

- Host: `cluster-drmorse-us-east1-v1`.
- Root filesystem: `362,633,863,168` bytes total and `170,878,173,184` bytes available (`51%` used).
- WordPress files: `1,686,371,782` bytes.
- WordPress database: `1,599,438,848` bytes.
- Conservative files-and-database preflight budget with the default `3 GB` margin: approximately `13.08 GB`.
- Current storage headroom above that calculated budget: approximately `157.8 GB`.
- Alynt Drime Backups Uploader `0.4.0` is active.
- Legacy Alynt Drime WPvivid Uploader `0.6.0` is also active.
- The new plugin settings option exists, but `site_uuid`, `server_outbox_path`, and automatic scanning are not initialized/configured.
- No standalone runner, private runner config, outbox, or production restore directory exists yet.
- WordPress `home` and `siteurl` both equal `https://staging.handcraftedbotanicalformulas.com`.
- Active theme: `blocksy-child`.
- Drop-in inventory: `wp-content/object-cache.php`.
- Symlink inventory: `wp-content/mu-plugins/wp-fail2ban.php` points to the site-local `wp-fail2ban` plugin file.
- GridPane local and remote scheduled backups are off.
- Manual GridPane revision `30` completed successfully on 2026-07-15, but it is outside the 24-hour production-preflight evidence window and must be refreshed before the rehearsal.
- This VPS also hosts production workloads. Package creation, extraction, and later simulation writes must use a quiet period and retain site-specific path, ownership, and confirmation gates.

## HBF Staging Enrollment Checklist

Each write group requires explicit approval. Cron installation and cleanup remain separate from the one-time rehearsal.

### Gate A: Initialize And Record Plugin Identity

Status: completed on 2026-07-20.

1. Reconfirm site key `hbf-staging`, SSH alias `drm-1`, and the exact site paths.
2. Run the plugin status command once to generate and persist the non-secret `site_uuid`.
3. Record the UUID and immediately reread it from the WordPress option.
4. Confirm plugin `0.4.0` remains active and no upload or queue operation was triggered.

Result:

- Generated and reread site UUID `5f0c5974-edba-46c6-b0d3-8396feddb07a`.
- Plugin `0.4.0` remained active.
- Queue, uploaded, failed, and active-upload counts remained zero.
- Automatic scanning and server-cron expectation remained disabled.

### Gate B: Resolve Legacy Uploader And Source Scope

Status: completed for the server-runner rehearsal boundary on 2026-07-20.

1. Keep the new plugin's WPvivid source unconfigured during the production-simulation server-runner rehearsal.
2. The legacy uploader may remain active temporarily only while the new plugin is not scanning the same WPvivid backup directory.
3. Before enabling WPvivid in the new plugin, migrate/reconfigure its Drime destination, deactivate the legacy uploader, and verify that only one uploader owns the WPvivid source.
4. Configure the server outbox path and server-specific Drime relative path only when the server-runner upload proof is in scope.

Result:

- Legacy uploader `0.6.0` remains active.
- The new plugin has no WPvivid override, server outbox setting, automatic scanning, or server-cron expectation configured.
- No duplicate WPvivid source ownership was enabled.
- Migration/deactivation remains required before the new plugin is allowed to upload WPvivid backups.

### Gate C: Install The Private Runner

Status: completed on 2026-07-20.

1. Install runner `0.2.0` under `/var/www/staging.handcraftedbotanicalformulas.com/private/alynt-drime-backups/runner`.
2. Create site-private outbox, work, reports, evidence, and restore staging directories with the site user as owner.
3. Generate a non-secret runner config containing the recorded site UUID and exact `hbf-staging` paths.
4. Keep `production_restore_enabled` set to `false`.
5. Set `production_restore_environment` only to `production-simulation`.
6. Enroll the exact active plugin list, `blocksy-child`, `wp-content/object-cache.php`, and the reviewed `wp-fail2ban.php` symlink.
7. Run runner health and stop on any failed path, permission, WP-CLI, archive, or free-space check.
8. Do not install cron during this gate.

Result:

- Installed standalone runner `0.2.0` and its private `config.json`.
- Runner and private paths are owned by `handcraftedbotanicalformulas-com`; runner mode is `750` and config mode is `640`.
- Runner health passed PHP CLI, archive format, WordPress, outbox, work, restore, free-space, same-device, `tar`, and WP-CLI checks.
- Enrolled 61 active plugins, `blocksy-child`, `wp-content/object-cache.php`, and `wp-content/mu-plugins/wp-fail2ban.php`.
- Production restore remains disabled and the environment remains `production-simulation`.
- Cron, external-writer, and cache-purge review flags remain false for their later gate.
- Outbox, work, and restore directories are empty; no package, restore staging directory, or native-backup evidence file was created.
- Site-user crontab SHA-256 remained `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` before and after installation, confirming that no cron entry was installed.

### Gate D: Create Fresh Independent Recovery Evidence

Status: completed on 2026-07-20.

1. Create a fresh manual GridPane local backup for `staging.handcraftedbotanicalformulas.com`.
2. Verify the GridPane log records a completed revision and `Manual Local Backup Success`.
3. Record the revision, completion time, target hostname, and target UUID in the private native-backup evidence JSON.
4. Expose only the revision SHA-256 fingerprint in preflight/report output.
5. Stop if the evidence is missing, unsuccessful, mismatched, or older than 24 hours.

Result:

- GridPane manual local backup `pre-restore-preflight` completed successfully as revision `31` at `2026-07-20T20:26:33Z`.
- The private evidence file records the matching hostname and site UUID `5f0c5974-edba-46c6-b0d3-8396feddb07a` with mode `640` and the site user as owner.
- The revision identifier is represented in operator output by SHA-256 `eb1e33e8a81b697b75855af6bfcdbcbf7cbbde9f94962ceaec1ed8af21f5a50f`.
- Evidence must still be rechecked for the 24-hour freshness limit immediately before preflight.

### Gate E: Create, Verify, And Stage One Package

Status: completed on 2026-07-20.

1. Schedule package creation during a quiet server period.
2. Create one files-and-database package with runner `0.2.0`.
3. Verify its checksum, site URL, site UUID, producer version, archive members, and sidecars.
4. Stage it only under `/var/www/staging.handcraftedbotanicalformulas.com/restores/alynt-drime-backups/<package-id>`.
5. Verify staging reports `database_imported: false` and `live_files_overwritten: false`.
6. Do not upload, install cron, apply a restore, or delete artifacts as part of this gate.

Result:

- Created package `staging-handcraftedbotanicalformulas-com-20260720-203243.tar.gz` with runner `0.2.0`.
- Archive size is `1,021,656,232` bytes and SHA-256 is `ecf4d404deea15dcf6a3fe2495bbf0090982e324b0315712e57221473fd25fde`.
- Manifest identity matches the enrolled hostname, site UUID, site URL, files-and-database backup type, `htdocs` file root, and `database.sql` dump.
- Archive creation exited `0` with no archive warnings or live-change warnings.
- Staged successfully under `/var/www/staging.handcraftedbotanicalformulas.com/restores/alynt-drime-backups/staging-handcraftedbotanicalformulas-com-20260720-203243`.
- Staged content is `2,311,564,190` bytes, including a `626,021,509` byte database export.
- `RESTORE_REPORT.json` records verified safe members, inspection-only extraction, no database import, no live-file overwrite, and manual restore required.
- WordPress remained out of maintenance mode, its URL and plugin state were unchanged, and the site-user crontab hash remained unchanged.
- Available filesystem space after staging is `166,352,764,928` bytes.
- Configuration hygiene observation: the package contains approximately `36 MB` of logs under `wp-content/wpvividbackups` because the current exclusion covers `wp-content/uploads/wpvividbackups`. Add the direct path before routine package automation; this does not invalidate the verified rehearsal package.

### Gate F: Run The Read-Only Production Preflight

Status: completed on 2026-07-20.

1. Refresh the active plugin/theme/drop-in/symlink inventory immediately before preflight.
2. Run `restore-production-preflight` for `files-and-database` with the exact `--target-site=staging.handcraftedbotanicalformulas.com`.
3. Require every identity, containment, package, disk, maintenance/write-control, report-path, and native-backup check to pass.
4. Write the redacted audit report only after the command output has been reviewed.
5. Confirm production apply, rollback, database import, file overwrite, maintenance changes, and destructive-action flags all remain `false`.
6. Stop before Phase 3 even when preflight passes.

Reviewed no-report result:

- Refreshed and enrolled the exact 61-plugin inventory, `blocksy-child`, `wp-content/object-cache.php`, and the reviewed `wp-fail2ban.php` symlink.
- Reviewed the absence of a staging site-user crontab and the lack of a dedicated GridPane cron line for the staging path. The similarly named GridPane cron targets the production HBF path and was not changed.
- Reviewed due staging jobs and external-writer candidates including Fluent Forms, Action Scheduler, marketing automation, WooCommerce, payment gateways, analytics, MainWP, WPvivid, and related queues.
- Reviewed Redis, Nginx Helper, `object-cache.php`, and the WordPress maintenance-mode strategy.
- Updated only the private enrollment config review/inventory fields and added `wp-content/wpvividbackups` to future package exclusions; production restore remained disabled and cron remained unchanged.
- `restore-production-preflight` passed all `64` checks with zero failures at `2026-07-20T20:42:06Z`.
- Disk calculation measured `166,351,970,304` available bytes against `13,078,657,053` required additional bytes, including the configured `3 GB` safety margin.
- GridPane revision `31` evidence was fresh and matched the enrolled hostname and site UUID.
- Production apply allowed/available, rollback available, destructive actions, database import, live-file overwrite, maintenance-state change, report requested, and report written all remained `false`.
- No audit report was written during this reviewed run.

Approved audit-report result:

- Reran the identical preflight with `--write-report=1` at `2026-07-20T20:43:54Z`.
- The rerun again passed all `64` checks with zero failures.
- Wrote the private report `/var/www/staging.handcraftedbotanicalformulas.com/private/alynt-drime-backups/production-restore-reports/RESTORE_PRODUCTION_PREFLIGHT-staging-handcraftedbotanicalformulas-com-20260720-203243-20260720-204354.json`.
- The report is owned by `handcraftedbotanicalformulas-com`, is contained by the mode-`750` private reports directory, and records no write error.
- Sensitive-key scan found zero token, secret, password, authorization, cookie, nonce, salt, private-key, or signed-URL fields.
- The report contains neither a raw `revision_id` key nor a raw `database_name` key; it records only their SHA-256 fingerprints.
- Production apply allowed/available, rollback available, destructive actions, database import, live-file overwrite, and maintenance-state change all remain `false`.
- WordPress URL, maintenance status, and site-user crontab remained unchanged after report writing.

### Separate Future Gates

- Drime upload/fetch proof, if required for this target.
- Cron review and installation.
- Local retention or artifact cleanup.
- Phase 3 rollback foundation code or deployment.
- Any production-simulation apply.

## Version 1 Goals

- Provide a read-only production preflight that cannot write or restore.
- Require exact target identity, package identity, and path containment checks.
- Require fresh, verified pre-restore files and database evidence.
- Require an independently verified GridPane/host restore point when a safe supported procedure is available.
- Define and test a maintenance/write-control procedure before apply.
- Provide a production-simulation apply path behind stronger confirmation than staging.
- Provide and repeatedly test a rollback command before any real production enrollment.
- Run site health checks and keep maintenance active when checks fail.
- Produce redacted plan, apply, verification, and rollback reports.
- Keep every action operator-supervised.

## Version 1 Non-Goals

- No wp-admin production restore UI.
- No scheduled, webhook-triggered, or unattended restore.
- No first destructive test on an actual production site.
- No automatic Drime or local evidence deletion.
- No automatic cross-domain search-replace.
- No arbitrary third-party archive restore.
- No assumption that a GridPane backup exists merely because GridPane commands are installed.
- No automatic rollback cascade until the explicit rollback command is independently proven.
- No production restore when package, target, disk, backup, maintenance, or health-check evidence is incomplete.

## Command Surface

The production surface should use distinct commands so a staging confirmation cannot unlock production behavior.

Implemented read-only preflight:

```bash
php alynt-backup-runner.php restore-production-preflight --config=/path/to/config.json --staged-path=/path/to/staged/package --scope=files-and-database --target-site=example.com --format=json
```

Optional redacted report writing:

```bash
php alynt-backup-runner.php restore-production-preflight --config=/path/to/config.json --staged-path=/path/to/staged/package --scope=files-and-database --target-site=example.com --write-report=1 --format=json
```

Future production-simulation or production apply proposal:

```bash
php alynt-backup-runner.php restore-production-apply --config=/path/to/config.json --staged-path=/path/to/staged/package --scope=files-and-database --create-pre-restore-backup=1 --target-site=example.com --confirm=restore-production-site --confirm-site=example.com --format=json
```

Future explicit rollback proposal:

```bash
php alynt-backup-runner.php restore-production-rollback --config=/path/to/config.json --apply-report=/path/to/RESTORE_PRODUCTION_APPLY_REPORT.json --target-site=example.com --confirm=rollback-production-site --confirm-site=example.com --format=json
```

Only `restore-production-preflight` is implemented. It always keeps production apply and rollback unavailable. The apply and rollback names and arguments remain proposals for later separately approved phases.

## Proposed Configuration

Production keys must be absent or disabled by default and stored only in the private runner configuration.

```json
{
  "production_restore_enabled": false,
  "production_restore_environment": "production-simulation",
  "production_target_site_url": "https://staging.handcraftedbotanicalformulas.com",
  "production_target_site_uuid": "SITE_UUID_HERE",
  "production_target_site_root": "/var/www/staging.handcraftedbotanicalformulas.com",
  "production_target_wordpress_path": "/var/www/staging.handcraftedbotanicalformulas.com/htdocs",
  "production_target_wp_config_path": "/var/www/staging.handcraftedbotanicalformulas.com/wp-config.php",
  "production_restore_path": "/var/www/staging.handcraftedbotanicalformulas.com/restores/alynt-drime-backups",
  "production_pre_backup_path": "/var/www/staging.handcraftedbotanicalformulas.com/private/alynt-drime-backups/production-pre-restore",
  "production_reports_path": "/var/www/staging.handcraftedbotanicalformulas.com/private/alynt-drime-backups/production-restore-reports",
  "production_native_backup_required": true,
  "production_native_backup_evidence_path": "/var/www/staging.handcraftedbotanicalformulas.com/private/alynt-drime-backups/native-backup-evidence.json",
  "production_native_backup_max_age_seconds": 86400,
  "production_disk_safety_margin_bytes": 3221225472,
  "production_maintenance_strategy": "wp-maintenance-mode",
  "production_cron_control_reviewed": false,
  "production_external_writers_reviewed": false,
  "production_cache_purge_reviewed": false,
  "production_expected_active_plugins": [
    "REFRESH_EXACT_ACTIVE_PLUGIN_INVENTORY_BEFORE_ENROLLMENT"
  ],
  "production_expected_active_theme": "blocksy-child",
  "production_expected_drop_ins": [
    "wp-content/object-cache.php"
  ],
  "production_symlink_inventory_reviewed": false,
  "production_expected_symlink_paths": [
    "wp-content/mu-plugins/wp-fail2ban.php"
  ],
  "production_healthcheck_urls": [
    "https://staging.handcraftedbotanicalformulas.com/"
  ]
}
```

Required config behavior:

- `production_restore_enabled` defaults to `false` and is never enabled by plugin activation or upgrade.
- `REFRESH_EXACT_ACTIVE_PLUGIN_INVENTORY_BEFORE_ENROLLMENT` is an explicit placeholder, not a valid enrolled inventory. Replace it with the complete current active-plugin list immediately before installing the private config.
- `production_restore_environment` accepts only explicitly supported values; version 1 implementation begins with `production-simulation` only.
- Production simulation and real production must have distinct enrollment records and reports.
- Target URL, path, site UUID, package site identity, and command `--target-site` must agree.
- Broad paths such as `/`, `/var`, `/var/www`, or a site root above `htdocs` are rejected.
- Report and pre-backup paths must be private, writable, and outside `htdocs`.
- Config and reports must never contain Drime tokens, WordPress salts, database passwords, SSH keys, cookies, or signed URLs.

## Target Fingerprint

Preflight should build and verify a target fingerprint before any write:

- configured site UUID;
- normalized production URL and host;
- normalized `home` and `siteurl` values;
- canonical WordPress path and site-root containment;
- canonical external `wp-config.php` path without exposing its contents;
- database name fingerprint or redacted identifier;
- table prefix;
- site filesystem owner/group;
- runner/config version;
- active PHP and WP-CLI versions;
- expected GridPane markers, plugins, drop-ins, and symlinks;
- current maintenance state;
- current disk-space snapshot.

Any unexpected target drift should stop the operation and require a new plan/report.

## Package And Source Identity Gates

- Package must already be fetched, checksum-verified, archive-inspected, and staged.
- `RESTORE_REPORT.json` must be valid and show no earlier destructive action.
- Manifest site identity must match the enrolled production target unless a separate cross-site migration workflow is explicitly approved.
- Package ID, creation time, producer, archive format, database dump, and file root must be displayed in the preflight report.
- Package age must be visible to the operator; the tool must not silently choose "latest" during production apply.
- Files and database scopes must be fully present before a combined apply starts.
- No automatic URL search-replace should occur when package and target URL differ.

## Independent Recovery Evidence

Before production apply, require two recovery layers where the host supports them:

1. Fresh Alynt pre-restore evidence:
   - database export;
   - target-file archive;
   - SHA-256 values;
   - target fingerprint;
   - package ID and intended scope;
   - readable artifact and free-space checks.
2. Independent host-native restore point:
   - exact GridPane-supported backup command or dashboard procedure;
   - evidence that the backup completed;
   - a recorded restore identifier or timestamp;
   - a separately documented recovery path.

GridPane documents manual backups containing files and database and recommends independent server, local-site, and remote backup layers. The implementation must still verify the actual server's backup generation and command behavior before relying on it:

- https://gridpane.com/kb/local-website-backups/
- https://gridpane.com/kb/recommended-backup-strategy/

If native backup capability is unavailable, production apply should refuse by default. Any future override would require a separate risk decision and confirmation; it should not be part of the first production-capable release.

## Maintenance And Write Control

Production restore must have a site-specific write-control plan. A WordPress maintenance page alone may not stop WP-Cron, CLI jobs, webhooks, queue workers, or external writes.

Read-only investigation must identify:

- acceptable downtime window;
- WordPress maintenance mechanism;
- WP-Cron state and server cron jobs;
- WooCommerce, forms, memberships, queues, imports, and webhook writers;
- Redis/object-cache behavior;
- Nginx/FastCGI cache behavior;
- whether PHP workers or background jobs require controlled handling;
- commands needed to purge caches after apply or rollback.

Apply must stop if write control cannot be confirmed. Maintenance should remain active until post-restore checks pass or rollback is complete.

## Apply Order

Proposed combined flow:

1. Re-run the complete read-only preflight immediately before writes.
2. Confirm no target fingerprint or staged-package drift occurred.
3. Create and verify Alynt pre-restore evidence.
4. Create and verify the independent host-native restore point.
5. Enable the approved maintenance/write-control state.
6. Re-check active jobs and disk space.
7. Stop and request the exact production confirmation.
8. Replace files using the approved production file strategy.
9. Restore required GridPane/plugin drop-ins and ownership behavior.
10. Import the database.
11. Purge object and page caches through approved commands.
12. Run database, WP-CLI, filesystem, and HTTP checks.
13. Disable maintenance only when required checks pass.
14. Write the final report and retain all rollback evidence.

The final file strategy must be chosen after GridPane investigation. Reusing staging's remove-and-copy behavior without evaluating atomic swap, ownership, interrupted-copy recovery, and rollback time is not sufficient for production.

## Rollback Design

Rollback must be implemented and rehearsed before production apply can be enabled.

Required rollback behavior:

- Consume one specific failed or accepted apply report; never select evidence by filename guess.
- Verify that pre-restore evidence matches the same target, apply attempt, and scope.
- Require `--confirm=rollback-production-site` and an exact target-domain confirmation.
- Restore files and database in a documented order.
- Preserve failed post-apply state when disk permits for later diagnosis.
- Reapply ownership, permissions, symlink/drop-in handling, and cache purges.
- Run the same target and runtime checks used after apply.
- Keep maintenance enabled when rollback verification fails.
- Write a separate immutable rollback report.

Version 1 should provide a ready, explicit rollback command when apply or health checks fail. Automatic rollback may be reconsidered only after repeated explicit rollback rehearsals prove it safer than stopping for operator review.

## Post-Restore Verification

Minimum automated or operator-recorded checks:

- target fingerprint still matches enrollment;
- `wp core version` and WP-CLI bootstrap;
- `wp db check`;
- expected `home` and `siteurl`;
- expected table prefix and non-empty core tables;
- plugins and active theme load;
- filesystem owner/group and writable-path checks;
- `wp-config.php` remains outside and untouched by `htdocs` replacement;
- known GridPane/plugin symlinks and drop-ins are valid;
- Redis/object-cache connectivity;
- Nginx/page-cache purge completed;
- HTTPS returns an expected status;
- configured critical URLs pass;
- no fresh fatal errors appear in the approved log window;
- site-specific functional smoke checks pass.

HTTP success alone is not sufficient.

## Reports And Audit Trail

Create separate immutable JSON reports for:

- production preflight;
- pre-restore evidence creation;
- native backup evidence;
- maintenance/write-control actions;
- production apply;
- post-restore verification;
- rollback, when used;
- cleanup eligibility after operator acceptance.

Reports should include timestamps, target fingerprint, package ID, scope, exact approved action, phase status, commands represented as redacted action names, failure step, artifact hashes, and manual-review items. They must exclude credentials and raw sensitive output.

Large pre-restore and failed-restore artifacts must not be deleted automatically. Cleanup becomes eligible only after the operator accepts the restored or rolled-back state and a separate cleanup preview identifies exact artifacts.

## Read-Only GridPane Investigation

Before implementation, investigate `alyntdrime.sitesmain.com` without creating backups or changing state:

- Confirm installed GridPane backup generation and restore commands and versions.
- Determine whether backups are enabled for this site.
- Identify how to verify a manual backup completed and obtain its restore identifier.
- Identify the supported rollback procedure and expected duration.
- Inspect filesystem ownership, ACLs, symlinks, and external `wp-config.php` behavior.
- Identify safe maintenance, cron, queue, Redis, and Nginx cache commands.
- Measure current files/database size and calculate worst-case disk requirements.
- Confirm whether the runner can operate as the site user for every required phase.
- Record commands that are not read-only and keep them behind later approval gates.

Do not run bare `gpbup` during discovery. Earlier investigation showed that invoking it without a safe target-specific form can trigger backup behavior.

## Phase 1 Read-Only GridPane Investigation Findings

Investigation completed on 2026-07-14 against registered staging target `alyntdrime` through SSH alias `sites-main`. No backup, restore, maintenance, configuration, deployment, deletion, or service-restart command was run.

Target identity and permissions:

- SSH lands as `root`; site user/group is `alyntdrime-sitesmain-com`.
- Site root and `htdocs` are owned by the site user/group with mode `755`.
- Private path is owned by the site user/group with mode `750`.
- `wp-config.php` remains outside `htdocs`, owned by the site user/group with mode `600`.
- WordPress `home` and `siteurl` both equal `https://alyntdrime.sitesmain.com`.
- Site UUID is `76fea58c-46ba-460f-a971-2be7159f2ba0`.
- WordPress version is `7.0.1`; database size is approximately `139 MB`.
- Baseline `wp db check`, homepage HTTPS, and login-page HTTPS checks passed.

Disk and artifact budget at investigation time:

- Root filesystem was `62%` used with approximately `14.7 GB` available.
- `htdocs` was approximately `2.44 GB`.
- Private site data was approximately `3.83 GB`.
- Alynt outbox was approximately `3.60 GB`.
- Existing Alynt pre-restore evidence was approximately `230 MB`.
- Restore staging directories were effectively empty.
- Current free space is enough for planning but must not be treated as a permanent apply guarantee. Production preflight must calculate package, extraction, fresh pre-backup, failed-state preservation, native-backup, and safety-margin requirements immediately before work.

GridPane backup and restore findings:

- `/var/www/alyntdrime.sitesmain.com/logs/backups.env` reports `Local-Backups:OFF` and `Remote-Backups:OFF`.
- An encrypted Duplicacy repository preference exists for the site, but it is not evidence of a fresh restorable revision.
- `gpbup2` dispatches to GridPane's local single-site backup implementation. The state-changing command shape is `gpbup2 alyntdrime.sitesmain.com -local-single-site [tag]`.
- The local manual backup path writes GridPane logs and invokes the backup engine; it requires a separate write approval and completion verification.
- `gprestore` is state-changing even before restore apply: its dispatcher prepares restore logs and may install/check dependencies. Never use it as a read-only discovery command.
- GridPane command wrappers source `/usr/local/bin/gp`, whose initialization can update logs, markers, ACLs, or server state. Inspect source/configuration for discovery; run GridPane commands only behind an explicit write gate.
- The independent host-native rollback requirement is currently unmet. Before any production-simulation apply, a separately approved phase must create a fresh native backup, verify its revision/identifier, and record the supported restore procedure.

Maintenance and write-control findings:

- WordPress maintenance mode was inactive and WP-CLI supports explicit maintenance-mode status/activate/deactivate commands.
- Plugin status reports WordPress cron disabled, but GridPane runs due WP-Cron events every five minutes through `/etc/cron.d/gp-cron-alyntdrime-sitesmain-com`.
- The site-user crontab separately creates an Alynt package daily, runs Alynt scan/upload every 15 minutes, and records status daily.
- Due events include Action Scheduler, marketing automation, analytics, WPvivid, forms/mail, MainWP, and other plugin jobs.
- Production write control must suspend both the root-owned GridPane cron path and the site-user Alynt crontab path, then account for queues, forms, webhooks, and any site-specific external writers. WordPress maintenance mode alone is insufficient.

Cache and runtime findings:

- Nginx, Redis, and PHP 8.2 FPM services are active.
- `redis-cache`, `nginx-helper`, and `wp-content/object-cache.php` are present; no current `htdocs` symlinks were found during this snapshot.
- GridPane source uses site-user `wp cache flush` in maintenance workflows, and WP-CLI exposes maintenance-mode and object-cache commands.
- No target-specific FastCGI/proxy cache include was confirmed in the site configuration during this pass. Preflight should detect the active cache mode rather than assume it.
- Default CLI PHP is `8.2.32`, the GridPane WP-Cron line explicitly uses PHP `8.4.23`, and `/usr/local/bin/wp` uses environment PHP. Production commands must resolve and report the intended PHP/WP-CLI runtime instead of relying on an ambiguous default.

Runtime parity blockers:

- Installed Alynt Drime Backups Uploader reports version `0.3.1`, while the accepted source baseline is `0.4.0`.
- Installed server runner reports version `0.1.0` and its config lacks current restore-specific keys.
- The old Alynt Drime WPvivid Uploader remains active, and the current plugin reports the duplicate-uploader warning.
- Phase 2 may implement and test read-only preflight in source, but server deployment or production-simulation execution must first pass a separately approved runtime update/parity step.

Read-only investigation caveat:

- One attempted read-only `wp eval` cache-status probe failed at PHP parsing because of command quoting. It did not change WordPress content, configuration, plugins, files, or database data, but it may have appended an error entry to the existing debug log. Subsequent cache discovery used source/configuration inspection instead.

## Failure-Injection Matrix

Production simulation must prove safe behavior for:

- missing or wrong confirmation phrase;
- wrong target domain, UUID, path, or environment;
- package/target identity mismatch;
- altered staged package after preflight;
- missing or invalid pre-restore evidence;
- unavailable native backup evidence;
- insufficient disk space;
- maintenance/write-control failure;
- interrupted file replacement;
- failed database import;
- broken ownership or drop-ins;
- failed cache purge;
- failed WP-CLI or HTTP health check;
- explicit rollback after a successful restore;
- rollback after a simulated failed restore;
- rollback failure reporting.

Every failure before apply must prove that files and database were untouched. Failures after apply begins must retain enough evidence to choose and execute rollback safely.

## Implementation Phases And Approval Gates

### Phase 0: Plan Approval

- Approve `alyntdrime.sitesmain.com` as the production-simulation target.
- Confirm files plus database scope.
- Confirm temporary downtime is acceptable during rehearsals.
- Confirm production restore remains operator-supervised.
- Confirm no real production target is authorized.

### Phase 1: Read-Only GridPane Investigation

- Run only target, backup-capability, permissions, maintenance, cache, job, and disk discovery.
- Update this plan with verified commands and unresolved constraints.
- Stop before any backup creation or server write.

### Phase 2: Read-Only Production Preflight

- Status: implemented in source and rehearsed read-only on `alyntdrime.sitesmain.com` on 2026-07-15; no production-simulation apply was performed.
- Implemented target fingerprinting, package identity checks, conservative disk calculation, maintenance readiness, native backup readiness, and JSON output with optional redacted report writing.
- Production apply and rollback commands remain unavailable, and preflight always reports that neither is allowed or available.
- Refusal coverage includes wrong target/package identity, missing native backup and write-control evidence, and an unsafe report path under `htdocs`.
- Redaction coverage proves raw database names, config secrets, and bearer-like values do not appear in output or written reports.
- Advanced the standalone runner source identity to `0.2.0` and added producer-version evidence so older deployed runner copies can be distinguished during runtime parity checks.
- Verified the fixed UUID, database-size, maintenance-status, WP-CLI-version, active-plugin, and active-theme read commands against `alyntdrime.sitesmain.com` without deploying code or changing server state.
- Feature Light Review: passed after consolidating target-size and symlink collection into one filesystem walk.
- Feature Bloat And Structure Review: completed against explicit base `origin/master`. The standalone runner remains oversized at 5,070 total lines, the existing admin source-settings trait is 364 lines after a one-line identity addition, and the focused preflight test is 330 lines. No feature-stage split was made because runner splitting changes standalone deployment/install behavior, the trait's size predates this slice, and splitting a 30-line-over test would add fixture indirection without reducing production risk. Revisit the runner boundary during the full pre-release structure review.
- Feature UI/UX Implementation Review: not applicable; no WordPress or frontend UI changed.
- Feature Security Review: passed after canonical-path checks were added for target, staging, external config, native evidence, and report paths, and native backup revision identifiers were changed to report only a SHA-256 fingerprint.
- Documentation Sync Audit: completed across `README.md`, `readme.txt`, `CHANGELOG.md`, runner documentation, restore runbook, implementation plan, example config, and this plan.
- Validation: full PHPUnit passed with 174 tests, 1,245 assertions, and 1 existing expected skip; full PHPCS passed across 54 files; JSON parsing and `git diff --check` passed with line-ending warnings only.
- Runtime parity deployment: plugin `0.4.0` and standalone runner `0.2.0` were installed on the staging target. Production restore remains disabled and the environment is enrolled only as `production-simulation`.
- Native backup evidence: GridPane manual local backup revision `1`, completed at `2026-07-14T20:23:01Z`, was recorded in a site-private evidence file. Preflight output exposes only the revision identifier SHA-256 fingerprint.
- Real package rehearsal: runner `0.2.0` created and verified package `alyntdrime-sitesmain-com-20260715-103906`, including the target site UUID and producer version. The package was staged for inspection without importing the database or overwriting WordPress files.
- Real preflight result: 63 of 64 checks passed. Target identity, package identity, active plugin/theme/drop-in and symlink inventories, staged files/database, maintenance/write-control review, report-path safety, and native-backup freshness all passed.
- Safe refusal proof: `disk_budget_sufficient` was the only failed check. The report measured `4,978,192,384` available bytes against `10,881,712,593` required additional bytes for the conservative files-and-database budget. Production apply remained unavailable, no destructive action occurred, and the refused audit report was written under the site-private production reports directory.

### Phase 3: Pre-Restore And Rollback Foundation

- Extend pre-restore evidence for production attempts.
- Implement explicit production-simulation rollback.
- Test rollback from controlled known states before implementing production apply.

### Phase 4: Production-Simulation Apply

- Implement the separate production-simulation apply command.
- Require exact production-style confirmation and target-domain confirmation.
- Rehearse files-only and database-only scopes before combined apply.

### Phase 5: Combined Apply And Rollback Rehearsals

- Run combined files-and-database simulation.
- Run an explicit rollback to the pre-restore state.
- Repeat the apply/rollback cycle to prove it is not a one-off.
- Retain reports and clean large artifacts only after separate approval.

### Phase 6: Failure Injection

- Execute the approved failure matrix on the production-simulation target.
- Verify maintenance and reports remain safe at each failure point.
- Update implementation or stop conditions before release work.

### Phase 7: Feature Reviews And Full Validation

Run whichever feature-stage workflows are applicable, in the established order:

1. Feature Light Review.
2. Feature Bloat And Structure Review.
3. Feature UI/UX Implementation Review, only if an interface changes.
4. Feature Security Review.
5. Documentation Sync Audit.

Then run the WordPress component testing/troubleshooting workflow, full local automated tests, production-simulation E2E, build/lint/audit steps that apply, and the plugin pre-release workflows.

### Phase 8: Release Candidate

- Prepare a release only after all production-simulation acceptance criteria pass.
- Use the normal release approval checkpoint before commit, tag, push, or publication.
- Production capability must remain disabled by default after installation or update.

### Phase 9: First Real Production Enrollment

- Select a real production site in a separate task.
- Perform read-only enrollment and site-specific write inventory first.
- Confirm native backup and rollback behavior for that exact site.
- Add site-specific critical URL and functional checks.
- Require separate approval before enabling production restore in private runner config.

### Phase 10: First Real Production Restore

- Treat the restore as a new incident task, not a continuation of development approval.
- Locate the requested Drime package and show its timestamp and identity.
- Run preflight and present the complete plan/report.
- Stop for maintenance approval.
- Stop again for exact production apply confirmation.
- Stop for rollback confirmation if verification fails.

## Acceptance Criteria

Production-simulation release readiness requires:

- staging restore behavior remains unchanged and tested;
- production preflight is read-only and refuses all incomplete evidence;
- production commands are disabled by default;
- target fingerprint mismatch blocks apply;
- staging confirmation cannot unlock production apply;
- exact target-domain confirmation is required;
- fresh Alynt pre-restore evidence is created and verified;
- host-native restore evidence is created and verified through a proven procedure;
- maintenance/write control is confirmed before apply;
- files-only, database-only, and combined simulation passes;
- post-restore WP-CLI, database, filesystem, cache, and HTTP checks pass;
- explicit rollback restores the exact known pre-restore state;
- apply plus rollback passes at least twice;
- failure injection produces safe refusal or actionable rollback evidence;
- no report or command output exposes secrets;
- large-artifact cleanup remains separately approved;
- no real production site is modified during development acceptance.

## Approved Phase 0 Decisions

The user explicitly confirmed:

1. Use `staging.handcraftedbotanicalformulas.com` as the current production-simulation target; retain `alyntdrime.sitesmain.com` as historical Phase 1/2 evidence.
2. Keep version 1 scope as files plus database.
3. Allow temporary downtime during simulation rehearsals.
4. Keep every production restore operator-supervised.
5. Require a verified GridPane/host restore point in addition to Alynt pre-restore evidence when the site supports it.

## Current Recommendation

Phase 0 through Phase 2 and `hbf-staging` enrollment Gates A through F are complete. The read-only preflight and private redacted report both passed all `64` checks. The next feature phase is Phase 3: design and implement the production-simulation pre-restore/rollback foundation, with rollback proven before any production-simulation apply command is added. Phase 3 code, deployment, cron, cleanup, production apply, and rollback execution remain unapproved pending the next explicit decision.
