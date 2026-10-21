# Favicon Package Generation

Emulsify 7.x generates a complete favicon package from a main SVG source and an optional dark-mode SVG configured in the Drupal theme settings form for `emulsify` or a generated child theme.

## Requirements

Generated PNG and ICO assets require both PHP extensions:

- GD
- Imagick

The uploaded source must be an SVG file with a `viewBox`. Non-square sources are centered on a square canvas before package generation so the original aspect ratio is preserved across browser, iOS, and Android assets. The generator accepts embedded raster image data inside the SVG, but the theme settings UI warns that fully vector sources usually scale more cleanly.

Source uploads are limited to 5 MB. The sanitized portable SVG copy is stored in theme config for portability; copies larger than 256 KB are allowed but flagged as review noise because they make config exports harder to inspect.

The sanitizer allows static SVG drawing elements: `svg`, `g`, `path`, `rect`,
`circle`, `ellipse`, `line`, `polyline`, `polygon`, `text`, `tspan`, `defs`,
`symbol`, `use`, `image`, `clipPath`, `mask`, `linearGradient`, `radialGradient`,
`stop`, `title`, and `desc`. Unsupported elements, event handlers (including
namespaced attributes), styles, and unsafe references are removed with a
warning. Image data URIs may contain PNG, GIF, JPEG, or WebP; nested SVG data
URIs are removed. Simplify unsupported artwork to static drawing elements
before uploading it.

The `viewBox` width and height must be finite and no larger than 4096 units.
This allows a canvas eight times the largest generated icon (512 pixels) while
rejecting extreme dimensions before rasterization. Numeric root dimensions
above 4096 are also rejected. For rasterization only, the root viewport is set
to four times the output size, at most 2048 × 2048 pixels at 96 DPI, while the
original `viewBox` is preserved.

Before decoding SVG, Imagick limits pixel-cache memory to 64 MiB, area to
4,194,304 pixels, and width and height to 2048 pixels each, or the host's
stricter limits. Memory and area thresholds can fall back to disk caching;
width and height limits reject oversized images. Existing host policy still
applies, and the prior process-wide limits are restored after rasterization.
See [ImageMagick resource policies](https://imagemagick.org/security-policy/).

## Generated Package

Packages are written to the public files directory using a deterministic hash:

```text
public://favicon-package/<theme_name>/<package_hash>
```

The hash is derived from the sanitized source SVG and favicon rendering settings. The package path, hash, timestamp, sanitized SVG source, and source filename are stored in `<theme>.settings` so the package can be regenerated in another environment after config import.

Each generated package contains:

- `favicon.svg`
- `favicon-dark.svg` (only when a dark-mode option is enabled)
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

## Dark-mode browser tabs

In **Browser favicon settings → Dark mode**, choose one of these treatments:

- **None** keeps the main icon in both color schemes (the default).
- **Invert** inverts the icon's RGB colors in dark mode while preserving its
  transparency. This works best for single-color marks.
- **Alternate icon** uses a separately uploaded SVG in dark mode. It has the
  same upload limits, sanitizer, and diagnostics as the main icon. Its sanitized
  portable copy is saved in config, and the managed upload lives under
  `public://favicon-source/<theme_name>/dark`.

Optionally enable **Use a different background in dark mode** and choose its
color, or **Set a dark-mode browser theme color** and choose that color. An
unchecked color option saves an empty value; it does not change the existing
light color. A dark browser theme color emits separate light and dark
`theme-color` metadata with matching color-scheme media attributes. The web app
manifest continues to use the Android background color.

These options store `favicon_dark_mode`, `favicon_dark_background_color`,
`favicon_dark_theme_color`, `favicon_dark_source_fid`,
`favicon_dark_source_svg`, and `favicon_dark_source_filename`. All are off or
empty by default. Older generated themes receive these defaults from the
parent even when their copied config schema predates these settings.

Enabling a treatment or either dark color adds a generated
`@media (prefers-color-scheme: dark)` rule to `favicon.svg` and writes
`favicon-dark.svg`, which always shows the dark appearance. Chromium-based
browsers and Firefox apply the SVG's dark-mode rule; other browsers show the
light icon. See [color-scheme media queries in SVG](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/prefers-color-scheme#embedded_elements).
PNG, ICO, Apple, and Android icons keep their existing light appearance because
those formats cannot follow the browser's color scheme. All defaults preserve
the existing package hash, files, and head tags. **Reset to theme default** also
clears the dark options and alternate icon.

## Saved package previews

Admin previews show the enabled package referenced by saved theme settings, and
appear only when that managed package exists in the current environment. An
uploaded SVG or portable SVG source alone does not produce a preview. Source
and package diagnostics remain available when generation is needed.

The Light tab preview uses the `favicon.svg` URL attached to the page head. The
Dark tab preview uses the saved `favicon-dark.svg` when present, falling back to
`favicon.svg` for older packages. Its dark appearance is independent of the
administrator's own color scheme. The iOS preview uses its `apple-touch-icon.png` URL and saved iOS title. Android
previews use the normal 192-pixel icon and the separate 512-pixel maskable icon
from the saved manifest's package; the launcher label comes from that manifest.
The generated files already contain their configured background and padding,
so the preview adds no second layer of either. Tab chrome and launcher masks
provide viewing context; they are not additional favicon output.

Changing unsaved colors, padding, labels, or the source upload leaves those
previews unchanged. The pending-changes notice and generation controls indicate
when a save or regeneration is needed. If imported settings describe a newer
package than the saved path, previews continue to show the package referenced by
the emitted head links until regeneration updates the saved reference.

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
