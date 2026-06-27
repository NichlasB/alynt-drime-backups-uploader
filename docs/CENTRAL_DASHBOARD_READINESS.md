# Central Dashboard Readiness

This document records what Alynt Drime Backups Uploader already provides for a future central monitoring dashboard, and what must remain out of scope until a separate dashboard plugin project is started.

The dashboard plugin is not part of this repository.

## Current Uploader-Side Foundation

The uploader already has the foundation a future dashboard would need:

- A stable non-secret `site_uuid`.
- A redacted status payload contract in `docs/STATUS_PAYLOAD.md`.
- WP-CLI status output through `wp alynt-drime-backups status`.
- Queue, uploaded, failed, active-upload, producer, cron-health, and warning counts.
- Redaction tests that guard the default status payload against secret and path-like fields.
- Documentation that keeps restore, deletion, backup execution, and credential mutation out of the first dashboard version.

This is enough preparation for now. No central dashboard UI, enrollment flow, REST endpoint, or remote-control feature should be added to this plugin until that separate project is explicitly started.

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

Only after the read-only dashboard is proven should any remote actions be considered.

## Current Decision

For the current uploader plugin, preparation is complete when the docs clearly identify the existing status foundation and future boundaries. Starting the central dashboard plugin is a separate future project.
