# Social share images

The **Social share image** section of
`/admin/appearance/settings/<your_theme>` supplies an optional fallback image
for shared links. It works independently of generated favicon packages and is
off by default. Configure the child theme the site uses; Drupal does not
inherit a base theme's saved settings.

## Choose an image

Enable **Enable social share image**, then upload an image or enter a local
image path. Supported formats are JPG/JPEG, PNG, WebP, and GIF. SVG, remote
URLs, and private files are not supported. The file must already exist and be
publicly readable. Supported path examples are:

- `public://images/share.jpg`
- `images/share.jpg`, relative to the public files directory when no matching
  file exists under the Drupal web root
- `themes/custom/my_theme/images/share.jpg`, relative to the Drupal web root

A new upload replaces the configured path; a path to a different image replaces
the saved upload. A path to the same image retains its managed reference so
unused-file cleanup cannot delete it. Uploads are stored under
`public://social-share-image/<theme_name>`.
Save the form to update the image preview and metadata.

A 1200 × 630 image is a useful starting point for large social cards. Emulsify
scales larger images proportionally within 1200 × 1500 pixels, covering wide
cards and portrait previews without cropping or upscaling. The original file
and configured source path are retained. Preview and sharing metadata use the
resized copy and its actual width, height, and MIME type. Describe
the image in **Image alternative text**; leaving it blank uses the current
site name.

## Preview different platforms

The saved image can be previewed as Facebook, X/Twitter, LinkedIn, or Pinterest
cards, at desktop and mobile widths. The layouts approximate each platform's
cropping and framing. The initial title, description, and URL come from the
site name, slogan, and current site's base URL.

Preview title, description, and URL controls only change the local mock card.
They are not saved as settings, do not change page metadata, and do not fetch
the entered URL. The image continues to show the saved configuration until
image changes are saved. A page-specific Metatag image may differ from the
theme-wide image shown here.

Platform and device selections update immediately, including when a site's
existing select enhancement emits jQuery change events. The controls use Drupal
Form API elements so the active administration theme supplies their styling.
Emulsify does not include Chosen or require a select enhancement.

## Metadata precedence

For an enabled, valid image, Emulsify adds absolute image URLs with:

- Open Graph properties: `og:image`, `og:image:type`, `og:image:width`,
  `og:image:height`, and `og:image:alt`.
- X/Twitter metadata: `twitter:card` (`summary_large_image`), `twitter:image`,
  and `twitter:image:alt`.

Existing `og:image`, `og:image:url`, `og:image:secure_url`, `twitter:image`,
`twitter:image:src`, or `twitter:card` metadata takes precedence,
case-insensitively. If any of those is present, Emulsify adds none of its
fallback sharing tags. This preserves page-specific metadata, including images
and card types configured through Metatag.

Social platforms may cache previous images and metadata. After deployment,
check public pages with [LinkedIn's Post Inspector](https://www.linkedin.com/post-inspector/)
or [Facebook's Sharing Debugger](https://developers.facebook.com/tools/debug/).

## Configuration and deployment

The theme saves four settings: `social_share_image_enabled`,
`social_share_image_path`, `social_share_image_fid`, and `social_share_image_alt`.
Existing generated themes receive missing defaults from the parent. Optional
child schema additions are documented in the [upgrade guide](../UPGRADE.md#optional-social-share-image).

Deploy the selected image with the site's public files or theme assets. An
upload also references a managed file entity; retain that file record when
moving content between environments, or configure a `public://` path without
an upload reference on the destination. Unlike the portable favicon SVG,
social image bytes are not stored in configuration and cannot be regenerated
by the favicon Drush commands.

Large-image derivatives are stored in
`public://social-share-image/processed/`, keyed by source content and resize
settings. They are prepared when opening or saving theme settings; public page
requests only read existing derivatives. Deploy these public files with the
source images, or open and save the theme settings on the destination to
prepare them. After adopting resizing, open the settings once to prepare any
previously configured large image.
