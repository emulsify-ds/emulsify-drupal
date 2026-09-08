# Support information for %%EMULSIFY_THEME_NAME%%

Start with the common fixes below. If the problem continues, collect the smallest useful, sanitized diagnostic bundle before requesting support. Run commands from this theme directory unless a step says otherwise.

## Troubleshooting

- **Wrong Node.js version:** run `nvm use` and compare `node --version` with the resolved Emulsify Core package's `engines.node`. Core 4.3.1/4.4.0 requires at least 24.13.0 even though the theme advertises `>=24`; `.nvmrc` selects only the Node 24 line.
- **Missing package or command:** run `npm install` again from this directory; keep the dependency metadata and Emulsify Core configuration intact.
- **Drupal cannot find project assets:** follow the selected component library's build and Drupal integration guidance, then confirm its declared outputs exist and its libraries are attached.
- **Storybook fails:** run `npm run build` first, then capture tool versions and the complete failing command output using the checklist below.
- **A generated value looks wrong:** inspect `%%EMULSIFY_MACHINE_NAME%%.info.yml`, `package.json`, and `project.emulsify.json` before editing documentation by hand.

## Theme and frontend information

Record the tool versions, installed Emulsify Core version, project metadata, and available scripts:

```bash
node --version
npm --version
npm ls @emulsify/core --depth=0
npm run
node -e "const data=require('./project.emulsify.json'); console.log(data.project)"
```

The expected Emulsify Core range is `%%EMULSIFY_CORE_RANGE%%`. The generated-source record is `%%EMULSIFY_SOURCE_PROJECT%%` `%%EMULSIFY_SOURCE_VERSION%%` for machine name `%%EMULSIFY_MACHINE_NAME%%`.

After a component library is installed, reproduce the relevant failure and retain the complete terminal output:

```bash
npm run build
npm run storybook-build
```

## Drupal environment information

From the Drupal project root, record only version information:

```bash
php --version
composer --version
composer show drupal/core --no-ansi
```

Also note the database driver, active default theme, and whether the problem occurs for administrators, anonymous visitors, or both. A short sanitized Drupal status summary is useful when available.

Do not share database credentials, API keys, environment variables, private URLs, user data, full configuration exports, or unreviewed status reports. Redact local paths and hostnames when they identify private infrastructure.

## Problem description

Include:

- the command or page that fails and the complete error message;
- the smallest steps that reproduce the problem;
- whether it began after a dependency, Drupal, content, configuration, or project-code change;
- the relevant project diff, with secrets and private data removed;
- whether `npm run build` and `npm run storybook-build` pass independently.

Future automated Drupal diagnostic collection belongs in Emulsify Tools so it can use Drupal APIs and apply consistent redaction. This generated theme intentionally provides guidance only and adds no diagnostic runtime code.
