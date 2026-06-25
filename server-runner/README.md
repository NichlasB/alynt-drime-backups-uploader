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
```

The WordPress plugin setting `server_outbox_path` should point at the same outbox path.

This runner currently supports `tar.gz` only. Additional archive formats should be added after live GridPane validation proves the first flow.

## Package Artifacts

A completed package writes:

```text
example-com-YYYYmmdd-HHMMSS.tar.gz
example-com-YYYYmmdd-HHMMSS.tar.gz.manifest.json
example-com-YYYYmmdd-HHMMSS.tar.gz.sha256
```

The plugin can detect the archive and read the manifest/checksum sidecars.
