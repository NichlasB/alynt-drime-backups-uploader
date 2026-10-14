# V2.3 Trusted Context Observation Safety Foundation

Approved local-only on 2026-10-14. Source checkpoints: uploader `b5813a7`, dashboard `ad7c9f5`. This implements the bounded dormant observer described in dashboard `docs/V2_3_TRUSTED_CONSENT_CONTEXT_DESIGN.md`; it is not an authenticated consent command, storage migration, production provider or runtime permission. The canonical milestone remains dashboard `docs/IMPLEMENTATION_PLAN.md`.

## Implementation And Trust Boundary

`includes/class-cadence-authority-context-observer.php` is a final observer using the private validation/binding trait `includes/trait-cadence-authority-context-facts.php`. Both are **absent from the production include list**. Only the new test support explicitly loads them. Existing schema/protocol, evidence producer, Cron/factory, settings/connection writers and scan/queue/action handlers remain unchanged.

Construction requires the existing final schema-3 lifecycle store, two explicit type-preserving fact readers and an explicit UTC clock. The observer switch defaults off and only literal true starts observation; it is not consent or mode activation. Construction registers nothing and reads nothing. Disabled observation also reads nothing. The typed lifecycle dependency reuses strict schema validation and authoritative bounded state reads rather than introducing an alternative lifecycle parser or accepting a caller's purported verified model.

No WordPress settings getter, normalized connection getter, option mutation, identity generation, enrollment completion, scheduler, SQL connection, hook, scanner, queue, upload, restore or rollback operation is added. All providers in this slice are in-memory fixtures. Provider **selection** must eventually be trusted, closed and separately reviewed; this class validates values but cannot sandbox an arbitrary callback or prove that a future provider bypasses caches. It does not authenticate an administrator or establish provenance by accepting a boolean named trusted.

## Exact Private Fact Slices

These narrow input slices are not whole stored options, accepted request payloads, status fields or a new public protocol. A future provider must extract these fixed fields from approved authoritative storage without filling defaults, coercing values, creating missing identity or including unrelated private settings. No such production provider is wired here.

| Provider | Exact fields and validation |
| --- | --- |
| Settings | `site_uuid`, `auto_scan_enabled`; existing lowercase UUID shape and literal boolean. Missing identity never generates or persists a UUID. |
| Connection | `connection_status`, `status_endpoint_enabled`, `remote_actions_enabled`; exact paired status and literal true flags. False/revoked/missing/coercible facts remain unavailable, not legacy. |
| Binding strings | `dashboard_origin`, `expected_client_origin`, `dashboard_site_public_id`, `polling_key_id`, `polling_credential_verifier`, `action_key_id`, `action_public_key`; bounded strings, exact UUID/key/hash/base64url shapes, no trimming/coercion. |
| Pairing times | `paired_at`, `remote_actions_opted_in_at`; positive integer UTC values not beyond the initial observation clock. |
| Lifecycle | Injected strict lifecycle store only, existing schema 3/128 KiB/depth-16/50-journal limits. Unknown/active-shaped/corrupt/autoloaded/missing state rejects without repair. |

Settings have exactly two fields and connection exactly twelve. Reject additional fields including trust/provenance assertions. Ordinary polling timestamps, last-error codes and other-purpose opt-ins are not inputs to this private semantic identity. The fixture demonstrates type-preserving extraction alongside the actual existing evidence producer; this is not proof that an installed provider implements that extraction safely.

Binding uses the unchanged version-1 domain, field order and JSON/hash semantics. Compatibility tests compare with `Cadence_Authority_Evidence::enrollment_binding()` on the same synthetic facts; semantic identity changes reject the old model, while ordinary polling metadata does not change that identity. No existing evidence contract, cadence fingerprint or due-advancement staleness is changed.

The observer intentionally accepts a narrow canonical lowercase DNS/IPv4-style HTTPS-origin subset, optionally a valid port. Userinfo, paths, query/fragment, non-HTTPS, malformed ports, trailing slash and IPv6 forms are rejected rather than normalized. This is **not** an SSRF/public-IP validator or a change to existing origin handling; no request is made. Additional valid origin forms require explicit compatibility review before a real provider/runtime integration.

## Observation And Output Contract

`observe(entry_point)` uses the existing internal entry-point vocabulary. Upload-next/abort return the existing neutral outside-scope result; unknown entry points and reserved rollback return their existing rejection. These cases read no providers, even if the observer is enabled or state is corrupt. Neutral upload classification neither grants permission nor stops independent uploads/retention/abort policy.

Enabled scan/requeue/apply observations use at most two lifecycle snapshots, two settings slices, two connection slices and two clock reads, with early rejection. Invalid clock, missing/corrupt/future model or invalid settings short-circuit later private reads. No retry, initialization or write occurs.

Require positive integer clocks bounded before overflow, no regression and elapsed collection strictly below 300 seconds. That limit is not a cached permission lifetime or lease. Compare complete validated lifecycle snapshots and the two freshly derived semantic fact observations, then compare current binding against stored binding. An observed epoch/revision/state/context change blocks; no snapshot is retained between calls. Unknown actual-provider cache/ABA/global writer behavior is **not** certified by this comparison.

Every result has exactly `disposition`, `code`, `dispatch_supported: false`, `execution_supported: false`. No input, hash/binding, journal, generation, owner, path, credentials, exception, timestamp or compatible 16-field policy projection is returned. Internal fixed reason codes are observer-disabled, invalid clock, observation-unavailable/stale/changed, binding-changed, inspection-required, automatic-scan-disabled or provenance-unavailable, plus existing neutral/unknown/rollback reasons. These codes are not added to status/REST/UI contracts.

Every valid schema-3 scan/requeue/apply observation remains blocked. New-model/unknown history, synthetic consent true, positive permission epoch, no pending work or automatic scans enabled never become active or verified legacy. All unfinished journals and nonterminal dispatches require inspection; expiry does not cancel or resolve them. Disabled automatic scanning gives a negative reason, not proof that enabling that one flag would admit work. No observer result is an owner-context callback, lease or execution token.

Catch provider/store/clock Throwables without exposing their messages; WP_Error/non-array/partial provider outcomes also reject. This is redacted observation failure, **not** a claim that a provider made no write or that a failed/ambiguous consent command can be retried. The observer itself calls no mutation APIs and invokes no recovery or legacy fallback.

## Focused Review And Permanent Tests

New tests explicitly load the observer, inject raw fact slices and the real lifecycle model over an in-memory CAS, and compare exact redacted results and retained bytes/write counts. They do not mock the observer algorithm or certify WordPress authorization/SQL/cache integration. Existing tests, assertions, skips and thresholds are unchanged.

| Test class | Protected boundary |
| --- | --- |
| `CadenceContextObserverTest` | Default construction/disabled zero reads; literal switch; definitions absent from bootstrap; version-1 binding compatibility; all nine scan/queue/apply routes stay blocked through synthetic grant/unknown history/revoke; neutral upload/abort and rollback; automatic-off and expired journal/dispatch uncertainty. |
| `CadenceContextValidationTest` | Missing/wrong/extra/nested/oversized fields, malformed identities/keys/origins, unknown trust assertion, semantic binding changes, clock limits, exact expiry, raw provider roots, future/active/unknown/corrupt lifecycle without repair. |
| `CadenceContextRaceTest` | Fresh second epoch detects change-away-and-back; settings/key changes during collection; polling metadata extraction; provider/read/clock exceptions and errors redacted without retry; missing state zero private reads; no cross-call cache; failed final reads never return first facts. |

Fixture interleavings deliberately run external synthetic consent writers; tests assert only those writers increment CAS counts. They do not pretend the observer can prevent concurrent transitions or guarantee all changes after its final read. Recovery rows are synthetic valid evidence, not a newly implemented runtime recovery path. Callers cannot reuse this pre-admission classification as post-start ownership validation.

The initial definition-only scaffold failed because new definitions correctly were not bootstrap-loaded; it was replaced before completion with behavioral zero-read and source-bootstrap assertions. That is test-loading/quality work, not a confirmed existing production defect or a production red-to-green claim. New formatting/doc-comment lint findings were fixed; no rules were lowered. Focused source/privacy review found no effectful observer path, raw failure output, injected bypass grant or schema/history promotion. This is a scoped foundation review, not full-plugin pre-release security clearance.

## Executed Validation And Limits

Final focused `php vendor/bin/phpunit --do-not-cache-result --filter CadenceContext`: **85 tests / 503 assertions**, no skips. New runtime class/trait PHPCS passed; full PHPCS passed **132** runtime files with the existing nonfatal PHPCompatibility deprecation visible. Build, deterministic runner verification, portable runner syntax, three dev-tool tests, npm audit and Composer audit passed; audits reported zero vulnerabilities and generated assets/runner stayed unchanged.

Full uploader `php vendor/bin/phpunit --do-not-cache-result` passed **719 tests / 4252 assertions**, four existing Windows skips, in 8:32.242, with no failures/errors/risky tests. Dashboard docs-only validation passed **299 tests / 1423 assertions**, two existing Windows skips, plus PHPCS (138 files), structure, build and both dependency audits. Exact-commit CI is the remaining publication gate. New source sizes are 150/96 lines, support fixture 53 lines; three new test classes are 52/50/53 lines. The restore-point check confirmed clean uploader checkpoint `b5813a7`; no source archive or site/database backup was created. No LocalWP/Novamira/browser/SQL/live site or credentials were accessed in this slice; versions remain uploader `0.5.25` and dashboard `0.1.77`.

Not proven: currentness or cache isolation of real providers, independent-option/global-writer coordination, every ABA/interleaving, multisite/per-blog scope, authenticated administrator consent, provenance/handoff storage, admitted-effect fencing or real recovery/lifecycle/wake-up behavior. No package/release is justified for this dormant foundation; real production wiring or activation requires separate approvals and integration/release evidence.

## Implementation-Plan Progress And Exact Next Gate

The approved trusted-context observation item is implemented and adversarially tested at its injected read/validation layer. The next meaningful gated slice is **local-only V2.3 Authenticated Local Consent and Durable Admission-Closure Design**: specify the client-local capability/nonce/intent command boundary, versioned durable provenance and idempotency receipts, and how grant/revoke/context transitions close admission despite failed or ambiguous writes and separate-option writers. Keep actual permission controls, schema/option writes, production wiring, activation, schedule/queue effects and rollback disabled during that design. Unknown history cannot be upgraded into never-activated/handoff proof. Do not substitute maintenance or package work for this new permission/storage decision.

## Publication Receipt

Implementation `c57c577c2f8f5908d38e67b1dbc0b3d414dfb873` is pushed to `origin/master`; exact-commit [Quality 38574311202](https://github.com/NichlasB/alynt-drime-backups-uploader/actions/runs/38574311202) passed PHP 7.4/8.3, each **719 tests / 4306 assertions**, no skips, plus PHPCS/runner/proof-script syntax and Node build/dev-tool/generated-asset/audit checks. Dashboard plan `00d63f5f756704b112d55cd949f6507ac29f3ac7` passed exact-commit [CI 38574325775](https://github.com/NichlasB/alynt-drime-backups-dashboard/actions/runs/38574325775), each PHP version **299 tests / 1430 assertions**, no skips, plus lint/structure/build/audits. Both implementation branches are clean/synced; no existing production source, bootstrap/caller, version or generated artifact changed. This completes the approved dormant observation milestone, not real provider/authentication or admission closure proof. The receipt-only follow-up changes no tested code and needs final exact-head CI verification before handoff.
