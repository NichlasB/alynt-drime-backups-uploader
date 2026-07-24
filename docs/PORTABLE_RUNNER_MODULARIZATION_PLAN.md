# Portable Runner Modularization And Deterministic Build Plan

Updated: 2026-07-24

## At A Glance

| Item | Decision |
| --- | --- |
| Goal | Make the server runner easier and safer to maintain without changing its commands or deployment shape |
| Source shape | Small, valid PHP source modules grouped by responsibility |
| Deployment shape | One generated `server-runner/alynt-backup-runner.php` file |
| Build rule | Fixed source order, normalized line endings, no timestamps, reproducible output |
| Runtime behavior | No intentional behavior, config, command, report-schema, or confirmation changes |
| Initial validation | Existing complete runner test matrix plus generated-output drift checks |
| Runtime target | `hbf-staging` for separately approved non-destructive parity checks |
| Destructive rehearsal | Not required by default; reconsider only if review finds behavior-affecting risk |
| Release | Separate Git/release approval after implementation, reviews, and staging parity |

## Purpose

The standalone runner has grown into a safety-critical file of roughly 6,400
source lines and more than 140 methods. It now owns backup creation, package
inventory, cleanup, Drime fetching, restore staging, staging restore,
production-simulation preflight, apply, rollback, maintenance control,
filesystem safety, reporting, and command dispatch.

The deployed one-file format remains useful for GridPane installation and
rollback. The maintenance problem is the source layout, not the deployment
format. This project will introduce maintainable source modules and a
deterministic build that continues to emit the same one-file runner contract.

## Current Contract To Preserve

- The installed runner path remains
  `server-runner/alynt-backup-runner.php`.
- Existing generated setup commands continue copying that one file.
- Every current CLI command, option, confirmation phrase, exit behavior, JSON
  field, report location, and safety refusal remains compatible.
- Existing private `config.json` files remain compatible without migration.
- Existing package, restore, pre-restore evidence, apply-report, and rollback
  report schemas remain compatible.
- The WordPress plugin continues shipping only the generated runner needed at
  runtime.
- The standalone runner identity remains independent from the WordPress plugin
  version. Any changed generated artifact receives a new runner identity so
  deployed checksums are never mislabeled as runner `0.4.7`.

## Non-Goals

- No new backup producer, archive format, restore command, or UI.
- No relaxation of staging or production-simulation safety gates.
- No automatic runner deployment.
- No automatic cron installation.
- No actual-production enrollment or restore.
- No destructive staging rehearsal unless later review establishes that the
  refactor changed behavior in a way automated and read-only tests cannot
  adequately cover.

## Target Source Layout

The exact file names may be adjusted during the structure review, but the
responsibility boundaries should remain close to:

```text
server-runner/
  alynt-backup-runner.php          Generated deployable artifact
  src/
    runner-bootstrap.php           Runner identity, class shell, dispatch
    backup.php                     Health, package creation, DB export, archive
    inventory-cleanup.php          List, inventory, preview, approved cleanup
    package-restore.php            Verify, inspect, fetch, stage
    staging-restore.php            Dry run, pre-backup, staging apply
    production-preflight.php       Production-simulation identity and preflight
    production-apply.php           Apply, maintenance, reports
    production-rollback.php        Rollback, recovery, cleanup
    filesystem-security.php        Paths, archives, symlinks, ownership
    drime-client.php               Remote listing and streamed downloads
    config-runtime.php             Config access, process and filesystem helpers
    cli-entrypoint.php             Argument parsing, config loading, invocation
scripts/
  build-runner.mjs                 Deterministic single-file generator
```

Source modules should be valid PHP units so syntax and coding-standard tools can
inspect them directly. Traits are the preferred first implementation because
they preserve the existing class method visibility and `$this` behavior while
allowing responsibility-based files. A broader service-object redesign is
outside this slice.

## Deterministic Build Contract

The generator must:

1. Use an explicit, reviewed module order.
2. Normalize generated output to LF and one final newline.
3. Add a generated-file warning and the source manifest without adding a
   timestamp or machine-specific path.
4. Produce identical bytes for identical source on Windows and Linux.
5. Write atomically so a failed build cannot leave a partial runner.
6. Fail on a missing, duplicate, or unexpected module.
7. Preserve the executable PHP entrypoint and direct CLI behavior.

`npm run build` should generate both frontend assets and the runner. A focused
`npm run build:runner` command should be available for runner-only work. CI must
run the build and fail when the committed generated runner differs from the
source modules.

The release ZIP must include `server-runner/alynt-backup-runner.php` and exclude
`server-runner/src/` plus the generator script. This keeps the production
package minimal and preserves current installation commands.

## Implementation Phases

### Phase 0: Freeze Baseline Evidence

- Record the current runner identity and SHA-256.
- Record the generated file size, method inventory, CLI usage output, and
  current automated-test totals.
- Run PHP syntax, PHPCS, PHPUnit, build, and whitespace checks before moving
  code.
- Preserve an exact local baseline artifact for byte and behavior comparison.

Gate: no source movement begins until the existing baseline passes.

### Phase 1: Add Generation Without Logical Splitting

Status: completed locally on 2026-07-24.

- Add the runner generator and explicit source manifest.
- Move the current authoritative source under `server-runner/src/` with the
  smallest possible structural change.
- Generate the deployable runner from that source.
- Add repeat-build and cross-platform line-ending tests.
- Prove two consecutive builds produce the same SHA-256.

Gate: the generated runner must pass the complete existing runner test matrix
before responsibility-based splitting begins.

### Phase 2: Split One Responsibility At A Time

Status: all eight batches completed locally on 2026-07-24.

Move methods in small ordered batches:

1. inventory and cleanup;
2. package verification, fetching, and staging;
3. backup creation and archive helpers;
4. staging restore;
5. production preflight;
6. production apply;
7. production rollback;
8. shared filesystem, Drime, config, and CLI helpers.

After each batch:

- regenerate the runner;
- run PHP syntax and focused tests for the moved responsibility;
- run the full runner test matrix;
- compare CLI usage and public report schemas;
- inspect the diff in the generated artifact before continuing.

Gate: stop and revert only the current batch if parity fails. Do not combine a
failed structural move with a behavioral fix.

### Phase 3: Build, CI, And Packaging Integration

Status: implemented locally on 2026-07-24; clean-checkout Linux CI proof remains
pending until Git operations are separately approved.

- Add `build:runner` and generated-runner verification scripts.
- Integrate runner generation into the existing production build.
- Extend GitHub quality checks so generated runner drift fails on PHP 7.4 and
  PHP 8.3 jobs or in the Node build job, whichever keeps the failure clearest.
- Update release packaging to exclude modular runner source while retaining the
  generated artifact.
- Add a release ZIP assertion for the generated runner and absence of source
  modules.

Gate: a clean checkout must build and test without relying on an untracked
local file.

### Phase 4: Automated Parity Validation

Status: local audit and missing coverage completed on 2026-07-24; Linux PHP
matrix execution remains pending CI.

Required checks:

- all current PHPUnit runner tests;
- full plugin PHPUnit suite;
- PHPCS for production plugin PHP plus dedicated PHP syntax checks for modular
  runner source and the generated runner;
- PHP 7.4 and PHP 8.3 syntax/behavior compatibility;
- deterministic repeat-build hash;
- generated-file freshness check;
- command usage snapshot;
- config-example compatibility;
- report-schema and exact-confirmation regression tests;
- release ZIP content audit;
- `git diff --check`.

No test should switch to running modular source directly. Runtime tests must
continue executing the generated one-file artifact because that is what servers
receive.

### Phase 5: Staging Parity

Status: completed on `hbf-staging` on 2026-07-24.

After separate approval:

1. Select `hbf-staging`, confirm current health, and record the deployed runner
   identity and checksum.
2. Preserve a recoverable copy of the deployed runner.
3. Deploy the generated candidate atomically without changing config or cron.
4. Run runner health, inventory, package verification, restore inspection, and
   the existing read-only production-simulation preflight.
5. Confirm WordPress identity, maintenance state, HTTP health, paths, ownership,
   reports, and cron remain unchanged.
6. Restore the preserved runner immediately if parity fails.

A new package creation may be added only after a fresh disk-space check and
separate approval. Destructive apply/rollback is not part of the default parity
rehearsal.

### Phase 6: Post-Feature Reviews

Status: completed on 2026-07-24.

Run the applicable wp-plugin-toolkit workflows in the established order:

1. Feature Light Review.
2. Feature Bloat And Structure Review.
3. Feature UI/UX Implementation Review only if operator-facing output changes.
4. Feature Security Review.
5. Documentation Sync Audit.

Then update the pre-release checklist. Run Git Operations only after the
implementation, validation, and staging evidence are accepted.

## Acceptance Criteria

- Developers edit modular source rather than the generated runner.
- One deterministic command produces the deployable runner.
- Repeated Windows and Linux builds produce identical runner bytes.
- CI rejects stale generated output.
- The release ZIP contains one deployable runner and no modular source.
- Existing setup, deployment, cron, CLI, config, package, and report contracts
  remain compatible.
- Existing automated runner behavior passes against the generated artifact.
- Approved `hbf-staging` read-only parity checks pass with no config, cron,
  maintenance, ownership, or site-health regression.
- The old deployed runner can be restored from its exact preserved copy.
- Documentation clearly distinguishes source modules, generated artifact,
  runner identity, and plugin release identity.

## Main Risks And Controls

| Risk | Control |
| --- | --- |
| Module ordering changes behavior | Explicit manifest and generated diff review |
| Generated file becomes stale | Build plus CI drift failure |
| Windows/Linux output differs | LF normalization and repeat-hash tests |
| Tests accidentally exercise source only | All CLI tests execute generated artifact |
| Release ships development modules | Release ZIP exclusion and content assertion |
| Deployed runner cannot be identified | New independent runner identity and SHA record |
| Large move hides a behavior change | Small responsibility batches with full tests |
| Staging deployment regresses runtime | Atomic deployment, preserved binary, read-only parity |

## Approval Gates

The following require separate confirmation:

1. Beginning implementation after this plan is reviewed.
2. Deploying the generated runner to `hbf-staging`.
3. Creating a real server backup package during parity testing.
4. Running any destructive restore apply or rollback, if later justified.
5. Git commit, push, tag, and release operations.

## Current Status

Plan approved on 2026-07-24. Phase 0 baseline evidence completed on the same
date without moving runner code:

- Repository baseline commit:
  `b344bfb62b8184c924638756bd50f90956573f30`.
- Runner identity: `0.4.7`.
- Runner size: `274195` bytes and `6412` lines.
- Runner inventory: `206` class methods and `15` command dispatch cases.
- Runner SHA-256:
  `16faf5e026b86d37b1f7834d394e09e14a894ae21bdfd5f117a34af8b4765321`.
- Exact local baseline copy:
  `C:\Users\Captain\Documents\AI Workflows\work\alynt-runner-modularization-baseline\alynt-backup-runner-0.4.7-16faf5e026b86d37.php`.
- Local PHP `8.5.1` syntax check passed.
- Existing CLI usage output was captured and contains all `15` commands.
- PHPCS passed all `58` configured production PHP files.
- PHPUnit passed `214` tests with `1656` assertions and `4` expected Windows
  symlink skips.
- The production asset build passed and changed no tracked generated asset.
- The runner hash remained unchanged after validation.
- `git diff --check` passed with only the existing CRLF normalization warning
  for `PRE_RELEASE_CHECKLIST.md`.

Phase 1 completed locally on 2026-07-24:

- The authoritative, still-logically-intact source is
  `server-runner/src/runner.php`.
- `scripts/build-runner.mjs` uses an explicit manifest, normalizes LF endings,
  adds no timestamp or machine path, writes atomically, and avoids rewriting a
  current artifact.
- `scripts/verify-runner-build.mjs` proves repeat rendering, generated-output
  freshness, manifest recording, LF-only output, and exactly one final newline.
- `scripts/lint-runner.mjs` runs PHP syntax checks against authoritative source
  and generated output on the local platform and in the PHP CI matrix.
- `npm run build` now generates the runner before frontend assets.
- GitHub quality checks rebuild and verify the runner and fail on generated
  drift. Release packaging excludes `server-runner/src/` and retains the
  generated runner.
- The generated artifact differs from the frozen `0.4.7` baseline logic only
  by its generated-file header and independent runner identity.
- Runner identity advanced to `0.4.8`.
- Generated runner SHA-256:
  `558fe5a3fa4f04b478480bfc0763f58f043eb9250a341f65992f1ba5b6d04f40`.
- Repeated generation reports the runner current with the same SHA-256.
- Baseline and generated CLI usage output match exactly with exit code `0`.
- Source and generated PHP syntax checks pass.
- The final production build and PHPCS across `58` configured plugin PHP files
  pass.
- The final PHPUnit suite passes `214` tests with `1656` assertions and `4`
  expected Windows symlink skips against the generated artifact.
- `git diff --check` passes with line-ending normalization warnings only.

Linux CI proof, release ZIP content proof, post-feature reviews, and staging
parity remain later gates. No server, config, cron, or release state has been
changed.

Phase 2 batch 1 completed locally on 2026-07-24:

- Added `server-runner/src/inventory-cleanup.php` as a valid PHP trait.
- Moved the original contiguous package inventory, catalog snapshot,
  cleanup-preview, approved cleanup, containment, and age-calculation methods
  without changing their bodies.
- The trait contains `19` methods across `560` lines; the entrypoint retains
  `187` methods across `5862` lines.
- The generator manifest now emits the trait before the runner class, whose
  first declaration uses the trait.
- Generated runner SHA-256:
  `96c7c028298ce5fee2cede82f3b6c059dec5498e05e92a989c5ed386e6e2dfe3`.
- Baseline and generated CLI usage output still match exactly with exit code
  `0`.
- Source and generated PHP syntax checks pass.
- Focused inventory tests pass `4` tests with `92` assertions.
- Focused cleanup tests pass `3` tests with `46` assertions.
- Focused runner security tests pass `8` tests with `104` assertions.
- The production build and PHPCS across `58` configured plugin PHP files pass.
- The complete suite passes `214` tests with `1656` assertions and `4`
  expected Windows symlink skips.

Phase 2 batch 2 completed locally on 2026-07-24:

- Added `server-runner/src/package-restore.php` as a valid PHP trait.
- Moved the original contiguous command-level package verification, inspection,
  Drime fetch, restore staging, restore report, and staged integrity methods
  without changing their bodies.
- The trait contains `7` methods across `404` lines; the entrypoint retains
  `180` methods across `5467` lines.
- Lower-level shared package verification, Drime HTTP/download, archive
  validation, and path helpers remain in the entrypoint for the later
  responsibility-based shared-helper batch.
- The generator manifest now emits the inventory/cleanup trait, then the
  package/restore trait, before the runner class that uses both traits.
- Generated runner SHA-256:
  `b6c1063d77d92e1eb8cf19e21221f50a638a440bfc527ba7127a03ea34669e00`.
- Baseline and generated CLI usage output still match exactly with exit code
  `0`.
- Source and generated PHP syntax checks pass.
- Focused inventory/package tests pass `4` tests with `92` assertions.
- Focused restore staging tests pass `6` tests with `111` assertions.
- Focused runner security tests pass `8` tests with `104` assertions.
- The production build and PHPCS across `58` configured plugin PHP files pass.
- The complete suite passes `214` tests with `1656` assertions and `4`
  expected Windows symlink skips.

Phase 2 batch 3 completed locally on 2026-07-24:

- Added `server-runner/src/backup-archive.php` as a valid PHP trait.
- Moved backup package orchestration, quiet health checks, database export,
  archive creation, tar-warning handling, manifest/timing metadata, consistency
  metadata, and archive exclusion methods without changing their bodies.
- The trait contains `17` methods across `513` lines; the entrypoint retains
  `163` methods across `4969` lines.
- Restore database import and pre-restore file-backup creation remain in the
  entrypoint for the staging and production restore batches.
- The generator manifest now emits the inventory/cleanup, package/restore, and
  backup/archive traits before the runner class that uses all three traits.
- Generated runner SHA-256:
  `3af43209895a4be63a1ca1b3ae34932b777c00bb112537a2d7e39fb3cc87ca6f`.
- Baseline and generated CLI usage output still match exactly with exit code
  `0`.
- Source and generated PHP syntax checks pass.
- Focused package/inventory tests pass `4` tests with `92` assertions.
- Focused pre-restore backup creation tests pass `2` tests with `27`
  assertions.
- Focused runner security tests pass `8` tests with `104` assertions.
- The production build and PHPCS across `58` configured plugin PHP files pass.
- The complete suite passes `214` tests with `1656` assertions and `4`
  expected Windows symlink skips.

Phase 2 batch 4 completed locally on 2026-07-24:

- Added `server-runner/src/staging-restore.php` as a valid PHP trait.
- Moved staging restore dry-run/apply commands, database/files/combined result
  handling, automatic pre-restore evidence creation, staging reports, known
  drop-in review, and staging result output without changing method bodies.
- The trait contains `20` methods across `1256` lines; the entrypoint retains
  `143` methods across `3726` lines.
- Production-facing report readers, target replacement, scope checks, path
  safety, symlink copying, database import, and generic check helpers remain in
  the entrypoint for production or shared-helper batches.
- The generator manifest now emits four responsibility traits before the
  runner class that uses them.
- Generated runner SHA-256:
  `43a8c08353d2af2aefbeaf08b5c265cea2fb4a7220883d09238c0605566ff6c2`.
- Baseline and generated CLI usage output still match exactly with exit code
  `0`.
- Source and generated PHP syntax checks pass.
- Focused staging restore coverage passes `23` tests with `333` assertions and
  `1` expected Windows symlink skip.
- The production build and PHPCS across `58` configured plugin PHP files pass.
- The complete suite passes `214` tests with `1656` assertions and `4`
  expected Windows symlink skips.

Phase 2 batch 5 completed locally on 2026-07-24:

- Added `server-runner/src/production-preflight.php` as a valid PHP trait.
- Moved read-only production preflight orchestration, runtime/package identity,
  staged-integrity validation, disk budgeting, native-backup evidence checks,
  filesystem markers, and preflight report output without changing method
  bodies.
- The trait contains `18` methods across `659` lines; the entrypoint retains
  `125` methods across `3083` lines.
- Production pre-backup creation, WP-CLI actions, maintenance changes, symlink
  restoration, apply, rollback, and shared report redaction remain in the
  entrypoint for later production or shared-helper batches.
- The generator manifest now emits five responsibility traits before the
  runner class that uses them.
- Generated runner SHA-256:
  `f712dc24cbac00e2dc01cd1afca9ee0ffcbf5fce8350a77a95d5125d9735097e`.
- Baseline and generated CLI usage output still match exactly with exit code
  `0`.
- Source and generated PHP syntax checks pass.
- Focused production preflight and apply/rollback consumer coverage passes `28`
  tests with `364` assertions and `3` expected Windows symlink skips.
- The production build and PHPCS across `58` configured plugin PHP files pass.
- The complete suite passes `214` tests with `1656` assertions and `4`
  expected Windows symlink skips.

Phase 2 batch 6 completed locally on 2026-07-24:

- Added `server-runner/src/production-apply.php` as a valid PHP trait.
- Moved production pre-restore recovery-evidence creation, confirmation-gated
  apply orchestration, apply reporting, and apply-only enrolled-symlink
  preservation without changing method bodies.
- The trait contains `8` methods across `520` lines; the entrypoint retains
  `117` methods across `2573` lines.
- Rollback remains wholly in the entrypoint. Shared WP-CLI actions, emergency
  maintenance control, report redaction, target replacement, database import,
  and filesystem helpers remain available to both apply and rollback for the
  shared-helper batch.
- The generator manifest now emits six responsibility traits before the runner
  class that uses them.
- Generated runner SHA-256:
  `6940088e8844d8cda79798d1446bb8a87c757f495a6066fadd7d6427ebeeb481`.
- Baseline and generated CLI usage output still match exactly with exit code
  `0`.
- Source and generated PHP syntax checks pass.
- Focused production apply, failure, combined-recovery, identity, and security
  coverage passes `28` tests with `347` assertions and `1` expected Windows
  symlink skip.
- The production build and PHPCS across `58` configured plugin PHP files pass.
- The complete suite passes `214` tests with `1656` assertions and `4`
  expected Windows symlink skips.

Phase 2 batch 7 completed locally on 2026-07-24:

- Added `server-runner/src/production-rollback.php` as a valid PHP trait.
- Moved production rollback command/result handling, damaged-target preflight
  filtering, verified recovery archive application, private extraction cleanup,
  and rollback reporting without changing method bodies.
- The trait contains `7` methods across `365` lines; the entrypoint retains
  `110` methods across `2219` lines.
- Production pre-restore evidence/artifact validators remain in the entrypoint
  because both production apply and rollback consume them. Shared maintenance,
  WP-CLI action, report redaction, target replacement, database import, and
  filesystem helpers also remain for batch 8.
- The generator manifest now emits seven responsibility traits before the
  runner class that uses them.
- Generated runner SHA-256:
  `1a10b1b694aae4119539e3e4b8d1400e58cb43c80b2ba176c2bbc75fa792512f`.
- Baseline and generated CLI usage output still match exactly with exit code
  `0`.
- Source and generated PHP syntax checks pass.
- Focused rollback, maintenance recovery, extraction cleanup, database/files/
  combined failure, identity, and security coverage passes `26` tests with
  `359` assertions and `2` expected Windows symlink skips.
- The production build and PHPCS across `58` configured plugin PHP files pass.
- The complete suite passes `214` tests with `1656` assertions and `4`
  expected Windows symlink skips.

Phase 2 batch 8 completed locally on 2026-07-24:

- Added focused shared modules for production control, filesystem security,
  package support, the Drime client, and configuration/runtime behavior.
- Moved CLI parsing, config loading, library-only handling, and process startup
  into a final CLI entrypoint module.
- Moved `health()` into the existing backup/archive responsibility trait.
- The runner class entrypoint is now `104` lines with only its constructor,
  command dispatch, and usage methods. All `206` class methods remain present
  across the authoritative source modules.
- The explicit generator manifest now contains `14` ordered source files and
  emits CLI startup last.
- Generated runner SHA-256:
  `5f52fd679be5509649185cf4b4acc7774eea84da65bba49679086c7cd5e13376`.
- Baseline and generated CLI usage output still match exactly with exit code
  `0`.
- Source and generated PHP syntax checks pass.
- Focused inventory, restore dry-run, production preflight, production apply,
  production rollback, and security coverage passes `36` tests with `539`
  assertions and `3` expected Windows symlink skips.
- The production build and PHPCS across `58` configured plugin PHP files pass.
- The complete suite passes `214` tests with `1656` assertions and `4`
  expected Windows symlink skips.

Phase 3 build, CI, and packaging integration completed locally on 2026-07-24:

- `npm run build` generates the runner before production assets.
- GitHub PHP `7.4` and `8.3` jobs syntax-check all modular sources and the
  generated artifact; the Node job rebuilds and rejects generated drift.
- Release packaging excludes `server-runner/src/` while retaining
  `server-runner/alynt-backup-runner.php`.
- Added dependency-free `scripts/verify-release-zip.mjs` and
  `npm run verify:release-zip`.
- The release workflow now validates the ZIP before uploading its GitHub
  release asset.
- ZIP validation requires the expected plugin root and generated runner,
  rejects modular source, and refuses duplicate, absolute, traversal,
  backslash, and drive-prefixed paths.
- An isolated 73-entry release-shaped ZIP passed locally.
- A ZIP containing `server-runner/src/runner.php` was refused for modular
  source contamination.
- A ZIP without the generated runner was refused for the missing required
  artifact.
- The production build, runner syntax checks, PHPCS across `58` files, and the
  complete `214`-test/`1656`-assertion suite pass after the packaging change.
- Clean-checkout Linux execution and PHP `7.4`/`8.3` proof remain CI evidence
  that cannot exist until the current changes are committed and pushed under a
  separately approved Git operation.

Phase 4 automated parity validation completed locally on 2026-07-24:

| Required check | Evidence |
| --- | --- |
| Current runner tests | All CLI test helpers execute `server-runner/alynt-backup-runner.php`, the generated deployable artifact |
| Full plugin suite | `216` tests and `1668` assertions pass with `4` expected Windows symlink skips |
| PHPCS and runner syntax | PHPCS passes all `58` configured files; every source module and generated runner passes `php -l` |
| PHP `7.4` and `8.3` | GitHub Actions Quality run `30104887039` passed both clean-checkout jobs on commit `a306b221181a0b17e932a8c9b0ad728f14ebc9e7` |
| Deterministic build hash | `npm run verify:runner` renders twice and verifies SHA-256 `5f52fd679be5509649185cf4b4acc7774eea84da65bba49679086c7cd5e13376` |
| Generated freshness | The same verifier compares rendered bytes with the committed generated runner |
| CLI usage snapshot | Added `ServerRunnerParityTest::test_cli_usage_matches_snapshot` and a frozen usage fixture |
| Config-example compatibility | Added a real `list --format=json` execution using the published example with isolated local paths |
| Report schemas and confirmations | Existing staging/production preflight, apply, rollback, cleanup, failure, and security tests assert schema fields and exact confirmation refusal/acceptance behavior |
| Release ZIP audit | Release workflow runs `verify:release-zip`; valid, source-contaminated, and missing-runner packages were exercised locally |
| Diff hygiene | `git diff --check` and trailing-whitespace scans pass with line-ending normalization warnings only |

No additional report-schema or confirmation tests were added because the
existing behavioral matrix already exercises successful and refused staging
and production paths, including wrong phrases, wrong host confirmation,
disabled flags, stale evidence, tampering, and recovery retries.

Phase 5 staging parity completed on 2026-07-24:

- Confirmed target `hbf-staging` through SSH alias `drm-1` at
  `/var/www/staging.handcraftedbotanicalformulas.com/htdocs`.
- Baseline runner `0.4.7` SHA-256 was
  `f2fa8d004ee431dfe9ea54f8f4bb1cd48d9d7740521b39d60929371a3124fbcd`.
- Preserved the exact baseline with original ownership and mode as
  `alynt-backup-runner.php.pre-0.4.8-20260724-150207.bak`.
- Transferred, independently hashed, and atomically installed generated runner
  `0.4.8` with SHA-256
  `5f52fd679be5509649185cf4b4acc7774eea84da65bba49679086c7cd5e13376`.
- Deployed ownership remains `handcraftedbotanicalformulas-com`, group remains
  the same site user, and mode remains `0750`.
- Runner health passed all checks as the site user.
- Inventory matched the baseline: one `1021656232`-byte, verification-ready
  package with the same package ID, metadata, and checksum.
- Package verification passed and restore inspection produced the same package
  identity, timing, archive preview, and non-destructive guidance.
- Combined-scope read-only production preflight retained the same `2`
  pre-existing refusals before and after deployment:
  `expected_active_plugins_match` for enrollment drift and
  `native_backup_fresh` for July 20 evidence outside its freshness window.
- Preflight otherwise retained matching target/package fingerprints and safety
  controls; destructive actions, database import, file overwrite, pre-restore
  backup creation, native-backup creation, maintenance changes, and report
  writes all remained false.
- Config SHA-256 remained
  `831b6bbb77d2738d92d3c9978ef06e397ff1d05aa4025104832368e646b85259`.
- No site-user crontab existed before or after the deployment.
- WordPress remained version `7.0.2`; home and site URL remained
  `https://staging.handcraftedbotanicalformulas.com`; maintenance remained
  inactive; HTTPS remained `200`.
- No package was created, no report was written, no restore command ran, and no
  database, config, cron, plugin, theme, or site-content state was changed.

Phase 6 post-feature reviews completed on 2026-07-24:

- Feature Light Review covered build/CI, deterministic generation, portable
  file operations, packaging validation, CLI contracts, and directly affected
  documentation. No non-security issue or automatic fix was found.
- Feature Bloat And Structure Review used explicit base
  `b344bfb62b8184c924638756bd50f90956573f30`, reporting `16` changed PHP/JS/CSS
  files and `12` files above the generic PHP threshold.
- No dead code, duplicate troubleshooting paths, commented-out experiments, or
  debug remnants were found. The generated `6562`-line artifact is intentionally
  exempt because it is the deployable one-file contract. The larger source
  traits remain cohesive responsibility modules; splitting them further in a
  feature-stage cleanup would reopen architecture-sensitive boundaries.
- The post-review measurement remained identical because no Phase 2 cleanup
  edit was required.
- Feature UI/UX Implementation Review was not applicable: no admin/frontend
  UI, forms, AJAX, notices, JavaScript interaction, or user-facing runtime copy
  changed.
- Feature Security Review found no issue. Static scans found no introduced
  dangerous input/runtime pattern, generation uses a fixed manifest and atomic
  output, release ZIP metadata and paths are bounded/validated, and
  `npm audit --audit-level=high` reported zero vulnerabilities.
- Documentation Sync Audit confirmed plugin `0.5.1`, WordPress `6.0`, PHP
  `7.4`, text domain `alynt-drime-backups-uploader`, GitHub repository
  `NichlasB/alynt-drime-backups-uploader`, and latest tag `v0.5.1`.
- Documentation Sync updated high-confidence development guidance for modular
  runner generation/verification and corrected restore wording to distinguish
  supported production-simulation from unavailable actual-production
  enrollment. No low-confidence item was applied.

Phase 7 pre-release and packaging validation completed on 2026-07-24:

- Refreshed the complete toolkit sequence from model assessment through the
  final security audit. No production-code or test defect was confirmed.
- The temporary model-assessment file was removed before package creation.
- `npm test -- --do-not-cache-result` passed `216` tests and `1668` assertions
  with `4` expected Windows symlink skips.
- PHPCS passed all `58` configured production files.
- The production build completed with the generated runner already current.
- Deterministic verification retained SHA-256
  `5f52fd679be5509649185cf4b4acc7774eea84da65bba49679086c7cd5e13376`;
  all 14 source files and the generated artifact passed PHP syntax checks.
- `npm audit --audit-level=high` reported zero vulnerabilities. A local Composer
  audit could not be rerun because neither a Composer executable nor a
  repo-local phar is installed; the unchanged lockfile has no runtime package
  dependency.
- A first Windows-native ZIP was correctly refused for backslash entry names.
  The portable release-shaped ZIP was rebuilt with standard forward-slash
  paths and passed: `73` entries, generated runner present, modular source
  absent, no duplicate or unsafe paths, and zero forbidden development paths.
- Plugin release metadata remained at the accepted `0.5.1` baseline during
  this gate; no version bump, tag, or release publication occurred until the
  separate release approval was received.

Phase 8 release and acceptance completed on 2026-07-24:

- Release commit `19ec5311a9f34f907c4f9af0bed1b88b52c27791` passed the required
  Node, PHP `7.4`, and PHP `8.3` quality workflow before tagging.
- Annotated tag and GitHub release `v0.5.2` were published with asset
  `alynt-drime-backups-uploader-v0.5.2.zip`.
- Release workflow `30107383101` passed its independent quality gate and
  attached the `193557`-byte ZIP with SHA-256
  `3323a299298b4e59276e7ee067a95d8c68e65927481609db3471d9fd3dab87e6`.
- The downloaded 73-entry asset passed the package verifier, contained plugin
  `0.5.2` and runner `0.4.8`, included the generated runner, excluded modular
  source, and contained zero forbidden development paths.
- On `plugin-tester.local`, Alynt Plugin Updater detected `0.5.2` from active
  `0.5.1` and WordPress rendered the native update action. The first asset
  request received a transient `504`; maintenance mode cleared and `0.5.1`
  remained intact. A repeat WordPress HTTP probe received the complete asset
  with HTTP `200`, and the native retry completed download, unpack, install,
  old-version removal, and maintenance cleanup successfully.
- Final checks confirmed active plugin `0.5.2`, no remaining update notice,
  no maintenance marker, a working settings page, diagnostics version `0.5.2`,
  and no browser console errors.
- `v0.5.2` is accepted as the stable baseline.

Exact next step: return to the implementation roadmap and choose an optional
future feature slice or continue controlled site-by-site rollout; no required
portable-runner modularization work remains.
