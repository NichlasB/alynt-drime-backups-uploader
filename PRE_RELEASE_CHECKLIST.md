# Alynt Drime Backups Uploader Pre-Release Checklist

Updated: 2026-07-12

Use this checklist to track workflow progress for the `Alynt Drime Backups Uploader` plugin before release or release-candidate promotion. Mark a workflow complete only after its verification gate has passed or after confirming the workflow is not needed because the equivalent work is already complete.

## Toolkit Workflows

- [x] WP Plugin Toolkit Add Build Tooling Prompt: completed. Composer/npm dependencies, PHPCS/WPCS, PHPUnit, lint, build, and packaging scripts are in place.
- [x] WP Plugin Toolkit Add Observability Tooling Prompt: completed. Redacted diagnostics, export, settings controls, and runtime status reporting are implemented.
- [x] WP Plugin Toolkit Feature Bloat And Structure Review Prompt: completed for implemented feature slices. Large runtime classes were split into focused traits where useful.
- [x] WP Plugin Toolkit Documentation Sync Audit Prompt: completed for the `0.1.0` release line and refreshed after the GridPane runner hardening/admin UI polish pass.
- [x] Alynt Plugin Updater Compatibility Guide: completed. GitHub release asset install and Alynt Plugin Updater detection/update validation passed for `v0.1.0`; follow-up validation passed for the `v0.1.1` GitHub release asset on `plugin-tester.local`.

## Current Unreleased Feature Workflow Tracking

- [x] Server cron review UX: feature light review passed; feature bloat and structure review completed with explicit `origin/master` base and deferred a cosmetic split of `includes/trait-admin-page-source-settings.php`; UI/UX review passed; security review passed; documentation sync audit passed; local validation passed with 136 PHPUnit tests and 709 assertions, PHPCS, build, POT regeneration, and diff whitespace check.
- [x] Guided manual server setup commands: settings page now presents install/update, first test backup, scan/upload, and cron review as guided copy-paste blocks with inline shell commands; feature light review fixed first-test backup failure feedback; bloat review measured one oversized existing admin trait and deferred structure splitting; UI/UX, security, and documentation-sync reviews passed; validation passed with focused admin settings tests, full PHPUnit, PHPCS, build, and diff whitespace check.
- [x] Multiple standalone site runner guidance: documentation-only slice added to clarify that this is not WordPress Multisite work; docs now guide separate GridPane/VPS sites to use isolated runner configs, outboxes, restore paths, cron entries, and Drime site folders.
- [x] Package-level remote index sidecars: server runner writes `.remote-index.json`; generic-outbox uploads the sidecar with archive/manifest/checksum package sets; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; local validation passed with 136 PHPUnit tests and 734 assertions, PHPCS across 53 files, and diff whitespace check with only the existing `readme.txt` CRLF warning.
- [x] Folder catalog snapshot sidecars: server runner writes `.remote-catalog.json`; generic-outbox uploads the sidecar with archive/manifest/checksum/index package sets; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; local validation passed with 136 PHPUnit tests and 754 assertions, PHPCS across 53 files, and diff whitespace check with only the existing `readme.txt` CRLF warning.
- [x] Operator-approved local cleanup execution: server runner now supports guarded `cleanup --confirm=delete-local-artifacts`; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; local validation passed with 139 PHPUnit tests and 782 assertions, PHPCS across 53 files, syntax check, and diff whitespace check with only the existing `readme.txt` CRLF warning.
- [x] Richer server-runner restore guidance: fetch, verify, inspect, and stage-restore commands now print operator next steps while keeping restore non-destructive; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; local validation passed with 140 PHPUnit tests and 799 assertions, PHPCS across 53 files, syntax check, and diff whitespace check with only the existing `readme.txt` CRLF warning.
- [x] Restore dry-run preflight: server runner adds read-only `restore-dry-run` staged-evidence checks for future gated restore automation; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; validation passed with 145 PHPUnit tests and 879 assertions, PHPCS across 53 files, syntax checks, and diff whitespace check with only the existing `readme.txt` CRLF warning. Bloat review used base `52380a4`; the standalone runner remains oversized and is deferred because splitting the portable CLI script is architecture-sensitive.
- [x] Restore dry-run report generation: server runner adds optional `restore-dry-run --write-report=1` success evidence reports under configured `restore_reports_path`; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; validation passed with 147 PHPUnit tests and 912 assertions, PHPCS across 53 files, syntax checks, focused restore/security tests, and diff whitespace check with only the existing `readme.txt` CRLF warning. Bloat review used base `65aef5a`; the new test file was trimmed under threshold, and the standalone runner remains oversized/deferred because splitting the portable CLI script is architecture-sensitive.
- [x] Pre-restore backup evidence validation: server runner validates matching pre-restore evidence JSON and readable backup artifacts during `restore-dry-run`; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; validation passed with 147 PHPUnit tests and 930 assertions, PHPCS across 53 files, syntax checks, focused restore/security tests, and diff whitespace check with only the existing `readme.txt` CRLF warning. Bloat review used base `3239a3a`; changed test files are under threshold, and the standalone runner remains oversized/deferred because splitting the portable CLI script is architecture-sensitive.
- [x] Database restore apply: server runner adds `restore-apply --scope=database` behind `--confirm=restore-staging-site`, dry-run/evidence gates, and restore apply report output; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; validation passed with 152 PHPUnit tests and 978 assertions, PHPCS across 53 files, syntax checks, build, focused restore apply/security tests, and diff whitespace check with only the existing `readme.txt` CRLF warning. Bloat review used base `97ed388`; the new restore apply test file and changed security test are under threshold, and the standalone runner remains oversized/deferred because splitting the portable CLI script is architecture-sensitive.
- [x] Staging database restore rehearsal: `alyntdrime.sitesmain.com` passed the first real `restore-apply --scope=database --confirm=restore-staging-site` rehearsal on 2026-06-28. The runner created, verified, and staged package `alyntdrime-sitesmain-com-20260628-081212.tar.gz`; dry-run passed with `failure_count: 0`; database apply succeeded; post-apply WP-CLI `home`/`siteurl`, WordPress core version, `wp db check`, and HTTPS `200` verification passed. Evidence artifacts were retained under the staging site's private backup paths.
- [x] Staging rehearsal artifact cleanup: large `alyntdrime-sitesmain-com-20260628-081212` outbox package artifacts, staged restore directory, and temporary `/tmp` copies were removed from `alyntdrime.sitesmain.com`; filesystem availability returned to about `13G`. Small dry-run/apply reports and pre-restore database evidence were retained.
- [x] File restore apply: server runner adds `restore-apply --scope=files` behind `--confirm=restore-staging-site`, dry-run/evidence gates, guarded target replacement from staged `htdocs/`, and restore apply report output; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; validation passed with 153 PHPUnit tests and 1004 assertions, PHPCS across 53 files, syntax checks, build, focused restore apply/security tests, and diff whitespace check with only the existing `readme.txt` CRLF warning. Bloat review used base `3589cb6`; changed test files are under threshold, and the standalone runner remains oversized/deferred because splitting the portable CLI script is architecture-sensitive.
- [x] Staging file restore rehearsal: `alyntdrime.sitesmain.com` passed the first real `restore-apply --scope=files --confirm=restore-staging-site` rehearsal on 2026-06-28. The runner created, verified, and staged package `alyntdrime-sitesmain-com-20260628-083838.tar.gz`; dry-run passed with `failure_count: 0`; file apply succeeded; a post-package marker file was removed by restore; WP-CLI `home`/`siteurl`, active Query Monitor status, and HTTPS `200` verification passed. The package and pre-restore file backup both recorded live file-change warnings under `wp-content`. Query Monitor's `wp-content/db.php` symlink was restored from pre-restore backup after apply, but its target file was missing, so this remains a combined-restore design caveat. Large rehearsal artifacts were removed afterward and filesystem availability returned to about `13G`; small reports and evidence JSON were retained.
- [x] File restore symlink/drop-in reporting: server runner reports pre-restore symlinked drop-ins that are absent from staged files in restore apply reports, so file restore cannot silently lose a drop-in such as Query Monitor's `wp-content/db.php`; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; validation passed with 154 PHPUnit tests and 1008 assertions, focused restore/security tests, PHPCS across 53 files, syntax checks, build, and diff whitespace check. Bloat review used base `71a9b1f`; the new symlink test and changed security test are under threshold, and the standalone runner remains oversized/deferred because splitting the portable CLI script is architecture-sensitive.
- [x] Combined files-and-database restore apply: server runner supports `restore-apply --scope=files-and-database` behind `--confirm=restore-staging-site`, dry-run/evidence gates, file replacement first, database import second, and restore apply report output with separate phase status; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; validation passed with 155 PHPUnit tests and 1039 assertions, focused restore/security tests, PHPCS across 53 files, syntax checks, build, and diff whitespace check with only the existing `readme.txt` line-ending warning. Bloat review used base `4108fba`; changed test files are under threshold, and the standalone runner remains oversized/deferred because splitting the portable CLI script is architecture-sensitive.
- [x] Staging combined restore rehearsal: `alyntdrime.sitesmain.com` passed the first real `restore-apply --scope=files-and-database --confirm=restore-staging-site` rehearsal on 2026-06-28. The runner created, verified, and staged package `alyntdrime-sitesmain-com-20260628-115011.tar.gz`; combined dry-run passed with `failure_count: 0`; combined apply succeeded with file restore first and database import second; the post-package marker file was removed; WP-CLI `home`/`siteurl`, WordPress core version, `wp db check`, Query Monitor active status, and HTTPS `200` verification passed. The apply report recorded `file_restore_missing_symlink_count: 1` for Query Monitor's `wp-content/db.php` drop-in. Large rehearsal artifacts were removed afterward and filesystem availability returned to about `13G`; small dry-run/apply reports, evidence JSON, and the pre-restore database export were retained.
- [x] Post-restore known drop-in review items: file and combined restore apply reports identify known missing or broken symlinked drop-ins such as Query Monitor's `wp-content/db.php` in `post_restore_manual_review_items`, while leaving cleanup/regeneration to the operator; feature light, bloat/structure, security, and documentation-sync reviews completed where applicable; UI/UX review not applicable; validation passed with 155 PHPUnit tests and 1043 assertions, focused restore/security tests, PHPCS across 53 files, syntax checks, build, and diff whitespace check. Bloat review used base `a8037d7`; changed test files are under threshold, and the standalone runner remains oversized/deferred because splitting the portable CLI script is architecture-sensitive.
- [x] Final staging post-restore review rehearsal: `alyntdrime.sitesmain.com` passed a final real `restore-apply --scope=files-and-database --confirm=restore-staging-site` rehearsal on 2026-06-28 with package `alyntdrime-sitesmain-com-20260628-123900.tar.gz`. A temporary Query Monitor-style `wp-content/db.php` symlink was created before packaging to prove the new report path; dry-run passed with `failure_count: 0`; apply succeeded; the apply report recorded `post_restore_manual_review_required: true`, `post_restore_cleanup_required: false`, and `post_restore_manual_review_items[0].path: wp-content/db.php` with Query Monitor owner guidance. Post-apply WP-CLI `home`/`siteurl`, WordPress core version, `wp db check`, Query Monitor active status, marker removal, absent drop-in state, and HTTPS `200` verification passed. Large rehearsal artifacts were removed afterward and filesystem availability returned to about `13G`; small dry-run/apply reports, evidence JSON, and the pre-restore database export were retained.
- [x] Production-oriented upload defaults and per-source Drime relative paths: new installs default to 300-second minimum file age and 128 MB multipart chunks; generic outbox/server-runner and WPvivid uploads can use separate Drime relative paths with fallback to the shared path; uploaded registry records include `destination_relative_path`; feature light, bloat/structure, UI/UX, security, and documentation-sync reviews completed; validation passed with 162 PHPUnit tests and 1076 assertions, PHPCS across 53 files, build, syntax checks, and diff whitespace check with only existing line-ending warnings. Bloat review used base `origin/master`; changed files are existing oversized runtime/test files with small scoped additions, so no feature-stage split was needed.
- [x] Automatic local server outbox retention after confirmed upload: plugin settings now include server-specific uploaded-package pruning and a newest-package keep count; pruning only targets uploaded generic-outbox/server-runner records inside the configured server outbox and leaves WPvivid files untouched; feature light, bloat/structure, UI/UX, security, and documentation-sync reviews completed; validation passed with 167 PHPUnit tests, 1103 assertions, 1 expected skip, PHPCS across 54 files, build, and diff whitespace check with line-ending warnings only. Bloat review used base `origin/master`; changed oversized files are existing architecture/test aggregate files, and the new retention trait is under threshold.

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

### Final 2026-06-28 Pre-Release Refresh

- [x] 00 Plugin Model Assessment completed; result: Large/High enough for careful review, no Extra High model requirement justified. Temporary assessment notes were written to `pre-release-model-recommendations.tmp.txt` and should not be included in the release commit.
- [x] 01 Code Cleanup Review refreshed; no runtime debug/TODO debris found. Build-helper console output is intentional.
- [x] 02 File Structure Review refreshed; oversized files are known architecture-sensitive files, especially the portable server runner, and no safe pre-release split is needed.
- [x] 03 Error Handling Review refreshed for admin actions, upload flow, and restore runner reporting.
- [x] 04 WP Best Practices Review refreshed; capability and nonce gates remain in place for admin and AJAX actions.
- [x] 05 Database Review refreshed; no custom tables are used and uninstall coverage still matches plugin-owned options/cron hooks.
- [x] 06 Performance Review refreshed; no new performance blocker found in the final pre-release pass.
- [x] 07 Edge Cases Review refreshed; restore apply remains gated by dry-run evidence, staging-only config, report paths, and explicit confirmation.
- [x] 08 Uninstall Review refreshed; uninstall deletes plugin-owned options and clears scan/upload cron hooks while preserving backup files/artifacts.
- [x] 09 I18N Review refreshed; POT regenerated with WP-CLI 2.12.0 phar. WP-CLI emitted PHP 8.5 deprecation warnings from its own dependencies, but generation succeeded.
- [x] 10 Accessibility Review refreshed; admin tables retain captions/scope attributes and async workspace/folder controls retain busy/status semantics.
- [x] 11 Code Quality Review refreshed; old internal UI namespace remnants from the previous WPvivid-specific plugin line were renamed to `alynt-drime-backups` / `alyntDrimeBackups` and rebuilt.
- [x] 12 Documentation Review refreshed; implementation plan now distinguishes released stable `v0.1.1` from the current unreleased release-candidate scope and current restore behavior.
- [x] 13 Security Audit refreshed; restore apply remains staging-only, requires pre-restore evidence, validates staged reports/paths, rejects broad target paths, and writes apply reports. Composer audit is blocked locally because neither global Composer nor `composer.phar` is available.

## Release Validation

- [x] PHP syntax sweep passed.
- [x] PHPUnit passed: 121 tests, 589 assertions.
- [x] PHPCS passed: 53 files.
- [x] npm build passed.
- [x] npm audit passed when run during the release validation flow.
- [x] Final 2026-06-28 PHP syntax sweep passed for 86 PHP files.
- [x] Final 2026-06-28 PHPUnit passed: 155 tests, 1043 assertions, 1 expected Windows symlink skip.
- [x] Final 2026-06-28 PHPCS passed across 53 files.
- [x] Final 2026-06-28 npm build passed after admin namespace cleanup.
- [x] Final 2026-06-28 npm audit passed with 0 vulnerabilities.
- [ ] Composer audit not run in this environment: global `composer` is unavailable and repo-local `composer.phar` is absent. Production dependency risk is limited because `composer.json` has no runtime packages beyond PHP itself; dev dependency audit should be run on a machine with Composer before final release if required.
- [x] Final 2026-06-28 `git diff --check` passed with line-ending normalization warnings only for `PRE_RELEASE_CHECKLIST.md`, `docs/IMPLEMENTATION_PLAN.md`, `languages/alynt-drime-backups-uploader.pot`, `package-lock.json`, `package.json`, and `readme.txt`.
- [x] Distribution zip audit passed for `alynt-drime-backups-uploader-v0.1.0.zip`.
- [x] GitHub release asset was published and install-tested.
- [x] Alynt Plugin Updater detected and installed the GitHub release asset.
- [x] Alynt Plugin Updater detected `v0.1.1` from the GitHub release asset, populated WordPress's update transient with `alynt-drime-backups-uploader-v0.1.1.zip`, installed the update on `plugin-tester.local`, and final verification showed version `0.1.1`, active plugin state, clean queue/failed counts, and no remaining update response.
- [x] Final real UI-click updater rehearsal passed: `plugin-tester.local` was downgraded to `0.1.0`, the WordPress Plugins screen showed the Alynt Plugin Updater `0.1.1` update notice, Playwright clicked the rendered `update now` link, the UI reported `Updated!`, and runtime verification returned version `0.1.1`, active plugin state, clean queue/failed counts, no active upload, and no remaining update response.
- [x] Release `v0.1.1` accepted as the current stable baseline on 2026-06-26.
- [x] GitHub release `v0.2.0` published on 2026-06-28 with release asset `alynt-drime-backups-uploader-v0.2.0.zip`; the release workflow completed successfully.
- [x] Alynt Plugin Updater detected `v0.2.0` from the GitHub release asset, populated WordPress's update transient with `alynt-drime-backups-uploader-v0.2.0.zip`, installed the update through the rendered WordPress Plugins screen on `plugin-tester.local`, and final verification showed version `0.2.0`, active plugin state, clean queue/failed counts, no active upload, and no remaining update response.
- [x] Release `v0.2.0` accepted as the current stable baseline on 2026-06-28.
- [x] GitHub release `v0.2.1` published on 2026-06-28 with release asset `alynt-drime-backups-uploader-v0.2.1.zip`; the release workflow completed successfully.
- [x] Alynt Plugin Updater detected `v0.2.1` from the GitHub release asset, populated WordPress's update transient with `alynt-drime-backups-uploader-v0.2.1.zip`, installed the update on `plugin-tester.local`, and final verification showed version `0.2.1`, active plugin state, no remaining update response, guided setup labels, improved `tee /dev/stderr` command feedback, and compact command CSS.
- [x] GridPane staging parity passed for `alyntdrime.sitesmain.com`; active plugin version is `0.2.1`, release header is present, the guided setup label and improved first-test backup command are present, and changed PHP files pass syntax checks.
- [x] Release `v0.2.1` accepted as the current stable baseline on 2026-06-28.
- [x] GitHub release `v0.3.1` published on 2026-07-03 with release asset `alynt-drime-backups-uploader-v0.3.1.zip`; the release workflow completed successfully.
- [x] Alynt Plugin Updater detected `v0.3.1` from the GitHub release asset on `alyntdrime.sitesmain.com`, populated WordPress's update transient with the release package URL, installed the update through WP-CLI, and final verification showed active plugin version `0.3.1` with plugin status reporting `plugin_version: 0.3.1`.
- [x] Real LocalWP Plugins-screen updater rehearsal passed for `v0.3.1`: `plugin-tester.local` started at `0.3.0`, the WordPress Plugins screen showed the Alynt Plugin Updater `0.3.1` update notice, Playwright clicked the rendered `update now` link, the UI reported `Updated!`, and file verification showed plugin header and version constant `0.3.1`.
- [x] Release `v0.3.1` accepted as the current stable baseline on 2026-07-03.

## E2E Validation

- [x] LocalWP target confirmed: `plugin-tester.local`, path `C:\Users\Captain\Local Sites\plugin-tester\app\public`.
- [x] Novamira MCP available for `plugin-tester.local`.
- [x] LocalWP runtime copy was refreshed from the source tree for the final E2E pass.
- [x] LocalWP admin page rendered with expected controls, rebuilt admin assets, `alyntDrimeBackups` namespace, no stale `alyntDrimeWPvivid` namespace, and no blocking browser errors.
- [x] LocalWP admin status-table width check passed: `Server Runner Status`, `Scan State`, `Remote Retention`, and `Diagnostics` all measured `760px` wide in Playwright.
- [x] LocalWP generic outbox scan queued exactly one stable backup package from the E2E outbox.
- [x] LocalWP missing-token/workspace guardrail upload failed safely with `alynt_drime_workspace_not_allowed`, left active upload empty, and recorded failed state.
- [x] LocalWP temporary E2E artifacts, settings snapshot, queue, failed state, and active upload state were restored after the pass.
- [x] GridPane staging target confirmed: `sites-main`, `alyntdrime.sitesmain.com`, WordPress path `/var/www/alyntdrime.sitesmain.com/htdocs`.
- [x] Staging plugin and runner runtime files were refreshed from the source tree for the final E2E pass.
- [x] Staging runner health passed before package creation with correct command ordering: `health --config=...`.
- [x] Staging runner created and verified `alyntdrime-sitesmain-com-20260628-145312.tar.gz` with manifest, SHA-256, remote-index, and remote-catalog sidecars.
- [x] Staging plugin scan queued the fresh server-runner package after minimum-age was temporarily lowered and then restored to `900`.
- [x] Staging upload retry completed successfully after one transient Drime API error; final status showed queue `0`, failed `0`, uploaded `7`, and no active upload.
- [x] Staging uploaded registry recorded Drime file entry `766821258`, parent folder `764729789`, archive size `1802205159`, and all four sidecars for the fresh package.
- [x] Staging diagnostics settings were restored to disabled plus `warning` threshold after the pass.
- [x] Staging runner work directory was clean after the pass.
- [x] Staging final package set remains in the outbox for now: archive size about `1.7G`, outbox total about `3.4G`, and deletion remains an explicit operator-approved cleanup action.

## Open Items

- [x] Decide whether to remove old staging outbox packages to reclaim disk space. Completed on 2026-06-26; current outbox retains only the newest verified package set totaling about `1.7G`.
- [x] Push commit `90a1de2` after final review if the release branch should advance on GitHub. Completed with `64e5dc5` on `origin/master`.
- [x] Run a final documentation sync audit if additional release-facing behavior changes before the next release. Completed on 2026-06-26 after the GridPane runner hardening/admin UI polish pass.
