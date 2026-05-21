# Preparing Existing 6.x Projects for 7.x

This 6.x release is a bridge release. It keeps existing 6.x projects on the current supported workflow while preparing the theme codebase for the larger 7.x release line.

The goal is not to backport the full 7.x upgrade path into 6.x. Use this guide to review existing projects now so the eventual 7.x upgrade is more predictable.

## What Stays in 6.x

The 6.x release line continues to support the current project model:

- Stable9 remains the runtime fallback base theme.
- Emulsify Core 3 remains the supported component package.
- Webpack remains the supported frontend build workflow.
- Emulsify Tools remains the supported child theme generation workflow.

Drupal core Starterkit-based generation is being prepared for 7.x. It is not the primary supported public child-theme generation workflow in 6.x.

## What to Expect in 7.x

The 7.x release line is expected to introduce larger platform and tooling changes:

- Drupal 11.3 or newer will be required.
- Drupal 10 support will be removed.
- Stable9 will no longer be used as the runtime base theme.
- Whisk will become the public Starterkit generation source.
- Emulsify Core 4 will replace Emulsify Core 3.
- Vite will replace the Webpack build workflow.

Treat these as preparation notes until the 7.x beta and final upgrade documentation are published.

## Preparation Checklist

Before upgrading an existing 6.x project, review the project for assumptions that may depend on the 6.x runtime model:

- Confirm the project uses a generated child theme rather than enabling the parent theme or starter source directly.
- Audit custom templates copied from Stable9 or Emulsify and identify which ones are still intentional overrides.
- Check for assumptions that Stable9 fallback templates are available at runtime.
- Review component and template overrides for markup that depends on 6.x parent-theme behavior.
- Confirm the frontend build workflow can move from Webpack to Vite.
- Run visual or render checks on key pages before upgrading, then compare those pages again after the upgrade.

Projects that keep their child theme overrides explicit and well-tested should have a clearer path into 7.x once the full upgrade guide is available.
