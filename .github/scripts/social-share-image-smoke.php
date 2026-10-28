<?php

/**
 * @file
 * Verifies independent local social images and fallback metadata in Drupal.
 */

declare(strict_types=1);

use Drupal\Core\File\FileSystemInterface;
use Drupal\emulsify\Social\SocialShareHeadBuilder;
use Drupal\emulsify\Social\SocialShareImageResolver;
use Drupal\emulsify\Social\SocialShareSettings;
use Drupal\file\Entity\File;

/**
 * Fails a contract with an actionable message.
 */
function emulsify_social_image_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$fs = \Drupal::service('file_system');
$directory = 'public://social-share-smoke-' . bin2hex(random_bytes(4));
$fs->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
$uri = $directory . '/share.png';
$image = imagecreatetruecolor(1200, 630);
imagepng($image, $uri);
$resolver = new SocialShareImageResolver($fs);
$head = new SocialShareHeadBuilder(\Drupal::service('file_url_generator'), $resolver);
$settings = SocialShareSettings::normalize(['social_share_image_enabled' => TRUE, 'social_share_image_path' => $uri]);
$file = NULL;
$processed = [];

try {
  $resolved = $resolver->resolve($settings);
  emulsify_social_image_assert($resolved['info'] === ['width' => 1200, 'height' => 630, 'mime' => 'image/png'], 'Dimensions and MIME must come from the actual image.');
  $relative = substr($fs->realpath($uri), strlen(DRUPAL_ROOT) + 1);
  foreach ([$uri, $relative, '/' . $relative, basename($directory) . '/share.png'] as $path) {
    emulsify_social_image_assert($resolver->resolve(array_replace($settings, ['social_share_image_path' => $path]))['info'] === $resolved['info'], 'Public URIs, web-root paths, and public-relative paths must resolve.');
  }
  emulsify_social_image_assert($resolver->display($resolved, TRUE) === $resolved, 'Images within the display bounds must remain unchanged without upscaling.');
  foreach (['landscape' => [3600, 1890, 1200, 630], 'portrait' => [3000, 4500, 1000, 1500]] as $label => [$width, $height, $expected_width, $expected_height]) {
    $source_uri = $directory . '/' . $label . '.png';
    $large = imagecreatetruecolor($width, $height);
    $channels = [random_int(1, 254), random_int(1, 254), random_int(1, 254)];
    imagefill($large, 0, 0, imagecolorallocate($large, ...$channels));
    imagepng($large, $source_uri);
    unset($large);
    $source_hash = hash_file('sha256', $source_uri);
    $large_settings = array_replace($settings, ['social_share_image_path' => $source_uri]);
    $source = $resolver->resolve($large_settings);
    emulsify_social_image_assert($resolver->display($source) === [], 'Page requests must not generate an absent derivative or fall back to the high-resolution source.');
    $missing = [];
    $head->apply($missing, $large_settings, 'emulsify', 'Fixture site');
    emulsify_social_image_assert(empty($missing['#attached']['html_head']), 'An unprocessed high-resolution source must never be advertised to social platforms.');
    $cache = \Drupal::cache('data');
    $cache_id = 'social-share-derivative-smoke:' . bin2hex(random_bytes(8));
    $cache->set($cache_id, 'Previously cached missing social metadata', \Drupal\Core\Cache\CacheBackendInterface::CACHE_PERMANENT, ['rendered']);
    $display = $resolver->display($source, TRUE);
    emulsify_social_image_assert($cache->get($cache_id) === FALSE, 'Creating a derivative must invalidate rendered pages that previously lacked sharing metadata.');
    $cache->delete($cache_id);
    $processed[] = $display['uri'];
    emulsify_social_image_assert($display['uri'] !== $source['uri'] && str_starts_with($display['uri'], 'public://social-share-image/processed/'), 'Large images must use a separate public derivative.');
    emulsify_social_image_assert($display['info'] === ['width' => $expected_width, 'height' => $expected_height, 'mime' => 'image/png'], 'Landscape and portrait derivatives must preserve aspect ratio within 1200 × 1500.');
    emulsify_social_image_assert(hash_file('sha256', $source_uri) === $source_hash, 'Resizing must preserve the original source bytes.');
    emulsify_social_image_assert($resolver->display($source) === $display && $resolver->display($source, TRUE) === $display, 'Prepared derivatives must be stable and usable without runtime generation.');
    $large_attachments = [];
    $head->apply($large_attachments, $large_settings, 'emulsify', 'Fixture site');
    $large_attributes = array_column(array_column($large_attachments['#attached']['html_head'], 0), '#attributes');
    emulsify_social_image_assert($large_attributes[0]['content'] === \Drupal::service('file_url_generator')->generateAbsoluteString($display['uri']) && $large_attributes[2]['content'] === (string) $expected_width && $large_attributes[3]['content'] === (string) $expected_height, 'Sharing metadata must use the processed URL and actual processed dimensions.');
    if ($label === 'landscape') {
      $large = imagecreatetruecolor($width, $height);
      imagefill($large, 0, 0, imagecolorallocate($large, 255 - $channels[0], $channels[1], $channels[2]));
      imagepng($large, $source_uri);
      unset($large);
      touch($source['realpath'], filemtime($source['realpath']) + 2);
      clearstatcache(TRUE, $source['realpath']);
      $changed = $resolver->display($resolver->resolve($large_settings), TRUE);
      $processed[] = $changed['uri'];
      emulsify_social_image_assert($changed['uri'] !== $display['uri'], 'Changed source content must receive a new derivative URL.');
    }
  }
  file_put_contents($directory . '/fake.jpg', 'This is not an image.');
  file_put_contents($directory . '/unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
  foreach (['https://example.com/share.png', 'private://share.png', 'data:image/png;base64,abc', 'phar://unsafe.png', '//example.com/a.png', 'public://../share.png', $directory . '/unsafe.svg', $directory . '/fake.jpg', $directory . '/missing.png'] as $path) {
    try {
      $resolver->resolve(array_replace($settings, ['social_share_image_path' => $path]));
      throw new RuntimeException('Unsafe, missing, and non-image paths must fail: ' . $path);
    }
    catch (InvalidArgumentException) {
    }
  }
  $original_cache = ['tags' => ['fixture:existing'], 'contexts' => ['languages:language_interface'], 'max-age' => 600];
  $attachments = ['#cache' => $original_cache];
  $head->apply($attachments, $settings, 'emulsify', 'Fixture site');
  $tags = $attachments['#attached']['html_head'];
  $attributes = array_column(array_column($tags, 0), '#attributes');
  emulsify_social_image_assert(count($tags) === 8 && count(array_unique(array_column($tags, 1))) === 8, 'An enabled social image must attach eight distinct fallback tags without any favicon settings.');
  emulsify_social_image_assert($attributes[1]['content'] === 'image/png' && $attributes[2]['content'] === '1200' && $attributes[3]['content'] === '630' && $attributes[4]['content'] === 'Fixture site', 'Fallback tags must use actual image metadata and site-name alt fallback.');
  $defaults = array_replace($settings, ['social_share_image_title' => 'Default title', 'social_share_image_description' => 'Default description', 'social_share_image_url' => 'https://example.test/fallback']);
  $text = [];
  $head->apply($text, $defaults, 'emulsify', 'Fixture site');
  $text_values = [];
  foreach ($text['#attached']['html_head'] as [$element]) {
    $meta = $element['#attributes'];
    $text_values[$meta['property'] ?? $meta['name']] = $meta['content'];
  }
  emulsify_social_image_assert(count($text_values) === 13 && $text_values['og:title'] === 'Default title' && $text_values['twitter:title'] === 'Default title' && $text_values['og:description'] === 'Default description' && $text_values['twitter:description'] === 'Default description' && $text_values['og:url'] === 'https://example.test/fallback', 'Configured defaults must emit the declared text and URL tags alongside the image metadata.');
  $page_metadata = ['#attached' => ['html_head' => []]];
  foreach (['og:image' => 'https://example.test/page.png', 'og:title' => 'Page title', 'og:description' => 'Page description', 'og:url' => 'https://example.test/page'] as $name => $value) {
    $page_metadata['#attached']['html_head'][] = [['#tag' => 'meta', '#attributes' => ['property' => $name, 'content' => $value]], 'fixture_' . $name];
  }
  $page_original = $page_metadata;
  $head->apply($page_metadata, $defaults, 'emulsify', 'Fixture site');
  emulsify_social_image_assert($page_metadata === $page_original, 'Page-specific Open Graph metadata must remain authoritative, including X Open Graph fallback without generic Twitter text masking it.');
  $per_field = ['#attached' => ['html_head' => [[['#tag' => 'meta', '#attributes' => ['name' => 'twitter:title', 'content' => 'Page X title']], 'fixture_x_title']]]];
  $head->apply($per_field, $defaults, 'emulsify', 'Fixture site');
  $per_field_values = [];
  foreach ($per_field['#attached']['html_head'] as [$element]) {
    $meta = $element['#attributes'];
    $per_field_values[$meta['property'] ?? $meta['name']] = $meta['content'];
  }
  emulsify_social_image_assert($per_field_values['twitter:title'] === 'Page X title' && $per_field_values['og:title'] === 'Default title' && $per_field_values['og:description'] === 'Default description', 'Each existing tag must win without suppressing unrelated missing defaults.');
  emulsify_social_image_assert(in_array(parse_url($attributes[0]['content'], PHP_URL_SCHEME), ['http', 'https'], TRUE) && parse_url($attributes[0]['content'], PHP_URL_HOST), 'Social image URLs must be absolute.');
  emulsify_social_image_assert(array_diff(['fixture:existing', 'config:emulsify.settings', 'config:system.site'], $attachments['#cache']['tags']) === [] && in_array('url.site', $attachments['#cache']['contexts'], TRUE) && $attachments['#cache']['max-age'] === 600, 'Fallbacks must preserve and extend cache metadata.');
  foreach (['name', 'property', 'PROPERTY'] as $attribute) {
    foreach (['OG:IMAGE', 'og:image:url', 'og:image:secure_url', 'TWITTER:IMAGE', 'twitter:image:src', 'TWITTER:CARD'] as $identity) {
      $existing = ['#attached' => ['html_head' => [[['#tag' => 'meta', '#attributes' => [$attribute => ' ' . $identity . ' ', 'content' => 'Metatag value']], 'fixture_metatag']]], '#cache' => $original_cache];
      $guarded = $existing;
      $head->apply($guarded, $settings, 'emulsify', 'Fixture site');
      emulsify_social_image_assert($guarded === $existing, 'Existing social image/card metadata must suppress fallbacks without modifying attachments.');
    }
  }
  $file = File::create(['uri' => $uri, 'status' => 1]);
  $file->save();
  $managed = [];
  $hostile = '"><img src=x onerror=alert(1)>';
  $head->apply($managed, array_replace($settings, ['social_share_image_fid' => [$file->id()], 'social_share_image_alt' => $hostile, 'social_share_image_title' => $hostile, 'social_share_image_description' => $hostile]), 'emulsify', 'Fixture site');
  emulsify_social_image_assert(in_array('file:' . $file->id(), $managed['#cache']['tags'], TRUE), 'Managed image metadata must depend on its file entity and download access.');
  $element = $managed['#attached']['html_head'][4][0] + ['#type' => 'html_tag'];
  $rendered = (string) \Drupal::service('renderer')->renderInIsolation($element);
  emulsify_social_image_assert(!str_contains($rendered, '<img') && str_contains($rendered, '&lt;img'), 'Drupal must escape alternative text inside metadata attributes.');
  foreach (array_slice($managed['#attached']['html_head'], 8) as [$element]) {
    $element['#type'] = 'html_tag';
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($element);
    emulsify_social_image_assert(!str_contains($rendered, '<img') && str_contains($rendered, '&lt;img'), 'Drupal must escape saved default titles and descriptions inside metadata attributes.');
  }
  $disabled = [];
  $head->apply($disabled, SocialShareSettings::DEFAULTS, 'emulsify', 'Fixture site');
  emulsify_social_image_assert($disabled === [], 'Disabled social images must produce no metadata.');
  fwrite(STDOUT, "PASS Independent social images: public/local paths, bounded landscape/portrait derivatives without cropping/upscaling or runtime generation, source preservation and new-content URLs, actual dimensions/MIME, fallback metadata, Metatag precedence, file/access/cache dependencies, and escaped alt text.\n");
}
finally {
  if ($file) {
    $file->delete();
  }
  foreach (array_unique($processed) as $processed_uri) {
    $fs->delete($processed_uri);
  }
  $fs->deleteRecursive($directory);
}
