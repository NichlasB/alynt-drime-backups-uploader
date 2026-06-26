# Backup Consistency Model

This document explains what kind of backup consistency the Alynt server backup runner provides today.

The short version: the MVP creates a logical WordPress backup package from a WP-CLI database export plus a filesystem archive. It is suitable for cautious restore testing and many low-write sites, but it is not a transactional filesystem snapshot.

## Current Model

The server runner creates one package in this order:

1. Run health checks.
2. Create a per-package work directory.
3. Export the database with WP-CLI when database export is enabled.
4. Write `manifest.json`.
5. Archive the WordPress file tree, database dump, and manifest into a `.tar.gz`.
6. Write manifest and checksum sidecars beside the completed archive.
7. Move the completed package into the outbox atomically.

The package manifest uses:

```json
"backup_type": "logical_wordpress_backup"
```

That value means the package is a coordinated logical backup, not a block-level or filesystem-level snapshot.

## Timing Fields

New server-runner packages include non-secret UTC timing fields:

| Field | Meaning |
| --- | --- |
| `created_at` | Package run start time. |
| `database_dump_started_at` | Time the WP-CLI database export started, or empty when database export is disabled. |
| `database_dump_finished_at` | Time the WP-CLI database export finished, or empty when database export is disabled. |
| `file_archive_started_at` | Time the runner began preparing the manifest/archive step after the database dump was ready. |

These fields help an operator understand package timing during restore inspection. They do not convert the package into a transactionally consistent snapshot.

## What Is Consistent

The package completion workflow is consistent:

- Archives are created in a work path before being moved into the outbox.
- The plugin ignores temporary and partial package names.
- The generic outbox producer waits for stable file size before queueing.
- The archive checksum can be verified before restore staging.

The database dump is internally produced by WP-CLI and is captured before the file archive starts.

The manifest records the site, producer, runner version, package ID, database dump name, file root, exclusions, backup type, and timing fields.

## What Is Not Guaranteed

The MVP does not freeze the whole WordPress site while the package is created.

Possible timing gaps:

- A file can change while the database dump is running.
- A file can change while `tar` is archiving the WordPress tree.
- A database row can change after the database export but before the file archive is complete.
- User uploads, cache writes, order updates, form submissions, and membership changes can occur during a backup unless the site is quieted by an external operational process.

This is normal for a logical backup workflow, but it matters more on high-write sites.

## Low-Write Sites

For brochure sites, low-write content sites, and staging sites, the current model is acceptable for MVP validation when restore testing passes.

Recommended operator checks:

- Schedule package creation during low traffic.
- Keep default cache and generated-backup exclusions.
- Verify and stage a restore before relying on the flow.
- Confirm the staged files and `database.sql` match the expected site.

## High-Write Sites

High-write sites need extra caution before production rollout.

Examples:

- WooCommerce stores.
- Membership sites.
- LMS sites.
- Busy form or booking sites.
- Sites with frequent media uploads or imports.

For these sites, the MVP should not be treated as a fully proven production backup strategy until a stricter consistency mode is designed and tested.

Potential future modes:

- Maintenance-window backups.
- Temporary maintenance mode during the database dump and file archive.
- Database export flags tuned for the site's engine and table mix.
- Host-level filesystem snapshots when available.
- Database replica or snapshot-based package creation.
- Application-level quieting for queues, imports, and scheduled writes.

## Restore Interpretation

During restore inspection, compare:

- `created_at`
- `database_dump_started_at`
- `database_dump_finished_at`
- `file_archive_started_at`
- `manifest.json`
- `database.sql`
- `RESTORE_NOTES.txt`

If the site is high-write and the timing window overlaps important activity, treat the package as potentially stale or internally mixed. Stage it, inspect it, and decide whether a newer backup or a maintenance-window package is required.

## Release Gate

Before using the server-runner producer on high-write production sites:

- Document the site class and write profile.
- Prove at least one restore to a staging or restore directory.
- Decide whether the logical backup model is acceptable for that site.
- Use a maintenance window or stricter future consistency mode when write loss or mixed state is unacceptable.

The first production rollout should favor low-write sites until the full create -> upload -> fetch -> verify -> staging restore loop has been proven repeatedly.
