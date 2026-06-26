# Alynt Server Backup Runner

The server runner creates backup packages that the WordPress plugin can detect through the generic outbox producer.

This first runner is intentionally conservative:

- It is site-local and config-driven.
- It writes packages to a configured outbox directory.
- It uses WP-CLI for database exports.
- It uses `tar` for WordPress file archives.
- It writes manifest and SHA-256 sidecars.
- It writes temporary files first, then atomically renames completed artifacts into the outbox.

## Commands

```bash
php alynt-backup-runner.php health --config=/path/to/config.json
php alynt-backup-runner.php run --config=/path/to/config.json
php alynt-backup-runner.php list --config=/path/to/config.json
php alynt-backup-runner.php verify --config=/path/to/config.json --package=/path/to/package.tar.gz
php alynt-backup-runner.php inspect --config=/path/to/config.json --package=/path/to/package.tar.gz
php alynt-backup-runner.php stage-restore --config=/path/to/config.json --package=/path/to/package.tar.gz
```

Use server cron to call `run`, then call the plugin WP-CLI workflow to scan/upload the resulting package, for example:

```bash
php /path/to/alynt-backup-runner.php run --config=/path/to/config.json
wp --path=/var/www/example.com/htdocs alynt-drime-backups run --max-uploads=1
```

## GridPane Shape

For a GridPane site, keep the outbox outside the public web root when possible:

```text
/var/www/example.com/private/alynt-drime-backups/outbox
/var/www/example.com/private/alynt-drime-backups/work
/var/www/example.com/restores/alynt-drime-backups
```

The WordPress plugin setting `server_outbox_path` should point at the same outbox path.

The plugin settings screen can generate a non-secret `config.json` snippet for the current site. Review the generated paths, save it beside `alynt-backup-runner.php`, then run the generated health command before adding cron.

This runner currently supports `tar.gz` only. Additional archive formats should be added after live GridPane validation proves the first flow.

## Resource Safety

The runner health check verifies that `work_path`, `outbox_path`, and `restore_path` are writable and have at least `minimum_free_space_bytes` available. The default minimum is 1 GB when the setting is omitted.

The health check also verifies that `work_path` and `outbox_path` are on the same filesystem device, because completed archives are renamed from the work directory into the outbox. Keep both paths on the same mounted filesystem for atomic completion.

## Package Artifacts

A completed package writes:

```text
example-com-YYYYmmdd-HHMMSS.tar.gz
example-com-YYYYmmdd-HHMMSS.tar.gz.manifest.json
example-com-YYYYmmdd-HHMMSS.tar.gz.sha256
```

The plugin can detect the archive and read the manifest/checksum sidecars.

## Restore Staging

The first restore flow is intentionally non-destructive. `stage-restore` verifies the package and sidecars, creates a new directory under `restore_path`, extracts the archive there, and writes `RESTORE_NOTES.txt`.

It does not import `database.sql`, does not overwrite the live WordPress path, and refuses to use an existing restore directory.

See [docs/RESTORE_RUNBOOK.md](../docs/RESTORE_RUNBOOK.md) for the operator runbook, GridPane staging checks, and the currently gated manual disaster restore outline.
