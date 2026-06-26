# Producer Adapters

Producer adapters let the plugin discover completed local backup packages without tying Drime uploads to one backup tool.

The scanner asks each registered producer for normalized package records. The queue, uploader, registry, diagnostics, and restore tooling should be able to handle those records without knowing which producer created the package.

## Current Producers

| Producer Key | Class | Purpose |
| --- | --- | --- |
| `wpvivid` | `Alynt_Drime_Backups_Uploader_WPvivid_Producer` | Discovers completed local WPvivid ZIP archives and WPvivid-listed split archive sets. |
| `generic_outbox` | `Alynt_Drime_Backups_Uploader_Generic_Outbox_Producer` | Discovers completed archive packages from the configured server outbox. |

## Interface Contract

Every producer must implement `Alynt_Drime_Backups_Uploader_Producer_Interface`.

Required methods:

| Method | Requirement |
| --- | --- |
| `key()` | Return a stable machine key. Do not rename this after release unless a migration is also implemented. |
| `label()` | Return a human-readable label for admin/status surfaces. |
| `scan()` | Return a scan result with `directory`, `candidates`, and `errors`. |

The scan result shape is:

```php
array(
	'directory'  => '/absolute/source/path',
	'candidates' => array(),
	'errors'     => array(),
)
```

`candidates` must contain only completed, stable packages that are safe to queue.

## Package Record Shape

Each candidate must include the legacy fields still used by the queue and uploader:

| Field | Type | Requirement |
| --- | --- | --- |
| `signature` | string | Stable unique ID for this local package/version. |
| `path` | string | Absolute local path to the archive that will be uploaded. |
| `name` | string | Archive basename. |
| `size` | int | Archive size in bytes when known. |
| `mtime` | int | Local modified timestamp when known. |

Each candidate should also include the producer-neutral fields:

| Field | Type | Requirement |
| --- | --- | --- |
| `producer_key` | string | Must match `key()`. |
| `producer_label` | string | Must match `label()`. |
| `package_id` | string | Producer-level package ID. Prefer a manifest ID when available. |
| `filename` | string | Archive basename, usually same as `name`. |
| `modified_time` | int | Normalized local modified timestamp. |
| `backup_set_id` | string | Shared ID for related package parts, or `package_id` for single-file packages. |
| `backup_set_part` | string | Optional producer-specific part label. |
| `backup_set_total` | int | Total files in the set, or `1` for single-file packages. |
| `manifest_path` | string | Optional local manifest sidecar path. |
| `checksum_path` | string | Optional local checksum sidecar path. |
| `checksum_algorithm` | string | Optional checksum algorithm, for example `sha256`. |
| `checksum_value` | string | Optional checksum value. |
| `site_url` | string | Optional source site URL from package metadata. |
| `created_at` | int | Optional package creation timestamp. |
| `metadata` | array | Producer-specific non-secret metadata. |

Producer-specific fields are allowed when needed, but upload behavior should not depend on them unless the logic is deliberately producer-specific. Existing example: WPvivid set-aware local deletion uses the `wpvivid` metadata block.

## Stability Rules

Adapters must not queue files that may still be changing.

Use the same safety model as the existing producers unless the source format gives a stronger completion signal:

- Ignore temporary or partial filenames.
- Require the file to be readable.
- Require non-zero file size.
- Require the file to be older than `min_file_age_seconds`.
- Require the size to remain unchanged across scans.
- Prefer atomic producer completion, where the backup tool writes to a temporary path and renames only completed files into the scanned directory.

If a producer has manifest/checksum sidecars, read them only after the archive itself is stable.

## Registration

The default producers are created in `Alynt_Drime_Backups_Uploader_Scanner`.

Future adapters should be registered there or behind a deliberate producer registry abstraction. Avoid adding Drime upload logic to producer classes. A producer discovers packages; the uploader ships queued package records.

## Diagnostics And Redaction

Producer diagnostics may include operational details such as filenames, basenames, producer keys, and non-secret metadata.

Do not include:

- API tokens.
- Database credentials.
- Cookies or nonces.
- Full request bodies.
- Presigned upload URLs.
- Secret backup encryption keys.

Use the plugin logger so diagnostics go through the existing redaction and retention path.

## Test Expectations

Each producer should have tests for:

- No candidates when the source path is missing or disabled.
- Unreadable source handling.
- Temporary/partial file exclusion.
- Stability gating across scans.
- Final archive detection.
- Manifest/checksum sidecar parsing when supported.
- Normalized package fields.
- Queue/uploader boundary preservation for any new metadata required after upload.

If the producer has a unique multi-file package model, add fixture-based tests proving incomplete sets are not queued and complete sets are represented consistently.

Use `Alynt_Drime_Backups_Uploader_Test_Producer_Adapter_Assertions` for the shared normalized package-shape checks so every producer is held to the same baseline contract.

## Compatibility Notes

The producer key becomes part of persisted queue, uploaded-registry, failed-registry, diagnostics, and future monitoring payloads. Treat it as a stable contract.

Adding a producer should not require changes to Drime client behavior. If it does, first check whether the package record shape needs a generic field instead of a producer-specific branch.
