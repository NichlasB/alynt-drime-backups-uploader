# V2.3 Schedule Apply Uploader Implementation Plan

Status: uploader-side local release-candidate implementation is in progress for the first approved scope. This document does not approve release, deployment, live-site writes, broad client enablement, schedule rollback, backup creation, cleanup/delete actions, restore actions, WPvivid schedule management, server-runner schedule management, arbitrary cron editing, or Drime credential changes.

Related artifacts:

- `docs/IMPLEMENTATION_PLAN.md`
- `docs/STATUS_PAYLOAD.md`
- `docs/CENTRAL_DASHBOARD_READINESS.md`
- dashboard `docs/V2_3_SCHEDULE_APPLY_IMPLEMENTATION_PLAN.md`
- dashboard `docs/PROTOCOL_V2.md`
- dashboard `docs/THREAT_MODEL_V2.md`

## Goal

Track the uploader-side implementation for a signed V2.3 `schedule_apply` action.

The first apply slice lets a separately opted-in dashboard ask this client to apply exactly one previously previewed cadence change for this plugin's own scan/upload schedule:

```text
action_type: schedule_apply
schedule_id: alynt_scan_upload
owner: alynt_uploader
```

This slice should safely move from the already released non-mutating `schedule_preview` action to one narrowly scoped client-side mutation. It must not turn the uploader into a generic settings, cron, backup-creation, restore, cleanup, or Drime-control endpoint.

## Boundary

Allowed:

- accept a signed `schedule_apply` intent only after separate V2 action opt-in;
- target only `alynt_scan_upload`;
- reference a fresh successful local `schedule_preview` result by preview action ID and preview fingerprint;
- revalidate current local schedule state before applying;
- apply only a locally supported cadence label;
- persist a redacted local action/audit result;
- report redacted latest action evidence through the existing authenticated status payload.

Not allowed:

- `schedule_rollback` runtime behavior;
- WPvivid schedule changes;
- server-runner schedule changes;
- disabling or pausing all backup production;
- arbitrary cron expressions or custom intervals;
- raw WP-Cron arrays, crontab lines, option names/values, usernames, paths, shell commands, package names, Drime IDs, credentials, signed URLs, or arbitrary settings payloads;
- fresh backup creation;
- cleanup/delete/retention actions;
- restore preparation or restore execution;
- accepting the V1 polling credential as action authorization.

## First Managed Schedule Target

Use only the schedule capability already implemented for preview:

- schedule ID: `alynt_scan_upload`
- owner: `alynt_uploader`
- implementation owner: this uploader plugin
- supported cadence choices: local allowlist only
- minimum interval: local allowlist/minimum only
- disable/pause: unavailable
- rollback: unavailable in this slice; rollback metadata and runtime rollback remain future separately approved work

Do not include `alynt_server_runner` until the plugin can prove ownership, safe mutation, and rollback for that schedule. Do not include WPvivid schedules in this slice.

## Capability Reporting Plan

Current `0.5.18` capability reporting advertises `schedule_preview` support and keeps schedule management read-only.

The future apply implementation should continue reporting the same `remote_actions.schedule_management` object, but only report apply support when all local conditions are true:

- V1 dashboard pairing is active.
- V2 action opt-in is active.
- Sodium signature verification is available.
- The local administrator has separately enabled schedule mutation.
- `alynt_scan_upload` can be identified through WordPress scheduling APIs.
- The current cadence maps to a supported allowlisted cadence label.
- At least one supported non-disable target cadence is available.

Recommended capability values for the apply-capable slice:

```json
{
  "preview_only": false,
  "apply_supported": true,
  "rollback_supported": false,
  "schedules": [
    {
      "schedule_id": "alynt_scan_upload",
      "owner": "alynt_uploader",
      "manageable": true,
      "supported_cadences": ["every_15_minutes", "every_30_minutes", "hourly"],
      "can_disable": false,
      "rollback_supported": false
    }
  ]
}
```

If schedule mutation is not locally enabled, keep `preview_only: true`, `apply_supported: false`, and `rollback_supported: false`.

## Local Opt-In Plan

Do not enable schedule mutation merely because V2 actions are opted in.

Recommended local policy:

1. Keep the existing V2 action opt-in as the prerequisite.
2. Add a separate administrator-controlled policy for schedule mutation.
3. Initially allow only `schedule_apply` for `alynt_scan_upload`.
4. Default the new policy to disabled on install and upgrade.
5. Show clear wp-admin copy that schedule apply changes only future scan/upload cadence and does not create backups immediately.
6. Allow local administrators to disable schedule mutation without revoking V2 read/action opt-in entirely.

If implementation can reuse the existing action opt-in UI without adding a schema migration, prefer an option-backed policy with explicit sanitization, nonce, capability checks, and redacted diagnostics.

## Preview Evidence Plan

`schedule_apply` must be bound to a successful local `schedule_preview` result.

During `schedule_preview`, persist enough redacted evidence for later apply validation:

- preview action ID;
- schedule ID;
- proposed cadence;
- capability version;
- current cadence;
- current next-run timestamp when known;
- current local schedule fingerprint;
- preview fingerprint;
- preview created timestamp;
- preview expiry timestamp.

Do not store raw cron arrays, raw option blobs, usernames, shell commands, paths, or arbitrary dashboard-provided current-state assumptions.

Recommended preview freshness window: 15 minutes.

## Action Validation Plan

The action-intent endpoint must reject `schedule_apply` unless all checks pass:

- V1 pairing exists and matches the dashboard origin/site UUID/dashboard site public ID.
- V2 remote actions are enabled.
- Schedule mutation policy is enabled.
- Key ID is known and not revoked.
- Ed25519 signature validates the canonical request.
- Request timestamp and expiry are inside the accepted window.
- Idempotency key is new or exactly matches a prior identical request.
- Action type is allowlisted.
- Schedule ID is exactly `alynt_scan_upload`.
- Proposed cadence is allowlisted and supported locally.
- Preview action ID and preview fingerprint exist locally.
- Preview is fresh and bound to the same site/schedule/proposed cadence/capability version.
- Current local schedule state still matches the preview baseline.
- No schedule action lock is active.

Recommended client result codes:

- `schedule_apply_unavailable`
- `schedule_apply_preview_missing`
- `schedule_apply_preview_expired`
- `schedule_apply_preview_stale`
- `schedule_apply_unsupported_cadence`
- `schedule_apply_lock_busy`
- `schedule_apply_persist_failed`
- `schedule_apply_succeeded`

## Mutation Plan

Use WordPress scheduling APIs for `alynt_scan_upload`; do not edit raw cron arrays directly.

Implementation should:

1. Acquire a schedule-action lock.
2. Re-read the current schedule state.
3. Compare it to the preview baseline.
4. Unschedule/reschedule only the plugin-owned scan/upload event.
5. Verify the new scheduled cadence/next-run evidence.
6. Persist a redacted action result with rollback unavailable.
7. Release the lock.

If any mutation step fails, preserve the old schedule where possible and report the exact support-safe failure code. If partial mutation becomes possible, stop implementation and add a stronger recovery design before continuing.

## Local Rollback Metadata

Do not capture or expose rollback metadata in this slice. The action result must report rollback unavailable (`rollback_available: false`) and an empty rollback expiry until a separate rollback design and implementation is approved.

A later approved rollback slice may add bounded local metadata capture, but it must not include raw cron arrays, raw option blobs, paths, commands, usernames, or arbitrary internal state.

## Status Payload And Action Result Plan

After apply, the status payload should report:

- `remote_actions.allowed_actions` includes `schedule_apply` only when local mutation policy is enabled.
- `remote_actions.schedule_management.apply_supported` is true only when apply is actually available.
- latest action summary may report `action_type: schedule_apply`.
- latest action result may include a redacted `schedule_apply` object with previous/applied cadence, next-run evidence, `rollback_available: false`, and an empty rollback expiry.

The status payload must not include raw cron arrays, raw crontab lines, filesystem paths, option names/values, Drime IDs, package names, credentials, or signatures.

## Admin UI Plan

The client wp-admin dashboard connection area should clearly show:

- V2 action opt-in status;
- whether schedule mutation is disabled or enabled locally;
- which schedule target is enabled, initially only `Alynt scan/upload`;
- a warning that dashboard schedule apply changes future scan/upload timing;
- a statement that it does not create backups, change WPvivid, change server-runner cron, change Drime credentials, restore, delete, or clean up anything.

Any local setting change must use existing admin action patterns:

- `manage_options` capability;
- nonce verification;
- sanitization;
- translatable strings;
- no secret logging.

## Test Plan

Focused uploader tests should cover:

- schedule mutation is disabled by default on install/upgrade;
- capability reporting keeps `apply_supported: false` until local policy is enabled;
- enabling policy reports `apply_supported: true` only for `alynt_scan_upload`;
- V2 disabled rejects `schedule_apply`;
- schedule mutation disabled rejects `schedule_apply`;
- invalid signature, expired timestamp, wrong site UUID, wrong dashboard site public ID, and wrong key ID fail closed;
- unknown schedule ID is rejected;
- unsupported cadence is rejected;
- raw cron/free-form cadence is rejected;
- apply without matching preview is rejected;
- expired preview is rejected;
- stale local schedule state is rejected;
- valid apply changes only the scan/upload schedule;
- duplicate idempotency returns the prior apply result without applying twice;
- failed persistence preserves the prior schedule;
- latest action status is redacted;
- `schedule_rollback` remains rejected.

Cross-plugin/local checks should cover:

- dashboard can preview and then apply one harmless cadence change on a low-risk target;
- dashboard action history reconciles the client result;
- a follow-up poll reports the new cadence;
- existing `scan_upload_now` still works;
- existing `schedule_preview` remains non-mutating;
- no WPvivid, server-runner, Drime, cleanup, delete, backup creation, or restore behavior changes.

## Feature Workflow Plan

Before implementation:

1. Confirm clean dashboard and uploader baselines or create restore points.
2. Approve the exact implementation scope and local test target.

After implementation and before release planning, run the applicable `wp-plugin-toolkit` ds2 feature workflows:

- `FEATURE_LIGHT_REVIEW_PROMPT.md`;
- `FEATURE_BLOAT_AND_STRUCTURE_REVIEW_PROMPT.md`;
- `FEATURE_UI_UX_IMPLEMENTATION_PROMPT.md` if client admin UI changes;
- `FEATURE_SECURITY_REVIEW_PROMPT.md`;
- documentation sync audit.

Before release/deploy, run a targeted ds3 pre-release subset or the full pre-release workflow if schema/lifecycle/packaging changes are introduced.

## Release And Rollout Plan

1. Implement uploader first with schedule mutation disabled by default.
2. Release uploader only after tests/reviews pass.
3. Implement dashboard controls second with apply hidden until client capability is visible.
4. Release dashboard second.
5. Pilot on one low-risk site with an intentionally harmless cadence change.
6. Verify local schedule, dashboard row, Site Detail, action history, and status payload after polling.
7. Expand only after the pilot proves no unintended schedule, backup, Drime, cleanup, delete, or restore mutation.

## Acceptance Criteria

- `schedule_apply` is impossible until V1 pairing, V2 action opt-in, and local schedule-mutation policy are active.
- `schedule_apply` is limited to `alynt_scan_upload`.
- Apply requires a fresh matching preview.
- Client revalidates current local schedule state.
- Client changes only the scan/upload cadence.
- Status/action evidence is redacted and support-safe.
- Status/action evidence reports rollback unavailable.
- `schedule_rollback` remains impossible.
- Existing V1 polling, backup-source evidence, V2.1 `scan_upload_now`, and V2.3 `schedule_preview` remain unchanged.

## Approval Gate Before Code

Before implementation begins, explicitly approve:

- first managed target: `alynt_scan_upload`;
- first apply scope: cadence change only, no disable/pause;
- no WPvivid schedule management;
- no server-runner schedule management;
- no `schedule_rollback` runtime behavior;
- dashboard and uploader repo restore points or clean baselines;
- local test target;
- whether release/deployment should wait for both repositories to pass relevant feature/pre-release workflows;
- first live pilot target, if any.
