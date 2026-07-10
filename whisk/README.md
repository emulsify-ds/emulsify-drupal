# %%EMULSIFY_THEME_NAME%%

%%EMULSIFY_DESCRIPTION%%

This is the project-owned Emulsify child theme for **%%EMULSIFY_THEME_NAME%%**, with a Vite-based build workflow and Storybook.
Shared tooling comes from Emulsify Core; the project owns its templates and components.

|   |   |
|---|---|
| Machine name | `%%EMULSIFY_MACHINE_NAME%%` |
| Drupal files | `%%EMULSIFY_MACHINE_NAME%%.info.yml`, `%%EMULSIFY_MACHINE_NAME%%.libraries.yml` |
| Project metadata | [`project.emulsify.json`](project.emulsify.json) |
| Generated from | `%%EMULSIFY_SOURCE_PROJECT%%` `%%EMULSIFY_SOURCE_VERSION%%` |
| Expected Emulsify Core range | `%%EMULSIFY_CORE_RANGE%%` |

## Quick start

Use Node.js 24 or newer. If you use nvm, select the recommended version first:

```bash
nvm install
nvm use
```

Then install dependencies and start development:

```bash
npm install
npm run develop
```

## Documentation

- [Development guide](./docs/development.md) - Setup, commands, assets, and project ownership.
- [Upgrading](./docs/upgrading.md) - Dependency updates and starter-release comparisons.
- [Troubleshooting and support](./docs/support-information.md) - Common fixes and a sanitized diagnostic checklist.
