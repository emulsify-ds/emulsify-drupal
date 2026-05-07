<?php

declare(strict_types=1);

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\emulsify\Favicon\FaviconPackageGenerator;
use Drupal\emulsify\Favicon\FaviconSettings;
use Drupal\emulsify\Hook\FaviconHooks;

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

if (!class_exists('Imagick')) {
  fwrite(STDERR, "Imagick is required for favicon smoke tests.\n");
  exit(1);
}

if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
  fwrite(STDERR, "GD is required for favicon smoke tests.\n");
  exit(1);
}

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

/** @var \Drupal\Core\Theme\ThemeInitializationInterface $theme_initialization */
$theme_initialization = \Drupal::service('theme.initialization');
/** @var \Drupal\Core\Theme\ThemeManagerInterface $theme_manager */
$theme_manager = \Drupal::service('theme.manager');
$theme_manager->setActiveTheme($theme_initialization->initTheme($theme_name));

$attachments = [
  '#attached' => [
    'html_head' => [],
    'html_head_link' => [],
  ],
];

/** @var \Drupal\emulsify\Hook\FaviconHooks $hook_handler */
$hook_handler = \Drupal::service('class_resolver')->getInstanceFromDefinition(FaviconHooks::class);
$hook_handler->pageAttachmentsAlter($attachments);

$settings = [];
foreach (FaviconSettings::DEFAULTS as $key => $default) {
  $settings[$key] = $config->get($key);
}
$settings = FaviconSettings::normalize($settings, $site_name);

$generator = new FaviconPackageGenerator(
  \Drupal::service('file_system'),
  \Drupal::service('file_url_generator'),
  \Drupal::service('config.factory'),
  \Drupal::service('cache_tags.invalidator'),
  \Drupal::service('datetime.time'),
);
$definition = $generator->getPackageDefinition($theme_name, $settings, $svg);
$realpath = \Drupal::service('file_system')->realpath($definition['path']);

if (!$realpath || !is_dir($realpath)) {
  fwrite(STDERR, "Expected generated favicon package at {$definition['path']}.\n");
  exit(1);
}

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
  if (!is_file($realpath . DIRECTORY_SEPARATOR . $expected_file)) {
    fwrite(STDERR, "Missing generated favicon asset {$expected_file}.\n");
    exit(1);
  }
}

$manifest = file_get_contents($realpath . DIRECTORY_SEPARATOR . 'site.webmanifest');
if (!is_string($manifest) || !str_contains($manifest, '"display": "standalone"') || !str_contains($manifest, '"theme_color": "#005b99"')) {
  fwrite(STDERR, "Generated site.webmanifest is missing expected values.\n");
  exit(1);
}

$rels = [];
foreach ($attachments['#attached']['html_head_link'] as $item) {
  $rel = $item[0]['rel'] ?? NULL;
  if (is_string($rel)) {
    $rels[] = $rel;
  }
}
foreach (['icon', 'apple-touch-icon', 'manifest'] as $required_rel) {
  if (!in_array($required_rel, $rels, TRUE)) {
    fwrite(STDERR, "Missing {$required_rel} head attachment.\n");
    exit(1);
  }
}

$meta_names = [];
foreach ($attachments['#attached']['html_head'] as $item) {
  $name = $item[0]['#attributes']['name'] ?? NULL;
  if (is_string($name)) {
    $meta_names[] = $name;
  }
}
foreach (['theme-color', 'apple-mobile-web-app-title'] as $required_meta) {
  if (!in_array($required_meta, $meta_names, TRUE)) {
    fwrite(STDERR, "Missing {$required_meta} meta tag.\n");
    exit(1);
  }
}

fwrite(STDOUT, "Verified favicon package generation and head attachments at {$realpath}.\n");
