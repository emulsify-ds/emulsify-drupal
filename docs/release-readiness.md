# Release Readiness

Use this checklist before publishing an Emulsify Drupal 7.x release.

## Local validation

1. Confirm the current published version on GitHub, Drupal.org, and the direct
   [Packagist JSON endpoint](https://repo.packagist.org/p2/emulsify-ds/emulsify-drupal.json).
   See the [installed-release policy](../README.md#identify-your-installed-release).
2. Run `composer validate --no-check-publish --strict`.
3. Run `npm ci --ignore-scripts`.
4. Run `npm audit --omit=dev`.
5. Run `npm audit` and triage any development-tool findings.
6. Run `npm run lint:php`.
7. Run `npm run docs:check-commands` after documentation command example changes.
8. Run `npm run release:check`.
9. Run `npm run release:check -- --skip-smoke` after any release-guard edits.

To check generated audit wrappers against a candidate Core tarball, use an
installed Drupal Composer runtime (no database is needed):

```bash
EMULSIFY_CORE_TARBALL=/absolute/path/to/emulsify-core.tgz \
EMULSIFY_DRUPAL_AUTOLOAD=/absolute/path/to/vendor/autoload.php \
  npm run test:audit-wrappers
```

This opt-in test runs Drupal's actual `generate-theme` command and Whisk post
processing, installs that tarball in the generated consumer, and compares both
audit wrappers with the installed Core executables. It checks whole JSON
stdout, stderr footers, exact exits 0/1/2, and arguments containing spaces.
Deliberately broken wrappers must fail the same assertions. Without both input
paths, the check reports a skip. Set `EMULSIFY_AUDIT_WRAPPER_EVIDENCE` to a file
outside the repository to retain the tested scripts, tarball hash, and results.

The runtime audit is blocking. The full dependency audit remains visible but
advisory while the current Semantic Release toolchain depends on an npm package
that bundles vulnerable development-only transitive dependencies. Do not add
overrides or patches for npm's bundled dependencies; restore the full audit as
a blocking check after upstream npm releases a clean bundle.

## Release checks

- Confirm `composer.json`, `emulsify.info.yml`, `whisk/whisk.info.yml`, and `whisk/whisk.info.emulsify.yml` describe the same Drupal core and Emulsify Tools compatibility.
- Confirm the declared Emulsify Tools range resolves to a published release;
  the Drush generator must delegate to Drupal Starterkit. The `^2.2` requirement
  was introduced in Emulsify Drupal 7.2.1.
- Confirm `LICENSE`, `package.json`, `composer.json`, and `whisk/package.json` all identify the project as `GPL-2.0-or-later`, matching Drupal.org Composer metadata guidance for hosted Drupal projects.
- Confirm local README and upgrade notes describe the current Emulsify Drupal parent theme workflow, Drupal 11.3+ and Drupal 12 forward compatibility, and favicon package deployment expectations. PHP minimums are inherited from Drupal: 8.3 for Drupal 11 and 8.5 for Drupal 12. Whisk advertises Node.js `>=24`, Emulsify Core 4.3.1/4.4.0 require `>=24.13.0`, and release tooling requires `>=24.15`; preserve and document this distinction.
- Confirm the sister-project parity contract stays linked from the README and preserves the shared Emulsify Drupal/WordPress contract plus Drupal-specific generation, metadata, component include, and fixture readiness expectations.
- Confirm the Whisk starter remains generation-only and generated child themes keep `emulsify` as their runtime parent theme; review the [generated child-theme contract](./generated-child-theme-contract.md) for generation guarantees and intentional exclusions.
- Confirm both Drupal core and Drush generation retain `project.emulsify.json`
  with the generated project identity plus `platform`,
  `singleDirectoryComponents`, `generatedFrom`, and `generatedFromVersion`
  support metadata.
- Confirm generated child themes include a project-specific `README.md` plus `docs/development.md`, `docs/upgrading.md`, and `docs/support-information.md`; the shared documentation checker must validate their npm commands in both the Whisk source and real generated output.
- Confirm generated guidance keeps the project component-library-neutral, distinguishes npm dependency updates from comparing a fresh newer starter, and reserves future automated Drupal diagnostics for Emulsify Tools.
- Confirm `@emulsify/core` `4.3.1` or newer has been published with the `emulsify-inspect-components` binary before merging or releasing this Emulsify Drupal feature.
- Confirm the declared Whisk `@emulsify/core` range resolves to 4.3.1 or newer,
  exposes `npm run inspect:components`, and does not ship project asset source
  directories, entrypoints, or an attached global library before a component
  library is selected.
- Confirm favicon defaults, install config, schema, and `FaviconSettings::DEFAULTS` remain in sync.
- Confirm release automation still emits non-prefixed SemVer tags and has `npmPublish: false`.

## CI coverage

- Pull requests run Composer validation, `npm ci --ignore-scripts`, a blocking runtime npm audit, an advisory full dev-tool audit, PHP linting, static release checks, template parity, parent-theme render smoke, favicon smoke with GD and Imagick, and Whisk-starter generated child-theme build/test smoke, including byte-for-byte Drupal core/Drush generation parity and component inspection.
- The semantic-release workflow runs a blocking release-readiness job before publishing from `main`. That job repeats Composer validation, clean npm install, the blocking runtime audit, the advisory full dev-tool audit, PHP linting, static release checks, and full `npm run release:check` smoke coverage with GD and Imagick.
- Pull requests, pushes, scheduled runs, and manual Theme Readiness runs include a generated consumer job that builds Vite and Storybook, runs `npm run a11y`, and audits a real component plus Drupal pages against explicit WCAG 2.2 AA rules. The [consumer accessibility evidence](./consumer-accessibility.md) records current unsuppressed findings; a passing build alone does not make this gate pass.

## Version strategy

Semantic Release publishes only from `main` with the Angular conventional-commit preset. Use commit types intentionally:

- `feat:` produces a minor release.
- `fix:` and `perf:` produce a patch release.
- `docs:`, `test:`, `chore:`, `ci:`, and merge commits without a release-triggering conventional subject do not produce a release by themselves.
- `type!:` headers or `BREAKING CHANGE` footers produce a major release and should not be used for a 7.x minor unless maintainers are intentionally preparing the next major line.

Run `npm run publish-test -- --no-ci` before release. On non-`main` branches, this should load the release configuration but report that no version will be published because `release.config.js` only publishes from `main`. To predict the eventual version, review commits since the latest tag with the configured analyzer. Fixes produce a patch release; docs, tests, CI, and internal chores do not increase that release type.

Do not label fixes, tests, metadata cleanup, or documentation-only work as
`feat:` just to force a minor version. Keep
[docs/release-notes-next.md](./release-notes-next.md) unnumbered until the release
analyzer determines the version, and remove published changes after release.

`@semantic-release/npm` owns the root npm version during release preparation.
With `npmPublish: false` and no Git commit-back plugin, that version is not
persisted to the release tag's tree. Do not hand-edit it to match a tag. The
release guard checks the npm plugin policy and compares the committed npm
version with the latest tag's metadata baseline; it also rejects draft release
titles that name an existing tag. Run the guard in a full Git checkout with tags
(CI uses `fetch-depth: 0`). Published tags and Composer package metadata identify
the parent release; `generatedFromVersion` describes starter lineage only.

## Manual follow-up

- After publishing, verify Drupal.org and Packagist show the Composer package license as `GPL-2.0-or-later`.
- Copy final GitHub release notes into Drupal.org release notes and mark the release supported or recommended as appropriate.
