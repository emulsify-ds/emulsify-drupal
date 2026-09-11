# Upgrade Guide

## Unreleased: container and form template suggestions

The parent theme now adds container suggestions by class, structural form path,
Drupal selector, and ID; a shared Layout Builder form fallback; Views exposed
outer-form suggestions by View and display; and an exposed-form suggestion for
an individual configured block placement. Existing generated themes inherit
these hooks after updating the parent and rebuilding Drupal caches. No copied
starter-file changes or additional module dependencies are needed.

Existing form-ID suggestions retain their `#id` fallback and empty/null
behavior. The new shared Layout Builder suggestion comes before the exact
form-ID suggestion; the new Views variants come after the generic exposed-form
suggestion, with the block placement last. Drupal selects the last available
candidate. See the [full precedence and filename examples](./docs/twig-hook-contract.md#template-suggestions)
before adding overrides.

If your child theme already implements the same suggestion hooks, both parent
and child implementations run, with the child last. Review duplicate logic and
ordering, particularly child hooks that append generic form suggestions after
more specific parent suggestions. Existing templates matching the new
candidates can start taking effect after the update. The
`form--views-exposed-form--…` templates affect the outer `<form>`;
`views-exposed-form--…` templates continue to control its inner filter layout.

## Unreleased: copied Node and test guidance

The effective Node minimum for Emulsify Core 4.3.1/4.4.0 is 24.13.0, while
Whisk still advertises `>=24`; no engine field is changed. The copied guides
also now explain that project tests are required for `npm test` to pass.
Existing generated themes can opt in to the following documentation changes.
Use the corresponding files in your child theme and preserve its generated
project identity and other local edits.

```diff
diff --git i/whisk/README.md w/whisk/README.md
index 741c06b..638be6c 100644
--- i/whisk/README.md
+++ w/whisk/README.md
@@ -16,5 +16,9 @@ Shared tooling comes from Emulsify Core; the project owns its templates and comp
 ## Quick start

-Use Node.js 24 or newer. If you use nvm, select the recommended version first:
+Use Node.js 24.13.0 or newer for Emulsify Core 4.3.1/4.4.0. This theme's
+`package.json` advertises `>=24`, but Core's `>=24.13.0` requirement sets the
+effective minimum. `.nvmrc` selects the Node 24 line without pinning a minor
+version. Check the resolved Core package's `engines` when updating dependencies.
+If you use nvm, install and select that Node line first:

 ```bash
diff --git i/whisk/docs/development.md w/whisk/docs/development.md
index 1a7ed29..c77bd02 100644
--- i/whisk/docs/development.md
+++ w/whisk/docs/development.md
@@ -6,5 +6,5 @@ Run frontend commands from this theme directory.

 - An existing Drupal site with the Emulsify parent theme and this generated theme in `web/themes/custom/%%EMULSIFY_MACHINE_NAME%%` (or the equivalent custom-theme directory).
-- Node.js 24 or newer. `package.json` declares the compatibility range, and `.nvmrc` records the recommended version.
+- Node.js 24.13.0 or newer for Emulsify Core 4.3.1/4.4.0. The theme advertises `>=24`, but Core requires `>=24.13.0`; `.nvmrc` selects the Node 24 line without pinning a minor version. Check the resolved Core package's `engines` after dependency updates.
 - nvm is optional but is used by the version-selection commands below.
 - npm, which is included with Node.js.
@@ -90,5 +90,13 @@ npm run a11y
 ```

-`npm run test` succeeds when no tests have been added yet. The accessibility check builds Storybook first, so it takes longer than lint or unit tests.
+`npm run test` fails when no project tests are found. Add a colocated
+`*.test.js`, `*.test.mjs`, or `*.spec.js` file alongside a project component
+before relying on a green run. Native ESM imports execute directly, and coverage
+includes project JavaScript, including files the tests do not import. JSX or
+TypeScript requires a project transformer that emits ESM. Use `npm run twatch`
+for interactive watch mode, or `npm run twatch -- --watchAll` outside Git.
+
+The accessibility check builds Storybook first, so it takes longer than lint or
+unit tests.

 ## Project ownership
diff --git i/whisk/docs/support-information.md w/whisk/docs/support-information.md
index 01f15b6..992b108 100644
--- i/whisk/docs/support-information.md
+++ w/whisk/docs/support-information.md
@@ -5,5 +5,5 @@ Start with the common fixes below. If the problem continues, collect the smalles
 ## Troubleshooting

-- **Wrong Node.js version:** run `nvm use` and compare `node --version` with `.nvmrc` and the `engines.node` value in `package.json`.
+- **Wrong Node.js version:** run `nvm use` and compare `node --version` with the resolved Emulsify Core package's `engines.node`. Core 4.3.1/4.4.0 requires at least 24.13.0 even though the theme advertises `>=24`; `.nvmrc` selects only the Node 24 line.
 - **Missing package or command:** run `npm install` again from this directory; keep the dependency metadata and Emulsify Core configuration intact.
 - **Drupal cannot find project assets:** follow the selected component library's build and Drupal integration guidance, then confirm its declared outputs exist and its libraries are attached.
```

## Unreleased: project Jest discovery and ESM

New child themes search their project root, execute native ESM, and report
coverage for project JavaScript, including files that tests never import.
`npm test` now fails when no tests exist. Add a project test before relying on
a green run. `twatch` retains interactive watch mode; use
`npm run twatch -- --watchAll` outside a Git checkout. Custom JSX or TypeScript still needs a
project transformer that emits ESM.

Existing child themes can opt in by applying these copied-file changes; a
parent-theme update does not rewrite them.

`package.json`:

```diff
-    "test": "jest --coverage --passWithNoTests --config ./config/jest.config.js",
+    "test": "node --experimental-vm-modules node_modules/jest/bin/jest.js --coverage --config ./config/jest.config.js",
-    "twatch": "jest --no-coverage --watch --verbose --passWithNoTests --config ./config/jest.config.js",
+    "twatch": "node --experimental-vm-modules node_modules/jest/bin/jest.js --no-coverage --watch --verbose --config ./config/jest.config.js",
```

`config/jest.config.js`:

```diff
-export default {
-  testEnvironment: 'jsdom',
-  coverageDirectory: '.coverage',
-  passWithNoTests: true,
-};
+export default {
+  rootDir: '..',
+  testEnvironment: 'jsdom',
+  coverageDirectory: '.coverage',
+  // Execute the project's native ESM without converting imports to CommonJS.
+  transform: {},
+  coverageProvider: 'v8',
+  collectCoverageFrom: [
+    '**/*.{js,mjs,cjs,jsx}',
+    '!**/{node_modules,config,dist,.out,.coverage}/**',
+    '!**/*.{test,spec,stories}.{js,mjs,cjs,jsx}',
+    '!**/__tests__/**',
+  ],
+};
```

## Unreleased: preserve lint failures

Copied starter scripts now run every constituent check and fail if any check
fails. Existing child themes can apply this `package.json` diff; updating the
parent theme does not rewrite generated projects. No script names changed.

```diff
-    "format": "npm run lint-fix; npm run prettier-fix",
+    "format": "sh -c 'status=0; npm run lint-fix || status=$?; npm run prettier-fix || status=$?; exit $status'",
-    "lint": "npm run lint-js; npm run lint-styles",
+    "lint": "sh -c 'status=0; npm run lint-js || status=$?; npm run lint-styles || status=$?; exit $status'",
-    "lint-fix": "npm run lint-js -- --fix; npm run lint-styles -- --fix",
+    "lint-fix": "sh -c 'status=0; npm run lint-js -- --fix || status=$?; npm run lint-styles -- --fix || status=$?; exit $status'",
```

## Upgrading From 7.2.1 to 7.3.0

This is the combined upgrade path for the planned 7.3.0 release. Version 7.2.2
was tagged and published to Packagist but deliberately not published to
Drupal.org. Drupal.org consumers can move directly from 7.2.1 to 7.3.0 once it
is published; there is no intermediate 7.2.2 installation step. Packagist
consumers already on 7.2.2 follow the same guidance, with its fixes already
installed. See the [channel skew policy](./docs/published-dependency-validation.md).

The minimum versions do not change: Drupal remains `^11.3 || ^12`, and Emulsify
Tools remains `^2.2`. Blocking CI covers Drupal 11.3 and 11.4; Drupal 12 beta and
`dev-main` remain advisory compatibility checks. Drupal 11.4's `vendor/bin/dr`
generation command is experimental; the existing Drush generator remains
available.

After 7.3.0 is available on your install channel, update the parent and Tools
using the package already installed on the site. For Drupal.org installations:

```bash
composer require drupal/emulsify:^7.3 drupal/emulsify_tools:^2.2 --with-all-dependencies
```

For existing Packagist installations:

```bash
composer require emulsify-ds/emulsify-drupal:^7.3 drupal/emulsify_tools:^2.2 --with-all-dependencies
```

Keep one distribution of the parent theme installed; this upgrade does not
require changing channels.

### Required child-theme action

**Delete your generated child theme's copied
`templates/layout/page.html.twig` to receive parent-theme page fixes.** This is
the release's one required manual migration step; updating the parent package
does not remove files from an existing child theme. Newly generated child themes
no longer contain that copy and inherit the parent's page template directly.

If the old copy contains project customizations, preserve those changes in a
small override that extends
`@emulsify/templates/layout/page.html.twig` and overrides only the relevant named
block, instead of retaining the complete copied template. The
[template extension guide](./docs/template-extension.md) shows this replacement
and documents the page, HTML, region, and block extension points. Drupal 11.3
and 11.4 register the parent namespace automatically.

Rebuild caches after updating the package and child template:

```bash
drush cr
```

### Parent changes inherited after the update

- Five additional regions are declared consistently in the parent and starter:
  `status`, `breadcrumb`, `highlighted`, `sidebar_first`, and `sidebar_second`.
  The page template renders all ten declared regions. The added regions render
  nothing until blocks are placed in them. Existing child themes with an
  explicit `regions` list keep that list; adding these regions to a child's info
  file is optional when the site wants to use them.
- Nine named Twig blocks let children inherit page, HTML, region, and block
  markup while customizing individual sections. Adding the blocks preserves the
  parent's rendered bytes. Their names are a public contract.
- Container and form template suggestions now cover classes, structural paths,
  selectors, IDs, Layout Builder, and Views displays or block placements. Review
  the [precedence guide](./docs/twig-hook-contract.md#template-suggestions) if a
  child already implements similar hooks or matching template names.
- Form errors have deterministic IDs, `aria-describedby` associations, and the
  shared `form-item--error-message` class across all five error templates.
  Drupal's Inline Form Errors module controls whether inline errors appear.
  Datetime descriptions receive the `description` class, select options retain
  their attributes, and radios receive `form-radios`. **Sites using bespoke
  fieldset, details, or datetime error selectors may now get double styling. The
  new `form-radios` class may also match a downstream rule added to compensate
  for its previous absence.** Review those styles; no existing class is renamed
  or removed.
- The duplicate status-messages template is removed, message groups no longer
  duplicate block IDs or use a page-footer landmark, and feed/progress templates
  are filed under `templates/misc/`. Explicit includes of their old
  `templates/media-library/` paths need review; basename-based overrides still
  resolve. Block attributes now belong to the outer message wrapper, so review
  styles that depend on the previous wrapper placement. The unnecessary oEmbed `raw` filter is
  removed; Drupal's already-safe markup continues to render normally.
- Favicon SVG processing allows supported static drawing elements, removes
  unsafe or unsupported elements and attributes with warnings, rejects nested
  SVG data URIs, and bounds rasterization resources. SVG dimensions above 4096
  units are rejected. Exotic SVGs that previously passed may need simplifying
  before upload. Manifest previews now validate managed package paths. Generated
  packages continue to live in `public://favicon-package/`, outside the theme.
  Previously stored public SVGs are not rewritten by the update; review and
  re-upload existing sources when applying the new restrictions to them.
- Branding, local-task, and form-error summary links have minimum pointer
  targets of 24px through a zero-specificity rule. Child themes can still set
  their presentation; check navigation and error-summary spacing after updating.

### Fixes included from 7.2.2

This direct upgrade also includes 7.2.2's saved favicon-preview corrections:
previews use the enabled, existing saved package and actual maskable image,
without reapplying its padding or background. Unsaved form changes do not
replace the saved preview.

Newly generated child themes also receive the copied tooling fixes from 7.2.2:
audit output keeps machine-readable stdout; lint, fix, and format scripts
propagate failures; Jest discovers project tests, executes native ESM, collects
project coverage, and fails when no tests exist. Existing child projects keep
their own scripts and configuration. The unnumbered sections above provide
optional adoption diffs; these are not additional required migration steps.
Whisk continues to declare `@emulsify/core: ^4.3.1`. Core 4.3.1/4.4.0 requires
Node 24.13.0 even though Whisk advertises `>=24`; root release tooling requires
24.15 or newer.

The combined release adds working-tree library and breakpoint validation,
rendered form accessibility coverage, stronger template and SVG regression
checks, and Drupal 11.4 CI coverage. It also corrects README assets and identifies
the external documentation site's pre-7.x Webpack guidance; the README and
upgrade guide describe the 7.x Vite workflow. After upgrading, check the site's
pages, forms, block placement, and favicon settings. Rebuild child assets only
when adopting frontend changes or changing project sources.

## Upgrading From 7.2.0 to 7.2.1

7.2.1 fixes child-theme generation through the Emulsify Tools Drush command.
It requires Emulsify Tools 2.2 or newer, which delegates generation to Drupal
Starterkit instead of using the retired copy-and-replace implementation.

Update both packages together:

```bash
composer require drupal/emulsify:^7.2.1 drupal/emulsify_tools:^2.2 --with-all-dependencies
```

Newly generated child themes now retain `project.emulsify.json`, replace its
`whisk` project identity with the generated machine name, and replace every
`%%EMULSIFY_*%%` documentation token. Existing child themes are not rewritten;
regenerate a temporary comparison theme if you need to recover the corrected
metadata or documentation.

## Upgrading From 7.1.x to 7.2.0

7.2.0 adds the Emulsify Core component inspector as a backward-compatible
generated-theme feature.

### Component Inspector

Whisk changes only affect child themes generated after the Emulsify Drupal
release that contains this feature. Updating Emulsify Drupal does not rewrite
existing generated themes.

To adopt the inspector in an existing generated theme:

Upgrade `@emulsify/core` to at least `4.3.1`:

```bash
npm install @emulsify/core@^4.3.1
```

Add the command to the theme's `package.json`:

```json
{
  "scripts": {
    "inspect:components": "emulsify-inspect-components"
  }
}
```

Then run the inspector from the generated theme root:

```bash
npm run inspect:components
npm run inspect:components -- --json
npm run inspect:components -- --help
```

The inspector discovers components and reports metadata, dependencies,
configuration issues, and orphaned files. Publish the compatible
`@emulsify/core` version before merging and releasing the corresponding
Emulsify Drupal feature.

## Upgrading From 7.0.0 to 7.1.0

7.1.0 is intended as a backward-compatible 7.x minor release for the Emulsify Drupal parent theme. It keeps the 7.x architecture intact: Drupal 11.3+ support, Drupal 12 forward compatibility, no `stable9` parent theme, a complete Emulsify-owned template layer, generated child themes, Emulsify Core 4, and a Vite build workflow.

Before updating:

- Confirm the site is already on Drupal 11.3 or newer.
- Confirm `drupal/emulsify_tools:^2.2` is installed. Emulsify Tools is required by the parent theme and provides the Emulsify Tools Drush commands.
- For frontend tooling with Emulsify Core 4.3.1/4.4.0, use Node.js 24.13.0 or newer. Whisk advertises `>=24`, but Core's `>=24.13.0` sets the effective floor; `.nvmrc` selects only the Node 24 line. The root release tooling requires Node.js 24.15 or newer. Check the resolved Core version's engine requirement when updating dependencies.
- Keep generated child themes based on `whisk` configured with `base theme: emulsify`.
- Do not enable `whisk` directly. It is a generation-only starterkit source, not a runtime theme.

After updating:

1. Clear Drupal caches.
2. If a generated child theme overrides `templates/layout/page.html.twig`, compare it with the 7.x parent and `whisk` templates so declared regions such as `content_top` and `content_bottom` continue to render.
3. Rebuild generated child theme assets with the existing Vite build workflow when frontend dependencies or source files changed, then run the generated child-theme health checks from the README.
4. After config import or deploy, regenerate environment-local favicon package files when favicon packages are enabled:

```bash
drush emulsify_tools:favicon-generate [theme_name]
```

Normal page requests do not generate missing favicon package files. The theme settings UI and Emulsify Tools Drush commands are the supported generation paths.

## Upgrading From 6.x to 7.x

Emulsify 7.x is a breaking release. Plan the upgrade as a theme-platform change, not a patch-level update.

## Known Breaking Changes

- Drupal 10 support is removed.
- Drupal 11.3+ is required.
- Drupal 12 beta and `dev-main` CI jobs are non-blocking compatibility checks; Drupal 12 is not yet a verified release target.
- The `stable9` parent theme is removed.
- Emulsify now uses `base theme: false`.
- The `drupal/components` dependency is removed.
- `drupal/emulsify_tools:^2.2` is required.
- Generated child theme frontend workflow moved from Webpack to Vite.
- Generated child themes use Emulsify Core 4.
- `whisk` is now a generation-only starterkit source and should not be enabled directly.
- Favicon handling now uses a generated package workflow.
- Favicon generation happens on theme settings save or through Emulsify Tools Drush commands, not on normal page requests.
- Hook implementations moved to Drupal 11.3+ `#[Hook]` attributes.
- Legacy 6.x procedural hook include behavior is removed.

## Requirements

- Drupal 11.3+ is supported.
- Drupal 12 forward compatibility is included through the `^11.3 || ^12` core constraint.
- Blocking CI verifies Drupal 11.3 on PHP 8.3, 8.4, and 8.5, and Drupal 11.4 on PHP 8.3.
- Drupal 12 beta and `dev-main` jobs are non-blocking compatibility checks; they do not establish verified Drupal 12 support.
- Drupal 10 is no longer supported in 7.x.
- `drupal/emulsify_tools:^2.2` is required by both `composer.json` and `emulsify.info.yml`.

## Package Changes

- The old `drupal/components` dependency is gone.
- Frontend workflow references should move from Webpack-based build workflow commands and docs to the Vite-based build workflow shipped in 7.x.
- `whisk` remains a starterkit source only. Do not enable it as a runtime parent theme.

## Project Audit

Generated themes include an audit command for reviewing common frontend upgrade
items:

```bash
npm run audit
```

The command reports Storybook discovery issues, unresolved Twig `include()` or
`source()` references, Webpack-era patterns, direct imports of Emulsify Core
internals, platform assumptions, source-root issues, large Twig Storybook roots,
and older Twig stories that should move to Emulsify Core's preferred
`renderTwig()` helper.

For only the Twig story migration report, run:

```bash
npm run audit:twig-stories
```

Existing Twig stories that return HTML strings can continue rendering during the
upgrade, but actively maintained stories should be migrated to `renderTwig()` as
they are touched.

When updating project Twig includes, prefer Drupal Single Directory Component
names for new component work:

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

Legacy namespace includes such as
`{% include "@components/button/button.twig" %}` remain valid for existing
templates and migration work, but they are no longer the recommended default for
new project components. See
[docs/twig-component-includes.md](docs/twig-component-includes.md) for the
component include guidance.

## Theme Architecture Changes

- `stable9` is no longer the parent theme. Emulsify now ships its own full template layer.
- Generated child themes should keep `emulsify` as their parent theme.
- If you generated a child theme in the 6.x era, review any copied Twig overrides against the 7.x template surface before carrying them forward.
- The current template parity inventory is documented in [docs/template-map.md](docs/template-map.md).

## Recommended Upgrade Path

1. Update the site to Drupal 11.3 or a newer Drupal 11 release before moving to Emulsify 7.x.
2. Require `drupal/emulsify_tools:^2.2`.
3. Update the Emulsify Drupal parent theme to 7.x.
4. Regenerate or review custom child themes so they inherit from `emulsify`, not `stable9` or `whisk`.
5. Move frontend build and local-development docs, scripts, and team habits from Webpack-based build workflow terminology to the Vite-based build workflow.
6. Run the release-readiness checks before merge or release: `npm run release:check`. The static release gate verifies that favicon defaults, install config, and schema keys stay in sync.

## Favicon Migration Notes

- Favicon settings now store a portable sanitized SVG source in theme config.
- Emulsify Drupal owns the theme settings UI, config defaults and schema, admin previews, frontend head tags, generated asset references, and portable source storage.
- Configure or update favicons in the theme settings form for `emulsify` or a generated child theme.
- Generated favicon packages are environment-local build artifacts. After config import or deploy, use Emulsify Tools to regenerate them with `drush emulsify_tools:favicon-generate [theme_name]`.
- Emulsify Tools owns the full favicon Drush command documentation for `emulsify_tools:favicon-generate`, `emulsify_tools:favicon-status`, and `emulsify_tools:favicon-reset`.
- Runtime page requests do not generate missing favicon package files.
- PNG and ICO generation require the PHP `gd` extension and the `Imagick` extension.
- Generated files, package location, source limits, and deployment expectations are documented in [docs/favicon-generation.md](docs/favicon-generation.md).
