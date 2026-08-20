# Central Dashboard Readiness

This document records what Alynt Drime Backups Uploader provides for the separate central monitoring dashboard, and what must remain out of scope unless a later protocol expands the approved boundary.

The dashboard plugin is not part of this repository.

## Current Uploader-Side Foundation

The uploader has the foundation the dashboard needs:

- A stable non-secret `site_uuid`.
- A redacted status payload contract in `docs/STATUS_PAYLOAD.md`.
- WP-CLI status output through `wp alynt-drime-backups status`.
- Queue, uploaded, failed, active-upload, producer, cron-health, and warning counts.
- Redaction tests that guard the default status payload against secret and path-like fields.
- Explicit V2.1 action opt-in for one bounded signed action, `scan_upload_now`.
- A signed action-intent endpoint that remains inert unless V1 pairing and separate V2.1 opt-in are both active.
- Documentation that keeps restore, deletion, fresh backup creation, cleanup, settings changes, and credential mutation out of the V2.1 action boundary.

No central dashboard UI should be added to this plugin. The dashboard remains a separate project.

The separate dashboard project preparation plan is recorded in `docs/CENTRAL_DASHBOARD_PROJECT_PLAN.md`.

## Future Dashboard Shape

The first dashboard version should be read-only monitoring.

Useful first-version fields:

- Site label from the dashboard system.
- Site UUID from the uploader.
- Plugin version.
- Queue count.
- Uploaded count.
- Failed count.
- Active upload state.
- Automatic scanning state.
- Server cron expectation state.
- Server outbox configured/readable state.
- WP-Cron disabled state.
- Cron health status and reason.
- Last observed scan runner and timestamps.
- Warning count and warning codes/messages.

The dashboard can use those fields to show which sites are healthy, which sites need attention, and which sites have not reported recently.

## Explicit Non-Goals

Do not add these to the uploader as part of dashboard preparation:

- A dashboard plugin UI.
- Remote restore.
- Remote package deletion.
- Remote backup execution.
- Remote settings changes.
- Remote Drime credential updates.
- Remote local-file cleanup.
- Public unauthenticated status endpoints.

Those features require their own design, threat model, tests, and release plan.

## Endpoint Boundary For The Future

If this uploader later exposes an endpoint for a dashboard, it must be disabled until the site is explicitly paired or enrolled.

Minimum future endpoint requirements:

- Explicit administrator opt-in.
- Site-specific pairing/enrollment.
- Scoped authentication separate from the Drime API token.
- Capability checks for local administrators.
- Rate limiting or abuse protection.
- Redacted payload only.
- No local filesystem paths by default.
- No Drime tokens, signed URLs, request bodies, database names, salts, cookies, nonces, or package contents.
- Tests proving the default external payload stays redacted.

Do not reuse local CLI path-mode output as an external dashboard payload. The CLI status command may include local paths for trusted server operators, but a remote dashboard endpoint should call the health summary with path output disabled.

## Safe Integration Direction

When the dashboard project starts, prefer this order:

1. Define the dashboard site's data model.
2. Define the enrollment/pairing model.
3. Define the uploader endpoint authentication model.
4. Add a read-only uploader endpoint that returns the redacted status payload.
5. Add dashboard polling and stale-site detection.
6. Add tests for redaction, authentication failure, disabled endpoint behavior, and schema compatibility.

V2.1 adds the first remote-action slice only after separate local opt-in and only for `scan_upload_now`. Any broader remote action must receive its own design, threat model, tests, and release plan.

## Planning Preparation

`docs/CENTRAL_DASHBOARD_PROJECT_PLAN.md` records the recommended separate-plugin shape:

- read-only monitoring first;
- dashboard-owned site registry and status snapshots;
- explicit site enrollment and pairing;
- dashboard polling before considering client push;
- a disabled-by-default V2.1 action endpoint that requires separate opt-in, signed requests, rate limiting, idempotency, and redaction tests;
- no remote restore, deletion, fresh backup creation, settings changes, local cleanup, or Drime credential changes in version 1 or V2.1.

## Current Decision

For the current uploader plugin, dashboard readiness is complete when the docs clearly identify the existing read-only status foundation, the explicit V2.1 action opt-in boundary, and the higher-risk future boundaries.
