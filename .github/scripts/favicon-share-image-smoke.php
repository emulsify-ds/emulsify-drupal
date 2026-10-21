<?php

/**
 * @file
 * Verifies optional share artwork and its fallback social metadata.
 *
 * Run through Drush in a disposable fixture with GD and Imagick enabled.
 */

declare(strict_types=1);

use Drupal\emulsify\Favicon\FaviconHeadBuilder;
use Drupal\emulsify\Favicon\FaviconPackageGenerator;
use Drupal\emulsify\Favicon\FaviconSettings;

/**
 * Fails with a useful assertion message.
 */
function emulsify_share_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/**
 * Captures a package's bytes before another generation.
 */
function emulsify_share_generate(FaviconPackageGenerator $generator, string $theme, string $source, array $settings): array {
  $result = $generator->generateFromSvg($theme, $source, $settings, ['filename' => 'share-image-test.svg'], TRUE);
  $files = [];
  foreach (scandir($result['path']) ?: [] as $name) {
    if ($name !== '.' && $name !== '..') {
      $contents = file_get_contents($result['path'] . '/' . $name);
      emulsify_share_assert(is_string($contents), 'Generated files must be readable.');
      $files[$name] = $contents;
    }
  }
  return [
    'result' => $result,
    'files' => $files,
    'metadata' => json_decode($files['metadata.json'], TRUE, 512, JSON_THROW_ON_ERROR),
  ];
}

/**
 * Returns only the sharing attachments supplied by this theme.
 */
function emulsify_share_tags(array $attachments): array {
  return array_values(array_filter(
    $attachments['#attached']['html_head'] ?? [],
    static fn(array $item): bool => str_starts_with((string) ($item[1] ?? ''), 'emulsify_favicon_share_'),
  ));
}

/**
 * Checks dimensions, background, centering, clear edges, and artwork ratio.
 */
function emulsify_share_pixels(string $png, string $background, int $padding): void {
  $image = imagecreatefromstring($png);
  emulsify_share_assert($image instanceof GdImage && imagesx($image) === 1200 && imagesy($image) === 630, 'Share artwork must be a 1200 by 630 PNG.');
  $background_rgb = hexdec(substr($background, 1));
  foreach ([[0, 0], [1199, 0], [0, 629], [1199, 629]] as [$x, $y]) {
    emulsify_share_assert((imagecolorat($image, $x, $y) & 0xffffff) === $background_rgb, 'Every corner must use the configured background.');
  }
  emulsify_share_assert((imagecolorat($image, 600, 315) & 0xffffff) === 0xcc3300, 'The source artwork must occupy the center.');
  $left = 1200;
  $right = -1;
  $top = 630;
  $bottom = -1;
  for ($y = 0; $y < 630; $y++) {
    for ($x = 0; $x < 1200; $x++) {
      if ((imagecolorat($image, $x, $y) & 0xffffff) !== $background_rgb) {
        $left = min($left, $x);
        $right = max($right, $x);
        $top = min($top, $y);
        $bottom = max($bottom, $y);
      }
    }
  }
  $inset = (int) round(630 * ($padding / 100));
  emulsify_share_assert(min($left, 1199 - $right, $top, 629 - $bottom) >= $inset - 1, 'Padding must keep the requested percentage of image height clear on every edge.');
  emulsify_share_assert(abs(($left + $right + 1) / 2 - 600) <= 1 && abs(($top + $bottom + 1) / 2 - 315) <= 1, 'Artwork must remain centered.');
  emulsify_share_assert(abs(($right - $left + 1) / ($bottom - $top + 1) - 2) < 0.03, 'The two-to-one source artwork must retain its aspect ratio.');
  unset($image);
}

$file_system = \Drupal::service('file_system');
$url_generator = \Drupal::service('file_url_generator');
$generator = new FaviconPackageGenerator(
  $file_system,
  $url_generator,
  \Drupal::service('config.factory'),
  \Drupal::service('cache_tags.invalidator'),
  \Drupal::service('datetime.time'),
  \Drupal::service('lock'),
);
$head = new FaviconHeadBuilder($url_generator);
$theme = 'emulsify_share_' . bin2hex(random_bytes(4));
$source = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 20"><rect width="40" height="20" fill="#cc3300"/></svg>';
$site_name = (string) \Drupal::config('system.site')->get('name');
$settings = FaviconSettings::normalize(['favicon_package_enabled' => TRUE], $site_name);

try {
  $baseline = emulsify_share_generate($generator, $theme, $source, $settings);
  emulsify_share_assert(!isset($baseline['files']['share-image.png']), 'Default packages must retain their existing file list.');
  $inactive = array_replace($settings, [
    'favicon_share_image_background_color' => '#112233',
    'favicon_share_image_padding' => 7,
    'favicon_share_image_alt' => 'Unused label',
  ]);
  emulsify_share_assert(emulsify_share_generate($generator, $theme, $source, $inactive) === $baseline, 'Disabled sharing inputs must preserve the hash and every package byte, including metadata.');

  $enabled = array_replace($settings, ['favicon_share_image_enabled' => TRUE]);
  $variants = [
    'enabled' => $enabled,
    'background' => array_replace($enabled, ['favicon_share_image_background_color' => '#112233']),
    'padding' => array_replace($enabled, ['favicon_share_image_padding' => 7]),
  ];
  $seen_hashes = [$baseline['result']['hash']];
  $packages = [];
  foreach ($variants as $label => $configured) {
    $package = emulsify_share_generate($generator, $theme, $source, $configured);
    $packages[$label] = $package;
    $hash = $package['result']['hash'];
    emulsify_share_assert(!in_array($hash, $seen_hashes, TRUE), "$label must participate in the package hash.");
    $seen_hashes[] = $hash;
    $expected_files = array_merge(array_keys($baseline['files']), ['share-image.png']);
    sort($expected_files);
    emulsify_share_assert(array_keys($package['files']) === $expected_files, 'Enabled sharing must add exactly one PNG.');
    emulsify_share_assert($package['metadata']['files'] === array_merge($baseline['metadata']['files'], ['share-image.png']), 'Metadata must list the share image.');
    foreach ($baseline['files'] as $name => $contents) {
      if ($name === 'metadata.json') {
        continue;
      }
      $actual = $name === 'site.webmanifest' ? str_replace($hash, $baseline['result']['hash'], $package['files'][$name]) : $package['files'][$name];
      emulsify_share_assert($actual === $contents, "$label must preserve existing $name output apart from package URLs.");
    }
    emulsify_share_pixels($package['files']['share-image.png'], $configured['favicon_share_image_background_color'], $configured['favicon_share_image_padding']);
    $alt_only = array_replace($configured, ['favicon_share_image_alt' => 'Different accessible label']);
    emulsify_share_assert($generator->getPackageDefinition($theme, $alt_only, $source)['hash'] === $hash, 'Alt text must not affect the binary package hash.');
  }

  $dark = array_replace($settings, [
    'favicon_dark_mode' => 'alternate',
    'favicon_dark_source_svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8" fill="#0000ff"/></svg>',
  ]);
  $dark_baseline = emulsify_share_generate($generator, $theme, $source, $dark);
  $dark_shared = emulsify_share_generate($generator, $theme, $source, array_replace($dark, ['favicon_share_image_enabled' => TRUE]));
  foreach ($dark_baseline['files'] as $name => $contents) {
    if ($name !== 'metadata.json' && $name !== 'site.webmanifest') {
      emulsify_share_assert($dark_shared['files'][$name] === $contents, 'Sharing must preserve existing dark-mode and raster artwork.');
    }
  }
  emulsify_share_assert(str_replace($dark_shared['result']['hash'], $dark_baseline['result']['hash'], $dark_shared['files']['site.webmanifest']) === $dark_baseline['files']['site.webmanifest'], 'Sharing must preserve the dark-mode manifest apart from package URLs.');
  emulsify_share_assert($dark_shared['metadata']['files'] === array_merge($dark_baseline['metadata']['files'], ['share-image.png']), 'Dark-mode metadata must retain all files and add the share image.');
  emulsify_share_assert($dark_shared['files']['share-image.png'] === $packages['enabled']['files']['share-image.png'], 'Share artwork must use the primary source without dark-mode styling.');

  $package_path = $packages['enabled']['result']['path'];
  $head_settings = array_replace($enabled, ['favicon_package_path' => $package_path]);
  $attachments = [];
  $head->apply($attachments, $head_settings);
  $tags = emulsify_share_tags($attachments);
  $url = $url_generator->generateAbsoluteString($package_path . '/share-image.png');
  emulsify_share_assert(in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], TRUE) && parse_url($url, PHP_URL_HOST), 'Share URLs must be absolute HTTP URLs.');
  $expected = [
    ['property' => 'og:image', 'content' => $url],
    ['property' => 'og:image:type', 'content' => 'image/png'],
    ['property' => 'og:image:width', 'content' => '1200'],
    ['property' => 'og:image:height', 'content' => '630'],
    ['property' => 'og:image:alt', 'content' => $site_name],
    ['name' => 'twitter:card', 'content' => 'summary_large_image'],
    ['name' => 'twitter:image', 'content' => $url],
    ['name' => 'twitter:image:alt', 'content' => $site_name],
  ];
  emulsify_share_assert(array_map(static fn(array $item): array => $item[0]['#attributes'], $tags) === $expected, 'Fallback sharing must emit exactly the eight requested tags with a live site-name alt fallback.');
  emulsify_share_assert(count(array_unique(array_column($tags, 1))) === 8, 'Every sharing attachment must have a distinct key.');
  $original_cache = ['tags' => ['fixture:existing'], 'contexts' => ['languages:language_interface'], 'max-age' => 600];
  $cacheable = ['#cache' => $original_cache];
  $head->apply($cacheable, $head_settings);
  emulsify_share_assert(array_diff(['fixture:existing', 'config:' . $theme . '.settings', 'config:system.site'], $cacheable['#cache']['tags']) === [], 'Sharing must retain existing tags and depend on theme settings and site config.');
  emulsify_share_assert(count($cacheable['#cache']['contexts']) === 2 && array_diff(['languages:language_interface', 'url.site'], $cacheable['#cache']['contexts']) === [], 'Absolute share URLs must vary by site while retaining existing cache contexts.');
  emulsify_share_assert($cacheable['#cache']['max-age'] === 600, 'Sharing must preserve existing cache max-age.');
  $custom = [];
  $hostile_alt = '"><img src=x onerror=alert(1)> & share';
  $head->apply($custom, array_replace($head_settings, ['favicon_share_image_alt' => $hostile_alt]));
  $custom_tags = emulsify_share_tags($custom);
  emulsify_share_assert($custom_tags[4][0]['#attributes']['content'] === $hostile_alt && $custom_tags[7][0]['#attributes']['content'] === $hostile_alt, 'Both platforms must use the configured alt text.');
  foreach ([$custom_tags[4][0], $custom_tags[7][0]] as $element) {
    // HtmlResponseAttachmentsProcessor adds this type to head elements.
    $element['#type'] = 'html_tag';
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($element);
    $document = new DOMDocument();
    $document->loadHTML('<html><head>' . $rendered . '</head><body></body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    emulsify_share_assert($xpath->query('//meta')->length === 1, 'Drupal must render the attached meta element.');
    emulsify_share_assert($xpath->query('//img | //@onerror')->length === 0 && $xpath->query('//meta')->item(0)->getAttribute('content') === $hostile_alt, 'Drupal must escape hostile alt text as one meta content attribute.');
  }

  foreach (['name', 'property', 'PROPERTY'] as $attribute) {
    foreach (['og:image', 'twitter:image', 'twitter:card'] as $identity) {
      $existing = [['#tag' => 'meta', '#attributes' => [$attribute => ' ' . strtoupper($identity) . ' ', 'content' => 'Existing Metatag value']], 'metatag_fixture'];
      $guarded = ['#attached' => ['html_head' => [$existing]], '#cache' => $original_cache];
      $head->apply($guarded, $head_settings);
      emulsify_share_assert(emulsify_share_tags($guarded) === [], 'Any existing social image or Twitter card must suppress all fallback sharing tags.');
      emulsify_share_assert($guarded['#attached']['html_head'][0] === $existing, 'Existing Metatag attachments must remain untouched.');
      emulsify_share_assert($guarded['#cache'] === $original_cache, 'Suppressed sharing must not change existing cache metadata.');
    }
  }
  $unrelated = [['#tag' => 'meta', '#attributes' => ['property' => 'og:title', 'content' => 'Existing title']], 'metatag_title'];
  $allowed = ['#attached' => ['html_head' => [$unrelated]]];
  $head->apply($allowed, $head_settings);
  emulsify_share_assert(count(emulsify_share_tags($allowed)) === 8 && $allowed['#attached']['html_head'][0] === $unrelated, 'Unrelated Metatag output must remain intact and permit sharing fallbacks.');
  $incomplete_path = 'public://favicon-package/' . $theme . '/0123456789ab';
  emulsify_share_assert($file_system->prepareDirectory($incomplete_path, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY), 'The incomplete-package fixture directory must be created.');
  emulsify_share_assert(file_put_contents($incomplete_path . '/share-image.png', $packages['enabled']['files']['share-image.png']) !== FALSE, 'The incomplete-package fixture must contain its PNG.');
  foreach ([
    'package disabled' => array_replace($head_settings, ['favicon_package_enabled' => FALSE]),
    'sharing disabled' => array_replace($head_settings, ['favicon_share_image_enabled' => FALSE]),
    'package missing' => array_replace($head_settings, ['favicon_package_path' => 'public://favicon-package/' . $theme . '/abcdefabcdef']),
    'package marker missing' => array_replace($head_settings, ['favicon_package_path' => $incomplete_path]),
    'PNG missing' => array_replace($head_settings, ['favicon_package_path' => $baseline['result']['path']]),
    'unmanaged path' => array_replace($head_settings, ['favicon_package_path' => $file_system->realpath($package_path)]),
  ] as $label => $configured) {
    $guarded = ['#cache' => $original_cache];
    $head->apply($guarded, $configured);
    emulsify_share_assert(emulsify_share_tags($guarded) === [], "$label must not emit fallback sharing metadata.");
    emulsify_share_assert($guarded['#cache'] === $original_cache, "$label must preserve existing cache metadata.");
  }

  $artifact_directory = getenv('EMULSIFY_FAVICON_SHARE_ARTIFACT_DIR');
  if (is_string($artifact_directory) && $artifact_directory !== '') {
    emulsify_share_assert(str_starts_with($artifact_directory, '/tmp/') || str_starts_with($artifact_directory, '/private/tmp/'), 'Share artifacts must be exported to a temporary directory.');
    emulsify_share_assert(is_dir($artifact_directory) || mkdir($artifact_directory, 0777, TRUE), 'Unable to create the share artifact directory.');
    emulsify_share_assert(file_put_contents($artifact_directory . '/share-image.png', $packages['enabled']['files']['share-image.png']) !== FALSE, 'Unable to export the share image.');
  }
  fwrite(STDOUT, "PASS Favicon share image: disabled byte identity, 1200x630 geometry/background/padding, primary artwork, hashes, eight absolute-URL tags, alt fallback, and Metatag/package guards.\n");
}
finally {
  $file_system->deleteRecursive('public://favicon-package/' . $theme);
}
