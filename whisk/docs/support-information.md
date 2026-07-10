# Support information for %%EMULSIFY_THEME_NAME%%

Collect the smallest useful, sanitized diagnostic bundle before requesting support. Run commands from this theme directory unless a step says otherwise.

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

Reproduce the relevant failure and retain the complete terminal output:

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
