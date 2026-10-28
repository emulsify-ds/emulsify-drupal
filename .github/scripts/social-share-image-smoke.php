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

try {
  $resolved = $resolver->resolve($settings);
  emulsify_social_image_assert($resolved['info'] === ['width' => 1200, 'height' => 630, 'mime' => 'image/png'], 'Dimensions and MIME must come from the actual image.');
  $relative = substr($fs->realpath($uri), strlen(DRUPAL_ROOT) + 1);
  foreach ([$uri, $relative, '/' . $relative, basename($directory) . '/share.png'] as $path) {
    emulsify_social_image_assert($resolver->resolve(array_replace($settings, ['social_share_image_path' => $path]))['info'] === $resolved['info'], 'Public URIs, web-root paths, and public-relative paths must resolve.');
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
  $head->apply($managed, array_replace($settings, ['social_share_image_fid' => [$file->id()], 'social_share_image_alt' => '"><img src=x onerror=alert(1)>']), 'emulsify', 'Fixture site');
  emulsify_social_image_assert(in_array('file:' . $file->id(), $managed['#cache']['tags'], TRUE), 'Managed image metadata must depend on its file entity and download access.');
  $element = $managed['#attached']['html_head'][4][0] + ['#type' => 'html_tag'];
  $rendered = (string) \Drupal::service('renderer')->renderInIsolation($element);
  emulsify_social_image_assert(!str_contains($rendered, '<img') && str_contains($rendered, '&lt;img'), 'Drupal must escape alternative text inside metadata attributes.');
  $disabled = [];
  $head->apply($disabled, SocialShareSettings::DEFAULTS, 'emulsify', 'Fixture site');
  emulsify_social_image_assert($disabled === [], 'Disabled social images must produce no metadata.');
  fwrite(STDOUT, "PASS Independent social images: public/local paths, actual dimensions/MIME, raster validation, absolute fallback metadata, Metatag precedence, file/access/cache dependencies, and escaped alt text.\n");
}
finally {
  if ($file) {
    $file->delete();
  }
  $fs->deleteRecursive($directory);
}
