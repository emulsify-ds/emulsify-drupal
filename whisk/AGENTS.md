# Theme guidance

If this directory contains a `*.starterkit.yml` file, it is a generation source.
Follow the enclosing repository's contributor instructions and validate changes
through theme generation. Otherwise, this directory is a project-owned theme.

Read [README.md](README.md), [the development guide](docs/development.md),
`project.emulsify.json`, `package.json`, and the theme's `.info.yml` before
changing theme code. Discover identity, available commands, dependencies, and
installed library structure from those files and existing project code.

For Drupal template or component integration, use
[the integration skill](.agents/skills/emulsify-drupal-integration/SKILL.md) and
[the integration guide](docs/drupal-integration.md). For upgrades or failures,
read [upgrading](docs/upgrading.md) or
[support information](docs/support-information.md).

Read `docs/component-library.md` if the installed library supplies it, along
with relevant component schemas, stories, templates, and local instructions.
The library owns its layout, component APIs, assets, and tokens; the project
owns its Drupal field mappings. Discover these before choosing an implementation.

Run frontend commands from this directory. Select checks from `package.json`
for the changed behavior and available build inputs; verify Drupal integration
in the actual site. Preserve generated-source lineage in project metadata.
