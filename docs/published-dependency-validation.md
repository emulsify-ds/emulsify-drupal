# Published dependency validation

The `Published Dependency Compatibility` workflow installs the latest stable
Emulsify release from both public Composer routes in four fresh Drupal sites.
It checks the declared `drupal/core: ^11.3 || ^12` and
`drupal/emulsify_tools: ^2.2` requirements without changing those constraints.

| Published route | Drupal constraint | Actual PHP runtime |
| --- | --- | --- |
| `drupal/emulsify` (Drupal.org) | `^11.3` | 8.3 |
| `emulsify-ds/emulsify-drupal` (Packagist) | `^11.3` | 8.3 |
| `drupal/emulsify` (Drupal.org) | `^12` | 8.5 |
| `emulsify-ds/emulsify-drupal` (Packagist) | `^12` | 8.5 |

Each leg reads the live
[Drupal.org JSON](https://packages.drupal.org/files/packages/8/p2/drupal/emulsify.json)
and [Packagist JSON](https://repo.packagist.org/p2/emulsify-ds/emulsify-drupal.json)
endpoints and compares their latest stable version with the repository's latest
stable tag. Both published Drupal and Tools requirements must equal the
repository's current Composer requirements. The actual installed theme version
must equal the verified release; this catches a registry change during the run.

The fixture is a new Composer project with `minimum-stability: dev` and
`prefer-stable: true`, matching the policy already declared by this package.
Composer stability policy belongs to the consuming root project; it is set
explicitly here because dependency manifests do not impose it on consumers.
The fixtures require `drupal/core-recommended` for the selected Drupal line,
Drush 13 for Drupal 11, and Drush 14 for Drupal 12. The package's published
requirements select Tools. No Git branch clone, copied theme, disabled security
blocking or changed public constraint substitutes for the published packages.

After installation, both the fixture and the downloaded theme must pass
`composer validate --strict`, and the installed tree must pass `composer audit`.
The fixture then installs Drupal with SQLite, enables Emulsify Tools and the
parent theme, sets Emulsify as default, and asks Drupal's services to load:

- `emulsify/global` and `emulsify/favicon_admin`.
- `emulsify.xsmall`, `emulsify.small`, `emulsify.medium`, `emulsify.large`,
  `emulsify.xlarge`, and `emulsify.xxlarge` breakpoints.

The package paths differ by route: Drupal.org installs under
`web/themes/contrib/emulsify`, while Packagist installs under
`web/themes/contrib/emulsify-drupal`. The check discovers the real directory
from Composer's installed package metadata. Both register the theme's machine
name as `emulsify`.

The workflow has read-only repository permissions, pinned action commits,
Composer download caching, and a 20-minute timeout per leg. Resolution,
validation, audit and registration failures fail the job; there is no advisory
`continue-on-error` path. Every leg uploads its Composer manifest and lockfile,
registry responses, install logs, audit output and registration results.

## Local reproduction

Run each command with its matching PHP binary first on `PATH`, using a new
(nonexistent) fixture directory. `COMPOSER_CACHE_DIR` may point to a shared
download cache.

```sh
bash .github/scripts/published-dependency-check.sh '^11.3' drupal/emulsify /tmp/published-11-drupalorg /tmp/evidence-11-drupalorg
bash .github/scripts/published-dependency-check.sh '^11.3' emulsify-ds/emulsify-drupal /tmp/published-11-packagist /tmp/evidence-11-packagist
bash .github/scripts/published-dependency-check.sh '^12' drupal/emulsify /tmp/published-12-drupalorg /tmp/evidence-12-drupalorg
bash .github/scripts/published-dependency-check.sh '^12' emulsify-ds/emulsify-drupal /tmp/published-12-packagist /tmp/evidence-12-packagist
```

The script refuses an existing fixture directory and verifies the actual PHP
major/minor version. It does not emulate a PHP version through Composer's
`config.platform` setting.

## Recorded run: September 8, 2026

Both registries advertise Emulsify 7.2.1 with matching requirements. Drupal 11
resolves to 11.4.6 with Tools 2.2.1 and Drush 13.7.7. Drupal 12 resolves to
**12.0.0-alpha1**, with Tools 2.2.1 and Drush 14.x-dev; no stable Drupal 12 release
is published at this point. The Drupal 12 leg is therefore evidence of current
prerelease compatibility, not proof against a stable Drupal 12 release. The
unchanged `^12` fixture will select stable Drupal 12 when it becomes available.

The metadata drift check was also exercised against a temporary copy of the
Drupal.org response whose Tools range was deliberately changed to `^999`. It
failed with `drupal/emulsify 7.2.1 drupal/emulsify_tools differs from
composer.json.` The real registry data and source constraints were unchanged.

All four final script runs exited 0. Both fixture and downloaded-theme strict
validation passed in every leg, each Composer audit returned empty `advisories`
and `abandoned` lists, and Drupal confirmed both libraries and all six
breakpoints. Native PHP versions were 8.3.33 and 8.5.10. Cached runs completed
in 32–37 seconds per leg while all four ran concurrently on the local host;
GitHub-hosted execution remains to be measured after this workflow is pushed.

[The recorded results](evidence/published-dependencies-2026-09-08/results.json)
include the route, runtime, Drupal version, theme path and registration details
for each leg. [Registry comparison](evidence/published-dependencies-2026-09-08/metadata.json)
records the version and requirements from both endpoints. The adjacent per-leg
folders contain full Composer audit JSON, strict validation output and every
installed package version, including exact references for development versions.
