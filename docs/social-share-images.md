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

## Set and confirm fallback metadata

**Default sharing metadata** saves an optional title, description, and page
URL. Blank titles and descriptions add no text tags. A blank URL uses each
page's canonical URL, falling back to its current URL without tracking query
parameters. An explicit URL is a theme-wide fallback; leave it blank when each
page should identify itself.

The live confirmation table identifies the tags and their fallback values
after saving:

| Setting or source | Tags |
| --- | --- |
| Resized image URL | `og:image`, `twitter:image` |
| Actual image MIME type and dimensions | `og:image:type`, `og:image:width`, `og:image:height` |
| Alternative text, or the site name when blank | `og:image:alt`, `twitter:image:alt` |
| Default title | `og:title`, `twitter:title` |
| Default description | `og:description`, `twitter:description` |
| Default or canonical page URL | `og:url` |
| Large-image card format | `twitter:card` (`summary_large_image`) |

Typing updates the table and mock card immediately. Save the form to publish
the defaults. Image URLs in the table describe the saved, processed image;
save an image upload or path change before its preview updates. The entered
page URL is metadata only and is never fetched by the settings page.

## Preview different platforms

The saved image can be previewed as Facebook, X/Twitter, LinkedIn, or Pinterest
cards, at desktop and mobile widths. Facebook uses a wide image with domain,
title, and description below; LinkedIn uses a compact title/domain panel; X
uses an image-first card with a headline overlay; Pinterest uses a portrait
pin layout. A description can be present in metadata even when a platform does
not display it in its card.

Platform and client versions differ, so these are layout approximations. The
two reference tools also differ: [Social Share Preview](https://socialsharepreview.com/)
uses X's image-overlay layout, while [OpenTweet](https://opentweet.io/tools/x-card-validator)
shows an expanded image-and-text layout. Page-specific Metatag values may also
produce a different card from this theme-wide fallback preview.

Platform and device selections update immediately, including when a site's
existing select enhancement emits jQuery change events. The controls use Drupal
Form API elements so the active administration theme supplies their styling.
Emulsify does not include Chosen or require a select enhancement.

## Metadata precedence

Existing `og:image`, `og:image:url`, `og:image:secure_url`, `twitter:image`,
`twitter:image:src`, or `twitter:card` metadata takes precedence,
case-insensitively. If any of those is present, Emulsify adds none of its
fallback image/card tags. Text and URL defaults fill only missing tags.
Existing Open Graph titles and descriptions also protect X's Open Graph
fallback from a generic Twitter title or description. Existing tags are never
replaced, including metadata configured through Metatag.

Social platforms may cache previous images and metadata. After deployment,
check public pages with [LinkedIn's Post Inspector](https://www.linkedin.com/post-inspector/)
or [Facebook's Sharing Debugger](https://developers.facebook.com/tools/debug/).

## Configuration and deployment

The theme saves seven settings: `social_share_image_enabled`,
`social_share_image_path`, `social_share_image_fid`, `social_share_image_alt`,
`social_share_image_title`, `social_share_image_description`, and
`social_share_image_url`. Platform and device selections remain local preview
options and are not exported as theme settings.
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
