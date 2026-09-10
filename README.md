![Emulsify Design System](https://github.com/emulsify-ds/.github/blob/6bd435be881bd820bddfa05d88905efe29176a0a/assets/images/header.png)

# Emulsify Drupal

## Emulsify is an open-source toolset for creating and implementing design systems on your website

### Storybook, Emulsify Core 4, and a Vite-based build workflow for Drupal 11.3+

**Emulsify Drupal** is the official Drupal parent theme for Emulsify. It provides [Storybook](https://storybook.js.org/) integration, Emulsify Core 4 tooling, and a [Vite](https://vite.dev/)-based build workflow for Drupal 11.3+ with Drupal 12 forward compatibility. Your selected component library supplies the components. Until Drupal 12 beta or stable recommended-project releases are available, Drupal core development branch coverage is experimental.

The current 7.x series no longer depends on `stable9`; Emulsify now ships its own complete template layer instead of inheriting one from a Drupal parent theme.

## Documentation

Use this README and [UPGRADE.md](./UPGRADE.md) for the current 7.x installation
and upgrade workflow. The [Emulsify documentation site](https://emulsify.info/docs)
also contains guides for older release lines; check the guide's version before
following its commands.

### Quick Links

1. [Installation](#install-or-upgrade-the-parent-theme)
2. [Usage](#generate-a-child-theme)
3. [Upgrade guide](./UPGRADE.md)
4. [Twig component includes](./docs/twig-component-includes.md)
5. [Sister-project parity contract](./docs/sister-project-parity.md)
6. [Template override map](./docs/template-map.md)
7. [Favicon generation lifecycle](./docs/favicon-generation.md)
8. [Optional design-token integration](./docs/design-token-integration.md)
9. [Release readiness checklist](./docs/release-readiness.md)
10. [Twig hook variables and template suggestion precedence](./docs/twig-hook-contract.md)

## Demo

1. [Storybook](http://storybook.emulsify.info/)

## License

Emulsify Drupal is licensed under `GPL-2.0-or-later`, matching Drupal.org Composer metadata guidance for hosted Drupal projects.

## How To

### Install or upgrade the parent theme

Run these commands from an existing Composer-managed Drupal site's project root,
using that site's PHP/Composer environment. Examples assume a `web` document
root and Drush 13+ on your path; use your project's wrapper (for example,
`ddev composer` and `ddev drush`) when applicable.

The recommended distribution is `drupal/emulsify` from Drupal.org. Configure
Drupal.org in the site's root `composer.json` if it is not already configured,
then install the parent theme and its required companion module:

```bash
composer config repositories.drupal composer https://packages.drupal.org/8
composer require 'drupal/emulsify:^7.2' 'drupal/emulsify_tools:^2.2' --with-all-dependencies
drush en emulsify_tools -y
```

The parallel [Packagist package](https://repo.packagist.org/p2/emulsify-ds/emulsify-drupal.json)
is named `emulsify-ds/emulsify-drupal`. Existing sites using that package should
keep that name when updating:

```bash
composer update emulsify-ds/emulsify-drupal drupal/emulsify_tools --with-all-dependencies
```

Both package names distribute the parent theme and Whisk starter. Choose one
package name per site. The Packagist route also needs the Drupal.org repository
in the site's root Composer configuration to resolve `drupal/emulsify_tools`;
Composer does not load repository declarations from dependencies.

For an existing Drupal.org installation, update within your site's declared
ranges and read the applicable [upgrade notes](./UPGRADE.md):

```bash
composer update drupal/emulsify drupal/emulsify_tools --with-all-dependencies
drush updb -y
drush cr -y
```

The theme inherits its PHP floor from Drupal: PHP 8.3 for Drupal 11 and PHP 8.5
for Drupal 12. The declared core compatibility is `^11.3 || ^12`; consult
[Drupal's PHP requirements](https://www.drupal.org/docs/getting-started/system-requirements/php-requirements)
for the supported PHP versions of your installed core release.

### Identify your installed release

Composer's installed package version is authoritative for the parent theme.
Run the command matching your site's package name:

```bash
composer show drupal/emulsify
```

```bash
composer show emulsify-ds/emulsify-drupal
```

The `versions` and source reference in that output identify what is installed;
the matching `composer.lock` entry records what a subsequent `composer install`
will reproduce. A development branch is identified by its source commit rather
than by claiming the latest stable release. Published release history lives in
[GitHub Releases](https://github.com/emulsify-ds/emulsify-drupal/releases), with
consumer actions in [UPGRADE.md](./UPGRADE.md). The
[next-release draft](./docs/release-notes-next.md) contains unreleased changes.

The root `package.json` version is owned by `@semantic-release/npm`. That plugin
writes the calculated version during release preparation, with `npmPublish: false`;
the workflow does not commit that generated metadata back to Git.
Consequently, the checked-in npm version is tooling metadata, not an installed
Emulsify Drupal release identifier. Do not bump it manually. The release guard
checks this ownership policy and the unchanged metadata baseline from the latest
tag. The generated child theme's npm version belongs to that child project;
`generatedFromVersion` records its starter lineage, not the currently installed
parent theme version.

### Generate a child theme

Emulsify Tools 2.2 or newer is required by the Emulsify Drupal parent theme.
The `drush emulsify` and `drush emulsify_tools:bake` commands are implemented by
Emulsify Tools, which must be installed and enabled. Its Drush helper delegates
to Drupal Starterkit so both generation commands
produce the same child theme. Generate a child theme with:

```bash
drush emulsify my_theme
```

The helper module also exposes the fully qualified command name:

```bash
drush emulsify_tools:bake my_theme
```

The `whisk` directory is the Whisk starter source used by both generation methods. Do not enable `whisk` directly; generated child themes keep `emulsify` as their runtime parent theme.

You can also generate the same child theme with Drupal core's standard
Starterkit command from the root of your Drupal site. On Drupal 11.4 and newer,
use the Composer-installed `dr` executable:

```bash
vendor/bin/dr generate-theme my_theme --starterkit whisk --path themes/custom
```

For Drupal 11.3, which does not provide that executable, use the earlier entrypoint:

```bash
php web/core/scripts/drupal generate-theme my_theme --starterkit whisk --path themes/custom
```

These generation methods should be treated as equivalent:

1. They generate the theme into `web/themes/custom/my_theme`.
2. They use the Whisk starter source.
3. They keep `emulsify` as the runtime parent theme for the generated child theme.
4. They preserve `project.emulsify.json` so Emulsify Core can identify the generated Drupal project structure.
5. They retain `generatedFrom` and `generatedFromVersion` metadata so support tooling can identify the Emulsify Drupal source and version used to create the child theme.

After generation:

1. Enable the theme:

```bash
drush theme:enable my_theme -y
drush config:set system.theme default my_theme -y
drush cr -y
```

2. Install the generated child theme's frontend dependencies:

```bash
cd web/themes/custom/my_theme
npm install
```

3. Select and install the project's component library. Whisk does not provide a
   project source tree or build entrypoints. Its generated Drupal library file
   contains only commented CSS and JavaScript examples. Follow the component
   library's setup instructions; once it provides build inputs, update those
   examples and start the local tooling:

```bash
npm run develop
```

Generated child themes include the Vite and Storybook tooling from Emulsify
Core 4. The selected component library owns the source structure and asset
integration those tools consume.

### Write Twig component includes

For new project Twig, prefer Drupal Single Directory Component names:

```twig
{% include "my_theme:list" with {
  items: items,
} only %}
```

The Twig function form is also supported:

```twig
{{ include("my_theme:list", {
  items: items,
}, with_context = false) }}
```

Replace `my_theme` with the generated child theme machine name. Legacy namespace
includes such as `{% include "@components/button/button.twig" %}` are still
valid for existing projects and migrations, but they are not the recommended
default for new project components. See
[docs/twig-component-includes.md](./docs/twig-component-includes.md) for the
component include guidance.

### Verify your generated child theme

Run the install and test commands from the generated child theme directory, not
from `whisk`:

```bash
cd web/themes/custom/my_theme
node --version
npm install
npm run test
npm run inspect:components
```

The component inspector discovers the project component inventory and reports
component metadata, dependencies, configuration issues, and orphaned files. It
does not require components to exist, so a newly generated component-neutral
theme returns a valid empty report. Use JSON output for automation or view the
command help:

```bash
npm run inspect:components -- --json
npm run inspect:components -- --help
```

After the selected component library supplies project sources, also run its
asset-dependent checks:

```bash
npm run build
npm run storybook-build
```

Whisk advertises Node.js `>=24`, while Emulsify Core 4.3.1 and 4.4.0 require
`>=24.13.0`; the effective frontend floor for those versions is therefore
**24.13.0**. Root release tooling separately requires **24.15 or newer**. Both
`.nvmrc` files select the Node 24 line without pinning its minor version. These
declared requirements differ; a Node 24.0 installation does not satisfy Emulsify
Core. Check the resolved Core package's `engines` when updating dependencies.
Use `npm install` for the first local install, or `npm ci` when the generated
child theme already has a committed `package-lock.json`.

These checks verify the expected local workflow:

1. Compare `node --version` with the effective Node.js floor above.
2. `npm install` installs Emulsify Core 4 and the generated child theme tooling.
3. `npm run test` verifies the generated Jest setup. It passes when no project tests exist yet.
4. `npm run inspect:components` reports the component inventory and related project health information.
5. After component-library installation, `npm run build` compiles its Drupal-facing assets with the Vite build workflow.
6. `npm run storybook-build` verifies the component library's static Storybook build.

Optional browser-based accessibility check:

```bash
npm run a11y
```

`npm run a11y` builds Storybook and runs the Emulsify Core accessibility check.
Run it after component-library installation in local or CI environments that
can use the required browser-based tooling.

### Manage generated favicon packages

The generated favicon workflow is built around one portable SVG source stored in theme settings.

Emulsify Drupal owns the theme-facing parts of that workflow: the theme settings form, config defaults and schema, admin previews, frontend head tags, generated asset references in `<theme>.settings`, and sanitized SVG storage for config portability.

1. Configure the package in the theme settings form for `emulsify` or a generated child theme.
2. Save the theme settings form to generate or update the package during normal admin changes.
3. Review package and portable-source diagnostics in the theme settings UI.

Emulsify Tools owns deployment-oriented Drush operations for those same
settings. After configuration import or deploy, use the Emulsify Tools favicon
commands to generate, inspect, or reset environment-local package files before
public traffic reaches the environment. See the Emulsify Tools README for the
full command documentation.

Runtime page requests never generate favicon files. If the configured package is missing, Emulsify skips favicon head tags until the theme settings form or the Emulsify Tools generate command creates the package.

Generated favicon packages require the PHP `gd` extension and the `Imagick` extension for SVG rasterization. If either extension is unavailable, the uploaded SVG can still be stored in configuration, but PNG and ICO package generation will fail until those extensions are installed.

The theme settings UI surfaces the current portable-source and package status. Portable SVG copies larger than 256 KB are flagged because very large config payloads are awkward to review and deploy.

See [docs/favicon-generation.md](./docs/favicon-generation.md) for generated files, package location, generator limits, and deployment expectations.

## Contributing

### [Code of Conduct](https://github.com/emulsify-ds/emulsify-drupal/blob/main/CODE_OF_CONDUCT.md)

The project maintainers have adopted a Code of Conduct that we expect project participants to adhere to. Please read the full text so that you can understand what actions will and will not be tolerated.

### Contribution Guide

Please also follow the issue template and pull request templates provided. See below for the correct places to post issues:

1. [Emulsify Drupal](https://www.drupal.org/project/issues/emulsify?categories=All)
2. [Emulsify Tools Drupal Module](https://www.drupal.org/project/issues/emulsify_tools?categories=All)
3. [Emulsify Twig Extensions](https://github.com/emulsify-ds/emulsify-twig-extensions/issues)

### Committing Changes

To facilitate automatic semantic release versioning, we utilize the [Conventional Changelog](https://github.com/conventional-changelog/conventional-changelog) standard through Commitizen. Follow these steps when committing your work to ensure semantic release can version correctly.

1. Stage your changes, ensuring they encompass exactly what you wish to change, no more.
2. Create a [Conventional Commit](https://www.conventionalcommits.org/en/v1.0.0/) message, either manually or with your preferred commit helper.
3. Your commit message will be used to create the changelog for the next version that includes that commit.

### Release Readiness

Run the release guard before merging packaging, Whisk starter, favicon settings, or release metadata changes, and before preparing a 7.x release:

```bash
npm run release:check
```

Use Node.js 24.15 or newer for local release tooling.

Release automation publishes from `main` with non-prefixed SemVer tags.

Use `npm run release:check -- --skip-smoke` when you only want the static metadata, README, duplicate-script, and schema checks. The static checks verify that favicon settings stay aligned across `FaviconSettings::DEFAULTS`, `config/install/emulsify.settings.yml`, and `config/schema/emulsify.schema.yml`.

## Author

Emulsify&reg; is a product of [Four Kitchens &mdash; We make BIG websites](https://fourkitchens.com).

## Contributors

<!-- ALL-CONTRIBUTORS-LIST:START - Do not remove or modify this section -->
<!-- prettier-ignore-start -->
<!-- markdownlint-disable -->
<table>
  <tbody>
    <tr>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/ModulesUnraveled"><img src="https://avatars.githubusercontent.com/u/1663810?v=4?s=100" width="100px;" alt="Brian Lewis"/><br /><sub><b>Brian Lewis</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=ModulesUnraveled" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=ModulesUnraveled" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/amazingrando"><img src="https://avatars.githubusercontent.com/u/409903?v=4?s=100" width="100px;" alt="Randy Oest"/><br /><sub><b>Randy Oest</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=amazingrando" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=amazingrando" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/callinmullaney"><img src="https://avatars.githubusercontent.com/u/369018?v=4?s=100" width="100px;" alt="Callin Mullaney"/><br /><sub><b>Callin Mullaney</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=callinmullaney" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=callinmullaney" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/patrickocoffeyo"><img src="https://avatars.githubusercontent.com/u/1107871?v=4?s=100" width="100px;" alt="Patrick Coffey"/><br /><sub><b>Patrick Coffey</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=patrickocoffeyo" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=patrickocoffeyo" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/infiniteluke"><img src="https://avatars.githubusercontent.com/u/1127238?v=4?s=100" width="100px;" alt="Luke Herrington"/><br /><sub><b>Luke Herrington</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=infiniteluke" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=infiniteluke" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/acouch"><img src="https://avatars.githubusercontent.com/u/512243?v=4?s=100" width="100px;" alt="Aaron Couch"/><br /><sub><b>Aaron Couch</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=acouch" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=acouch" title="Documentation">📖</a></td>
    </tr>
    <tr>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/codechefmarc"><img src="https://avatars.githubusercontent.com/u/107938318?v=4?s=100" width="100px;" alt="Marc Berger"/><br /><sub><b>Marc Berger</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=codechefmarc" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=codechefmarc" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/techninja"><img src="https://avatars.githubusercontent.com/u/320747?v=4?s=100" width="100px;" alt="James Todd"/><br /><sub><b>James Todd</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=techninja" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=techninja" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/KurtTrowbridge"><img src="https://avatars.githubusercontent.com/u/848721?v=4?s=100" width="100px;" alt="Kurt Trowbridge"/><br /><sub><b>Kurt Trowbridge</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=KurtTrowbridge" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=KurtTrowbridge" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/ccjjmartin"><img src="https://avatars.githubusercontent.com/u/12279982?v=4?s=100" width="100px;" alt="Chris Martin"/><br /><sub><b>Chris Martin</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=ccjjmartin" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=ccjjmartin" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/americkson"><img src="https://avatars.githubusercontent.com/u/545638?v=4?s=100" width="100px;" alt="Adam Erickson"/><br /><sub><b>Adam Erickson</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=americkson" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=americkson" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/cruno91"><img src="https://avatars.githubusercontent.com/u/1760366?v=4?s=100" width="100px;" alt="Chris Runo"/><br /><sub><b>Chris Runo</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=cruno91" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=cruno91" title="Documentation">📖</a></td>
    </tr>
    <tr>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/andycarlberg"><img src="https://avatars.githubusercontent.com/u/7405933?v=4?s=100" width="100px;" alt="Andy Carlberg"/><br /><sub><b>Andy Carlberg</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=andycarlberg" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=andycarlberg" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/eatsmarter-benny"><img src="https://avatars.githubusercontent.com/u/78405000?v=4?s=100" width="100px;" alt="eatsmarter-benny"/><br /><sub><b>eatsmarter-benny</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=eatsmarter-benny" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=eatsmarter-benny" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/backlineint"><img src="https://avatars.githubusercontent.com/u/889478?v=4?s=100" width="100px;" alt="Brian Perry"/><br /><sub><b>Brian Perry</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=backlineint" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=backlineint" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/israelshmueli"><img src="https://avatars.githubusercontent.com/u/315597?v=4?s=100" width="100px;" alt="Israel Shmueli"/><br /><sub><b>Israel Shmueli</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=israelshmueli" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=israelshmueli" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/openjck"><img src="https://avatars.githubusercontent.com/u/933396?v=4?s=100" width="100px;" alt="John Karahalis"/><br /><sub><b>John Karahalis</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=openjck" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=openjck" title="Documentation">📖</a></td>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/Mihaic100"><img src="https://avatars.githubusercontent.com/u/14100169?v=4?s=100" width="100px;" alt="Mihaic100"/><br /><sub><b>Mihaic100</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=Mihaic100" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=Mihaic100" title="Documentation">📖</a></td>
    </tr>
    <tr>
      <td align="center" valign="top" width="16.66%"><a href="https://github.com/psebborn"><img src="https://avatars.githubusercontent.com/u/147779?v=4?s=100" width="100px;" alt="Paul Sebborn"/><br /><sub><b>Paul Sebborn</b></sub></a><br /><a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=psebborn" title="Code">💻</a> <a href="https://github.com/fourkitchens/emulsify-drupal/commits?author=psebborn" title="Documentation">📖</a></td>
    </tr>
  </tbody>
</table>

<!-- markdownlint-restore -->
<!-- prettier-ignore-end -->

<!-- ALL-CONTRIBUTORS-LIST:END -->

This project follows the [all-contributors](https://github.com/all-contributors/all-contributors) specification. Contributions of any kind welcome!
