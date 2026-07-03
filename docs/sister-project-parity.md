# Sister Project Parity Contract

This document defines the parity contract between Emulsify Drupal and
Emulsify WordPress. Parity means the two projects should preserve the same
product model and frontend authoring expectations, not that every CMS-specific
file or generation path must match.

## Shared Emulsify Contract

The parent theme owns the reusable CMS runtime. Shared behavior that every
generated project needs belongs in the parent theme: CMS integration, runtime
hooks, default templates, global asset library wiring, theme settings support,
release checks, and compatibility with the current Emulsify frontend stack.

The generated child theme owns the project implementation. Project-specific
component source, Twig templates, Sass entry points, compiled site assets,
content model assumptions, visual decisions, and local overrides belong in the
generated child theme. A project should extend the parent runtime rather than
forking reusable behavior into each implementation.

Whisk is the starter. It is a generation-only source for child themes, not a
runtime theme to enable directly. The generated theme keeps the parent theme as
its runtime base and keeps `project.emulsify.json` so Emulsify Core can identify
the generated CMS platform and project structure.

The shared frontend stack is Emulsify Core 4, Vite, Storybook, Twig, and
Node.js 24. Generated child themes should expose the expected Emulsify Core
scripts for local development, production builds, Storybook builds, tests, and
optional accessibility checks. Root release tooling may require a newer Node.js
24 patch line than generated child themes.

Component source and generated assets have separate ownership. Component source
and project Sass should stay in the generated child theme and remain the source
of truth. Compiled Drupal- or WordPress-facing assets should be reproducible
from that source and wired through the CMS asset system for the generated theme.
Reusable runtime assets and behaviors stay in the parent theme.

## Intentional Drupal Differences

Drupal generation uses Drupal Starterkit and Emulsify Tools. Emulsify Tools
provides the project-facing Drush generation workflow, while Drupal core's
Starterkit command remains an equivalent lower-level generation path. Both use
`whisk` as the starter source and produce a generated child theme.

Generated Drupal child themes use `base theme: emulsify`. The `whisk` starter is
hidden and generation-only, while generated themes are enabled as normal Drupal
themes that inherit the Emulsify Drupal parent runtime.

Drupal metadata and integration live in Drupal-native files. The parent and
starter define Drupal compatibility, dependencies, regions, and libraries in
`.info.yml` and `.libraries.yml` files. Theme settings defaults and schema live
in `config/install` and `config/schema`, and release checks keep those config
surfaces aligned with the PHP settings API.

Drupal Single Directory Components are preferred for new component includes.
New project Twig should include components with Drupal SDC names such as
`my_theme:list`. Legacy `@components` namespace includes remain valid migration
support for existing projects and shared templates, but they are not the default
pattern for new Drupal component code.

Drupal readiness requires Drupal fixtures and render coverage. Release checks
and the theme readiness workflow validate parent and starter metadata, build
disposable Drupal fixtures, verify declared region rendering, smoke test the
parent theme and generated child themes, exercise favicon generation with the
required PHP extensions, and run generated child-theme frontend checks. These
checks prove the Drupal runtime contract in addition to the shared Emulsify
frontend contract.
