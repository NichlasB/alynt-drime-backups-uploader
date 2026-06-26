# Alynt Drime Backups Uploader Pre-Release Checklist

Updated: 2026-06-26

Use this checklist to track workflow progress for the `Alynt Drime Backups Uploader` plugin before release or release-candidate promotion. Mark a workflow complete only after its verification gate has passed or after confirming the workflow is not needed because the equivalent work is already complete.

## Toolkit Workflows

- [x] WP Plugin Toolkit Add Build Tooling Prompt: completed. Composer/npm dependencies, PHPCS/WPCS, PHPUnit, lint, build, and packaging scripts are in place.
- [x] WP Plugin Toolkit Add Observability Tooling Prompt: completed. Redacted diagnostics, export, settings controls, and runtime status reporting are implemented.
- [x] WP Plugin Toolkit Feature Bloat And Structure Review Prompt: completed for implemented feature slices. Large runtime classes were split into focused traits where useful.
- [x] WP Plugin Toolkit Documentation Sync Audit Prompt: completed for the `0.1.0` release line; update again after any new release-facing behavior changes.
- [x] Alynt Plugin Updater Compatibility Guide: completed. GitHub release asset install and Alynt Plugin Updater detection/update validation passed for `v0.1.0`.

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

## Open Items

- [ ] Decide whether to remove old staging outbox packages to reclaim disk space. Current outbox retains three package sets totaling about `5.1G`.
- [ ] Push commit `90a1de2` after final review if the release branch should advance on GitHub.
- [ ] Run a final documentation sync audit if additional release-facing behavior changes before the next release.
