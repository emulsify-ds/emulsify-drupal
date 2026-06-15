![Emulsify Design System](https://github.com/emulsify-ds/.github/blob/6bd435be881bd820bddfa05d88905efe29176a0a/assets/images/header.png)

# Emulsify Drupal

## Emulsify is an open-source toolset for creating and implementing design systems on your website

### Storybook, Emulsify Core 4, and a Vite-based build workflow for Drupal 11.3+

**Emulsify Drupal** is the official Drupal parent theme for Emulsify. It provides a [Storybook](https://storybook.js.org/) component library, Emulsify Core 4 tooling, and a [Vite](https://vite.dev/)-based build workflow for Drupal 11.3+ with Drupal 12 forward compatibility. Until Drupal 12 beta or stable recommended-project releases are available, Drupal core development branch coverage is experimental.

The current 7.x series no longer depends on `stable9`; Emulsify now ships its own complete template layer instead of inheriting one from a Drupal parent theme.

## Documentation

[docs.emulsify.info](https://emulsify.info/docs)

### Quick Links

1. [Installation](https://www.emulsify.info/docs/emulsify-drupal)
2. [Usage](https://www.emulsify.info/docs/emulsify-drupal/basic-usage/commands)
3. [Upgrade guide](./UPGRADE.md)
4. [Twig component includes](./docs/twig-component-includes.md)
5. [Template override map](./docs/template-map.md)
6. [Favicon generation lifecycle](./docs/favicon-generation.md)
7. [Optional design-token integration](./docs/design-token-integration.md)
8. [Release readiness checklist](./docs/release-readiness.md)

## Demo

1. [Storybook](http://storybook.emulsify.info/)

## How To

### Generate a child theme

Emulsify Tools is required by the Emulsify Drupal parent theme. Use its Drush helper command to generate a child theme:

```bash
drush emulsify my_theme
```

The helper module also exposes the fully qualified command name:

```bash
drush emulsify_tools:bake my_theme
```

The `whisk` directory is the generation-only starterkit source used by both generation methods. Do not enable `whisk` directly; generated child themes keep `emulsify` as their runtime parent theme.

You can also generate the same child theme with Drupal core's standard Starterkit command from the root of your Drupal site:

```bash
php web/core/scripts/drupal generate-theme my_theme --starterkit whisk --path themes/custom
```

These generation methods should be treated as equivalent:

1. They generate the theme into `web/themes/custom/my_theme`.
2. They use the `whisk` starterkit source.
3. They keep `emulsify` as the runtime parent theme for the generated theme.
4. They preserve `project.emulsify.json` so Emulsify Core can identify the generated Drupal project structure.

After generation:

1. Enable the theme:

```bash
drush theme:enable my_theme -y
drush config:set system.theme default my_theme -y
drush cr -y
```

2. Install the generated theme's frontend dependencies:

```bash
cd web/themes/custom/my_theme
npm install
```

3. Start the generated theme's local tooling:

```bash
npm run develop
```

Generated child themes use the Vite build workflow and Emulsify Core 4 scripts shipped by the `whisk` starterkit source.

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

Replace `my_theme` with the generated theme machine name. Legacy namespace
includes such as `{% include "@components/button/button.twig" %}` are still
valid for existing projects and migrations, but they are not the recommended
default for new project components. See
[docs/twig-component-includes.md](./docs/twig-component-includes.md) for the
component include guidance.

### Verify your generated child theme

Run these commands from the generated child theme directory, not from `whisk`:

```bash
cd web/themes/custom/my_theme
node --version
npm install
npm run build
npm run storybook-build
npm run test
```

Generated child themes require Node.js 24 or newer. Use `npm install` for the first local install, or `npm ci` when the generated child theme already has a committed `package-lock.json`.

These checks verify the expected local workflow:

1. `node --version` confirms the Node.js runtime satisfies the generated theme requirement.
2. `npm install` installs Emulsify Core 4 and the generated theme tooling.
3. `npm run build` compiles Drupal-facing assets with the Vite build workflow.
4. `npm run storybook-build` verifies the static Storybook build.
5. `npm run test` verifies the generated Jest setup. It passes when no project tests exist yet.

Optional browser-based accessibility check:

```bash
npm run a11y
```

`npm run a11y` builds Storybook and runs the Emulsify Core accessibility check. Use it in local or CI environments that can run the required browser-based tooling.

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

Run the release guard before merging packaging, starterkit, favicon settings, or release metadata changes, and before preparing a 7.x release:

```bash
npm run release:check
```

Use Node.js 24.10 or newer for local release tooling.

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
