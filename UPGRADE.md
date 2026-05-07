# Upgrading From 6.x to 7.x

Emulsify 7.x is a breaking release. Plan the upgrade as a theme-platform change, not a patch-level update.

## Requirements

- Drupal 11.3 through Drupal 12.x is supported.
- Drupal 10 is no longer supported.
- `drupal/emulsify_tools:^2.0` is required by both `composer.json` and `emulsify.info.yml`.

## Package Changes

- The old `drupal/components` dependency is gone.
- Frontend workflow references should move from Webpack-era commands and docs to the Vite-based workflow shipped in 7.x.
- `whisk` remains a starter source only. Do not enable it as a runtime base theme.

## Theme Architecture Changes

- `stable9` is no longer the base theme. Emulsify now ships its own full template layer.
- Generated child themes should keep `emulsify` as their parent theme.
- If you generated a child theme in the 6.x era, review any copied Twig overrides against the 7.x template surface before carrying them forward.
- The current template parity inventory is documented in [docs/template-map.md](docs/template-map.md).

## Recommended Upgrade Path

1. Update the site to Drupal 11.3 or any Drupal 12 release.
2. Require `drupal/emulsify_tools:^2.0`.
3. Update the Emulsify base theme to 7.x.
4. Regenerate or review custom child themes so they inherit from `emulsify`, not `stable9` or `whisk`.
5. Move frontend build and local-development docs, scripts, and team habits from Webpack terminology to Vite.
6. Run the release-readiness checks before merge: `npm run release:check`.

## Favicon Migration Notes

- Favicon settings now store a portable sanitized SVG source in theme config.
- Generated favicon packages are environment-local build artifacts. After config import or deploy, regenerate them with `drush emulsify_tools:favicon-generate [theme_name]`.
- Use `drush emulsify_tools:favicon-status [theme_name]` to inspect the current package and source state.
- Use `drush emulsify_tools:favicon-reset [theme_name]` to remove generated assets and return to the default theme favicon behavior.
- PNG and ICO generation require the PHP `gd` extension and the `Imagick` extension.
