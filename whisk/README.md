# %%EMULSIFY_THEME_NAME%%

## Overview

This is the project-owned Emulsify child theme for **%%EMULSIFY_THEME_NAME%%**.

- **Machine name:** `%%EMULSIFY_MACHINE_NAME%%`
- **Description:** %%EMULSIFY_DESCRIPTION%%
- **Generated from:** `%%EMULSIFY_SOURCE_PROJECT%%` `%%EMULSIFY_SOURCE_VERSION%%`
- **Expected Emulsify Core range:** `%%EMULSIFY_CORE_RANGE%%`

The Emulsify Core range is the compatibility range declared in `package.json`. npm may install a newer compatible release within that range; review its release notes before accepting dependency updates.

## Prerequisites

- An existing Drupal site with the Emulsify parent theme and this generated theme in `web/themes/custom/%%EMULSIFY_MACHINE_NAME%%` (or the equivalent custom-theme directory).
- A Node.js version compatible with `.nvmrc`. With nvm, the commands below install and select it.
- npm, which is included with Node.js.

## Initial setup

From this theme directory:

```bash
nvm install
nvm use
npm install
```

Use `npm install` for the first installation because a generated theme does not initially include a lockfile. After your project commits a `package-lock.json`, use `npm ci` in CI and other reproducible installs.

## Development workflow

Run Vite in watch mode and Storybook together:

```bash
npm run develop
```

Run Storybook alone when you do not need the Vite watcher:

```bash
npm run storybook
```

Build production CSS or a static Storybook site:

```bash
npm run build
npm run storybook-build
```

Check project code before opening a pull request:

```bash
npm run lint
npm run test
npm run a11y
```

`npm run test` succeeds when no tests have been added yet. The accessibility check builds Storybook first, so it takes longer than lint or unit tests.

## Asset integration

The initial asset contract is CSS-only:

- `src/foundation.scss` builds to `dist/global/foundation.css`.
- `src/layout.scss` builds to `dist/global/layout.css`.
- `src/tokens.scss` builds to `dist/global/tokens.css`.
- `%%EMULSIFY_MACHINE_NAME%%.libraries.yml` declares those outputs, and `%%EMULSIFY_MACHINE_NAME%%.info.yml` attaches the global library.

Run `npm run build` after changing the Sass entrypoints. No JavaScript library is declared initially; add JavaScript only when the project needs it and declare the resulting output in `%%EMULSIFY_MACHINE_NAME%%.libraries.yml`.

## Component-library ownership

The generated theme does not prescribe or scaffold a component library. The project owns its templates, components, stories, styles, tests, and naming conventions from this point forward. It may use Drupal Single Directory Components, Twig components, React components, existing project components, or an intentional combination of those approaches.

Keep application-specific components in this repository. Treat `config/emulsify-core/` and the installed `@emulsify/core` package as shared tooling rather than a place for project code.

## Generated-source information

`project.emulsify.json` records the Drupal platform, machine name, Single Directory Component support, source project, source version, and starter repository. Preserve `generatedFrom` and `generatedFromVersion`: they identify the baseline used to create this theme and make future comparisons reproducible.

Generation is a starting point, not a continuing ownership boundary. Files copied into this theme—including `package.json`, build configuration, Sass entrypoints, Drupal metadata, templates, and these docs—now belong to the project.

## Maintenance and upgrades

Routine npm dependency updates and adopting changes from a newer starter release are different tasks. For the complete comparison and validation workflow, see [UPGRADING.md](UPGRADING.md).

At minimum:

1. Update dependencies intentionally and review the resulting lockfile.
2. Generate a fresh comparison theme when evaluating a newer starter release.
3. Port only relevant changes; do not overwrite project-owned templates or components.
4. Run lint, tests, the production build, and the static Storybook build.
5. Keep the lineage fields in `project.emulsify.json` unless you deliberately adopt a new generated baseline.

## Troubleshooting

- **Wrong Node.js version:** run `nvm use` and compare `node --version` with `.nvmrc` and the `engines.node` value in `package.json`.
- **Missing package or command:** remove neither dependency metadata nor Core configuration; run `npm install` again from this directory.
- **Drupal cannot find CSS:** run `npm run build`, confirm the `dist/global/*.css` files exist, and compare them with `%%EMULSIFY_MACHINE_NAME%%.libraries.yml`.
- **Storybook fails:** run the production build first, then use the support commands to capture versions and the failing command output.
- **A generated value looks wrong:** inspect `%%EMULSIFY_MACHINE_NAME%%.info.yml`, `package.json`, and `project.emulsify.json` before editing the docs by hand.

For a concise, sanitized diagnostic checklist, see [docs/support-information.md](docs/support-information.md).
