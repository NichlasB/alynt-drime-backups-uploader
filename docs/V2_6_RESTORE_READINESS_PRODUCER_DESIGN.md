# V2.6 Restore Readiness Producer Design

Status: planning-only design record. This document does not approve implementation, release, deployment, live enablement, restore preparation runtime actions, restore execution, backup deletion, Drime mutation, arbitrary filesystem browsing, dashboard-side Drime credentials, or production data changes.

Related artifacts:

- `docs/STATUS_PAYLOAD.md`
- `docs/CENTRAL_DASHBOARD_READINESS.md`
- `docs/REMOTE_RESTORE_DISCOVERY.md`
- Dashboard `docs/V2_6_RESTORE_PREPARATION_EVIDENCE_DESIGN.md`
- Dashboard `docs/PROTOCOL_V2.md`

## Decision

The next restore-adjacent uploader slice should produce optional, support-safe `restore_readiness` evidence for the authenticated dashboard status payload.

This is **not** a restore action. It is a read-only summary that lets the dashboard display whether the client has enough local evidence to treat the newest source-level backup candidate as plausible, incomplete, stale, incompatible, or unknown.

## Scope

Allowed scope:

- add an optional top-level `restore_readiness` object to the existing status payload;
- derive evidence from local uploader-owned registries, source summaries, and support-safe sidecar/manifest metadata already available to the plugin;
- report source-level candidate summaries for `server` and `wpvivid`;
- keep all values redacted, bounded, and schema-version-1 additive;
- keep the dashboard consumer display-only.

Out of scope:

- restore execution;
- restore staging, package download, unpacking, import, overwrite, or production mutation;
- Drime API calls from the dashboard;
- Drime mutation, cleanup, retention, or backup-set deletion;
- arbitrary filesystem browsing;
- dashboard-side Drime credentials;
- raw local paths, filenames, package names, backup IDs, Drime object IDs, signed URLs, SQL, commands, package internals, or credential material;
- treating this evidence as a restore guarantee.

## Payload Shape

When implemented, the uploader may include:

```json
{
  "restore_readiness": {
    "schema_version": 1,
    "generated_at": "2026-10-02T12:00:00Z",
    "overall_state": "evidence_available",
    "candidates": [
      {
        "source": "server",
        "candidate_ref": "opaque-client-ref_123",
        "latest_backup_finished_at": "2026-10-02T01:30:00Z",
        "component_state": "complete",
        "checksum_state": "verified",
        "manifest_state": "compatible",
        "sidecar_state": "present",
        "age_seconds": 37800,
        "warnings": []
      }
    ]
  }
}
```

The dashboard `0.1.52` consumer already sanitizes this exact shape. The uploader producer should not add fields unless the dashboard sanitizer and docs are updated first.

Allowed values:

- `overall_state`: `not_reported`, `evidence_available`, `incomplete`, `stale`, `incompatible`, `unknown`;
- `source`: `server`, `wpvivid`;
- `component_state`: `complete`, `partial`, `missing`, `unknown`;
- `checksum_state`: `verified`, `failed`, `not_reported`, `unknown`;
- `manifest_state`: `compatible`, `incompatible`, `not_reported`, `unknown`;
- `sidecar_state`: `present`, `missing`, `not_reported`, `unknown`.

`candidate_ref` must be an opaque client-generated token using only letters, numbers, underscores, and hyphens. It must not be a filename, package name, path fragment, Drime ID, backup ID, or signed URL.

## Evidence Sources

### Server / Generic Outbox

The first server candidate can use the latest uploaded generic-outbox/server-runner registry record and local sidecar metadata already known to the uploader.

Recommended mapping:

- `component_state: complete` only when the candidate has a complete uploaded package record and expected sidecar evidence is present enough for manual restore discovery.
- `checksum_state: verified` only when existing checksum evidence is available and consistent.
- `manifest_state: compatible` only when manifest evidence is present and uses a supported shape.
- `sidecar_state: present` only when required support-safe sidecar evidence exists.
- otherwise report `partial`, `missing`, `not_reported`, or `unknown` with warning codes.

### WPvivid

WPvivid restore readiness should be conservative.

The uploader can summarize local/upload registry evidence for the latest WPvivid upload set, but it should not claim checksum or manifest verification unless such support-safe evidence is actually available. If WPvivid package completeness cannot be proven from uploader-owned records without exposing filenames or backup IDs, report `component_state: unknown` or `partial` and add a warning such as `restore_evidence_incomplete`.

## Warning Codes

Initial warning codes should be short sanitized identifiers, for example:

- `restore_evidence_incomplete`
- `restore_evidence_stale`
- `checksum_not_reported`
- `checksum_failed`
- `manifest_not_reported`
- `manifest_incompatible`
- `sidecar_missing`
- `component_partial`
- `component_missing`
- `source_not_configured`
- `no_uploaded_candidate`

Do not include human-readable paths, names, IDs, SQL, commands, or remote object details inside warnings.

## Implementation Plan

1. Add focused producer helpers that build `restore_readiness` from already-redacted source/registry evidence.
2. Keep the top-level object absent when no valid candidates are available.
3. Limit candidates to `server` and `wpvivid` for this slice.
4. Generate opaque candidate references from support-safe local facts, such as source key plus bounded timestamps or registry fingerprints, without leaking package names or IDs.
5. Add the object to the normal health/status payload with path mode disabled.
6. Add redaction tests proving forbidden strings and path-like values never appear.
7. Add status payload tests for no evidence, server evidence, WPvivid incomplete evidence, stale evidence, and forbidden field exclusion.
8. Run applicable ds2 feature workflows and focused validation before any release planning.

## Acceptance Criteria

- The dashboard can show Restore Readiness Evidence on Site Detail once the client reports safe evidence.
- Missing evidence is reported as absent, incomplete, unknown, or not reported, never as ready.
- The status schema remains version `1` because the field is optional and additive.
- Existing v1 pairing, polling, backup freshness, schedule management, cleanup preview, and remote-action behavior remain unchanged.
- No restore action is advertised or accepted.
- No raw paths, filenames, package names, backup IDs, Drime object IDs, signed URLs, SQL, commands, credentials, or package internals appear in the payload.

## Approval Gate

Before code implementation, confirm this design boundary and choose the first implementation target:

- status-payload producer only;
- no admin UI beyond existing status display;
- no client-side restore action;
- no dashboard protocol expansion beyond the already released `restore_readiness` consumer shape.
