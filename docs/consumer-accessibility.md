# Generated consumer accessibility checks

The Theme Readiness workflow builds a disposable Whisk child theme and audits it on pull requests, pushes, scheduled runs, and manual runs. Validation jobs have `contents: read` permissions. The consumer job uses Drupal 11.4.6 with PHP 8.3 and resolves published Emulsify Tools using the theme's declared Composer range. Node uses `.nvmrc`; setup-node caches npm downloads, and a pinned cache action preserves Composer downloads and Puppeteer's browser cache across runs.

The project component fixture is `.github/fixtures/consumer-component`: a Twig status panel with an actual Storybook story, stylesheet, and ESM tests that render the real template and assert its DOM and text escaping. The fixture is copied only into the disposable generated consumer. Whisk remains component-neutral; no existing child theme is rewritten.

The generated consumer's existing `a11y` command builds Vite and Storybook and runs the Core accessibility script. An additional browser scan in `.github/scripts/rendered-a11y.cjs` checks the same story, `/node/1`, and `/user/login` with explicit `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, and `wcag22aa` axe tags. This matters because Pa11y's current built-in axe runner selects WCAG 2.1 tags by default. No rule allowlist is applied. Browser errors, missing rendered content, failed HTTP responses, and any reported violation fail the job.

Each run saves HTML, server/build logs, and full axe JSON, including incomplete checks for manual review, in the generated consumer artifact directory. Automated checks cover only what axe can evaluate; they do not establish complete WCAG conformance or replace keyboard and assistive-technology testing.

## September 8, 2026 local evidence

The fresh consumer resolved `@emulsify/core` 4.4.0 and published Emulsify Tools 2.2.1 on Drupal 11.4.6 / PHP 8.3.33 / Node 24.19.0. Both core `vendor/bin/dr generate-theme` and Drush `emulsify` generated a child theme successfully. Its real Vite build, Storybook build, existing `a11y` command, and Drupal render phase completed successfully.

The stricter WCAG 2.2 AA browser check **fails on the following existing issues**, with no suppression:

| Page | Rule / severity | Location | Finding |
| --- | --- | --- | --- |
| `/node/1` | `target-size` / serious | `a[rel="home"][href="/"]:nth-child(2)` inside the site-branding block | The site-name link is approximately 113.3 × 18.5 CSS pixels and has insufficient separation from neighboring targets. |
| `/user/login` | `target-size` / serious | `a[rel="home"][href="/"]:nth-child(2)` inside the site-branding block | The same site-name link is below the required size/separation. |
| `/user/login` | `target-size` / serious | `#block-example-theme-primary-local-tasks > ul > li:nth-child(1) > .is-active[href$="login"][data-drupal-link-system-path="user/login"]` | The active Log in tab is approximately 42.2 × 18.5 CSS pixels and has insufficient separation from neighboring targets. |

These findings concern WCAG 2.2 criterion 2.5.8, Target Size (Minimum). The relevant axe rule is [target-size](https://dequeuniversity.com/rules/axe/4.13/target-size). The real Twig component passed 11 rules with no violations; the Drupal node and login pages passed 23 and 27 rules respectively and each reported the `target-size` rule above. No incomplete checks were returned in this run. The CI gate remains failing until the theme's target sizing is addressed in separately reviewed styling work. This change does not modify the existing theme's markup, classes, or styles.

Failure propagation was demonstrated against the real consumer:

- An unclosed rule temporarily added to the component SCSS made the real build exit 1. Restoring the original file made the build exit 0.
- A temporarily injected image without alternative text produced a new `image-alt` violation in the previously passing component and made the browser command exit 1. The image existed only in the disposable browser DOM, which was closed; the normal subsequent run again had no component violations and retained only the Drupal findings above.
- Before the fixture correction below, the node page's empty main region failed the browser's visible-content assertion. The corrected fixture rendered actual body content and advanced to the accessibility audit.

## Fixture differences found during verification

Drupal 11.4's deprecated direct `core/scripts/drupal` entrypoint failed to locate the Composer autoloader in the recommended-project layout. The smoke runner now uses the installed `vendor/bin/dr` proxy when available, retaining the legacy entrypoint for earlier supported cores.

The Drupal 11.4 standard profile did not provide the page body field. The existing fixture created the bundle without the field, so its seeded body value was absent from the rendered page. The setup now creates the fixture field and view-display component using supported entity APIs before seeding real content.

The old fixture also cloned an in-flight Emulsify Tools branch. It now installs the published package through Composer using the theme's exact declared range, so rendering and helper observations use the API consumers install.
