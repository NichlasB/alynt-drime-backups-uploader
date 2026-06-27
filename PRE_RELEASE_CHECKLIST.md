# Alynt Drime Backups Uploader Pre-Release Checklist

Updated: 2026-06-27

Use this checklist to track workflow progress for the `Alynt Drime Backups Uploader` plugin before release or release-candidate promotion. Mark a workflow complete only after its verification gate has passed or after confirming the workflow is not needed because the equivalent work is already complete.

## Toolkit Workflows

- [x] WP Plugin Toolkit Add Build Tooling Prompt: completed. Composer/npm dependencies, PHPCS/WPCS, PHPUnit, lint, build, and packaging scripts are in place.
- [x] WP Plugin Toolkit Add Observability Tooling Prompt: completed. Redacted diagnostics, export, settings controls, and runtime status reporting are implemented.
- [x] WP Plugin Toolkit Feature Bloat And Structure Review Prompt: completed for implemented feature slices. Large runtime classes were split into focused traits where useful.
- [x] WP Plugin Toolkit Documentation Sync Audit Prompt: completed for the `0.1.0` release line and refreshed after the GridPane runner hardening/admin UI polish pass.
- [x] Alynt Plugin Updater Compatibility Guide: completed. GitHub release asset install and Alynt Plugin Updater detection/update validation passed for `v0.1.0`; follow-up validation passed for the `v0.1.1` GitHub release asset on `plugin-tester.local`.

## Current Unreleased Feature Workflow Tracking

- [x] Server cron review UX: feature light review passed; feature bloat and structure review completed with explicit `origin/master` base and deferred a cosmetic split of `includes/trait-admin-page-source-settings.php`; UI/UX review passed; security review passed; documentation sync audit passed; local validation passed with 136 PHPUnit tests and 709 assertions, PHPCS, build, POT regeneration, and diff whitespace check.
- [x] Multiple standalone site runner guidance: documentation-only slice added to clarify that this is not WordPress Multisite work; docs now guide separate GridPane/VPS sites to use isolated runner configs, outboxes, restore paths, cron entries, and Drime site folders.
- [x] Package-level remote index sidecars: server runner writes `.remote-index.json`; generic-outbox uploads the sidecar with archive/manifest/checksum package sets; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; local validation passed with 136 PHPUnit tests and 734 assertions, PHPCS across 53 files, and diff whitespace check with only the existing `readme.txt` CRLF warning.
- [x] Folder catalog snapshot sidecars: server runner writes `.remote-catalog.json`; generic-outbox uploads the sidecar with archive/manifest/checksum/index package sets; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; local validation passed with 136 PHPUnit tests and 754 assertions, PHPCS across 53 files, and diff whitespace check with only the existing `readme.txt` CRLF warning.
- [x] Operator-approved local cleanup execution: server runner now supports guarded `cleanup --confirm=delete-local-artifacts`; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; local validation passed with 139 PHPUnit tests and 782 assertions, PHPCS across 53 files, syntax check, and diff whitespace check with only the existing `readme.txt` CRLF warning.
- [x] Richer server-runner restore guidance: fetch, verify, inspect, and stage-restore commands now print operator next steps while keeping restore non-destructive; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; local validation passed with 140 PHPUnit tests and 799 assertions, PHPCS across 53 files, syntax check, and diff whitespace check with only the existing `readme.txt` CRLF warning.
- [x] Restore dry-run preflight: server runner adds read-only `restore-dry-run` staged-evidence checks for future gated restore automation; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; validation passed with 145 PHPUnit tests and 879 assertions, PHPCS across 53 files, syntax checks, and diff whitespace check with only the existing `readme.txt` CRLF warning. Bloat review used base `52380a4`; the standalone runner remains oversized and is deferred because splitting the portable CLI script is architecture-sensitive.

## Pre-Release Review Sequence

- [x] 01 Code Cleanup Review.
- [x] 02 File Structure Review.
- [x] 03 Error Handling Review.
- [x] 04 WP Best Practices Review.
- [x] 05 Database Review.
- [x] 06 Performance Review.
- [x] 07 Edge Cases Review.
- [x] 08 Uninstall Review.
- [x] 09 I18N Review.
- [x] 10 Accessibility Review.
- [x] 11 Code Quality Review.
- [x] 12 Documentation Review.
- [x] 13 Security Audit.

## Release Validation

- [x] PHP syntax sweep passed.
- [x] PHPUnit passed: 121 tests, 589 assertions.
- [x] PHPCS passed: 53 files.
- [x] npm build passed.
- [x] npm audit passed when run during the release validation flow.
- [x] Distribution zip audit passed for `alynt-drime-backups-uploader-v0.1.0.zip`.
- [x] GitHub release asset was published and install-tested.
- [x] Alynt Plugin Updater detected and installed the GitHub release asset.
- [x] Alynt Plugin Updater detected `v0.1.1` from the GitHub release asset, populated WordPress's update transient with `alynt-drime-backups-uploader-v0.1.1.zip`, installed the update on `plugin-tester.local`, and final verification showed version `0.1.1`, active plugin state, clean queue/failed counts, and no remaining update response.
- [x] Final real UI-click updater rehearsal passed: `plugin-tester.local` was downgraded to `0.1.0`, the WordPress Plugins screen showed the Alynt Plugin Updater `0.1.1` update notice, Playwright clicked the rendered `update now` link, the UI reported `Updated!`, and runtime verification returned version `0.1.1`, active plugin state, clean queue/failed counts, no active upload, and no remaining update response.
- [x] Release `v0.1.1` accepted as the current stable baseline on 2026-06-26.

## E2E Validation

- [x] LocalWP target confirmed: `plugin-tester.local`, path `C:\Users\Captain\Local Sites\plugin-tester\app\public`.
- [x] Novamira MCP available for `plugin-tester.local`.
- [x] LocalWP admin page rendered with expected controls and no blocking browser errors.
- [x] LocalWP no-op settings save preserved configured paths/settings.
- [x] LocalWP generic outbox scan queued exactly one stable backup package.
- [x] LocalWP missing-token upload failed safely, left active upload empty, retained retryable queue state, and recorded failed state.
- [x] LocalWP temporary E2E artifacts and plugin state were restored after the pass.
- [x] GridPane staging target confirmed: `sites-main`, `alyntdrime.sitesmain.com`, WordPress path `/var/www/alyntdrime.sitesmain.com/htdocs`.
- [x] Staging runner health passed before package creation.
- [x] Staging old approved restore/download artifacts were removed before the write-heavy pass.
- [x] Staging runner created and verified `alyntdrime-sitesmain-com-20260626-165727.tar.gz` with manifest and SHA-256 sidecars.
- [x] Staging plugin scan queued the fresh server-runner package after minimum-age was temporarily lowered and then restored to `900`.
- [x] Staging upload retry completed successfully; final status showed queue `0`, failed `0`, uploaded `5`, and no active upload.
- [x] Staging uploaded registry recorded Drime file entry `765316863`, parent folder `764729789`, and two sidecars for the fresh package.
- [x] Staging diagnostics settings were restored to disabled plus `warning` threshold after the pass.
- [x] Staging runner work directory was clean after the pass.
- [x] Staging outbox cleanup completed after E2E: older `20260625-225948` and `20260626-094942` package sets were removed, newest verified `20260626-165727` package set was retained, outbox dropped from about `5.1G` to `1.7G`, and filesystem availability rose to about `13G`.
- [x] LocalWP admin status-table width check passed after the UI polish: `Server Runner Status`, `Scan State`, `Remote Retention`, and `Diagnostics` all measured `760px` wide in Playwright after loading the rebuilt admin stylesheet.

## Open Items

- [x] Decide whether to remove old staging outbox packages to reclaim disk space. Completed on 2026-06-26; current outbox retains only the newest verified package set totaling about `1.7G`.
- [x] Push commit `90a1de2` after final review if the release branch should advance on GitHub. Completed with `64e5dc5` on `origin/master`.
- [x] Run a final documentation sync audit if additional release-facing behavior changes before the next release. Completed on 2026-06-26 after the GridPane runner hardening/admin UI polish pass.
