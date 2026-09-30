# V2.4 Cleanup Preview Uploader Implementation Plan

Status: first uploader-side runtime slice implemented locally for review. This document narrows the first V2.4 cleanup step to a signed, non-mutating `cleanup_preview` action. It does not approve release, deployment, live-site enablement, `cleanup_apply`, Drime retention/delete, backup-set deletion, restore behavior, arbitrary filesystem browsing, or dashboard-side Drime credential storage.

Related artifacts:

- `docs/IMPLEMENTATION_PLAN.md`
- `docs/STATUS_PAYLOAD.md`
- dashboard `docs/V2_4_CLEANUP_RETENTION_DESIGN.md`
- dashboard `docs/V2_4_CLEANUP_PREVIEW_IMPLEMENTATION_PLAN.md`
- dashboard `docs/PROTOCOL_V2.md`
- dashboard `docs/THREAT_MODEL_V2.md`

## Goal

Plan the uploader-side implementation for a non-mutating `cleanup_preview` action.

The action lets an explicitly opted-in dashboard ask this client for support-safe aggregate evidence about cleanup candidates that are owned by Alynt Drime Backups Uploader.

The preview should answer:

- whether cleanup preview is locally enabled;
- which allowlisted cleanup categories are supported;
- how many eligible items exist per category;
- approximate bytes per category and total;
- which bounded age band and reason code applies;
- whether preview is blocked, unsupported, unsafe, or unavailable.

The preview must not delete files, mutate registries, change queues, change schedules, call Drime, delete Drime objects, delete backup sets, restore data, browse arbitrary paths, expose raw paths, or change credentials.

## Boundary

Allowed in this first implementation slice:

- advertise `remote_actions.cleanup_management` only after V1 pairing, V2 action opt-in, Sodium support, and a separate local cleanup-preview policy are active;
- accept a signed `cleanup_preview` intent only after the normal V2 action-intent validation path passes;
- support only allowlisted category slugs and scope constants;
- compute aggregate evidence from uploader-owned safe cleanup sources;
- persist a redacted action result for dashboard reconciliation;
- report the latest redacted action evidence through the existing authenticated status payload.

Not allowed:

- `cleanup_apply` runtime behavior;
- deleting local files or local records;
- deleting Drime files or changing remote retention;
- deleting backup sets;
- cleaning WPvivid backups directly;
- cleaning server-runner artifacts outside explicit uploader-owned safe categories;
- restore preparation or restore execution;
- arbitrary filesystem browsing;
- raw paths, filenames, package names, Drime IDs, backup IDs, signed URLs, command strings, SQL, raw registry payloads, or delete criteria in requests or results;
- accepting the V1 polling credential as action authorization.

## First Preview Scope

Initial scope is intentionally narrower than all local cleanup features that already exist.

Recommended first category:

| Category | Description | Initial behavior |
| --- | --- | --- |
| `uploader_temp_artifacts` | Uploader-owned temporary/staging artifacts that are safe to summarize without paths. | Preview only; aggregate counts/bytes/age bands. First implementation reports stale active upload bookkeeping as aggregate evidence. |

Potential later category after separate proof:

| Category | Description | Requirement |
| --- | --- | --- |
| `stale_uploader_transient_records` | Uploader-owned stale transient bookkeeping records that are not active queue/registry evidence. | Separate design and tests proving no backup evidence loss. |

Do not include server-runner local package cleanup, restore staging cleanup, WPvivid backup cleanup, or Drime retention cleanup in the first remote preview. Those flows already have local/operator contexts and should not be exposed remotely until separately designed.

## Capability Reporting Plan

The status payload adds `remote_actions.cleanup_management` as an optional schema-1 additive object.

Report cleanup preview support only when all local conditions are true:

- V1 dashboard pairing is active.
- V2 action opt-in is active.
- Sodium signature verification is available.
- The local administrator has separately enabled cleanup preview.
- At least one cleanup preview category is safely available.

Recommended preview-capable values:

```json
{
  "cleanup_management": {
    "protocol_version": 2,
    "capability_version": 1,
    "enabled": true,
    "preview_supported": true,
    "apply_supported": false,
    "supported_categories": [
      "uploader_temp_artifacts"
    ],
    "requires_fresh_preview": true,
    "max_preview_age_seconds": 900
  }
}
```

If cleanup preview is not locally enabled, report `cleanup_management` unavailable without adding `cleanup_preview` to `allowed_actions`.

Do not report `apply_supported: true` until a future `cleanup_apply` slice is separately planned, implemented, tested, released, and explicitly enabled.

## Local Opt-In Plan

Do not enable cleanup preview merely because V2 actions are opted in.

Recommended local policy:

1. Keep existing V2 action opt-in as a prerequisite.
2. Add a separate administrator-controlled cleanup-preview policy.
3. Initially allow only `cleanup_preview`.
4. Default the policy to disabled on install and upgrade.
5. Show clear wp-admin copy that cleanup preview is read-only and does not delete files.
6. Allow local administrators to disable cleanup preview without revoking V2 action opt-in entirely.

Prefer an option-backed policy with explicit sanitization, nonce checks, capability checks, and redacted diagnostics if a later implementation can reuse existing action opt-in UI patterns without a schema migration.

## Request Validation Plan

The action-intent endpoint must reject `cleanup_preview` unless all checks pass:

- V1 pairing is active.
- V2 remote actions are enabled.
- Sodium verification is available.
- Dashboard signature, route, method, body hash, timestamp, site UUID, dashboard site public ID, expiry, and idempotency checks pass.
- Cleanup preview local policy is enabled.
- `cleanup_preview.capability_version` equals `1`.
- `cleanup_preview.scope` equals `safe_local_uploader_owned`.
- Every requested category is allowlisted and locally supported.
- No forbidden field appears in the request body.
- No other cleanup/delete/apply/restore payload is present.

Reject and persist a redacted failure state for:

- `cleanup_preview_unavailable`
- `cleanup_preview_opt_in_required`
- `cleanup_preview_unknown_category`
- `cleanup_preview_scope_not_allowed`
- `cleanup_preview_forbidden_field`
- `cleanup_preview_local_state_unavailable`
- `cleanup_preview_unsafe_local_state`

## Preview Evaluation Plan

The evaluator must be read-only.

It may:

- inspect uploader-owned safe roots or registries that are already known to the plugin;
- aggregate eligible item count;
- aggregate approximate bytes;
- bucket age into stable labels such as `older_than_7_days`;
- return stable reason codes.

It must not:

- delete or rename files;
- write cleanup markers;
- mutate registry, queue, failed, uploaded, remote-action, schedule, or restore state;
- call Drime;
- recurse outside an internally allowlisted safe root;
- include raw item paths, filenames, package names, backup IDs, Drime IDs, raw sidecar contents, raw registry payloads, or exception traces in output.

## Result Shape

Recommended support-safe action result:

```json
{
  "action_type": "cleanup_preview",
  "state": "succeeded",
  "result_code": "cleanup_preview_ready",
  "result_summary": "Cleanup preview is ready. No files or records were changed.",
  "cleanup_preview": {
    "capability_version": 1,
    "scope": "safe_local_uploader_owned",
    "preview_fingerprint": "sha256-redacted-preview-fingerprint",
    "expires_at": "2026-10-01T12:15:00Z",
    "categories": [
      {
        "category": "uploader_temp_artifacts",
        "eligible_count": 3,
        "approx_bytes": 10485760,
        "age_band": "older_than_7_days",
        "reason_code": "stale_temp_artifacts"
      }
    ],
    "total_eligible_count": 3,
    "total_approx_bytes": 10485760
  }
}
```

The `preview_fingerprint` should bind the redacted preview scope, category list, totals, reason codes, and expiry so a future `cleanup_apply` design can decide whether revalidation is possible. It must not be computed from or expose raw paths or object identifiers.

## Status Payload Reporting Plan

After a later implementation:

- `remote_actions.allowed_actions` may include `cleanup_preview` only when local cleanup preview policy is enabled.
- `remote_actions.cleanup_management.preview_supported` may be true only when preview is actually available.
- `remote_actions.cleanup_management.apply_supported` must remain false.
- latest action summary may report `action_type: cleanup_preview`.
- latest action result may include the redacted `cleanup_preview` object.

The status payload must not include paths, filenames, package names, backup IDs, Drime identifiers, credentials, tokens, cookies, nonces, salts, signatures, SQL, command strings, raw request bodies, raw response bodies, raw registry payloads, or arbitrary delete criteria.

## Test Plan

Focused unit/integration tests for this implementation:

- cleanup preview disabled by default;
- V2 disabled rejects `cleanup_preview`;
- cleanup preview policy disabled rejects `cleanup_preview`;
- unknown category is rejected;
- unsupported scope is rejected;
- forbidden fields are rejected;
- request with paths, URLs, SQL, commands, raw criteria, or object IDs is rejected;
- preview evaluator is non-mutating;
- preview result contains aggregate counts/bytes only;
- result redaction rejects paths, filenames, package names, backup IDs, Drime IDs, and raw registry payloads;
- `cleanup_apply` remains rejected;
- existing `scan_upload_now`, `schedule_preview`, `schedule_apply`, and `schedule_rollback_preview` behavior remains unchanged;
- no WPvivid, server-runner, Drime, delete, backup creation, restore, or schedule behavior changes.

## Workflow Gates

Before release/deployment:

1. Update dashboard and uploader protocol/status docs.
2. Recommend or create a restore point before edit-heavy work.
3. Run relevant ds2 feature workflows for both repos after implementation.
4. Run targeted ds3 pre-release workflows before any release.
5. Release uploader support before dashboard UI depends on it.
6. Pilot on one low-risk site only after explicit release/deploy/live enablement approval.

Before any future `cleanup_apply`:

- prove preview-only behavior in release and pilot;
- create a separate apply design;
- update protocol and threat model;
- define restore-point requirements;
- add high-friction confirmation UX;
- prove stale preview rejection and non-overbroad cleanup.

## Acceptance Criteria For This Implementation Slice

- A dedicated uploader-side `cleanup_preview` implementation plan exists.
- The plan limits the first remote cleanup step to non-mutating preview only.
- `cleanup_apply`, Drime retention/delete, backup-set deletion, restore, arbitrary filesystem browsing, and dashboard Drime credentials remain unavailable.
- Status payload updates are additive schema-1 fields.
- Runtime code remains preview-only: no release, deploy, push, live-site change, database change, or cleanup behavior is introduced by this local implementation slice.
