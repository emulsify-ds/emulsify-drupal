# Upgrade Guide

## Upgrading From 7.0.0 to 7.1.0

7.1.0 is intended as a backward-compatible 7.x minor release for the Emulsify Drupal parent theme. It keeps the 7.x architecture intact: Drupal 11.3+ support, Drupal 12 forward compatibility, no `stable9` parent theme, a complete Emulsify-owned template layer, generated child themes, Emulsify Core 4, and a Vite build workflow.

Before updating:

- Confirm the site is already on Drupal 11.3 or newer.
- Confirm `drupal/emulsify_tools:^2.0` is installed. Emulsify Tools is required by the parent theme and provides the Emulsify Tools Drush commands.
- Use Node.js 24 or newer for generated child theme frontend tooling. The root release tooling requires Node.js 24.10 or newer.
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
- Drupal 12 compatibility is forward-looking until Drupal 12 beta or stable releases are available.
- The `stable9` parent theme is removed.
- Emulsify now uses `base theme: false`.
- The `drupal/components` dependency is removed.
- `drupal/emulsify_tools:^2.0` is required.
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
- Drupal core development branch coverage is experimental until Drupal 12 beta or stable releases are available.
- Drupal 10 is no longer supported in 7.x.
- `drupal/emulsify_tools:^2.0` is required by both `composer.json` and `emulsify.info.yml`.

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
2. Require `drupal/emulsify_tools:^2.0`.
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
