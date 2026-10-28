# Emulsify Drupal contributor guidance

## Repository map

- This repository distributes the `emulsify` Drupal parent theme, not a site.
- `src/Hook/`, `src/Favicon/`, `templates/`, and `assets/` implement parent behavior.
  `dist/` contains shipped assets; rebuild it when asset sources change.
- `whisk/` is the generation-only Drupal Starterkit source. Generated themes
  keep `emulsify` as their runtime parent and own the files copied into them.
- `whisk/AGENTS.md` and its integration skill describe generated-theme work.
  While editing the starter source here, use this contributor workflow and
  validate the resulting generated theme rather than developing in the starter.
- `config/emulsify-core/` configures parent asset tooling;
  `whisk/config/emulsify-core/` supplies project-owned extension points after
  generation. Shared tooling is provided by the installed `@emulsify/core`.
- `.github/scripts/` and `.github/fixtures/` implement release and consumer
  contracts. They are maintainer tooling, not generated project commands.

Read [README.md](README.md) and [UPGRADE.md](UPGRADE.md) for installation and
compatibility. The external documentation site still describes older tooling.
Read only the relevant contracts in `docs/`, especially
[generated themes](docs/generated-child-theme-contract.md),
[template extension](docs/template-extension.md), and
[hook output](docs/twig-hook-contract.md).

## Ownership boundaries

Keep the starter component-library-neutral. Libraries supply component
directories, APIs, namespaces, stories, tokens, asset entrypoints, and Drupal
asset integration. Projects supply content models and field-to-component
mappings. Discover these from the selected library and project; do not add a
particular library's structure or components to the starter.

Preserve Drupal render arrays, cache/access metadata, attributes, translations,
and contextual editing when changing rendering. Parent Twig extension blocks
and hook outputs are public contracts; check their existing documentation and
fixtures before changing them.

New generated guidance should discover identity from local metadata rather
than embed a starter machine name or a component path. Keep guidance links
valid within the generated theme. Files with documentation tokens need explicit
processing in `whisk/src/StarterKit.php`; token-free guidance can be copied
unchanged using `whisk/whisk.starterkit.yml` protection rules. Test both Drupal
core and Emulsify Tools Drush generation.

## Validation

Use the root `package.json` for maintainer commands and the generated theme's
own package for consumer commands. Use `npm ci --ignore-scripts` to install
root tooling. Check declared and resolved runtime requirements before updating
dependencies; root release tooling and generated themes have different Node
minimums.

- Generated files or contracts: `npm run test:generated-theme`; use the
  disposable generation smoke workflow in the generated-theme contract for
  Starterkit changes, including hidden instruction files.
- Documentation commands: `npm run docs:check-commands`.
- PHP, JavaScript, or Sass: `npm run lint`; rebuild parent assets with
  `npm run build` when their sources change and include the resulting `dist/`.
- Release guard changes: `npm run release:check -- --skip-smoke`.

Run checks appropriate to the change and report omitted fixture checks and
their prerequisites accurately. A component-neutral generated theme may have
an empty inspector report; builds require library inputs and tests require
project tests. Storybook alone does not verify Drupal rendering or PHP hooks.

Release automation calculates and commits the root npm version and lockfile
metadata. A release branch may prepare its next planned semantic version;
routine updates should leave versioning to the release workflow.
Generated starter lineage records the original baseline, not the currently
installed parent version. Preserve unrelated working-tree changes.
