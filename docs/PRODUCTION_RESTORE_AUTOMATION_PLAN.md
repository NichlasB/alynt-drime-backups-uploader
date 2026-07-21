# Production-Capable Restore Automation Plan

Updated: 2026-07-21

## At A Glance

| Item | Decision |
|---|---|
| Status | Runner `0.4.7` combined apply/rollback and report-first extraction cleanup passed on `hbf-staging`; production apply is relocked and the site is healthy |
| Goal | Operator-supervised files-and-database restoration from a verified Alynt server-runner package |
| Existing baseline | Staging-only `restore-apply` version 1 is implemented and rehearsed |
| Current test target | Approved: `staging.handcraftedbotanicalformulas.com` as a production-simulation target |
| Historical test target | `alyntdrime.sitesmain.com`; retained as Phase 1/2 evidence, but no longer the preferred target because of disk capacity |
| Actual production target | None selected or approved |
| First implementation surface | Read-only preflight, private recovery evidence, and rollback foundation |
| Required recovery protection | Verified Alynt pre-restore files/database evidence plus an independently verified host-native restore point when available |
| Confirmation model | Separate production command plus exact action and target-domain confirmations |
| Automation level | Supervised only; no cron, wp-admin button, or unattended restore |
| Next implementation step | Begin Phase 7 full validation: component troubleshooting/E2E, applicable feature and pre-release workflows, then release-candidate review |

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

Implemented source-only Phase 4 production-simulation apply commands:

```bash
php alynt-backup-runner.php restore-production-apply --config=/path/to/config.json --staged-path=/path/to/staged/package --scope=files --pre-restore-evidence=/path/to/PRODUCTION_PRE_RESTORE_EVIDENCE.json --target-site=example.com --confirm=restore-production-site --confirm-site=example.com --format=json
php alynt-backup-runner.php restore-production-apply --config=/path/to/config.json --staged-path=/path/to/staged/package --scope=database --pre-restore-evidence=/path/to/PRODUCTION_PRE_RESTORE_EVIDENCE.json --target-site=example.com --confirm=restore-production-site --confirm-site=example.com --format=json
php alynt-backup-runner.php restore-production-apply --config=/path/to/config.json --staged-path=/path/to/staged/package --scope=files-and-database --pre-restore-evidence=/path/to/PRODUCTION_PRE_RESTORE_EVIDENCE.json --target-site=example.com --confirm=restore-production-site --confirm-site=example.com --format=json
```

Implemented production-simulation recovery evidence command:

```bash
php alynt-backup-runner.php restore-production-create-pre-backup --config=/path/to/config.json --staged-path=/path/to/staged/package --scope=files-and-database --target-site=example.com --confirm=create-production-pre-restore-backup --format=json
```

Implemented, disabled-by-default production-simulation rollback command:

```bash
php alynt-backup-runner.php restore-production-rollback --config=/path/to/config.json --apply-report=/path/to/RESTORE_PRODUCTION_APPLY_REPORT.json --target-site=example.com --confirm=rollback-production-site --confirm-site=example.com --format=json
```

`restore-production-create-pre-backup` runs the complete read-only preflight before writing recovery artifacts. It writes a private evidence JSON file with package, target hostname, target UUID, scope, target fingerprint, and SHA-256 values for each selected artifact. It never imports a database, replaces target files, or changes maintenance state.

`restore-production-apply` is source-implemented for `production-simulation` files-only, database-only, and combined files-and-database scopes. It requires both private enablement flags, a fresh matching Phase 3 evidence record, unchanged target fingerprint, exact action and hostname confirmations, maintenance activation, post-apply identity checks, and a private rollback-ready report. Combined scope replaces files and restores enrolled symlinks before importing the database under one maintenance window. No runtime apply rehearsal is authorized merely because this command exists.

`restore-production-rollback` is source-implemented for `production-simulation` only. It remains unavailable unless private runner config explicitly sets `production_rollback_enabled` to `true`; it then requires a particular `restore-production-apply` report, matching package/target/evidence identity, verified artifact hashes, and both exact confirmations.

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
  "production_pre_backup_max_age_seconds": 3600,
  "production_reports_path": "/var/www/staging.handcraftedbotanicalformulas.com/private/alynt-drime-backups/production-restore-reports",
  "production_rollback_enabled": false,
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
  "production_expected_symlink_targets": {
    "wp-content/mu-plugins/wp-fail2ban.php": "/var/www/staging.handcraftedbotanicalformulas.com/htdocs/wp-content/plugins/wp-fail2ban/wp-fail2ban.php"
  },
  "production_healthcheck_urls": [
    "https://staging.handcraftedbotanicalformulas.com/"
  ]
}
```

Required config behavior:

- `production_restore_enabled` defaults to `false` and is never enabled by plugin activation or upgrade.
- `production_rollback_enabled` defaults to `false`, is not implied by `production_restore_enabled`, and may be enabled only for an approved production-simulation rehearsal with a verified rollback plan.
- `production_pre_backup_max_age_seconds` defaults to `3600`; production apply refuses older or future-dated recovery evidence.
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

### Read-Only Coverage Audit - 2026-07-21

This audit compared the matrix against source branches, automated tests, and retained `hbf-staging`/`alyntdrime` runtime evidence. It made no server changes.

| Failure case | Current evidence | Status / next action |
|---|---|---|
| Missing or wrong confirmation phrase | Automated apply/rollback gate tests plus Linux refusal probes | Proven |
| Wrong target domain, UUID, path, or environment | Automated target/package preflight tests and enabled-state wrong-host refusals on `hbf-staging` | Proven for domain/package identity; retain path, UUID, and environment assertions in the focused regression suite |
| Package/target identity mismatch | Automated preflight refusal and runtime wrong-package/target refusal evidence | Proven |
| Altered staged package after preflight | Runner `0.4.4` records deterministic extracted file-tree/database SHA-256 values, binds the integrity object into private pre-restore evidence, and recomputes the selected input after maintenance activation immediately before writes | Implemented and proven automatically for direct tampering, forged report replacement, and maintenance-time tampering; Linux runtime proof remains separately gated |
| Missing or invalid pre-restore evidence | Automated stale/tampered evidence and rollback hash refusal tests | Proven automatically; stale evidence also proven at runtime |
| Unavailable native backup evidence | Automated preflight refusal for missing native evidence | Proven automatically |
| Insufficient disk space | Real read-only refusal on `alyntdrime.sitesmain.com` | Proven at runtime |
| Maintenance/write-control failure | Apply and rollback fixtures cover total activation failure, post-copy reactivation failure with emergency-marker protection, and rollback deactivation failure after successful verification | Proven automatically on both apply and rollback boundaries; every case stops safely or supports a verified retry |
| Interrupted file replacement | Runner `0.4.5` exposes a protected copy boundary for tests only; deterministic coverage interrupts after one successful file copy and verifies truthful destructive/live-overwrite flags, retained maintenance, a private failure report, and rollback guidance | Proven automatically; no runtime failure switch was added |
| Failed database import | Deterministic WP-CLI failure fixtures prove a failed production apply import retains maintenance and rolls back successfully; a failed rollback import retains maintenance and succeeds on retry after the fault clears | Proven automatically; reports conservatively set `database_may_be_modified` as soon as any import begins |
| Broken ownership or drop-ins | Apply and rollback now compare the post-write WordPress root owner/group with private pre-restore evidence and require the exact enrolled drop-in inventory | Proven automatically: ownership mismatch remains rollback-ready, and rollback drop-in mismatch retains maintenance and succeeds on retry |
| Failed cache purge | Configuration records only that the purge procedure was reviewed; the runner does not execute or verify a purge | Scope decision required: implement an explicit site-specific purge/check or retain it as an operator step |
| Failed WP-CLI or HTTP health check | Deterministic post-write WP-CLI read failures retain maintenance and produce successful rollback/retry paths. Public HTTP `200` was independently checked after rehearsals | WP-CLI proven automatically. Version 1 keeps public HTTP as a mandatory operator/agent check rather than a runner gate; revisit only as an optional configured supplement |
| Explicit rollback after successful restore | Automated rollback success plus two complete Linux files-only apply/rollback cycles | Proven |
| Rollback after a simulated failed restore | Automated rollback fixtures and the recovered runner `0.4.1` interrupted rollback incident | Substantial evidence; rerun only after deterministic interruption hooks exist |
| Rollback failure reporting | Automated report assertions, Linux refusal report mode `0640`, and retained failure/recovery reports | Proven |

Audit conclusion:

- Do not run broader blind destructive failure injection yet.
- Staged file-tree and database digest binding is implemented in runner `0.4.4` and reverified immediately before production writes.
- The deterministic local failure matrix now covers apply and rollback maintenance boundaries, file-copy interruption, database imports, WP-CLI identity reads, root ownership, and drop-in mismatches.
- Keep cache purge and public HTTP verification as explicit operator steps in version 1. A later optional configured HTTP probe may supplement, but must not gate destructive recovery or replace WP-CLI/filesystem verification.
- After those changes pass review and automated validation, reduce the live matrix to a small number of high-value Linux proofs instead of reproducing every unit failure on the staging server.

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

- Status: source implementation complete; deployment and runtime rehearsal remain separately gated.
- Added `restore-production-create-pre-backup`, which reruns complete production preflight and then atomically writes private recovery evidence for the selected files/database scope. Evidence includes target hostname/UUID, package ID, paths contained under `production_pre_backup_path`, SHA-256 values for each artifact, and mode `640` on the database export, file archive, and evidence JSON.
- Added `restore-production-rollback`, restricted to `production-simulation`, disabled by default through `production_rollback_enabled`, and guarded by a particular future production-apply report, exact action confirmation, exact target-domain confirmation, path containment, repeated preflight, evidence identity, and artifact hash checks.
- Advanced the standalone runner source identity to `0.3.0` so Phase 3 deployment parity can be observed independently of historical `0.2.0` package evidence.
- Focused tests prove fresh files/database evidence creation, successful file-and-database rollback from a controlled changed state, and safe refusal before target writes when the file recovery artifact is tampered.
- Production apply remains unimplemented, and no Phase 3 code has been deployed or rehearsed on `hbf-staging` yet.
- Runtime parity deployment: runner `0.3.0` was installed at the private `hbf-staging` runner path with site-user ownership and mode `750`. Private config remains mode `640` and now records `production_rollback_enabled: false`; no apply or rollback capability was enabled.
- Runtime verification: runner health passed all checks. The final site-user `restore-production-preflight` against the existing staged package passed all `64` checks at `2026-07-20T21:33:47Z`, reported runner version `0.3.0`, measured `166,198,484,992` bytes free against `13,068,226,563` required bytes, and retained false values for production apply availability, rollback availability, destructive actions, database import, file overwrite, and maintenance-state changes.
- Approved recovery-evidence rehearsal: `restore-production-create-pre-backup` completed successfully at `2026-07-20T21:43:36Z` after a zero-failure preflight. It created a `598 MB` database export, an `880 MB` file archive, and a `4.7 KB` evidence JSON under the private production pre-backup path. Independent SHA-256 verification matched evidence values `83c3d4110f8c738fae381192e0915fa5f1f5b4c447d9fb1b26512f37172ae74a` for the database and `69762a94fe185debac6b693bf32ef5f9bec53ca31a87f1a2d39026fde795728f` for the files. All three artifacts are site-user owned with mode `640`; disk remained healthy at 53% used with approximately `154 GB` available. No database import, target-file overwrite, maintenance change, rollback enablement, cron change, or service restart occurred.
- Feature Light Review: passed. The slice is limited to standalone CLI restore/recovery operations, private-file writes, file replacement/database import only behind the rollback gate, focused tests, and operational documentation. No WordPress admin UI, REST, AJAX, cron, or third-party API behavior changed.
- Feature Bloat And Structure Review: completed against explicit base `39a0f1c`. The existing standalone runner is 5,495 total lines and remains architecture-sensitive because splitting it changes the private single-file deployment model. The focused rollback test is 384 total lines (84 over the feature threshold) but only 300 code lines; its fixture boundary is clear but single-use, so introducing a test-support abstraction now would add indirection without reducing runtime risk. Both files are intentionally deferred to pre-release `02-FILE_STRUCTURE_REVIEW_PROMPT.md`.
- Feature UI/UX Implementation Review: not applicable; no admin or frontend UI changed.
- Feature Security Review: passed. The new commands validate fixed scopes and exact host confirmations, require private contained paths, require a disabled-by-default rollback flag, rerun preflight before rollback, validate apply-report/evidence identity, and hash-verify every selected recovery artifact. Focused tests cover successful controlled rollback, hash-tamper refusal, and disabled-flag refusal before target writes.
- Documentation Sync Audit: completed. Plugin metadata remains `0.4.0` with WordPress `6.0` and PHP `7.4` minimums; `README.md`, `readme.txt`, `CHANGELOG.md`, runner documentation, restore runbook, config example, and this plan accurately describe the source-implemented but runtime-gated Phase 3 boundary.

### Phase 4: Production-Simulation Apply

- Status: source implementation and focused local validation complete; deployment and runtime rehearsals remain separately gated.
- Implemented the separate production-simulation apply command for files-only and database-only scopes; combined scope is refused before maintenance or target writes.
- Requires exact production-style confirmation, exact target-domain confirmation, both private enablement flags, complete preflight, verified Phase 3 evidence, unchanged target fingerprint, and a private report path.
- Requires Phase 3 evidence to be no older than `production_pre_backup_max_age_seconds`, defaulting to one hour.
- Activates maintenance before apply, reactivates it after full file replacement, verifies target URL/UUID while maintenance remains active, and deactivates maintenance only after verification passes. Failure after target writes leaves maintenance active and points to the matching rollback report.
- Focused tests cover successful files-only and database-only apply plus refusal for combined scope, wrong target confirmation, and disabled apply configuration.
- Feature Light Review: passed after tightening private report-mode handling and report-state accuracy. The slice changes only the standalone runner CLI, private restore artifacts/reports, focused tests, and operator documentation; no WordPress admin UI, REST, AJAX, cron, or Drime API behavior changed.
- Feature Bloat And Structure Review: completed against explicit base `5885dda`. Five changed PHP files were measured; the focused test and support files remain below the `300`-line threshold. The single-file runner is `5,771` total lines and remains architecture-sensitive because it is deployed as one private executable, so splitting it is deferred to pre-release `02-FILE_STRUCTURE_REVIEW_PROMPT.md`.
- Feature UI/UX Implementation Review: not applicable; no browser or WordPress admin interface changed.
- Feature Security Review: passed after adding a configurable one-hour default freshness gate and regression test for stale Phase 3 evidence. Exact scope/target confirmations, dual disabled-by-default flags, contained paths, artifact hashes, unchanged target fingerprint, private `0640` reports, maintenance control, and rollback-ready failure output remain enforced.
- Documentation Sync Audit: completed. Plugin metadata and stable tag remain `0.4.0`, WordPress `6.0` and PHP `7.4` minimums remain unchanged, latest release tag remains `v0.4.0`, and Unreleased documentation records runner `0.4.0` capability and deployment state separately from plugin release state.
- Runner source identity is now `0.4.0`; `hbf-staging` has matching runner parity while production apply and rollback remain disabled.
- The next runtime step requires separate approval to enable the two private flags and create fresh scope-matched recovery evidence before a files-only rehearsal.
- Approved runtime parity deployment completed on `hbf-staging` on 2026-07-21. Runner `0.4.0` was installed with site-user ownership and mode `750`; SHA-256 `352d9bc14bd333beb38763114ee08ea892546864a35fbb01feab355371a0d586` matched the locally validated source, and runner `0.3.0` was retained as `alynt-backup-runner.php.pre-0.4.0-20260721.bak` with its original ownership and mode.
- Runtime health passed before and after refusal testing. The staging-only confirmation phrase was rejected immediately. A production-confirmed database-scope attempt was then refused at preflight while both `production_restore_enabled` and `production_rollback_enabled` remained `false`; maintenance, file replacement, and database import were never attempted. Its private refusal report was written with mode `640`, maintenance remained inactive, and no restore apply occurred.
- Approved files-only rehearsal preparation completed on `hbf-staging` on 2026-07-21. A files-scope preflight passed all checks while apply remained disabled, then `restore-production-create-pre-backup` created fresh evidence `PRODUCTION_PRE_RESTORE_EVIDENCE-staging-handcraftedbotanicalformulas-com-20260720-203243-files-20260721-080225.json` and a `922,102,514`-byte file archive with SHA-256 `f19fdc1fb8552ef1c27dfe46b04aa15ef070022bc4dea73dab81752e166cd106`. Both artifacts are site-user owned with mode `640`; approximately `174.6 GB` remained free.
- The private runner config was backed up as `config.json.pre-phase4-files-20260721-080225.bak`, then both `production_restore_enabled` and `production_rollback_enabled` were set to `true`. An enabled-state apply readiness rehearsal used the correct action phrase but an intentionally invalid `--confirm-site`; the complete production preflight and all fresh evidence/fingerprint checks passed, and the sole failed check was `confirmation_site_matches_target`. No maintenance, file replacement, or database import was attempted. The refusal report was written privately with mode `640`, maintenance remained inactive, and health passed. The files-only apply now waits at its separate exact target confirmation gate.
- The separately confirmed files-only production-simulation apply completed successfully at `2026-07-21T08:13:47Z`. All preflight, confirmation, evidence freshness/hash, target fingerprint, maintenance, and post-apply identity checks passed. Maintenance activated before replacement, was reactivated after the WordPress tree replacement, and deactivated only after verification. Files were replaced from package `staging-handcraftedbotanicalformulas-com-20260720-203243`; no database import was attempted. The private apply report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-20260721-081347-8a39b7ad.json`, site-user owned with mode `640`.
- Independent post-apply verification confirmed the maintenance marker absent, WordPress maintenance inactive, home/site URL and site UUID matching enrollment, Alynt Drime Backups Uploader active, `blocksy-child` active, runner health passing, and HTTP `200` through the staging login redirect. Rollback remains available through the matching report and fresh evidence. After verification, `production_restore_enabled` was returned to `false` while `production_rollback_enabled` remained `true`, preventing an accidental second apply while preserving recovery capability.
- The subsequent database-scope preflight detected that the files-only replacement had removed the enrolled GridPane symlink `wp-content/mu-plugins/wp-fail2ban.php`. Preparation stopped before evidence creation or database import. Read-only inspection confirmed the verified pre-restore archive recorded the exact target `/var/www/staging.handcraftedbotanicalformulas.com/htdocs/wp-content/plugins/wp-fail2ban/wp-fail2ban.php`, and that target file remained present. After separate approval, the symlink was recreated as the site user and the database-scope preflight passed with zero failures. Before any future files-only or combined rehearsal, the runner must be patched and tested so production file apply preserves/recreates enrolled symlinks and verifies the full enrolled filesystem inventory before reporting success.
- Approved database-only rehearsal preparation then completed on `hbf-staging` on 2026-07-21. Fresh evidence `PRODUCTION_PRE_RESTORE_EVIDENCE-staging-handcraftedbotanicalformulas-com-20260720-203243-database-20260721-082839.json` records a `624,304,116`-byte database export with independently matched SHA-256 `1937bccb4bdce985dd5b9e7b7581ccbdddb102d629f648340eaccad25e142009`; both files are site-user owned with mode `640`, and approximately `175 GB` remained free.
- `production_restore_enabled` was then set to `true` while rollback remained enabled. The database apply readiness rehearsal used the correct production action phrase but an intentionally invalid `--confirm-site`; the complete enabled-state preflight and every database evidence, freshness, hash, and target-fingerprint check passed. The sole failure was `confirmation_site_matches_target`; maintenance and file/database actions were never attempted. The private refusal report is mode `640`, the repaired GridPane symlink remains correct, maintenance is inactive, and runner health passes. Database-only apply now waits at its separate exact target confirmation gate.
- The separately confirmed database-only production-simulation apply completed successfully at `2026-07-21T08:31:25Z`. All preflight, confirmation, database evidence freshness/hash, target fingerprint, maintenance, import, and post-apply identity checks passed. The staged database imported successfully; files were not replaced. The private apply report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-database-20260721-083125-68f63839.json`, site-user owned with mode `640`.
- Independent verification confirmed maintenance inactive and its marker absent, home/site URL and site UUID matching enrollment, the uploader and `blocksy-child` active, the repaired GridPane symlink intact, HTTP `200` through the staging login redirect, and runner health passing. `production_restore_enabled` was returned to `false` while rollback remained enabled.
- Runner `0.4.1` is implemented locally to preserve the enrolled GridPane symlink during production file replacement. Preflight now requires exact enrolled symlink paths and targets; file apply captures only matching links, recreates them before post-apply verification, and leaves maintenance active on any restore failure. Success now requires the complete enrolled active-plugin, active-theme, drop-in, symlink-path, and symlink-target inventories. Focused Windows tests pass with the real symlink recreation case expectedly skipped where symlink creation is unavailable; Linux runtime deployment remains separately gated.
- Feature Light Review: passed. The patch is confined to the standalone production-simulation runner, private config enrollment, focused tests, and operator documentation. File-operation failures remain explicit and rollback-oriented; no WordPress UI, AJAX, REST, cron, database schema, or Drime API behavior changed.
- Feature Bloat And Structure Review: completed against explicit base `5885dda`. Seven changed PHP files were measured. The runner is `5,942` total lines and remains architecture-sensitive because it is deployed as one private executable; the `385`-line rollback test and `332`-line preflight test received only one enrollment-fixture line each. No low-risk feature-stage split was identified, so all three are deferred to pre-release `02-FILE_STRUCTURE_REVIEW_PROMPT.md`.
- Feature UI/UX Implementation Review: not applicable; no WordPress or browser interface changed.
- Feature Security Review: passed. Preflight now refuses missing or changed symlink-target enrollment, file apply snapshots only exact configured targets, unsafe relative paths and null-byte/empty targets are rejected, symlink restoration occurs while maintenance remains active, and complete post-apply identity mismatches preserve maintenance plus rollback guidance. Regression coverage includes source security assertions and a Linux-capable symlink recreation test; the latter is an expected skip on Windows where symlink creation is unavailable.
- Documentation Sync Audit: completed. Plugin metadata, stable tag, and latest Git tag remain `0.4.0`; standalone runner identity is documented separately as `0.4.1`. `README.md`, `readme.txt`, `CHANGELOG.md`, runner documentation, restore runbook, config example, and this plan now describe the exact symlink enrollment and preservation behavior.
- Validation: full PHPUnit passed with 184 tests, 1,351 assertions, and 2 expected Windows symlink skips; full PHPCS, build, JSON parsing, and `git diff --check` passed.
- Approved runtime deployment completed on `hbf-staging` on 2026-07-21. Runner `0.4.1` was installed with site-user ownership and mode `750`; SHA-256 `ffd327679405cb30d216510bce90469a45bb4a95daca1f9d572e40f980ff754f` matched the validated local source. The previous runner and config were retained as `alynt-backup-runner.php.pre-0.4.1-20260721.bak` and `config.json.pre-0.4.1-symlink-target-20260721.bak`.
- The private config now enrolls `wp-content/mu-plugins/wp-fail2ban.php` with its exact target `/var/www/staging.handcraftedbotanicalformulas.com/htdocs/wp-content/plugins/wp-fail2ban/wp-fail2ban.php`. `production_restore_enabled` remains `false`; `production_rollback_enabled` remains `true`.
- Runtime health passed as the site user. The staging-only confirmation phrase was rejected immediately. A production-confirmed files-scope attempt then passed the new symlink path and target checks but was refused because production apply remained disabled; the older evidence fingerprint also correctly differed from the new runner/config fingerprint. No maintenance change, file replacement, database import, or other destructive action was attempted. Its private mode-`640` report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-20260721-085217-4f387538.json`.
- Independent post-deployment verification confirmed runner `0.4.1`, maintenance inactive, the GridPane symlink and target intact, both private flags in their intended states, runner health passing, and HTTP `200` through the staging login redirect. Temporary transfer files were removed.
- Approved runner `0.4.1` files-only rehearsal preparation completed on `hbf-staging` at `2026-07-21T08:56:10Z`. The read-only preflight passed with zero failures, including exact symlink path/target enrollment, the complete 61-plugin inventory, `blocksy-child`, `wp-content/object-cache.php`, native GridPane evidence, and `175,777,640,448` available bytes against an `8,277,844,503`-byte conservative requirement.
- Fresh evidence `PRODUCTION_PRE_RESTORE_EVIDENCE-staging-handcraftedbotanicalformulas-com-20260720-203243-files-20260721-085610.json` and file backup `production-files-before-staging-handcraftedbotanicalformulas-com-20260720-203243-20260721-085610.tar.gz` were created outside `htdocs` with site-user ownership and mode `640`. The archive is `922,032,613` bytes; independent SHA-256 `8f67d8e1fa1cbb24c9963a85fe1b6a14c7d3b9f4db052d5952535a116c882485` matches the evidence, and archive listing confirms the GridPane `wp-fail2ban.php` symlink entry is retained for rollback.
- A production-confirmed apply readiness attempt using the fresh evidence was safely refused while `production_restore_enabled` remained `false`. Every evidence hash, target fingerprint, package identity, symlink path, and symlink target check passed; only the disabled apply gate and its aggregate preflight result failed. No maintenance, file, or database action was attempted. The private mode-`640` refusal report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-20260721-085757-396e1fd5.json`. Maintenance remains inactive, the symlink remains intact, and HTTP verification reaches `200`.
- After separate approval, private config was backed up as `config.json.pre-0.4.1-files-apply-enable-20260721-090116.bak` and `production_restore_enabled` was set to `true` while rollback remained enabled. Config JSON, site-user ownership, mode `640`, exact symlink enrollment, and runner health were reverified.
- The enabled-state rehearsal then used the correct production action phrase with intentionally wrong `--confirm-site=wrong.staging.handcraftedbotanicalformulas.com`. The complete preflight passed with zero failures, and every evidence freshness/hash, package, target fingerprint, plugin/theme/drop-in, symlink path, symlink target, apply, and rollback gate passed except `confirmation_site_matches_target`. No maintenance, file, or database action was attempted. Its private mode-`640` report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-20260721-090158-18dac63c.json`; maintenance remains inactive, the symlink remains intact, runner health passes, and HTTP verification reaches `200`.
- After exact destructive approval, the runner `0.4.1` files-only production-simulation apply completed successfully at `2026-07-21T09:03:57Z`. Preflight, confirmation, evidence freshness/hash, target fingerprint, maintenance activation/reactivation/deactivation, staged file replacement, and every post-apply identity check passed with zero failures. The database was not imported. One enrolled symlink was captured before replacement and recreated successfully; the post-apply symlink path and exact target checks both passed. The rollback-ready private report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-20260721-090357-190f25a8.json`, site-user owned with mode `640`.
- Independent verification confirmed maintenance inactive and its marker absent, home/site URL and site UUID matching enrollment, Alynt Drime Backups Uploader active, `blocksy-child` active, the recreated `wp-fail2ban.php` path a real symlink to the expected existing target, runner health passing, and HTTP `200` through the staging login redirect. Approximately `162 GB` remained free. `production_restore_enabled` was then returned to `false` while `production_rollback_enabled` remained `true`; JSON, mode `640`, exact symlink enrollment, maintenance state, link target, and runner health were reverified.
- The separately approved files-only rollback passed every preflight, confirmation, apply-report, evidence freshness/hash, target identity, and symlink enrollment gate, then failed during file copy. Runner `0.4.1` deleted the target and rejected the enrolled symlink in the extracted recovery tree because the generic copier was intentionally link-denying. The failed report is `RESTORE_PRODUCTION_ROLLBACK_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-20260721-091206.json`. The database was untouched, but `htdocs` was incomplete and HTTP returned `403` until recovery completed.
- Read-only incident inspection confirmed the private extracted rollback tree `.rollback-staging-handcraftedbotanicalformulas-com-20260720-203243-20260721-091209/htdocs` was complete at approximately `1.8 GB`, including WordPress core and the exact enrolled symlink, while the partial target was approximately `34 MB`. The already approved rollback was completed as the site user with an archival `cp -a` from that hash-verified private tree. WordPress `7.0.2`, home URL, site UUID, uploader, `blocksy-child`, symlink type/target, runner health, maintenance inactive, and HTTP `200` all passed afterward. A read-only `rsync` comparison found no substantive source/target differences; WPvivid recreated only its excluded runtime backup directory. The immutable private mode-`640` recovery report is `RESTORE_PRODUCTION_ROLLBACK_RECOVERY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-20260721-091414.json`.
- Runner `0.4.2` is implemented locally in response. It validates the complete extracted rollback symlink map against exact private enrollment before target deletion, allows only those exact links during production rollback copy, activates and reactivates maintenance around full file replacement, requires complete post-rollback WordPress/plugin/theme/drop-in/symlink identity verification, and removes maintenance only after verification succeeds. Focused rollback/apply/security tests and coding standards pass; real symlink regressions are expectedly skipped on Windows and remain pending Linux runtime proof after a separately approved deployment.
- Feature Light Review: passed. The corrective slice is confined to production-simulation rollback file operations, maintenance control, private reports, regression tests, and operator documentation. No WordPress UI, AJAX, REST, cron, database schema, Drime API, or ordinary backup/upload behavior changed.
- Feature Bloat And Structure Review: completed against explicit base `5885dda`. Seven changed PHP files were measured. The standalone runner is `6,039` total lines, the rollback regression test is `504` lines, and the preflight test is `332` lines. The runner remains architecture-sensitive because it is deployed as one private executable; splitting the incident-focused test or preflight fixture during recovery hardening would increase movement without reducing runtime risk. All three are deferred to pre-release `02-FILE_STRUCTURE_REVIEW_PROMPT.md`.
- Feature UI/UX Implementation Review: not applicable; no WordPress or browser interface changed.
- Feature Security Review: passed. Recovery archive hashes and all prior gates remain required; extracted source symlinks must now exactly match private path/target enrollment before target deletion; unknown, changed, empty, null-byte, or unsafe relative links are refused; only exact enrolled links can be recreated. Maintenance surrounds target writes, is reactivated after file replacement, and remains active on rollback or identity-verification failure. Regression coverage includes successful maintenance-controlled rollback, enrolled-symlink restoration, unenrolled-symlink refusal before deletion, hash tamper refusal, disabled-flag refusal, and source security assertions. Real link cases are expectedly skipped on Windows and remain pending Linux proof.
- Documentation Sync Audit: completed. Plugin metadata, stable tag, and latest Git tag remain `0.4.0`; standalone runner identity is documented separately as `0.4.2`. `README.md`, `readme.txt`, `CHANGELOG.md`, runner documentation, restore runbook, config example, and this plan reflect the incident, recovery boundary, and hardened rollback behavior.
- Validation: full PHPUnit passed with 186 tests, 1,368 assertions, and 4 expected Windows symlink skips; full PHPCS, build, focused security tests, JSON parsing, and `git diff --check` passed. Validated runner `0.4.2` SHA-256 is `abc041dd0a355ae6d1489e38e6b69bd1d87004e5d6167f29df0820d89adc6c25`.
- Approved runtime deployment completed on `hbf-staging` on 2026-07-21. Runner `0.4.2` was installed with site-user ownership and mode `750`; its server SHA-256 exactly matches the validated local hash. Runner `0.4.1` was retained as `alynt-backup-runner.php.pre-0.4.2-20260721.bak`.
- Linux runtime health passed every resource and tool check. Production apply remains disabled, rollback remains enabled, maintenance is inactive, the enrolled `wp-fail2ban.php` symlink and exact target are intact, WordPress `7.0.2`, the expected home URL, Alynt Drime Backups Uploader, and `blocksy-child` all verify, and HTTP returns `200`.
- Safe refusal checks proved that `restore-production-apply` rejects `--confirm=restore-staging-site` and `restore-production-rollback` independently rejects the same phrase in favor of `--confirm=rollback-production-site`. Neither probe reached maintenance, file replacement, or database import. A destructive Linux rollback proof remains separately gated.
- Fresh runner `0.4.2` files-only preparation completed on `hbf-staging` at `2026-07-21T09:37:34Z`. The read-only production preflight passed with zero failures and measured `171,089,506,304` bytes available against an `8,172,715,242`-byte conservative requirement. Private evidence `PRODUCTION_PRE_RESTORE_EVIDENCE-staging-handcraftedbotanicalformulas-com-20260720-203243-files-20260721-093734.json` and its `922,031,983`-byte recovery archive were created with site-user ownership and mode `640`; independent SHA-256 `6084ab4739f5839a23050e86fca89be82705df6dc311c2bd8ada4d62451b484e` matches the evidence, and the archive contains the exact enrolled GridPane symlink. Production apply remains disabled, rollback remains enabled, maintenance is inactive, and HTTP remains `200`.
- After separate exact approval, runner `0.4.2` completed the files-only production-simulation apply at `2026-07-21T09:43:38Z` with zero failures. Maintenance activation, post-copy reactivation, complete WordPress/plugin/theme/drop-in/symlink verification, and maintenance deactivation all passed; one enrolled symlink was recreated with its exact target. Files were overwritten from package `staging-handcraftedbotanicalformulas-com-20260720-203243`; the database was not imported. The private mode-`640` apply report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-20260721-094338-e6965cc9.json`.
- Independent post-apply checks confirmed WordPress `7.0.2`, matching home/site URLs, Alynt Drime Backups Uploader and `blocksy-child` active, the exact GridPane symlink and target present, runner health passing, maintenance inactive, and HTTP `200`. Production apply was immediately relocked to `false`; rollback remains enabled for the matching separately confirmed proof.
- After distinct exact approval, runner `0.4.2` completed the matching files-only rollback at `2026-07-21T09:47:55Z` with zero failures. It hash-verified and privately extracted the recovery archive, validated the complete extracted symlink map against exact enrollment before target deletion, kept maintenance active through file replacement and complete WordPress/plugin/theme/drop-in/symlink verification, and then removed maintenance. Files were restored; the database was untouched. The rollback report is `RESTORE_PRODUCTION_ROLLBACK_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-20260721-094755.json`.
- Independent checks confirmed WordPress `7.0.2`, matching home/site URLs, Alynt Drime Backups Uploader and `blocksy-child` active, the exact GridPane symlink and target present, runner health passing, maintenance inactive, production apply disabled, rollback enabled, and HTTP `200`. This proves the hardened rollback ordering and enrolled-symlink handling on Linux.
- Independent report inspection found the successful rollback report had inherited mode `0644`; it was immediately corrected to site-user-owned mode `0640`. The local writer omitted the explicit `chmod( ..., 0640 )` already used by the production apply report writer.
- Local runner `0.4.3` adds the missing rollback-report permission enforcement and a POSIX regression assertion. Full PHPUnit passes with 186 tests, 1,368 assertions, and 4 expected Windows skips; full PHPCS passes across 54 files; build and `git diff --check` pass. Runtime deployment and a non-destructive Linux refusal-report mode proof remain separately gated.
- Feature Light Review: passed. The corrective patch reuses the existing production apply report pattern, changes one private CLI writer plus one focused assertion, and adds no UI, database, cron, API, or restore-behavior surface.
- Feature Security Review: the confirmed low-severity report-permission defect is fixed. Production rollback reports now require successful atomic write plus mode `0640`; POSIX automated coverage was added, and the Linux refusal-report mode check remains the runtime regression gate.
- Documentation Sync Audit: completed across `README.md`, `readme.txt`, `CHANGELOG.md`, runner documentation, restore runbook, and this plan. Plugin metadata remains `0.4.0`; standalone runner identity is separately documented as `0.4.3`.
- Validated local runner `0.4.3` SHA-256 is `5f37fdb197ed252954d637b9b8850237063554aff7b550be383ce9f77b42d34f`.
- Approved runtime deployment completed on `hbf-staging` on 2026-07-21. Runner `0.4.3` was installed with site-user ownership and mode `750`; its server SHA-256 exactly matches the validated local hash. Runner `0.4.2` was retained as `alynt-backup-runner.php.pre-0.4.3-20260721.bak`.
- Linux health passed. A non-destructive rollback probe used the valid apply report and correct action phrase with an intentionally wrong `--confirm-site`. The complete preflight passed, the sole failed check was `confirmation_site_matches_target`, and the runner reported no maintenance, file rollback, database import, or destructive action attempted.
- The resulting private refusal report `RESTORE_PRODUCTION_ROLLBACK_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-20260721-100018.json` is site-user owned with mode `0640`, closing the runtime permission regression. Production apply remains disabled, rollback remains enabled, maintenance is inactive, WordPress/plugin/theme/symlink checks pass, and HTTP remains `200`.
- Second-cycle runner `0.4.3` preparation completed at `2026-07-21T10:03:58Z`. The read-only files preflight passed with zero failures and measured `168,277,069,824` bytes available against an `8,172,715,242`-byte conservative requirement. Fresh private evidence `PRODUCTION_PRE_RESTORE_EVIDENCE-staging-handcraftedbotanicalformulas-com-20260720-203243-files-20260721-100358.json` and its `922,032,337`-byte recovery archive are site-user owned with mode `0640`; independent SHA-256 `f96c78be822920a3b61e3bad2f3385cd83aa38679199398f4d578d5d541de9ac` matches the evidence, and the archive contains the exact enrolled GridPane symlink. Production apply remains disabled, rollback remains enabled, maintenance is inactive, and HTTP remains `200`.
- After separate exact approval, runner `0.4.3` completed the second files-only production-simulation apply at `2026-07-21T10:08:27Z` with zero failures. Maintenance activation/reactivation/deactivation, file replacement, exact enrolled-symlink recreation, and complete WordPress/plugin/theme/drop-in/symlink verification all passed; the database was not imported. The private mode-`0640` apply report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-20260721-100827-c5af1efa.json`.
- Independent checks confirmed WordPress `7.0.2`, matching home/site URLs, Alynt Drime Backups Uploader and `blocksy-child` active, the exact GridPane symlink and target present, runner health passing, maintenance inactive, and HTTP `200`. Production apply was immediately relocked to `false`; rollback remains enabled for the separately confirmed matching proof.
- After distinct exact approval, runner `0.4.3` completed the matching second files-only rollback at `2026-07-21T10:16:30Z` with zero failures. Recovery hash checks, private extraction, pre-deletion symlink-map validation, maintenance activation/reactivation/deactivation, file replacement, and complete post-rollback WordPress/plugin/theme/drop-in/symlink verification all passed; the database was untouched. The private mode-`0640` report is `RESTORE_PRODUCTION_ROLLBACK_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-20260721-101630.json`.
- Independent verification confirmed WordPress `7.0.2`, matching home/site URLs, Alynt Drime Backups Uploader and `blocksy-child` active, the exact GridPane symlink and target present, runner health passing, maintenance inactive, production apply disabled, rollback enabled, and HTTP `200`. Checksum-mode comparison against the private extracted recovery tree found zero regular-file content differences after excluding WPvivid's runtime backup directory; the symlink target also matched exactly. Two complete files-only apply/rollback cycles have now passed.
- Runner `0.4.4` is implemented locally to bind extracted restore inputs after staging. `RESTORE_REPORT.json` now records deterministic SHA-256 integrity details for the WordPress tree (including paths, file contents/sizes, directories, and symlink targets) and database dump. Production preflight recomputes the selected scope, production pre-restore evidence stores the full package/integrity identity privately, and apply compares that evidence before maintenance and recomputes the current staged input after maintenance activation immediately before any target write.
- Focused coverage proves direct staged-file tampering is refused before maintenance, a tampered database plus rewritten public staging report cannot bypass the private evidence binding, and maintenance-time file tampering is refused before target writes while maintenance remains active. Full local PHPUnit passes with `189` tests, `1,402` assertions, and `4` expected Windows symlink skips; full PHPCS and build validation pass. Validated runner `0.4.4` SHA-256 is `03d8977824a013847c70a560eafbd6765da12f003b9c64a969163459f3acb25d`. Deployed `hbf-staging` remains on runner `0.4.3`; no server deployment or runtime mutation occurred in this slice.
- Feature Light Review: passed after broadening staged filesystem iterator failure handling to fail closed on any runtime exception. The slice remains confined to private runner staging/preflight/apply evidence, focused tests, and operator documentation; no WordPress UI, AJAX, REST, cron, database schema, or Drime API behavior changed.
- Feature Bloat And Structure Review: completed against the established explicit base `5885dda`. Eight changed PHP files were measured. The standalone runner is `6,226` total lines, the rollback test is `508`, and the preflight test is `333`; all remain architecture-sensitive and are deferred to pre-release `02-FILE_STRUCTURE_REVIEW_PROMPT.md`. The new production apply test is `296` lines and the two shared test helpers remain below threshold. No Phase 2 cleanup or split was justified.
- Feature UI/UX Implementation Review: not applicable; no browser, WordPress admin, or user-facing interface changed.
- Feature Security Review: passed. Unsafe staged path segments are not scanned, deterministic digests cover relative paths, file content/size, directories, and symlink targets, private evidence binds the full package identity, and apply recomputes the selected staged input after maintenance activation before writes. Automated regression coverage proves direct tampering, forged report replacement, and maintenance-time tampering all fail closed without target writes.
- Documentation Sync Audit: completed. Plugin metadata and stable tag remain `0.4.0`, latest Git tag remains `v0.4.0`, and standalone runner identity is separately documented as `0.4.4`. `README.md`, `readme.txt`, `CHANGELOG.md`, runner documentation, restore runbook, and this plan describe the new integrity requirement and the need to restage directories created by older runners.
- Approved runner `0.4.4` deployment completed on `hbf-staging` on 2026-07-21. The deployed runner SHA-256 is `03d8977824a013847c70a560eafbd6765da12f003b9c64a969163459f3acb25d`, exactly matching the reviewed local source; runner `0.4.3` is retained as a site-user-owned mode-`750` backup.
- The prior staged directory was preserved as `staging-handcraftedbotanicalformulas-com-20260720-203243.pre-0.4.4-20260721`, and the same source package was checksum-verified and restaged with runner `0.4.4`. Its integrity record covers `63,755` regular files, `7,327` directories, `1,685,538,976` file bytes, and the `626,021,509`-byte database dump. The clean files-and-database production preflight passed with zero failures while production apply remained disabled.
- The non-destructive Linux tamper proof appended a marker only to the staged copy of `htdocs/index.php`, then ran a files-scope production preflight. It was refused with exactly one failed check, `staged_file_integrity_matches`; `destructive_actions_performed` and `maintenance_state_changed` both remained `false`. The private evidence report `INTEGRITY_TAMPER_PROOF-20260721.json` is site-user owned with mode `0640`.
- The staged `index.php` was restored byte-for-byte from its private original and that temporary original was removed. A clean files-scope preflight then passed with zero failures and `staged_file_integrity_matches: true`. Runner health passes, production apply remains disabled, rollback remains enabled, maintenance is inactive, HTTP returns `200`, and approximately `152 GB` remains available. The preserved pre-`0.4.4` staged directory occupies approximately `2.4 GB` and remains pending separate cleanup approval.
- Deterministic production files-apply maintenance failure coverage is complete. A simulated initial activation failure exits at `maintenance-activation` before file replacement, leaves the original target unchanged, and does not create maintenance state. A simulated post-copy reactivation failure exits at `maintenance-reactivation` after the staged files replace the target, keeps the maintenance marker present, writes the private apply report, and directs the operator to `restore-production-rollback`.
- Full local validation after this coverage passes with `191` tests, `1,430` assertions, and `4` expected Windows symlink skips. PHPCS, the production build, and `git diff --check` also pass.
- Feature Light Review: passed. The test-only slice follows the existing production-restore fixture architecture, exercises useful operator-facing failure fields, and introduces no runtime behavior, UI, API, cron, schema, or remote-service changes.
- Feature Bloat And Structure Review: completed against explicit base `5885dda`. Nine changed PHP files were measured. The new failure test is `90` total lines and the shared production fixture is `239`, both below the `300`-line threshold. The existing `6,226`-line runner, `508`-line rollback test, and `333`-line preflight test remain deferred to the full pre-release file-structure review; no feature-stage cleanup or split was justified.
- Feature UI/UX Implementation Review: not applicable; this slice adds only CLI fixture controls and automated tests.
- Feature Security Review: passed. The fixture trigger files exist only in isolated temporary test roots, and the assertions prove fail-closed behavior at the two maintenance boundaries. No confirmed security defect requires a regression handoff.
- Documentation Sync Audit: this implementation plan required the only documentation change. Public plugin behavior, metadata, runner version, stable tag, README, readme, changelog, runner guide, and restore runbook are unchanged by test-only coverage.
- Runner `0.4.5` adds deterministic production file-copy interruption coverage through a protected copy method that is overridable only when the runner is loaded as a library. No config key, CLI flag, or environment-triggered failure mode exists. The simulated copier succeeds once and then refuses the next file, proving a genuine partial target write.
- A partial production file replacement now conservatively records `live_files_overwritten: true` as soon as destructive replacement starts instead of tying that flag to total copy success. The failure path immediately attempts maintenance reactivation; if WP-CLI cannot recreate the marker, a symlink-refusing atomic emergency fallback writes the core `.maintenance` file. The result remains `file-restore`, writes the private report, and points the operator to `restore-production-rollback`.
- The interruption test then consumes that exact failure report through the normal rollback implementation. Rollback permits only the target runtime and filesystem-inventory drift that can result from a failed apply; enrolled path safety, environment, package integrity, disk, reports path, operator-review settings, private pre-restore evidence, and native-backup evidence remain blocking. The verified rollback restores the original files, passes post-rollback identity checks, and removes maintenance mode.
- Full local validation for runner `0.4.5` passes with `192` tests, `1,456` assertions, and `4` expected Windows symlink skips. Full PHPCS, production build, and `git diff --check` pass. The validated local runner SHA-256 is `e7a6dff2838bdf4d3ea1cb8c3ef32a637bac20d9fba3dd28cdd5b7ba9761dc6b`; no deployment or server mutation occurred.
- Feature Light Review: found and fixed a recovery deadlock where the rollback preflight required the already-damaged target to retain its original drop-in/runtime inventory. Recovery now tolerates only the named mutable target-state checks while preserving every immutable safety gate, and the same interruption test proves the generated failure report successfully restores the target.
- Feature Bloat And Structure Review: completed against explicit base `5885dda`. Nine changed PHP files were measured. The interruption/failure test is `184` total lines and the shared fixture is `242`, both below the `300`-line threshold. The existing `6,325`-line runner, `508`-line rollback test, and `333`-line preflight test remain architecture-sensitive and deferred to the full pre-release file-structure review; no feature-stage split was justified.
- Feature UI/UX Implementation Review: not applicable; this slice changes only the standalone CLI runner, isolated fixtures, tests, and operator documentation.
- Feature Security Review: passed after hardening the emergency marker fallback. The marker is restricted to the exact enrolled WordPress root, rejects an existing symlink, writes through a private temporary file, applies mode `0644`, and atomically renames into place. The copy-failure seam is protected and available only through subclassing when the runner is deliberately loaded as a library; no runtime config, CLI, or environment failure switch exists.
- Documentation Sync Audit: completed. The WordPress plugin remains version/stable tag `0.4.0`; the standalone runner is now documented as `0.4.5`. `CHANGELOG.md`, `readme.txt`, the runner guide, restore runbook, and this plan describe partial-copy reporting, maintenance recovery, and rollback handling for damage-induced drift. Historical `0.4.4` deployment and integrity-baseline references remain intentionally unchanged.
- Deterministic database failure coverage is complete for both production apply and rollback. A failed staged-database import records `failure_step: database-import`, keeps maintenance active, writes the private apply report, and successfully rolls back after the simulated WP-CLI fault is removed. A failed recovery import records `failure_step: database-rollback`, keeps maintenance active, writes its rollback report, and succeeds when the same verified rollback is retried after the fault clears.
- Production apply and rollback reports now set `database_may_be_modified: true` before invoking WP-CLI import. `database_imported` remains tied to successful completion, so operators can distinguish a confirmed completed import from a failed import that may nevertheless have applied partial SQL changes.
- Full local validation passes with `194` tests, `1,489` assertions, and `4` expected Windows symlink skips. PHPCS, the production build, and `git diff --check` pass. The validated runner `0.4.5` SHA-256 is `ad5570df803653dc395431e77f9a025ef72b27856b279f76ee6dfb0920db5acf`; no deployment or server mutation occurred.
- Feature Light Review: passed. The slice uses the established private WP-CLI fixture trigger and verifies both immediate failure state and successful recovery. No unrelated runtime, WordPress admin, Drime API, cron, schema, or upload behavior changed.
- Feature Bloat And Structure Review: completed against explicit base `5885dda`. Ten changed PHP files were measured. The new database failure test is `126` total lines and the shared fixture is `245`, both below the `300`-line threshold. The existing `6,329`-line runner, `508`-line rollback test, and `333`-line preflight test remain deferred to the full pre-release file-structure review; no feature-stage split was justified.
- Feature UI/UX Implementation Review: not applicable; this slice changes CLI reports, isolated fixtures, automated tests, and operator documentation only.
- Feature Security Review: passed. The failure trigger exists only inside isolated temporary test roots and is not exposed through runner config, CLI options, environment variables, or production code. Existing exact confirmation, private evidence, target identity, maintenance, and report-path gates remain intact. No confirmed security defect requires a regression handoff.
- Documentation Sync Audit: completed. Plugin metadata and stable tag remain `0.4.0`, the standalone runner remains `0.4.5`, and public/operator documentation now explains conservative partial-database reporting and recovery retry behavior.
- Deterministic post-write WP-CLI identity-read failure coverage is complete for both file apply and rollback. A simulated failed `home` read after destructive apply produces `post-apply-verification`, retains maintenance, writes a rollback-ready private report, and rolls back successfully after the fault clears. The same failure after rollback produces `post-rollback-verification`, retains maintenance, and succeeds on retry.
- `production_rollback_available` is now set when destructive file replacement or database import begins rather than only after a fully successful apply. This matches the proven ability to consume failed apply reports through rollback while preserving the separate success and verification fields.
- Public HTTP health remains a mandatory operator/agent check after successful runner verification and maintenance removal. Version 1 will not gate apply or rollback on runner-side HTTP because CDN, WAF, DNS, caching, maintenance responses, and server egress can produce misleading results or block recovery. An optional configured probe remains a future extension.
- Full local validation passes with `196` tests, `1,517` assertions, and `4` expected Windows symlink skips. PHPCS, the production build, and `git diff --check` pass. The validated runner `0.4.5` SHA-256 is `ccc856dbdc3cb44fb0e8f8bd496dd71462dad263fd2bda955a66450c9598389c`; no deployment or server mutation occurred.
- Feature Light Review: passed. The focused fixtures fail one fixed read only after a second maintenance activation, so preflight remains healthy and the post-write boundary is exercised directly. Recovery succeeds after the isolated fault is removed, and rollback availability now matches actual behavior.
- Feature Bloat And Structure Review: completed against explicit base `5885dda`. Eleven changed PHP files were measured. The new identity failure test is `138` total lines and the shared fixture is `255`, both below the `300`-line threshold. The existing `6,331`-line runner, `508`-line rollback test, and `333`-line preflight test remain deferred to the full pre-release file-structure review; no feature-stage split was justified.
- Feature UI/UX Implementation Review: not applicable; this slice changes CLI report semantics, private fixtures, automated tests, and operator documentation only.
- Feature Security Review: passed. The identity-failure trigger and post-write state marker exist only in isolated temporary tests and are not exposed through production runner inputs. Exact confirmations, private evidence, target containment, maintenance, report permissions, and rollback gates remain unchanged. No confirmed security defect requires a regression handoff.
- Documentation Sync Audit: completed. Plugin metadata and stable tag remain `0.4.0`, runner identity remains `0.4.5`, and the changelog, WordPress readme, runner guide, restore runbook, and this plan now align on post-write identity failures, truthful rollback availability, and operator-run HTTP verification.
- Post-write ownership checks now compare the WordPress root owner and group IDs against the private `preflight_target_fingerprint` during both apply and rollback. A protected observation method supplies the real filesystem IDs in production and permits deterministic mismatch simulation only through a test subclass.
- Deterministic filesystem failure coverage proves an ownership mismatch after file apply produces `post-apply-verification`, retains maintenance, remains rollback-ready, and restores successfully. A missing enrolled `wp-content/object-cache.php` after rollback produces `post-rollback-verification`, retains maintenance, and succeeds when the rollback is retried after the isolated fault clears.
- Full local validation passes with `198` tests, `1,544` assertions, and `4` expected Windows symlink skips. PHPCS, the production build, and `git diff --check` pass. The validated runner `0.4.5` SHA-256 is `2390ad9a5a3245ee0d1e81fc6dabc1d2350d87965c25815ab431f61fda4e03c4`; no deployment or server mutation occurred.
- Feature Light Review: passed. The slice reuses the existing private target fingerprint and filesystem scanner, adds two explicit post-write checks per operation, and proves both rollback and retry behavior without introducing config or runtime failure switches.
- Feature Bloat And Structure Review: completed against explicit base `5885dda`. Twelve changed PHP files were measured. The new filesystem failure test is `166` total lines and the shared fixture is `261`, both below the `300`-line threshold. The existing `6,354`-line runner, `508`-line rollback test, and `333`-line preflight test remain deferred to the full pre-release file-structure review; no feature-stage split was justified.
- Feature UI/UX Implementation Review: not applicable; this slice changes CLI verification, private fixtures, automated tests, and operator documentation only.
- Feature Security Review: passed. Ownership expectations come only from private pre-restore evidence already bound to the exact target, and the observation seam is protected with no production input surface. The drop-in fault exists only in isolated test scripts. Existing containment, confirmation, maintenance, evidence, and report gates remain intact.
- Documentation Sync Audit: completed. Plugin metadata/stable tag remain `0.4.0`, runner identity remains `0.4.5`, and repository/operator documentation now describes post-write owner/group and enrolled drop-in verification.
- Rollback maintenance failure coverage is complete. A total WP-CLI activation failure plus simulated unavailable emergency marker produces `rollback-maintenance-activation` before any rollback write. Clearing the fault permits the same verified rollback to succeed.
- A post-copy reactivation failure produces `rollback-maintenance-reactivation`, confirms file rollback completed, writes the emergency `.maintenance` marker, and succeeds on retry. A deactivation failure after complete post-rollback verification produces `rollback-maintenance-deactivation`, leaves maintenance active, and also succeeds on retry after the fault clears.
- Full local validation passes with `201` tests, `1,582` assertions, and `4` expected Windows symlink skips. PHPCS, the production build, and `git diff --check` pass. The validated runner `0.4.5` SHA-256 is `3dff6af93c69811a3c2277e37dfad0a5798f4a96dc982ff9f3d5503e546a1fe9`; no deployment or server mutation occurred.
- Feature Light Review: passed. The slice exercises existing rollback branches and the emergency marker through isolated fixture controls. The only runtime structural change is making the already-private emergency marker method protected for deterministic subclass failure simulation; no production input can select that subclass.
- Feature Bloat And Structure Review: completed against explicit base `5885dda`. Thirteen changed PHP files were measured. The new rollback maintenance failure test is `171` total lines and the shared fixture is `270`, both below the `300`-line threshold. The existing `6,354`-line runner, `508`-line rollback test, and `333`-line preflight test remain deferred to the full pre-release file-structure review; no feature-stage split was justified.
- Feature UI/UX Implementation Review: not applicable; this slice adds CLI failure fixtures, automated tests, and operator documentation only.
- Feature Security Review: passed. The fallback-failure seam is protected and reachable only through a test subclass; maintenance triggers exist only in isolated temporary fixtures. Production confirmation, path containment, private evidence, report permissions, and maintenance controls remain intact.
- Documentation Sync Audit: completed. Plugin metadata/stable tag remain `0.4.0`, runner identity remains `0.4.5`, and documentation now covers all rollback maintenance stop/retry states.

#### Combined Production-Simulation Apply And Rollback Slice (`0.4.6`, local only)

- Combined `files-and-database` production apply now passes the same enablement, confirmation, package-integrity, target-fingerprint, private-evidence, report-path, maintenance, and post-write identity gates as the existing single-scope paths.
- Combined execution is explicitly files first and database second. Enrolled symlinks are restored after file replacement, maintenance is re-established, and the database import then runs under the same protected window before final verification and deactivation.
- A successful automated cycle proves combined apply followed by the matching verified combined rollback. A second test forces the database import to fail after files have changed, verifies that maintenance and rollback evidence remain available, then restores both files and database successfully after the fault clears.
- Full local validation passes with `202` tests, `1,619` assertions, and `4` expected Windows symlink skips. PHPCS, the production build, and `git diff --check` pass. The validated runner `0.4.6` SHA-256 is `b41b638ff08f202f3f208f6e367c3e36764b0102cd2c64108b6bd1cbb78407dd`; no deployment or server mutation occurred.
- Feature Light Review: passed. The change reuses the existing production transaction, evidence, report, maintenance, verification, and rollback paths; no duplicate restore implementation or runtime bypass was introduced.
- Feature Bloat And Structure Review: completed against explicit base `5885dda`. Fourteen changed PHP files were measured. The new combined test is `132` total lines and the existing apply test is `277`, both below the `300`-line threshold. The `6,357`-line runner, `508`-line rollback test, and `333`-line preflight test remain architecture-sensitive and deferred to the full pre-release file-structure review; no feature-stage split was justified.
- Feature UI/UX Implementation Review: not applicable; the slice changes only the private server-runner CLI, reports, tests, and operator documentation.
- Feature Security Review: passed. The three-value scope allowlist is regression-checked, combined execution retains both exact confirmations and every immutable evidence gate, partial database failure remains maintenance-protected and rollback-ready, and no new runtime input or public WordPress surface was added.
- Documentation Sync Audit: completed. Plugin metadata and stable tag remain `0.4.0`; runner identity is `0.4.6`, and repository/operator documentation now describes combined files-first production simulation without implying actual-production availability.
- Approved runner `0.4.6` deployment completed on `hbf-staging` at `2026-07-21T16:04:05Z`. The deployed runner is owned by `handcraftedbotanicalformulas-com`, has mode `750`, and has SHA-256 `b41b638ff08f202f3f208f6e367c3e36764b0102cd2c64108b6bd1cbb78407dd`, exactly matching the reviewed local source. The prior runner is retained with its original ownership and mode as `alynt-backup-runner.php.pre-0.4.6-20260721-160405.bak`; private config remained unchanged at mode `640`.
- Runner health passed every check. Private config still records `production_restore_enabled: false`, `production_rollback_enabled: true`, and `production_restore_environment: production-simulation`.
- The read-only combined `files-and-database` preflight passed with `failure_count: 0` at `2026-07-21T16:05:04Z`. Target identity, complete plugin/theme/drop-in/symlink enrollment, staged file-tree and database integrity, write-control review, private report path, and fresh GridPane native-backup evidence all passed. Available space was `160,517,955,584` bytes against `12,861,995,379` required bytes.
- The preflight reported production apply unavailable, no destructive actions, no database import, no live-file overwrite, no pre-restore backup creation, no maintenance change, and no report write. Maintenance remains inactive. The site returns its expected login redirect and a final HTTP `200` after following it.
- After separate approval, fresh combined-scope recovery evidence was created successfully at `2026-07-21T16:07:49Z` while production apply remained disabled. All preflight and creation checks passed with zero failures; no database import, live-file overwrite, maintenance change, or other destructive action occurred.
- Private evidence is `PRODUCTION_PRE_RESTORE_EVIDENCE-staging-handcraftedbotanicalformulas-com-20260720-203243-files_and_database-20260721-160749.json`. Its database export is `621,895,864` bytes with independently matched SHA-256 `9863a6679df1a991787ffa3a11b6325a4ebcd27dfcd57e215c17808c24211ac9`. Its file archive is `922,197,401` bytes with independently matched SHA-256 `6766f5e972406f6209bbedd001e98503f60bc2782557a72b2081734810138c5f`; an independent full archive listing completed successfully.
- Evidence JSON, database export, and file archive are owned by `handcraftedbotanicalformulas-com` with mode `0640`. Approximately `158,927,298,560` bytes remained available afterward. Production apply remains disabled, rollback remains enabled, maintenance is inactive, and the site reaches final HTTP `200`.
- After separate config-change approval, private config was preserved as `config.json.pre-0.4.6-combined-apply-enable-20260721-161125.bak`. The active config changed only `production_restore_enabled` from `false` to `true`; rollback remains enabled, the environment remains `production-simulation`, and both active config and backup are site-user owned with mode `0640`.
- The enabled-state rehearsal used the real production action phrase and an intentionally wrong `--confirm-site`. The complete combined preflight passed with zero failures, all fresh evidence hashes and target/package fingerprints matched, and the only failed apply check was `confirmation_site_matches_target`.
- The refusal stopped at `failure_step: production-apply-preflight` with no maintenance activation, file replacement, symlink work, database import, or destructive action. Its private mode-`0640` report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-and-database-20260721-161234-3995d10f.json`. Runner health passes, maintenance remains inactive, and final HTTP status is `200`.
- After exact destructive confirmation, the runner `0.4.6` combined production-simulation apply completed successfully at `2026-07-21T16:17:45Z` with zero failures. Maintenance activated before target writes; the staged WordPress tree replaced the target first, one enrolled GridPane symlink was recreated with its exact target, and the staged database imported second under the same maintenance window.
- Immediate staged file-tree and database integrity checks passed before writes. Post-apply WordPress URL, site UUID, complete active-plugin/theme/drop-in/symlink inventories, symlink targets, root owner/group, and maintenance-active verification all passed before maintenance deactivated successfully. No emergency maintenance fallback was needed.
- The rollback-ready private mode-`0640` report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-and-database-20260721-161745-2a9eb27d.json`. Independent verification confirmed WordPress `7.0.2`, matching home/site URLs, the enrolled site UUID, active uploader and `blocksy-child`, successful database connectivity, exact GridPane symlink target, root owner/group `1005:2006`, inactive maintenance, passing runner health, and final HTTP `200` through the expected login redirect.
- After separate exact rollback confirmation, the matching combined rollback completed successfully at `2026-07-21T16:23:56Z` with zero blocking preflight failures and zero execution failures. The runner hash-verified both private recovery artifacts, restored files first, re-established maintenance, restored the pre-apply database second, passed every post-rollback identity check, and deactivated maintenance normally without emergency fallback.
- The private mode-`0640` rollback report is `RESTORE_PRODUCTION_ROLLBACK_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-20260721-162356.json`. Independent verification again confirmed WordPress `7.0.2`, matching URLs and site UUID, active uploader and `blocksy-child`, successful database connectivity, the exact GridPane symlink target, root owner/group `1005:2006`, inactive maintenance, passing runner health, and final HTTP `200`. The runner removed its temporary private extraction directory.
- The separately confirmed second combined apply was safely refused at `2026-07-21T16:28:33Z` before maintenance or writes. Its complete preflight passed, but `target_fingerprint_unchanged_since_pre_backup` correctly failed. The evidence recorded `database_size_bytes: 1,562,771,456`; after logical database rollback the current physical size is `1,556,414,464` bytes. Every other fingerprint field matches.
- This is expected storage-engine allocation drift after a logical SQL import, not an identity or content failure. The strict fingerprint gate remains unchanged. Cycle two must create fresh recovery evidence from the restored baseline rather than reusing cycle-one evidence or weakening the comparison.
- The private mode-`0640` refusal report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-and-database-20260721-162833-b3c6420e.json`. It records no maintenance activation, file replacement, database import, or destructive action. Maintenance is inactive, runner health passes, final HTTP status is `200`, and approximately `157,067,034,624` bytes remain available.
- Before cycle-two evidence creation, the enabled runner config was preserved as `config.json.post-cycle1-enabled-20260721-163200.bak` and the active config was atomically returned to `production_restore_enabled: false`, `production_rollback_enabled: true`, and `production_restore_environment: production-simulation`. Both configs are site-user owned with mode `0640`; maintenance remained inactive.
- After separate approval, fresh cycle-two combined-scope recovery evidence was created successfully at `2026-07-21T16:33:43Z` from the restored cycle-one baseline. All production preflight and evidence-creation checks passed with zero failures. No maintenance change, file replacement, database import, or destructive action occurred.
- The new private evidence is `PRODUCTION_PRE_RESTORE_EVIDENCE-staging-handcraftedbotanicalformulas-com-20260720-203243-files_and_database-20260721-163343.json`. Its `621,889,751`-byte database export has independently matched SHA-256 `f07cf20c1fde28e22e18057b7215246c0b8632dce6d954923a50c0fc3a0cace3`. Its `922,203,132`-byte files archive has independently matched SHA-256 `7e6ea335b6ff431c4c51bde851fdb1bfe70335d2d09798f6a88cabc900ab18e6`, and an independent full archive listing completed successfully.
- Evidence JSON, database export, and files archive are owned by `handcraftedbotanicalformulas-com` with mode `0640`. Approximately `155,522,662,400` bytes remain available. Production apply remains disabled, rollback remains enabled, maintenance is inactive, runner health passes, and final HTTP status is `200`.
- After separate approval, the active private config was preserved as `config.json.pre-cycle2-apply-enable-20260721-163343.bak`, then `production_restore_enabled` was set to `true`; rollback remains enabled and the environment remains `production-simulation`. Active config and backup are site-user owned with mode `0640`.
- The cycle-two enabled-state readiness rehearsal completed at `2026-07-21T16:38:07Z` using the real production action phrase and intentionally wrong `--confirm-site=wrong.staging.handcraftedbotanicalformulas.com`. The complete production preflight passed with zero failures, and every evidence freshness/hash, staged-package integrity, target fingerprint, enablement, and report-path check passed. The sole failed apply check was `confirmation_site_matches_target`.
- The private mode-`0640` refusal report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-and-database-20260721-163807-7a091ab0.json`. It records no maintenance activation, file replacement, database import, or destructive action. Maintenance is inactive, runner health passes, and final HTTP status is `200`. The second destructive combined apply now waits at its fresh exact target confirmation gate.
- After fresh exact destructive confirmation, runner `0.4.6` completed the second combined files-and-database production-simulation apply successfully at `2026-07-21T16:43:38Z` with zero failures. Maintenance activated before writes; staged files replaced the target first, the exact enrolled GridPane symlink was recreated, and the staged database imported second under the same protected maintenance window. Immediate staged-input integrity and all post-apply URL, UUID, plugin/theme/drop-in, ownership, symlink, and maintenance checks passed before normal maintenance deactivation.
- The rollback-ready private mode-`0640` apply report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-and-database-20260721-164338-0024408e.json`. Independent verification confirmed WordPress `7.0.2`, matching home/site URLs and site UUID, active uploader and `blocksy-child`, a complete successful database check, exact GridPane symlink and target, root owner/group `1005:2006`, passing runner health, inactive maintenance, final HTTP `200`, and approximately `155,474,505,728` bytes available.
- After verification, the enabled config was preserved as `config.json.post-cycle2-apply-success-20260721-164338.bak` and production apply was atomically relocked to `false`; rollback remains enabled and the environment remains `production-simulation`. The matching combined rollback now waits at its separate exact destructive confirmation gate.
- After separate exact destructive confirmation, the matching cycle-two combined rollback completed successfully at `2026-07-21T17:14:47Z` with zero blocking preflight failures and zero execution failures. The runner hash-verified both private recovery artifacts, restored files first, re-established maintenance, restored the cycle-two pre-apply database second, passed every post-rollback identity check, and deactivated maintenance normally without emergency fallback.
- The private mode-`0640` rollback report is `RESTORE_PRODUCTION_ROLLBACK_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-20260721-171447.json`. Independent verification confirmed WordPress `7.0.2`, matching home/site URLs and site UUID, active uploader and `blocksy-child`, a complete successful database check, exact GridPane symlink and target, root owner/group `1005:2006`, passing runner health, inactive maintenance, final HTTP `200`, and approximately `145,699,586,048` bytes available.
- Production apply remains relocked to `false`; rollback remains enabled and the environment remains `production-simulation`. Two complete combined files-and-database apply/rollback cycles have now passed on Linux with independent verification.
- Read-only post-cycle inventory found five retained private `.rollback-*` extraction trees under `production_restore_path`, including the latest successful cycle-two extraction. Each is approximately `1.65 GB`; together they use `8,253,126,916` bytes. Source inspection confirmed `restore_production_files_from_pre_backup()` creates and validates these trees but does not remove them after successful rollback. A focused patch must remove only the exact private extraction tree after complete rollback success, while retaining it on extraction, copy, database, verification, maintenance, or report failure when it may support recovery.
- The same inventory measured the preserved pre-`0.4.4` staged package at `2,311,564,190` bytes, the active staged package at `2,311,564,765` bytes, production pre-restore artifacts at `8,948,835,241` bytes, the newest local outbox package/sidecars at `1,021,661,555` bytes, and all small production reports at approximately `524 KB`. Recommended cleanup preserves the active staged package, the newest local outbox package/sidecars, all small reports/evidence JSON, current runner/config, and useful small backups; it removes the five stale extraction trees, the superseded pre-`0.4.4` staged copy, and large completed-cycle recovery SQL/archive artifacts. Expected recovery is approximately `19.5 GB`, subject to filesystem accounting.
- After separate exact approval, the documented rehearsal cleanup completed on `hbf-staging`. It removed only the five named `.rollback-*` extraction trees, the named pre-`0.4.4` staged copy, and eleven named completed-cycle recovery SQL/archive files. The active staged package, newest local outbox package plus sidecars, eight evidence JSON records, all `25` production reports, runner/config files, and small backups remain present.
- Available filesystem space increased from `145,699,430,400` to `174,162,296,832` bytes, an observed gain of `28,462,866,432` bytes after filesystem accounting. The restore path now contains only the active staged package (`2.4G`), production pre-restore evidence JSON totals `68K`, reports total `524K`, and the preserved newest outbox package/sidecars total `975M`. Production apply remains disabled, rollback remains enabled, maintenance is inactive, runner health passes, and final HTTP status is `200`.

#### Successful Rollback Extraction Cleanup Slice (`0.4.7`, local only)

- Successful files or combined rollback now retains its exact generated `.rollback-*` extraction path through verification and maintenance deactivation, writes the private rollback report first, and only then removes that exact direct child of `production_restore_path`. Extraction, copy, database, post-rollback verification, maintenance, and report-write failures return before cleanup and retain the tree for operator recovery.
- Cleanup reuses the existing restore-staging remover with an explicit production restore base. The remover now requires canonical containment, rejects a symlinked root, handles child symlinks before directory checks so links are unlinked rather than followed, catches traversal/runtime exceptions, and refuses names outside the generated `.rollback-*` direct-child boundary.
- CLI results expose the generated extraction path plus retained, cleanup-attempted, and cleanup-succeeded fields. Cleanup failure does not falsify a completed restore; it returns an explicit manual cleanup note while leaving the directory present.
- Focused regression coverage proves complete success removes the extraction tree only after a durable report, simulated report-write failure retains it, and rollback maintenance reactivation/deactivation failures retain their trees. Existing Linux-capable enrolled-symlink rollback coverage protects the external symlink target from cleanup; Windows continues to skip only tests that require actual symlink creation.
- Full local PHPUnit passes with `204` tests, `1,647` assertions, and `4` expected Windows symlink skips. Full PHPCS, production build, PHP syntax checks, focused debug/dangerous-pattern scan, and `git diff --check` pass. Validated local runner `0.4.7` SHA-256 is `f2fa8d004ee431dfe9ea54f8f4bb1cd48d9d7740521b39d60929371a3124fbcd`; no deployment or server mutation occurred in this slice.
- Feature Light Review: passed. Scope is confined to private CLI file operations, rollback result fields, focused tests, and operator documentation. The report-first ordering preserves recovery evidence, cleanup failures remain explicit, and no WordPress UI, AJAX, REST, cron, database schema, Drime API, or upload behavior changed.
- Feature Bloat And Structure Review: completed against explicit base `5885dda`. Fifteen changed PHP files were measured because the broader uncommitted production-restore feature remains in the worktree. The new cleanup test is `132` total lines, the maintenance test is `177`, and the shared fixture is `271`, all below the `300`-line threshold. The `6,399`-line runner and pre-existing `508`-line rollback and `333`-line preflight tests remain architecture-sensitive and deferred to full pre-release `02-FILE_STRUCTURE_REVIEW_PROMPT.md`; no low-risk feature-stage split was justified.
- Feature UI/UX Implementation Review: not applicable. This slice changes only the private server-runner CLI, file cleanup behavior, tests, and operator documentation.
- Feature Security Review: passed. Cleanup accepts no new CLI or config input, operates only on the internally generated exact path, requires direct-child and canonical containment, rejects a symlinked root, unlinks child symlinks without following them, and never runs before a durable private rollback report. No confirmed security defect requires a regression handoff.
- Documentation Sync Audit: completed. WordPress plugin metadata, stable tag, and latest Git tag remain `0.4.0`; standalone runner identity is `0.4.7`. `CHANGELOG.md`, `readme.txt`, runner documentation, restore runbook, and this plan now align on report-first successful cleanup and failed-rollback retention.
- After separate deployment approval, runner `0.4.7` was installed on `hbf-staging` with site-user ownership and mode `0750`. Server SHA-256 `f2fa8d004ee431dfe9ea54f8f4bb1cd48d9d7740521b39d60929371a3124fbcd` exactly matches the final reviewed local source. Proven runner `0.4.6` was preserved with its original ownership and mode as `alynt-backup-runner.php.pre-0.4.7-20260721.bak`, retaining SHA-256 `b41b638ff08f202f3f208f6e367c3e36764b0102cd2c64108b6bd1cbb78407dd`.
- Post-deployment runner health passed every check as `handcraftedbotanicalformulas-com`. Production apply remains disabled, rollback remains enabled, the environment remains `production-simulation`, maintenance is inactive, WordPress `7.0.2`, home URL, site UUID, and active uploader match enrollment, final HTTP status is `200`, and `174,100,344,832` bytes remain available. No preflight, evidence creation, apply, rollback, or other destructive runtime proof ran during deployment.
- After separate approval, runner `0.4.7` passed a read-only combined `files-and-database` preflight with `failure_count: 0` at `2026-07-21T18:05:34Z`. Fresh combined recovery evidence was then created successfully at `2026-07-21T18:06:00Z` while production apply remained disabled. The private evidence is `PRODUCTION_PRE_RESTORE_EVIDENCE-staging-handcraftedbotanicalformulas-com-20260720-203243-files_and_database-20260721-180600.json`; its `621,891,222`-byte database export has independently matched SHA-256 `2bfae211d00f2eebef3f0c3e55c017436c59f46669ee20e815e9d814cecd30f0`, and its `922,201,902`-byte file archive has independently matched SHA-256 `d43d20a25629bc62cf0bd34b57243f7e3729d0fc644d87624a7a70858771224b`. A complete archive listing passed. All three artifacts are site-user owned with mode `0640`; runner health passes, maintenance is inactive, final HTTP status is `200`, and `172,500,692,992` bytes remain available. No database import, live-file overwrite, maintenance change, restore apply, or rollback occurred.
- After separate approval, private config was preserved as `config.json.pre-0.4.7-cleanup-proof-enable-20260721-180600.bak` and only `production_restore_enabled` changed from `false` to `true`; active and backup configs remain site-user owned with mode `0640`. The enabled-state combined readiness attempt used the real action phrase with intentionally wrong `--confirm-site=wrong.staging.handcraftedbotanicalformulas.com`. Its complete preflight, fresh evidence hashes, staged-package integrity, target fingerprint, and all other gates passed; the sole failed check was `confirmation_site_matches_target`. The private mode-`0640` refusal report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-and-database-20260721-181137-9b736588.json`. It records no maintenance attempt, file replacement, symlink work, database import, possible database modification, or destructive action. Post-refusal UUID, uploader, active theme, enrolled GridPane symlink, inactive maintenance, and final HTTP `200` were independently verified. Production apply and rollback remain enabled at the exact destructive confirmation gate.
- After exact destructive confirmation, runner `0.4.7` completed the combined files-and-database apply successfully at `2026-07-21T18:15:25Z` with `failure_count: 0`. Maintenance activated before writes; staged files replaced the target first, the exact enrolled GridPane symlink was restored, and the staged database imported second under the same protected maintenance window. Every immediate staged-integrity, post-apply URL, UUID, plugin, theme, drop-in, owner/group, symlink, and maintenance check passed before normal maintenance deactivation. The rollback-ready private mode-`0640` report is `RESTORE_PRODUCTION_APPLY_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-files-and-database-20260721-181525-10375b79.json`. Independent verification confirmed WordPress `7.0.2`, matching URLs and UUID, active uploader and `blocksy-child`, all database tables healthy, root owner/group `1005:2006`, the exact GridPane symlink target, inactive maintenance, passing runner health, final HTTP `200`, and `172,448,186,368` bytes available. The enabled config was preserved as `config.json.post-0.4.7-combined-apply-success-20260721-181525.bak`, then production apply was relocked to `false` while rollback remained enabled. The production restore path contains zero pre-existing `.rollback-*` trees, providing a clean baseline for the matching rollback cleanup proof.
- After separate exact confirmation, the matching runner `0.4.7` combined rollback succeeded at `2026-07-21T18:22:34Z` with zero blocking preflight failures and zero execution failures. The runner hash-verified both recovery artifacts, restored files and the enrolled GridPane symlink, imported the pre-apply database, passed every post-rollback identity, ownership, drop-in, symlink, and maintenance check, and deactivated maintenance normally. Its durable private mode-`0640` report is `RESTORE_PRODUCTION_ROLLBACK_REPORT-staging-handcraftedbotanicalformulas-com-20260720-203243-20260721-182234.json`. After that report write succeeded, the final CLI result recorded cleanup attempted and succeeded, extraction retained false, and report written true. Independent filesystem verification confirmed the exact generated `.rollback-staging-handcraftedbotanicalformulas-com-20260720-203243-20260721-182240` path is absent and the complete `.rollback-*` count returned from zero to zero. Because the immutable report is intentionally serialized before cleanup, its embedded cleanup/report-written fields are the pre-cleanup snapshot; final CLI output plus direct filesystem inspection provide the post-cleanup evidence. Independent verification confirmed WordPress `7.0.2`, matching URLs and UUID, active uploader and `blocksy-child`, healthy database tables, root owner/group `1005:2006`, the exact GridPane symlink target, inactive maintenance, passing runner health, final HTTP `200`, and `172,475,428,864` bytes available. Production apply remains disabled and rollback remains enabled.

### Phase 5: Combined Apply And Rollback Rehearsals

- Local implementation and automated validation are complete for successful combined apply/rollback and for rollback after a database import failure following file replacement.
- After separate deployment approval, verify runner parity, health, and combined-scope preflight on `hbf-staging` without enabling apply or changing site state.
- Run combined files-and-database simulation only after a fresh evidence set and a separate exact destructive confirmation.
- Run an explicit rollback to the pre-restore state only after its separate exact confirmation.
- Repeat the apply/rollback cycle to prove it is not a one-off.
- Retain reports and clean large artifacts only after separate approval.

### Phase 6: Failure Injection

- Deterministic local failure-matrix implementation and automated coverage are complete.
- Prepare only a small set of high-value Linux runtime proofs after combined apply/rollback is implemented and reviewed.
- Execute any approved runtime proof on the production-simulation target only after a separate deployment and mutation confirmation.
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

Phase 7 progress on 2026-07-21:

- Component and end-to-end checks passed on `plugin-tester.local` and the proven `hbf-staging` production-simulation runtime.
- Full automated validation passes with `207` tests, `1,663` assertions, and `4` expected Windows symlink skips. PHPCS passes across `58` files, the production build passes, npm audit reports zero vulnerabilities, PHP syntax checks pass, and `git diff --check` reports no whitespace errors.
- Pre-release File Structure Review split oversized admin action/rendering traits and the `1,130`-line uploader test aggregate into focused files without changing public hooks, method names, behavior, or assertions.
- Pre-release Error Handling Review now reports queue persistence failures instead of presenting a successful scan, bounds each Drime restore file download to a configurable six-hour default, and aborts stalled admin folder/workspace requests after 30 seconds with actionable retry guidance.
- Pre-release WordPress Best Practices Review moved the private plugin's explicit text-domain loader from `plugins_loaded` to `init` priority `0`, matching current WordPress guidance while retaining bundled translation support.
- Pre-release Database Review confirmed there are no custom tables, raw SQL, post meta, user meta, or transients. Twelve prefixed plugin options use verified WordPress option storage with non-autoload writes and complete multisite-aware uninstall cleanup; no schema or migration work is required.
- Pre-release Performance Review found no release blocker: there are no N+1 or custom-query paths, admin assets are small and loaded only on the plugin screen, and large transfer/restore work is streamed and performed through cron or CLI rather than ordinary page rendering. Diagnostics are bounded. The non-autoloaded uploaded-file registry remains a low-priority capacity watch because it grows with upload history, but pruning it without a replacement tombstone strategy could requeue retained local backups and weaken remote-retention auditability; no speculative optimization was added.
- Pre-release Edge Cases Review found and fixed one High-severity concurrency risk: the ten-minute upload lock could expire during a long multipart transfer, allowing another cron worker to resume the same Drime session concurrently. Upload locks now use unique owner tokens, renew throughout multipart progress, can be released only by their owner, and persist resumable session state before the first part. A worker that loses ownership exits without clearing the replacement lock or changing shared retry/active state. Focused concurrency coverage and the complete suite pass with 209 tests, 1,674 assertions, and 4 expected Windows symlink skips; PHPCS and the production build pass.
- Pre-release Adversarial Test-Suite Review added behavioral capability and nonce rejection coverage for admin-post and AJAX guards, explicitly scoped to the mocked WordPress API layer. A reusable GitHub quality workflow now runs PHPUnit/PHPCS across PHP 7.4 and 8.5 plus the Node build, generated-asset verification, and dependency audit on pull requests and pushes to `main` or the repository's active `master` branch; release packaging depends on that gate. The expanded local suite passes with 214 tests, 1,682 assertions, and 4 expected Windows symlink skips. The dedicated Uninstall Review owns strengthening the remaining source-text uninstall checks.
- Pre-release Uninstall Review confirmed complete cleanup of all 12 plugin-owned options and both cron hooks on single-site and every multisite blog. The plugin creates no custom tables, transients, metadata, roles, capabilities, post types, taxonomies, or rewrite rules. Operator-owned backup packages, runner files, staging trees, reports, and recovery evidence remain intentionally preserved. Behavioral single-site/multisite execution coverage now replaces the earlier source-only cleanup assertions and passes with 4 tests and 15 assertions.
- Pre-release Internationalization Review found no untranslated user-facing string, text-domain mismatch, or placeholder defect across 320 PHP translation calls and the localized JavaScript interfaces. The POT was regenerated successfully and now contains the upload-lock ownership-loss message. PHP 8.5 deprecation output came from the shared WP-CLI phar's bundled dependencies and did not affect generation.
- Pre-release Accessibility Review confirmed explicit labels/descriptions for settings controls, native keyboard-operable buttons, polite atomic live regions and busy/disabled semantics for asynchronous destination controls, status/alert roles for notices, and captions plus header scopes for data tables. Heading order, focus behavior, responsive text containment, and WordPress-admin contrast usage passed; no code change was required.
- Pre-release Code Quality Review found no new duplication, naming defect, debug residue, or unjustified abstraction after the earlier refactors and hardening. The configured final gate passes: PHPCS across 58 production PHP files, 214 PHPUnit tests with 1,657 assertions and 4 expected Windows symlink skips, a successful production build, and clean diff whitespace validation. Prompt 07A evidence is complete; portable runner modularization remains the separately scoped architecture backlog item.
- Pre-release Documentation Review synchronized the README, WordPress readme, changelog, settings schema, and code annotations with the final release-candidate behavior. The docs now cover the renewable owner-aware upload lease, mandatory CI quality gate, and exact staging/production-simulation/actual-production boundary. All 28 user settings and 12 operational options are documented, local links resolve, plugin release-candidate `0.5.0` and runner `0.4.7` identities remain deliberately separate, and missing public-method `@since` annotations were completed with workspace history corrected to `0.2.0`; premature `0.5.1` annotations were normalized to the actual `0.5.0` candidate. PHPCS, build, and diff whitespace validation pass.
- Final pre-release Security Audit reviewed all 59 runtime PHP files and the 4 JavaScript source/served assets across input validation, output escaping, capability/nonce enforcement, secret redaction, Drime requests, archive/path/symlink safety, shell construction, and every staging/production-simulation destructive gate. No Critical, High, Medium, or Low defect was confirmed. Composer and npm audits report no known vulnerabilities; syntax, PHPCS, build, and the final 214-test/1,657-assertion suite pass with 4 expected Windows symlink skips. Security remains the final completed gate and the release-candidate security status is clear.
- The standalone runner remains intentionally deployable as one file. A future architecture slice should introduce modular runner source files and a deterministic build that emits the single portable `alynt-backup-runner.php`; this must preserve release hashing, direct deployment, rollback compatibility, and the complete runner test matrix.

### Phase 8: Release Candidate

- Prepare a release only after all production-simulation acceptance criteria pass.
- Use the normal release approval checkpoint before commit, tag, push, or publication.
- Production capability must remain disabled by default after installation or update.
- Release-candidate `0.5.0` metadata is prepared across the plugin header/constant, WordPress stable tag, npm metadata, POT header, changelog, upgrade notice, and `@since` annotations. The reusable quality workflow covers both `main` and the repository's active `master` branch, and release packaging depends on it. No release commit, tag, push, or publication has occurred.

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

Phase 0 through Phase 7 implementation, production-simulation rehearsals, full E2E validation, feature reviews, and ordered pre-release workflows are complete. Runner `0.4.7` is deployed on `hbf-staging` with exact reviewed SHA-256 parity and a proven zero-failure combined apply/rollback cycle, including report-first successful rollback extraction cleanup. Production apply is relocked, rollback remains enabled only for this approved production-simulation target, maintenance is inactive, and the site is healthy. Plugin release-candidate metadata is prepared as `0.5.0`; actual-production enrollment remains unavailable. The exact next step is the Git Operations Option C human approval checkpoint for release title, description, and upgrade notes before any commit, tag, push, or publication.
