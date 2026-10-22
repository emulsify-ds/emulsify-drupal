# Media embeds

Emulsify's **Media embeds** theme settings apply to Drupal core's oEmbed video
formatter for YouTube and Vimeo. Existing generated child themes inherit the
parent hooks and template. Both features are off by default, preserving the
existing iframe document and formatter iframe output.

## Options

- **Privacy mode** uses YouTube's `www.youtube-nocookie.com` player host and
  adds Vimeo's `dnt=1` parameter. Other player parameters remain intact,
  including Vimeo's `h` parameter for unlisted videos. The player still loads
  before playback; this option reduces tracking rather than hiding the visitor
  from the provider.
- **Click to load** replaces the player with a keyboard-accessible **Play
  video** link. Activating it opens the player inside the same frame, with
  `autoplay=1`. It works without JavaScript. Until activation, the browser
  makes no player request. Loading the player adds a browser history entry;
  the first Back returns the frame to the poster.
- **Poster image** chooses the provider's thumbnail or a neutral background.
  The provider thumbnail makes a request to the provider's image server before
  activation, revealing the visitor's network address. Neutral mode makes no
  third-party image request; its player link is fetched only when activated.

The settings keys are `media_embed_privacy_mode` (false),
`media_embed_click_to_load` (false), and `media_embed_poster` (`provider` or
`neutral`, default `provider`). Configure the active theme through its Drupal
theme settings page. Settings come from that theme's own saved settings;
Drupal core does not inherit saved settings from a base theme. Save these
options on the child theme the site uses. Missing keys in older child themes
fall back to the off defaults.
Other providers and unsupported or malformed embed HTML keep core's behavior.

YouTube describes the [privacy-enhanced player](https://support.google.com/youtube/answer/171780)
as limiting personalization. Vimeo's [DNT behavior](https://help.vimeo.com/hc/en-us/articles/26080940921361-Vimeo-Player-Cookies)
still permits essential cookies, and previously stored cookies may be sent.
Once the player loads or a video plays, the provider receives data. Drupal may
still fetch oEmbed metadata on the server; click to load controls
visitor-browser player requests.

The play link explicitly uses `referrerpolicy="strict-origin-when-cross-origin"`.
This sends the iframe document's origin to the player without its page path or
query, including on sites whose default policy is `no-referrer`. YouTube
[requires a referrer or equivalent client identity](https://developers.google.com/youtube/iframe_api_reference#onError)
to avoid error 153. Thumbnail images separately use `no-referrer`.

When either option is enabled, the outer iframe permits autoplay, fullscreen,
picture-in-picture, and encrypted media for its own source and the recognized
YouTube and Vimeo player origins. Explicit provider origins keep those
permissions available when the poster link navigates the frame to the player.

The iframe document uses the site's default theme even when the surrounding
page uses another theme, such as an admin preview in Claro. Those pages can
still show the poster, but their outer iframe does not receive Emulsify's
`allow` attribute. Autoplay can require an extra click, and fullscreen may be
unavailable there.

Sites with a Content Security Policy must allow the player origins in
`frame-src`: `https://www.youtube-nocookie.com` or `https://www.youtube.com`,
depending on privacy mode, and `https://player.vimeo.com`. Provider poster mode
also needs the provider's thumbnail hosts in `img-src`.

## Template overrides

Child themes overriding `media-oembed-iframe.html.twig` must render
`emulsify_oembed_poster` when present to receive click-to-load behavior, and
otherwise render `{{ media }}`. The variable contains `player_url`, `title`,
`provider`, `thumbnail_url`, `thumbnail_width`, `thumbnail_height`,
`aspect_ratio`, and the current interface `language_code`. The parent's
template sets the document language only for posters and shows the complete
translated, escaped link, decorative play icon, hidden title, and optional
thumbnail. Controls become compact in frames 160 pixels high or shorter.

Keep provider HTML inside this iframe document, as Drupal core does. Do not
print `media` in the surrounding page or replace core's `IFrameMarkup` trust
boundary with a general-purpose `raw` filter. The poster attaches
`emulsify/oembed_poster`, a CSS-only library; its local hidden-text and focus
styles work without core's main-page CSS or a JavaScript placeholder. Child
themes can override its low-specificity selectors.

## Browser verification

After enabling the feature, check YouTube and Vimeo media items in the site's
supported browsers. With the network panel open, verify that no player request
occurs before activation, and that neutral mode makes no thumbnail request.
Tab to **Play video**, activate it with Enter, and test playback and fullscreen.
Check that the play circle and label fit in 200 × 113 and 320 × 180 frames.
Browser autoplay policies or provider restrictions can still require pressing
the player's own play button; verify this on the deployed site and its devices.
