# %%EMULSIFY_THEME_NAME%%

%%EMULSIFY_DESCRIPTION%%

This is the project-owned Emulsify child theme for **%%EMULSIFY_THEME_NAME%%**, with a Vite-based build workflow and Storybook.
Shared tooling comes from Emulsify Core; the project owns its templates and components.

|   |   |
|---|---|
| Machine name | `%%EMULSIFY_MACHINE_NAME%%` |
| Drupal metadata | `%%EMULSIFY_MACHINE_NAME%%.info.yml` |
| Project metadata | [`project.emulsify.json`](project.emulsify.json) |
| Generated from | `%%EMULSIFY_SOURCE_PROJECT%%` `%%EMULSIFY_SOURCE_VERSION%%` |
| Expected Emulsify Core range | `%%EMULSIFY_CORE_RANGE%%` |

## Quick start

Use Node.js 24.13.0 or newer for Emulsify Core 4.5.0. This theme's
`package.json` advertises `>=24`, but Core's `>=24.13.0` requirement sets the
effective minimum. `.nvmrc` selects the Node 24 line without pinning a minor
version. Check the resolved Core package's `engines` when updating dependencies.
If you use nvm, install and select that Node line first:

```bash
nvm install
nvm use
```

Then install the shared tooling:

```bash
npm install
```

The generated theme does not include project asset source files or active asset references. Its Drupal library file contains commented CSS and JavaScript examples to update after installing the project's component library. Install that library before running development and build commands.

## Documentation

- [Development guide](./docs/development.md) - Setup, commands, assets, and project ownership.
- [Drupal integration](./docs/drupal-integration.md) - Template discovery, component data mapping, and Drupal verification.
- [Upgrading](./docs/upgrading.md) - Dependency updates and starter-release comparisons.
- [Troubleshooting and support](./docs/support-information.md) - Common fixes and a sanitized diagnostic checklist.

## Working with AI

[AGENTS.md](AGENTS.md) routes an assistant to this theme's metadata and guides.
The theme includes an optional
[Drupal integration skill](.agents/skills/emulsify-drupal-integration/SKILL.md)
for connecting project components to Drupal. The selected component library
supplies its own structure, APIs, assets, and token guidance.

Start an AI session from this theme directory when using local skill discovery.
For sessions started at the Drupal repository root, explicitly read this theme's
`AGENTS.md` and skill, or add a reference in the site's existing agent
instructions. See [the development guide](docs/development.md) for setup.
