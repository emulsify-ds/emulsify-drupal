# Generated Child Theme Contract

Emulsify Drupal uses the Whisk starter as a Drupal Starterkit source. A
generated child theme is a new, project-owned theme: it is not the Whisk source
directory and it is not part of the Emulsify Drupal repository.

## What the contract guarantees

Release checks generate more than one valid child-theme identity in temporary
fixture directories. The scenarios include different machine names, a display
name containing spaces, and a description containing punctuation.

For each generated child theme, the checks verify:

- Starterkit generation completes and consistently replaces the requested
  machine name, display name, description, filenames, namespaces, and internal
  references.
- Starter-only files and placeholder values are absent except for focused
  source-lineage references that are intentionally retained.
- The generated `.info.yml` filename and Drupal metadata agree with the
  requested identity, preserve the supported Drupal core constraint, required
  regions, existing libraries, and the `emulsify` runtime parent theme, and do
  not mark the result as a Starterkit source.
- Library names, breakpoints, theme entrypoints, install config, and schema
  filenames use the generated machine name. Optional files are checked only
  when they are part of the current Whisk starter contract.
- `package.json` and `project.emulsify.json` are valid JSON, retain the expected
  npm scripts, Emulsify Core version range, Drupal platform metadata, and
  generated-source lineage, and agree on the generated project identity.
- `README.md`, `docs/development.md`, `docs/upgrading.md`, and
  `docs/support-information.md` are present. The README contains the requested
  display name, machine name, description, source project, source version, and
  Emulsify Core range, and the guides document only npm commands that the
  generated `package.json` exposes.
- Sass entrypoints referenced by the Drupal libraries contract exist, expected
  Vite output paths agree with the library definitions, and build and Storybook
  configuration resolve from the generated child theme or declared packages.
- Relative file references do not escape the generated child theme or depend on
  unpublished files from the Emulsify Drupal repository or Whisk source.
- No retired Stable9 inheritance metadata or language is reintroduced.

Failures identify the generated child theme, validation category, affected
file, stale or inconsistent value, and expected replacement. Successful output
identifies every scenario that passed.

## Local and fixture checks

The focused helper tests create small generated-theme fixtures in the system
temporary directory. They exercise valid metadata, placeholder detection,
missing references, inconsistent frontend metadata, and actionable error
messages without creating a Drupal site.

The static release check runs repository metadata, documentation, and helper
tests. Passing `--skip-smoke` avoids Drupal fixture creation while preserving
those static checks.

The full release check creates a disposable Drupal site, invokes Drupal core's
Starterkit generator, validates every generated identity, and exercises the
primary generated child theme through enable, render, frontend install, build,
and configured frontend test and Storybook smoke phases. It requires Bash,
Git, rsync, a supported PHP version, Composer, SQLite, network access, Node.js,
GD, and Imagick.

GitHub Actions uses the same release and Starterkit helpers as local checks.
Pull requests run the static and Drupal fixture coverage. Scheduled and manual
readiness runs add the extended Storybook and browser-based accessibility
checks.

## Run the checks

Install the root dependencies first with `npm ci --ignore-scripts`. Run the
focused helper tests or the static release gate without a Drupal fixture:

```bash
npm run test:generated-theme
npm run release:check -- --skip-smoke
```

When all fixture prerequisites are available, run the complete release check:

```bash
EMULSIFY_STARTERKIT_TEST=1 \
EMULSIFY_STARTERKIT_STORYBOOK_BUILD=1 \
npm run release:check
```

Do not report the full smoke test as passing when a required fixture
prerequisite is unavailable. Record the exact missing prerequisite and the
checks that did run.

## Common failures

- **Generation:** Drupal could not create the named child theme. Review the
  reported Starterkit command and fixture prerequisites.
- **Placeholder replacement:** A file still contains a Whisk machine name,
  display name, description, namespace, or source-only path. Fix the Whisk
  source or its Starterkit replacement rules.
- **Drupal metadata:** The generated `.info.yml`, library reference, region,
  core constraint, or parent-theme metadata is missing or inconsistent. Fix the
  source metadata rather than editing generated fixture output.
- **Frontend metadata:** Package scripts, the Emulsify Core range, Drupal
  platform data, or generated-source lineage disagree. Align `whisk/package.json`
  and `whisk/project.emulsify.json` with the release contract.
- **File references:** A referenced config file, Sass entrypoint, asset, or
  relative path is missing or resolves outside the generated child theme. Fix
  the reference or add the required source file.
- **Documentation:** A required generated guide is missing, still contains a
  source token, lost project-specific metadata, or documents an npm command the
  generated package does not expose. Fix the Whisk documentation template or
  its generation-only post-processor.
- **Build or Storybook:** Generation and static validation passed, but installed
  tooling could not produce the declared output. Inspect the named npm phase
  and its preserved log artifact.

## Outside the contract

The generated child-theme contract does not require or test:

- a particular component library, component directory, or example component;
- frontend CSS behavior or visual design;
- frontend JavaScript behavior;
- a particular design-token system or token build pipeline;
- Emulsify Tools administration behavior, Twig helpers, or Drush commands.

Projects select and install their own component libraries after generation.
The contract may preserve component-neutral project metadata, including Drupal
Single Directory Component support, without requiring any components to exist.
