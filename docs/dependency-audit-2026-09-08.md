# Dependency audit — September 8, 2026

The root dependency audit now reports zero vulnerabilities, down from seven
vulnerable package entries. The fresh Whisk installation resolves Emulsify Core
4.4.0 and reports six high-severity package entries, all caused by one unresolved
upstream `extract-zip` advisory. The installed Composer dependency tree reports
no advisories or abandoned packages. These are point-in-time results, not a
promise that future registry resolutions will remain unchanged.

## Changes and preserved constraints

The local lockfile incorporates the changes reviewed in
[PR #378](https://github.com/emulsify-ds/emulsify-drupal/pull/378) and
[PR #379](https://github.com/emulsify-ds/emulsify-drupal/pull/379):

- Root `js-yaml` 5.2.2 → 5.2.3 and Cosmiconfig's nested copy 4.3.0 → 4.3.1.
- `fast-uri` 3.1.4 → 3.1.7, plus the PR's relocation of unchanged
  `conventional-commits-filter` 5.0.0 copies.
- A further compatible update to npm 11.19.1 fixes its bundled
  `brace-expansion` 5.0.9, `ip-address` 10.5.0, `tar` 7.5.22 and `undici` 6.28.0.

No direct dependency, override, Drupal, PHP, Tools or engine constraint was
changed by this audit. The committed starter declares Core `^4.3.1`, which
permits the audited Core 4.4.0 installation. Registry
metadata confirms Core 4.3.1 and 4.4.0 both require Node `>=24.13.0`; the starter's
advertised `>=24` and the root's `>=24.15` fields remain unchanged.

The old Whisk tree contained Core 4.3.0 and had no lockfile, so an audit before
installation failed with `ENOLOCK`. It was replaced with a clean dependency
installation. The resulting temporary lockfile was used for the audit, then
retained outside the starter. No lockfile was added to the copied starter
contract or to an existing generated child theme.

## Remaining finding and deferral

[GHSA-jmr9-qjv8-65gv](https://github.com/advisories/GHSA-jmr9-qjv8-65gv)
reports unvalidated symlink path traversal in `extract-zip <=2.0.1`. npm reports
`fixAvailable: false` and the registry's latest `extract-zip` is still 2.0.1.
The six high-severity entries are the same finding propagated through this
installed tree:

```text
@emulsify/core@4.4.0
└─ pa11y@9.1.1
   └─ puppeteer@24.43.1
      ├─ @puppeteer/browsers@2.13.2
      │  └─ extract-zip@2.0.1
      └─ puppeteer-core@24.43.1
         └─ @puppeteer/browsers@2.13.2 (deduplicated)
```

Each affected entry (`@emulsify/core`, `pa11y`, `puppeteer`, `puppeteer-core`,
`@puppeteer/browsers`, and `extract-zip`) is deferred because its installed path
ends in this unfixed upstream package. No forced major update, replacement,
override or declared range change was made. The browser tooling is used for
accessibility checks; this audit did not download a browser or exercise archive
extraction.

## Reproduction and full outputs

Run the root maintenance and checks with Node 24.19.0 / npm 11.17.0:

```sh
npm audit fix --package-lock-only --ignore-scripts
npm ci --ignore-scripts
npm install --ignore-scripts
npm audit --json
```

The final `npm install` refreshes npm's bundled lock metadata: the lock-only
update initially retained vulnerable bundled version numbers even after the
parent npm tarball had been upgraded. The clean installed versions and the
final lock now agree.

For Whisk, start with no `node_modules` or lockfile, run `npm install
--ignore-scripts`, verify `npm ls @emulsify/core --depth=0`, then run `npm audit
--json` while that generated lockfile is present. Retain the lockfile outside
Whisk afterward to avoid shipping an unintended copied starter file.

The repository has no Composer lock or installed vendor tree. A copy of its
unchanged `composer.json` was installed in an isolated temporary directory using
PHP 8.5.10 and Composer 2.10.3:

```sh
composer update --no-interaction --no-plugins --no-scripts --prefer-dist
COMPOSER_ROOT_VERSION=7.2.1 composer audit --no-interaction --no-plugins --format=json
```

This resolved Drupal 11.4.6 and Emulsify Tools 2.2.1 (64 packages). Plugins were
disabled for this dependency-only audit; Drupal installation and theme enabling
are separately exercised by the published dependency matrix. The local
`composer.json` also passed `composer validate --strict --no-check-publish`.

Complete machine-readable command outputs are tracked without truncation:

| Tree | Exit code | Full audit output |
| --- | --- | --- |
| Root before updates | 1 (findings) | [Root before](evidence/dependency-audit-2026-09-08/root-npm-before.json) |
| Root after updates | 0 | [Root after](evidence/dependency-audit-2026-09-08/root-npm.json) |
| Fresh Whisk | 1 (findings) | [Whisk](evidence/dependency-audit-2026-09-08/whisk-npm.json) |
| Installed Composer tree | 0 | [Composer](evidence/dependency-audit-2026-09-08/composer.json) |

The final three commands completed successfully as audit requests and produced
empty stderr. Whisk's nonzero exit is the recorded advisory result. The exact
constraints and installed versions are in
[the installation evidence](evidence/dependency-audit-2026-09-08/installed-versions.json).
