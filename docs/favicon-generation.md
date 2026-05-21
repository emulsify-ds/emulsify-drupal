# Favicon Package Generation

Emulsify 7.x generates a complete favicon package from one SVG source configured in the Drupal theme settings form for `emulsify` or an Emulsify child theme.

## Requirements

Generated PNG and ICO assets require both PHP extensions:

- GD
- Imagick

The uploaded source must be an SVG file with a square `viewBox`. If root `width` and `height` values are present, they must also describe a square. The generator accepts embedded raster image data inside the SVG, but the theme settings UI warns that fully vector sources usually scale more cleanly.

Source uploads are limited to 5 MB. The sanitized portable SVG copy is stored in theme config for portability; copies larger than 256 KB are allowed but flagged as review noise because they make config exports harder to inspect.

## Generated Package

Packages are written to the public files directory using a deterministic hash:

```text
public://favicon-package/<theme_name>/<package_hash>
```

The hash is derived from the sanitized source SVG and favicon rendering settings. The package path, hash, timestamp, sanitized SVG source, and source filename are stored in `<theme>.settings` so the package can be regenerated in another environment after config import.

Each generated package contains:

- `favicon.svg`
- `favicon.ico`
- `favicon-96x96.png`
- `apple-touch-icon.png`
- `web-app-manifest-192x192.png`
- `web-app-manifest-512x512.png`
- `web-app-manifest-512x512-maskable.png`
- `site.webmanifest`
- `metadata.json`

`metadata.json` records the theme name, package hash, generation timestamp, source metadata, normalized favicon settings, source warnings, and generated file list. It is used as the package existence marker.

Do not manually edit generated package files. Change the source SVG or favicon settings, then regenerate the package so metadata, hash, and head attachments stay consistent.

## Lifecycle

Generated favicon packages are environment-local build artifacts. They are expected to exist in each deployed environment, but they should be recreated from configuration rather than treated as hand-maintained source files.

Generation happens only in these workflows:

1. Save the Emulsify Drupal theme settings form after configuring or changing favicon-related settings.
2. Run the Emulsify Tools Drush generate command after deploy or config import:

```bash
drush emulsify_tools:favicon-generate [theme_name]
```

Use Emulsify Tools for deployment diagnostics and reset workflows:

```bash
drush emulsify_tools:favicon-status [theme_name]
drush emulsify_tools:favicon-reset [theme_name]
```

Normal page requests do not create, modify, or regenerate favicon files. At runtime, Emulsify only attaches head tags for an existing generated package. If the configured package path is missing, page rendering continues without generated favicon head tags until the theme settings form or Emulsify Tools command creates the package.
