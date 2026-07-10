# Development guide for %%EMULSIFY_THEME_NAME%%

Run frontend commands from this theme directory.

## Prerequisites

- An existing Drupal site with the Emulsify parent theme and this generated theme in `web/themes/custom/%%EMULSIFY_MACHINE_NAME%%` (or the equivalent custom-theme directory).
- Node.js 24 or newer. `package.json` declares the compatibility range, and `.nvmrc` records the recommended version.
- nvm is optional but is used by the version-selection commands below.
- npm, which is included with Node.js.

## Initial setup

If you use nvm, install and select the recommended Node.js version:

```bash
nvm install
nvm use
```

Install dependencies:

```bash
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

Build production CSS:

```bash
npm run build
```

Build the static Storybook site (this also runs the production build):

```bash
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

## Project ownership

### Component library

The generated theme is project-owned and does not prescribe or scaffold a component library. The project owns its templates, components, stories, styles, tests, and naming conventions from this point forward. It may use Drupal Single Directory Components, Twig components, React components, existing project components, or an intentional combination of those approaches.

Keep application-specific components in this repository. Treat `config/emulsify-core/` and the installed `@emulsify/core` package as shared tooling rather than a place for project code.

### Generated source

`project.emulsify.json` records the Drupal platform, machine name, Single Directory Component support, source project, source version, and starter repository. Preserve `generatedFrom` and `generatedFromVersion`: they identify the baseline used to create this theme and make future comparisons reproducible.

Generation is a starting point, not a continuing ownership boundary. Files copied into this theme—including `package.json`, build configuration, Sass entrypoints, Drupal metadata, templates, and these docs—now belong to the project.

The original baseline is `%%EMULSIFY_SOURCE_PROJECT%%` `%%EMULSIFY_SOURCE_VERSION%%`, and the expected Emulsify Core range is `%%EMULSIFY_CORE_RANGE%%`. npm may install a newer compatible Core release within that range; review its release notes before accepting dependency updates.

See [Upgrading](upgrading.md) before changing dependencies or adopting changes from a newer starter release.
