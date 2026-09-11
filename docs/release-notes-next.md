# Unreleased changes

Semantic Release determines the next version from the commits on `main`.
This draft covers changes since the latest tagged release; the combined
[7.2.1 upgrade path](../UPGRADE.md#upgrading-from-721-to-730) also includes the
fixes from 7.2.2, which was deliberately not published to Drupal.org.

## Added

- Nine named Twig blocks in the parent page, HTML, region, and base block
  templates let child themes extend individual sections. Their names are a
  public contract. Pre-change golden captures verify unchanged default bytes,
  and generated-child inheritance is tested on Drupal 11.3 and 11.4. See the
  [extension guide](./template-extension.md).
- The parent and starter declare `status`, `breadcrumb`, `highlighted`,
  `sidebar_first`, and `sidebar_second`, alongside the existing five regions.
  New regions render nothing until blocks are placed in them. Existing children
  retain their own explicit region lists.
- Container and form suggestions cover classes, structural form paths,
  selectors, IDs, Layout Builder, and Views displays or block placements.
  See the [suggestion precedence](./twig-hook-contract.md#template-suggestions)
  before adding matching overrides or overlapping child hooks.

## Fixed

- Whisk no longer ships a page copy that hides future parent fixes. Fresh
  generated children inherit the parent template, and parity checks reject
  divergent files under the starter's template directory.
- Form errors now have IDs, control associations, and a common styling class
  across all five error templates. Datetime descriptions receive `description`,
  select options preserve their attributes, and radios receive `form-radios`.
  Sites with bespoke fieldset/details/datetime error selectors may get double
  styling; the radios class may match rules compensating for its earlier absence.
- The duplicate status-messages registry key is removed, message groups avoid
  duplicate block IDs and the footer landmark role, and feed/progress templates
  move to `templates/misc/`. The oEmbed template drops its unnecessary `raw`
  filter while retaining Drupal's safe-markup behavior.
- Favicon sources allow supported static SVG elements. Animated links, script
  handlers, namespaced event attributes, and nested SVG data URIs are removed
  with warnings. Oversized canvases are rejected and rasterization is bounded.
  Some previously accepted artwork may need simplifying. Manifest previews
  validate managed package paths, and unreachable favicon code is removed.
- Branding, local-task, and form-error summary links receive minimum pointer
  target sizes while allowing child themes to set their presentation.

## Compatibility and validation

- Drupal and Tools minimums remain `^11.3 || ^12` and `^2.2`. Blocking CI now
  includes Drupal 11.4; Drupal 12 beta and `dev-main` remain advisory checks.
  Drupal 11.4's `vendor/bin/dr` is documented as experimental.
- Pull requests verify the parent library and breakpoint contract against the
  working tree. Rendered accessibility coverage includes a form with validation
  errors; template and favicon checks cover the new contracts and restrictions.
- README images use raw asset URLs, the demo uses HTTPS, and the external docs
  site is identified as pre-7.x Webpack guidance while it is rewritten.
- Published-package validation permits only explicitly listed Drupal.org
  omissions. Version 7.2.2 is the deliberate exception; unlisted gaps fail.
  See the [channel policy](./published-dependency-validation.md).

## Consumer action

Existing generated child themes must delete their copied
`templates/layout/page.html.twig` to receive parent fixes. This is the one
required manual migration step. Preserve customizations through a namespaced
parent extension and selected blocks instead of a full copy. Parent updates
do not rewrite existing child files. See [UPGRADE.md](../UPGRADE.md) for the
combined path, optional adoption of the earlier copied-tooling fixes, and
styling considerations.

Run `npm run release:check -- --skip-smoke` for static validation and
`npm run release:check` for the complete local gate. Use
`npm run publish-test -- --no-ci` to review the analyzer's calculated release.
The dated [dependency audit](./dependency-audit-2026-09-08.md) retains its
recorded upstream browser-tooling advisory; its results are historical evidence,
not a claim about a future dependency resolution.
