# Producer Adapter Backlog Guide

This guide records how to decide whether a new backup producer adapter should be added to Alynt Drime Backups Uploader.

The plugin architecture is already producer-aware, but new adapters should be added only when there is a real site need, real package evidence, and a clear restore story.

## Current Position

Current supported sources:

- `wpvivid`: scans WPvivid local backup archives.
- `generic_outbox`: scans completed packages from the server runner or another process that writes stable archive files to the configured outbox.

The generic outbox is the preferred first choice for producer-agnostic server automation. A dedicated third-party adapter should be added only when generic outbox scanning cannot represent the package safely or ergonomically.

## When To Add A Dedicated Adapter

Add a dedicated adapter when at least one of these is true:

- The backup tool stores package metadata in WordPress options or files that must be read to know whether a set is complete.
- The package uses multi-part archives where incomplete sets are easy to upload accidentally without producer-specific logic.
- The package has sidecars, manifests, checksums, or restore metadata that should travel with the archive.
- The backup location is difficult for operators to configure reliably as a generic outbox.
- The producer needs source-specific warnings in admin/status output.

Do not add a dedicated adapter just because a backup tool exists. If the tool can write completed archives into the generic outbox with stable filenames and optional sidecars, prefer the generic outbox.

## Evidence Required Before Implementation

Before selecting a producer, collect:

- Example complete backup package files.
- Example incomplete/in-progress package files.
- Example multi-part package files, if the producer supports splitting.
- Any manifest, checksum, catalog, or metadata sidecars.
- The source path rules on LocalWP and GridPane or the target host.
- The WordPress option names or filesystem metadata needed to detect complete sets.
- Restore documentation from the producer.
- A small non-production restore proof for at least one package.

Do not implement an adapter from filenames alone when the producer has an internal catalog or multi-part package model.

## Adapter Implementation Checklist

When a target producer is chosen:

1. Confirm the producer key and never rename it after release without a migration.
2. Add a producer class that implements `Alynt_Drime_Backups_Uploader_Producer_Interface`.
3. Keep producer code focused on discovery and package metadata only.
4. Return normalized package records that the scanner, queue, uploader, and registry can process without producer-specific Drime logic.
5. Keep Drime upload behavior in the uploader layer.
6. Add fixture coverage for missing source, incomplete package, complete package, split package if supported, and metadata normalization.
7. Reuse `Alynt_Drime_Backups_Uploader_Test_Producer_Adapter_Assertions`.
8. Update settings and admin UI only if the producer needs a distinct path/configuration choice.
9. Update restore docs if the producer has a different restore proof path.
10. Update the status payload only for non-secret, dashboard-safe fields.

## Security And Redaction Rules

Adapters must not expose:

- Backup encryption keys.
- Drime tokens or signed URLs.
- Database credentials.
- Cookies, nonces, salts, or secrets.
- Raw package contents.
- Local paths in dashboard-safe payloads.

Producer diagnostics should use stable codes, basenames, counts, and non-secret metadata. Use the plugin logger so diagnostics pass through the existing redaction path.

## Backlog Decision Template

Use this template before starting a future adapter:

```text
Producer name:
Why generic outbox is not enough:
Sites needing it:
Package samples collected:
Incomplete package samples collected:
Split package model:
Metadata source:
Restore proof available:
New settings needed:
New admin UI needed:
New status fields needed:
Security concerns:
Recommended scope:
```

## Current Decision

No additional third-party producer is selected for implementation yet.

The next real producer adapter should start only after a target producer is chosen and the package evidence above has been collected.
