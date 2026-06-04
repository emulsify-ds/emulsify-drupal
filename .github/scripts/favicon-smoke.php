<?php

/**
 * @file
 * Runtime favicon package attachment smoke helper.
 */

declare(strict_types=1);

use Drupal\emulsify\Favicon\FaviconPackageGenerator;
use Drupal\emulsify\Favicon\FaviconSettings;
use Drupal\emulsify\Hook\FaviconHooks;

/**
 * Smoke-tests favicon package attachment for a bootstrapped theme.
 *
 * Favicon-smoke.sh executes this file through `drush php:eval`, so Drupal
 * services, config, and theme hooks are available exactly as they are during a
 * request. The test proves page attachments do not generate missing files,
 * then pre-generates a package and verifies existing package head tags.
 */
$argv = $_SERVER['argv'] ?? [];
$theme_name = getenv('EMULSIFY_FAVICON_THEME');
$theme_name = is_string($theme_name) && $theme_name !== ''
  ? $theme_name
  : (string) ($argv[1] ?? 'emulsify');
$site_name = (string) \Drupal::config('system.site')->get('name');
$svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
  <rect width="512" height="512" rx="96" fill="#005b99"/>
  <circle cx="256" cy="256" r="120" fill="#ffffff"/>
  <path d="M256 150v212" stroke="#005b99" stroke-width="48" stroke-linecap="round"/>
  <path d="M150 256h212" stroke="#005b99" stroke-width="48" stroke-linecap="round"/>
</svg>
SVG;

/**
 * Fails the smoke script with a clear message.
 */
function emulsify_favicon_smoke_fail(string $message): void {
  fwrite(STDERR, $message . PHP_EOL);
  exit(1);
}

/**
 * Asserts a condition and exits if it fails.
 */
function emulsify_favicon_smoke_assert(bool $condition, string $message): void {
  if (!$condition) {
    emulsify_favicon_smoke_fail($message);
  }
}

/**
 * Loads normalized theme settings.
 */
function emulsify_favicon_smoke_load_settings(string $theme_name, string $site_name): array {
  $stored = \Drupal::configFactory()->get($theme_name . '.settings')->getRawData();
  return FaviconSettings::normalize(is_array($stored) ? $stored : [], $site_name);
}

// The generator can store SVG config without these extensions, but producing
// PNG/ICO files for the release smoke requires both rasterization stacks.
if (!class_exists('Imagick')) {
  emulsify_favicon_smoke_fail('Imagick is required for favicon smoke tests.');
}

if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
  emulsify_favicon_smoke_fail('GD is required for favicon smoke tests.');
}

// Reset package metadata before invoking the hook so this smoke proves page
// requests do not create missing package files.
$config = \Drupal::configFactory()->getEditable($theme_name . '.settings');
$config
  ->set('favicon_package_enabled', TRUE)
  ->set('favicon_source_fid', [])
  ->set('favicon_source_svg', $svg)
  ->set('favicon_source_filename', 'release-check.svg')
  ->set('favicon_background_color', '#005b99')
  ->set('favicon_theme_color', '#005b99')
  ->set('favicon_ios_background_color', '#ffffff')
  ->set('favicon_ios_padding', 16)
  ->set('favicon_ios_icon_name', 'Emulsify')
  ->set('favicon_android_background_color', '#005b99')
  ->set('favicon_android_padding', 20)
  ->set('favicon_android_maskable_enabled', TRUE)
  ->set('favicon_manifest_name', $site_name)
  ->set('favicon_manifest_short_name', 'Emulsify')
  ->set('favicon_manifest_display', 'standalone')
  ->set('favicon_package_hash', '')
  ->set('favicon_package_path', '')
  ->set('favicon_package_generated_at', 0)
  ->save();

$generator = new FaviconPackageGenerator(
  \Drupal::service('file_system'),
  \Drupal::service('file_url_generator'),
  \Drupal::service('config.factory'),
  \Drupal::service('cache_tags.invalidator'),
  \Drupal::service('datetime.time'),
  \Drupal::service('lock'),
);
$settings = emulsify_favicon_smoke_load_settings($theme_name, $site_name);
$definition = $generator->getPackageDefinition($theme_name, $settings, $svg);
$realpath = \Drupal::service('file_system')->realpath($definition['path']);
if ($realpath && is_dir($realpath)) {
  \Drupal::service('file_system')->deleteRecursive($realpath);
}

$theme_initialization = \Drupal::service('theme.initialization');

$theme_manager = \Drupal::service('theme.manager');
$theme_manager->setActiveTheme($theme_initialization->initTheme($theme_name));

// Call the hook handler directly with a minimal attachments array. That keeps
// the assertion focused on theme integration without requiring a full HTTP
// request for the favicon-specific smoke.
$attachments = [
  '#attached' => [
    'html_head' => [],
    'html_head_link' => [],
  ],
];

$hook_handler = \Drupal::service('class_resolver')->getInstanceFromDefinition(FaviconHooks::class);
$hook_handler->pageAttachmentsAlter($attachments);

emulsify_favicon_smoke_assert(!$generator->packageExists($definition['path']), 'Runtime page attachments must not generate missing favicon package files.');
emulsify_favicon_smoke_assert($attachments['#attached']['html_head_link'] === [], 'Runtime page attachments should skip missing favicon packages.');
emulsify_favicon_smoke_assert($attachments['#attached']['html_head'] === [], 'Runtime page attachments should not add favicon metadata for missing packages.');

$result = $generator->generateFromSvg(
  $theme_name,
  $svg,
  $settings,
  [
    'filename' => 'release-check.svg',
  ],
);
$config = \Drupal::configFactory()->getEditable($theme_name . '.settings');
$config
  ->set('favicon_package_hash', $result['hash'])
  ->set('favicon_package_path', $result['path'])
  ->set('favicon_package_generated_at', $result['generated_at'])
  ->save();

$realpath = \Drupal::service('file_system')->realpath($result['path']);

emulsify_favicon_smoke_assert((bool) $realpath && is_dir($realpath), "Expected generated favicon package at {$result['path']}.");

$expected_files = [
  'favicon.svg',
  'favicon.ico',
  'favicon-96x96.png',
  'apple-touch-icon.png',
  'web-app-manifest-192x192.png',
  'web-app-manifest-512x512.png',
  'web-app-manifest-512x512-maskable.png',
  'site.webmanifest',
  'metadata.json',
];
foreach ($expected_files as $expected_file) {
  emulsify_favicon_smoke_assert(is_file($realpath . DIRECTORY_SEPARATOR . $expected_file), "Missing generated favicon asset {$expected_file}.");
}

$manifest = file_get_contents($realpath . DIRECTORY_SEPARATOR . 'site.webmanifest');
emulsify_favicon_smoke_assert(is_string($manifest), 'Unable to read generated site.webmanifest.');
$manifest_data = json_decode($manifest, TRUE);
emulsify_favicon_smoke_assert(is_array($manifest_data), 'Generated site.webmanifest is not valid JSON.');
emulsify_favicon_smoke_assert(($manifest_data['display'] ?? '') === 'standalone', 'Generated site.webmanifest is missing the standalone display mode.');
emulsify_favicon_smoke_assert(($manifest_data['theme_color'] ?? '') === '#005b99', 'Generated site.webmanifest is missing the expected theme color.');

$attachments = [
  '#attached' => [
    'html_head' => [],
    'html_head_link' => [],
  ],
];
$hook_handler->pageAttachmentsAlter($attachments);

$rels = [];
foreach ($attachments['#attached']['html_head_link'] as $item) {
  $rel = $item[0]['rel'] ?? NULL;
  if (is_string($rel)) {
    $rels[] = $rel;
  }
}
foreach (['icon', 'apple-touch-icon', 'manifest'] as $required_rel) {
  emulsify_favicon_smoke_assert(in_array($required_rel, $rels, TRUE), "Missing {$required_rel} head attachment.");
}

$meta_names = [];
foreach ($attachments['#attached']['html_head'] as $item) {
  $name = $item[0]['#attributes']['name'] ?? NULL;
  if (is_string($name)) {
    $meta_names[] = $name;
  }
}
foreach (['theme-color', 'apple-mobile-web-app-title'] as $required_meta) {
  emulsify_favicon_smoke_assert(in_array($required_meta, $meta_names, TRUE), "Missing {$required_meta} meta tag.");
}

fwrite(STDOUT, "Verified favicon package attachment without runtime generation at {$realpath}.\n");
