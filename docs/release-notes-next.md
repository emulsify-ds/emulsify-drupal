# Unreleased changes

Semantic Release determines the next version from the commits on `main`.
This draft covers changes since the latest tagged release; the combined
[7.2.1 upgrade path](../UPGRADE.md#upgrading-from-721-to-730) also includes the
fixes from 7.2.2, which was deliberately not published to Drupal.org.

## Added

- Optional YouTube and Vimeo embed settings reduce player tracking or replace
  the player with a keyboard-accessible, JavaScript-free click-to-load poster.
  Both options default to off; provider thumbnails are optional. Posters fit
  small frames, declare the interface language, and keep keyboard focus visible
  over thumbnails. See [media embeds](./media-embeds.md) for privacy caveats,
  settings scope, CSP requirements, and the contract for child template overrides.
- An optional default social share image uses the configured favicon SVG to
  generate a branded 1200 × 630 PNG and sharing metadata. Existing `og:image`,
  `twitter:image`, `twitter:card`, and their supported image URL aliases take
  precedence, including metadata supplied by Metatag. See
  [favicon generation](./favicon-generation.md#default-social-share-image).
- Optional dark-mode favicons offer inverted or alternate SVG artwork, with
  separate dark background and browser theme colors. Invert also reverses the
  light background unless a dark background is chosen. Settings default to off,
  preserving existing output. Saved Light tab and Dark tab previews show their
  respective appearances independently of the administrator's color scheme.
  See [favicon generation](./favicon-generation.md#dark-mode-browser-tabs).
- Generated themes include `AGENTS.md`, a Drupal integration guide, and an
  `emulsify-drupal-integration` skill. They discover the installed project's
  conventions while leaving component-library details to the selected library.
  Generation checks cover protected guidance, skill metadata, and links.
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
- Full and mini pagers identify the current page with `aria-current="page"`.
  Numbered links keep their hidden "Page" prefix and drop redundant titles;
  existing CSS classes remain unchanged.
- Form errors now have IDs, control associations, and a common styling class
  across all five error templates. Datetime descriptions receive `description`,
  select options preserve their attributes, and radios receive `form-radios`.
  Sites with bespoke fieldset/details/datetime error selectors may get double
  styling; the radios class may match rules compensating for its earlier absence.
- The duplicate status-messages registry key is removed, message groups avoid
  duplicate block IDs and the footer landmark role, and feed/progress templates
  move to `templates/misc/`. The oEmbed template drops its unnecessary `raw`
  filter while retaining Drupal's safe-markup behavior.
- Favicon sources allow supported static SVG elements. Links become groups or
  text spans, preserving artwork, presentation attributes, and attribute
  namespaces. Conditional switches retain one alternative, preferring a child
  without `requiredExtensions` or `requiredFeatures`. Scripts, animation, event
  handlers, nested SVG data URIs, and external references (including unclosed CSS
  `url(` values) are removed with warnings. Oversized canvases are rejected and
  rasterization is bounded.
  Some previously accepted artwork may need simplifying. Manifest previews
  validate managed package paths, and unused internal favicon helpers are
  removed while preserving the public API used by Emulsify Tools.
- Branding, local-task, and form-error summary links, plus links in full pagers,
  receive 24px minimum pointer targets through a zero-specificity `:where()`
  rule while allowing child themes to set their presentation.
- The parent's declared default logo now ships as a valid Emulsify SVG and is
  visible on light backgrounds.

## Compatibility and validation

- Parent assets moved to `dist/`; update child `libraries-override` path keys.
  See the [asset migration and global-rule opt-out guidance](../UPGRADE.md#parent-changes-inherited-after-the-update).
- Newly generated themes use Emulsify Core `^4.5.0`, which requires Node
  24.13.0 or newer; existing child manifests and lockfiles remain project-owned.
  Root release tooling requires Node 24.15 or newer. See the
  [frontend upgrade guidance](../UPGRADE.md#frontend-tooling-and-validation).
- Drupal and Tools minimums remain `^11.3 || ^12` and `^2.2`. Blocking CI now
  includes Drupal 11.4; Drupal 12 beta is blocking on PHP 8.5. Drupal 11.5 beta
  on PHP 8.3 and `dev-main` remain advisory.
  Drupal 11.4's `vendor/bin/dr` is documented as experimental.
- Static checks protect the favicon API used by Emulsify Tools 2.2. Fixture
  smoke tests run the published package's favicon generate and status Drush
  commands and verify the saved package path and generated files.
- Pull requests verify the parent library and breakpoint contract against the
  working tree. Rendered accessibility coverage includes a form with validation
  errors and page 2 of a paged view, with exactly one current pager item;
  template and favicon checks cover the new contracts and restrictions.
- README images use raw asset URLs, the demo uses HTTPS, and the external docs
  site is identified as pre-7.x Webpack guidance while it is rewritten.
- Published-package validation permits only explicitly listed Drupal.org
  omissions. Version 7.2.2 is the deliberate exception; unlisted gaps fail.
  See the [channel policy](./published-dependency-validation.md).

## Consumer action

Existing generated child themes must delete their copied
`templates/layout/page.html.twig` to receive parent fixes. Preserve customizations
through a namespaced parent extension and selected blocks instead of a full
copy. Parent updates do not rewrite existing child files. See
[UPGRADE.md](../UPGRADE.md) for the combined path, optional adoption of the earlier
copied-tooling fixes, and styling considerations.

Sites using the generated favicon package must [regenerate it in development
and export their theme settings](../UPGRADE.md#after-updating-regenerate-favicons-and-export-config)
before deploying the update.

Run `npm run release:check -- --skip-smoke` for static validation and
`npm run release:check` for the complete local gate. Use
`npm run publish-test -- --no-ci` to review the analyzer's calculated release.
The dated [dependency audit](./dependency-audit-2026-10-21.md) records the root
and fresh Whisk findings, upstream fix owners, and the unavailable Composer
audit endpoint. Earlier audits remain history; results describe the recorded
resolutions rather than future dependency trees.
