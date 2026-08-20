# Central Dashboard Plugin Project Plan

This plan defined the separate dashboard plugin for monitoring sites that run Alynt Drime Backups Uploader. It remains as historical planning context; current runtime protocol details live in the dashboard repository's V1/V2 protocol and threat-model docs.

The dashboard plugin is a separate project. This document started as planning preparation and should not be treated as the current implementation source of truth for V2.1.

## At A Glance

- Build a control-center WordPress plugin installed on one dashboard website.
- Confirmed dashboard host site: `control-sitesmanage live-only`.
- Confirmed dashboard plugin name: `Alynt Drime Backups Dashboard`.
- Enroll multiple client/managed sites that have Alynt Drime Backups Uploader installed.
- Show a simple read-only status overview for each site.
- Focus version 1 on monitoring only: no remote restore, delete, backup execution, settings changes, or Drime credential changes.
- Use the uploader's existing `site_uuid` and redacted status payload as the foundation.
- V2.1 adds a disabled-by-default uploader action endpoint only after explicit V1 pairing, separate action opt-in, signed request verification, rate limiting, idempotency, and redaction tests.

## Confirmed Project Decisions

These decisions are approved as the starting point for the future dashboard plugin project:

| Decision | Value |
| --- | --- |
| Dashboard host site | `control-sitesmanage live-only` |
| Host WordPress path | `/var/www/control.sitesmanage.com/htdocs` |
| Dashboard plugin name | `Alynt Drime Backups Dashboard` |
| Version 1 scope | Read-only monitoring |
| Enrollment direction | Dashboard-generated pairing token, entered on client sites |
| Status direction | Dashboard polls enrolled client sites |
| Remote actions in v1 | Not included |
| Uploader endpoint state today | V1 read-only status endpoint implemented; V2.1 signed action-intent endpoint implemented locally and disabled until separate opt-in |
| Repo creation state | Dashboard repository exists separately |

## Why This Comes After Single-Site Status

The dashboard needs a stable answer to this question before it can be useful:

```text
What does "healthy backup status" mean for one site?
```

That single-site shape now exists through:

- `docs/STATUS_PAYLOAD.md`
- `docs/CENTRAL_DASHBOARD_READINESS.md`
- `wp alynt-drime-backups status --format=json`
- `C:\Users\Captain\Documents\AI Workflows\Task Workflows\WordPress\scripts\alynt-drime-backups\get-site-backup-status.ps1`

The PowerShell helper is an operator-side audit tool, not a dashboard endpoint. Its compact reports are useful for shaping the dashboard UI and alert language.

## Version 1 Goal

Create a read-only dashboard that answers, at a glance:

- Which sites are connected?
- Which sites are healthy?
- Which sites have not reported recently?
- Which sites have failed uploads, queued uploads, active uploads, or warnings?
- Which sites have server backups configured?
- Which sites have WPvivid uploads configured or recently observed?
- Which sites have automatic scanning enabled?
- Which sites show server cron health problems?
- Which plugin version is each site running?

## Suggested Dashboard Views

### Site List

One row per enrolled site:

- Site label
- Site URL or domain
- Environment label such as production, staging, or local
- Overall status: Working, Needs attention, Not reporting, Not configured
- Last report time
- Plugin version
- Queue count
- Failed count
- Warning count
- Server backups: configured / not configured
- WPvivid uploads: configured or recently observed / not observed
- Cron health

### Site Detail

One page per enrolled site:

- At-a-glance status summary
- Source status:
  - server/generic outbox
  - WPvivid
- Drime destination summary without secrets
- Retention summary
- Schedule and cron health summary
- Recent warning codes/messages
- Recent status history

### Attention Queue

A filtered view for sites needing action:

- failed uploads
- queued uploads that are not draining
- no recent report
- plugin inactive or old version
- server outbox not readable
- cron expected but not observed
- warning count greater than zero

## Dashboard Data Model

Suggested first tables/options:

```text
dashboard_sites
- id
- site_uuid
- site_label
- site_url
- environment
- enrollment_status
- last_seen_at
- plugin_version
- status_summary
- created_at
- updated_at

dashboard_status_snapshots
- id
- dashboard_site_id
- reported_at
- payload_schema_version
- overall_status
- queue_count
- uploaded_count
- failed_count
- active_upload
- warning_count
- cron_status
- payload_json
```

For a small first version, WordPress custom database tables may be cleaner than options because the dashboard will store repeated status snapshots.

## Enrollment Model

Recommended v1 flow:

1. In the dashboard plugin, create a new site entry.
2. Dashboard generates a one-time pairing secret or site token.
3. Operator pastes that token into Alynt Drime Backups Uploader on the client site.
4. Client site enables the dashboard endpoint explicitly.
5. Dashboard verifies the site by fetching the redacted status payload.
6. Dashboard stores the site UUID and starts polling.

The dashboard token must be separate from the Drime API token. Never send, copy, or reuse the Drime token for dashboard enrollment.

## Uploader-Side Future Endpoint

The uploader currently has no public dashboard endpoint by default.

If the dashboard project starts, add a disabled-by-default uploader endpoint with these requirements:

- administrator opt-in
- site-specific pairing/enrollment
- scoped authentication separate from the Drime API token
- redacted status payload only
- no local filesystem paths by default
- no Drime token, signed URL, request body, cookies, nonces, salts, database credentials, or package contents
- rate limiting or replay protection
- tests for disabled endpoint behavior
- tests for bad token/authentication failure
- tests proving redaction stays intact

Do not expose the local CLI path-mode status payload through this endpoint.

## Polling Direction

Recommended v1 direction:

```text
Dashboard pulls status from enrolled sites.
```

Why:

- the dashboard controls polling frequency
- stale-site detection is straightforward
- enrolled sites do not need to know the dashboard schedule
- easier to retry and summarize failures centrally

Possible later alternative:

```text
Client sites push status to dashboard.
```

Only consider push if pull is blocked by hosting, firewall, authentication, or scale constraints.

## Security Boundaries

Version 1 must remain read-only.

Do not include:

- remote restore
- remote backup execution
- remote package deletion
- remote local cleanup
- remote settings changes
- remote Drime credential changes
- unauthenticated status endpoints
- raw path-mode payloads

Any future remote action should become a separate gated project with its own threat model and approval flow.

## Open Decisions Before Implementation

- Dashboard plugin repo name. Suggested: `alynt-drime-backups-dashboard`.
- Whether the first version stores snapshots in custom tables or options.
- How long dashboard snapshots should be retained.
- Polling interval.
- Whether dashboard notices should be on-screen only or email/notification based.
- Exact enrollment UI wording and token lifecycle.
- Whether sites are grouped by client, server, environment, or Drime folder.

## Recommended Implementation Phases

### Phase 0: Project Setup

- Create a new plugin repo.
- Add build tooling, linting, tests, updater compatibility, and release workflow only where useful.
- Write the initial implementation plan and security model.
- Target the dashboard plugin to run first on `control-sitesmanage live-only`.
- Keep the first implementation local/plugin-repo focused before installing anything on the live host site.

### Phase 1: Read-Only Dashboard Shell

- Add site registry UI.
- Add manual "check status now" action for one enrolled site.
- Store the latest status payload.
- Display a simple site list and site detail screen.

### Phase 2: Enrollment And Auth

- Add dashboard-generated pairing tokens.
- Add disabled-by-default uploader endpoint.
- Add client-site opt-in setting.
- Add endpoint authentication and redaction tests.

### Phase 3: Polling And History

- Add scheduled polling.
- Add stale-site detection.
- Store status snapshots.
- Add retention cleanup for old dashboard snapshots.

### Phase 4: Alerts And Rollout

- Add attention queue.
- Add configurable alert thresholds.
- Add email or notification support if needed.
- Roll out to a small set of known sites first.

## Current Preparation Status

- Uploader-side status foundation exists.
- Single-site operator status helper exists and has been verified against rollout sites.
- Host site is confirmed as `control-sitesmanage live-only`.
- Plugin name is confirmed as `Alynt Drime Backups Dashboard`.
- Version 1 scope is confirmed as read-only monitoring.
- Enrollment direction is confirmed as dashboard-generated pairing token plus dashboard polling.
- Dashboard implementation has not started.
- No uploader endpoint exists yet.
- Next practical step is creating a fresh implementation plan and repo for the separate dashboard plugin.

## Fresh Implementation Chat Starting Point

When the separate dashboard project begins, start with this sequence:

1. Create a new plugin implementation plan for `Alynt Drime Backups Dashboard`.
2. Confirm repo path and package identity before scaffolding.
3. Create a new repo only after the implementation plan is accepted.
4. Scaffold a minimal owner-managed WordPress plugin.
5. Add only the tooling that makes sense for this plugin.
6. Build the dashboard shell before adding uploader endpoint work.
7. Keep `control-sitesmanage live-only` as the eventual host target, but do not install there until the local/repo baseline is reviewed.

Recommended first implementation-plan sections:

- Purpose and non-goals.
- Host site and rollout target.
- Version 1 user stories.
- Data model.
- Enrollment/authentication model.
- Uploader endpoint requirements.
- Admin UI views.
- Polling and stale-site detection.
- Security and redaction model.
- Build/test/release workflow.
- Phase checklist.
