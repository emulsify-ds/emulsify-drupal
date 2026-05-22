<?php

/**
 * @file
 * Mode-driven favicon portability smoke helper.
 */

declare(strict_types=1);

use Drupal\emulsify\Favicon\FaviconPackageGenerator;
use Drupal\emulsify\Favicon\FaviconSettings;

/**
 * Mode-driven favicon portability smoke helper.
 *
 * The shell wrapper runs this script multiple times through Drush so each phase
 * starts with a fresh PHP process. That makes the test closer to real deploy
 * flows where config import, package regeneration, missing-file recovery, and
 * reset operations happen as separate commands.
 */
$mode = getenv('EMULSIFY_FAVICON_MODE') ?: 'assert-generated';
$theme_name = getenv('EMULSIFY_FAVICON_THEME');
$theme_name = is_string($theme_name) && $theme_name !== ''
  ? $theme_name
  : 'emulsify';
$site_name = (string) \Drupal::config('system.site')->get('name');
$svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
  <rect width="512" height="512" rx="96" fill="#0b5d1e"/>
  <path d="M256 112c79.5 0 144 64.5 144 144s-64.5 144-144 144-144-64.5-144-144 64.5-144 144-144Zm0 78c-36.5 0-66 29.5-66 66s29.5 66 66 66 66-29.5 66-66-29.5-66-66-66Z" fill="#ffffff"/>
</svg>
SVG;

if (!class_exists('Imagick')) {
  fwrite(STDERR, "Imagick is required for favicon portability smoke tests.\n");
  exit(1);
}

if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
  fwrite(STDERR, "GD is required for favicon portability smoke tests.\n");
  exit(1);
}

/**
 * Creates the generator used by the smoke assertions.
 */
function emulsify_favicon_generator(): FaviconPackageGenerator {
  return new FaviconPackageGenerator(
    \Drupal::service('file_system'),
    \Drupal::service('file_url_generator'),
    \Drupal::service('config.factory'),
    \Drupal::service('cache_tags.invalidator'),
    \Drupal::service('datetime.time'),
    \Drupal::service('lock'),
  );
}

/**
 * Fails the smoke script with a clear message.
 */
function emulsify_favicon_fail(string $message): void {
  fwrite(STDERR, $message . PHP_EOL);
  exit(1);
}

/**
 * Asserts a condition and exits if it fails.
 */
function emulsify_favicon_assert(bool $condition, string $message): void {
  if (!$condition) {
    emulsify_favicon_fail($message);
  }
}

/**
 * Asserts that a callback throws an InvalidArgumentException.
 */
function emulsify_favicon_assert_invalid(callable $callback, string $expected_message): void {
  try {
    $callback();
  }
  catch (\InvalidArgumentException $exception) {
    emulsify_favicon_assert(
      str_contains($exception->getMessage(), $expected_message),
      sprintf('Expected exception containing "%s", got "%s".', $expected_message, $exception->getMessage()),
    );
    return;
  }

  emulsify_favicon_fail(sprintf('Expected InvalidArgumentException containing "%s".', $expected_message));
}

/**
 * Loads normalized theme settings.
 */
function emulsify_favicon_load_settings(string $theme_name, string $site_name): array {
  $stored = \Drupal::configFactory()->get($theme_name . '.settings')->getRawData();
  return FaviconSettings::normalize(is_array($stored) ? $stored : [], $site_name);
}

/**
 * Returns normalized default favicon settings for the current site.
 */
function emulsify_favicon_default_settings(string $site_name): array {
  return FaviconSettings::normalize(FaviconSettings::DEFAULTS, $site_name);
}

/**
 * Persists generated package metadata back to theme settings.
 */
function emulsify_favicon_save_generated_state(string $theme_name, array $settings): void {
  $config = \Drupal::configFactory()->getEditable($theme_name . '.settings');
  foreach (FaviconSettings::DEFAULTS as $key => $default) {
    $config->set($key, $settings[$key] ?? $default);
  }
  $config->save();
}

/**
 * Exercises the SVG sanitizer allow/strip/reject matrix.
 */
function emulsify_favicon_run_sanitizer_matrix(FaviconPackageGenerator $generator): void {
  $simple = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#000"/></svg>';
  $symbols = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><symbol id="mark"><circle cx="32" cy="32" r="16"/></symbol><use href="#mark"/></svg>';
  $gradients = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g"><stop offset="0%" stop-color="#000"/><stop offset="100%" stop-color="#fff"/></linearGradient></defs><rect width="64" height="64" fill="url(#g)"/></svg>';
  $raster = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><image href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z5x8AAAAASUVORK5CYII=" width="64" height="64"/></svg>';

  // Allowed SVG features used by real design-system icons.
  $analysis = $generator->validateSourceSvg($simple, FALSE);
  emulsify_favicon_assert(($analysis['sanitized_svg'] ?? '') !== '', 'Simple square SVG should be accepted.');

  $analysis = $generator->validateSourceSvg($symbols, FALSE);
  emulsify_favicon_assert(str_contains((string) $analysis['sanitized_svg'], 'href="#mark"'), 'Symbol/use references should be preserved.');

  $analysis = $generator->validateSourceSvg($gradients, FALSE);
  emulsify_favicon_assert(str_contains((string) $analysis['sanitized_svg'], 'linearGradient'), 'Inline gradients should be preserved.');

  $analysis = $generator->validateSourceSvg($raster, FALSE);
  emulsify_favicon_assert(
    !empty($analysis['has_embedded_raster_images']),
    'Base64 embedded raster images should be detected.',
  );

  // Dangerous markup should be stripped without rejecting usable SVGs.
  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><script>alert(1)</script><rect width="64" height="64"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains((string) $analysis['sanitized_svg'], '<script'), 'Script tags should be stripped from sanitized SVG output.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><foreignObject><div>bad</div></foreignObject><rect width="64" height="64"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains(strtolower((string) $analysis['sanitized_svg']), 'foreignobject'), 'foreignObject nodes should be stripped from sanitized SVG output.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><use href="https://example.com/icon.svg#mark"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains((string) $analysis['sanitized_svg'], 'https://example.com/icon.svg'), 'External href values should be stripped.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><use href="javascript:alert(1)"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains(strtolower((string) $analysis['sanitized_svg']), 'javascript:'), 'javascript: href values should be stripped.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><image href="https://example.com/icon.png" width="64" height="64"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains((string) $analysis['sanitized_svg'], 'https://example.com/icon.png'), 'Remote image href values should be stripped.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" onload="alert(1)"><rect width="64" height="64" onclick="alert(1)"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains(strtolower((string) $analysis['sanitized_svg']), 'onclick='), 'Inline event handlers should be stripped.');
  emulsify_favicon_assert(!str_contains(strtolower((string) $analysis['sanitized_svg']), 'onload='), 'Root event handlers should be stripped.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 32"><rect width="64" height="32"/></svg>', FALSE);
  emulsify_favicon_assert(
    ($analysis['view_box'] ?? []) === [0.0, -16.0, 64.0, 64.0],
    'Non-square SVG sources should be centered on a square viewBox.',
  );
  emulsify_favicon_assert(
    str_contains((string) $analysis['sanitized_svg'], 'width="64" height="64"'),
    'Non-square SVG sources should receive square root dimensions.',
  );

  // Hard rejects protect package generation from excessive input.
  $oversized_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><desc>' . str_repeat('x', FaviconPackageGenerator::MAX_FILE_SIZE) . '</desc></svg>';
  emulsify_favicon_assert_invalid(
    fn() => $generator->validateSourceSvg($oversized_svg, FALSE),
    'smaller than 5 MB',
  );
}

$generator = emulsify_favicon_generator();

switch ($mode) {
  case 'prepare':
    // Store every portable setting needed to regenerate the package from config
    // alone, then clear package metadata so generate must create fresh files.
    \Drupal::configFactory()
      ->getEditable($theme_name . '.settings')
      ->set('favicon_package_enabled', TRUE)
      ->set('favicon_source_fid', [])
      ->set('favicon_source_svg', $svg)
      ->set('favicon_source_filename', 'portable-release-check.svg')
      ->set('favicon_background_color', '#0b5d1e')
      ->set('favicon_theme_color', '#0b5d1e')
      ->set('favicon_ios_background_color', '#ffffff')
      ->set('favicon_ios_padding', 16)
      ->set('favicon_ios_icon_name', 'Emulsify')
      ->set('favicon_android_background_color', '#0b5d1e')
      ->set('favicon_android_padding', 20)
      ->set('favicon_android_maskable_enabled', TRUE)
      ->set('favicon_manifest_name', $site_name)
      ->set('favicon_manifest_short_name', 'Emulsify')
      ->set('favicon_manifest_display', 'standalone')
      ->set('favicon_package_hash', '')
      ->set('favicon_package_path', '')
      ->set('favicon_package_generated_at', 0)
      ->save();
    fwrite(STDOUT, "Prepared portable favicon config for {$theme_name}.\n");
    return;

  case 'delete-package':
    // Simulate a deploy/filesystem state where config references a package but
    // generated files are missing and need to be recreated.
    $settings = emulsify_favicon_load_settings($theme_name, $site_name);
    $definition = $generator->getPackageDefinition($theme_name, $settings, $svg);
    $realpath = \Drupal::service('file_system')->realpath($definition['path']);
    if ($realpath && is_dir($realpath)) {
      \Drupal::service('file_system')->deleteRecursive($realpath);
    }
    emulsify_favicon_assert(!$generator->packageExists($definition['path']), sprintf('Expected the favicon package at %s to be deleted.', $definition['path']));
    fwrite(STDOUT, "Deleted generated package at {$definition['path']}.\n");
    return;

  case 'generate':
    // Generate from the portable SVG stored in config instead of from an upload
    // entity, which is the critical behavior after config import.
    $settings = emulsify_favicon_load_settings($theme_name, $site_name);
    $source_svg = FaviconSettings::getPortableSourceSvg($settings);
    emulsify_favicon_assert($source_svg !== '', 'Expected a portable SVG source before generation.');

    $result = $generator->generateFromSvg(
      $theme_name,
      $source_svg,
      $settings,
      [
        'filename' => (string) ($settings['favicon_source_filename'] ?: 'portable-release-check.svg'),
      ],
    );
    $settings['favicon_package_hash'] = $result['hash'];
    $settings['favicon_package_path'] = $result['path'];
    $settings['favicon_package_generated_at'] = $result['generated_at'];
    emulsify_favicon_save_generated_state($theme_name, $settings);
    fwrite(STDOUT, "Generated portable favicon package at {$result['path']}.\n");
    return;

  case 'assert-generated':
    // Verify deterministic package metadata and the actual file payload. The
    // sanitizer matrix runs here so it is covered after each generation path.
    $settings = emulsify_favicon_load_settings($theme_name, $site_name);
    $definition = $generator->getPackageDefinition($theme_name, $settings, $svg);
    emulsify_favicon_assert($settings['favicon_package_hash'] === $definition['hash'], 'Theme config should store the deterministic favicon package hash.');
    emulsify_favicon_assert($settings['favicon_package_path'] === $definition['path'], 'Theme config should store the deterministic favicon package path.');
    emulsify_favicon_assert($generator->packageExists($definition['path']), sprintf('Expected generated favicon package at %s.', $definition['path']));

    $realpath = \Drupal::service('file_system')->realpath($definition['path']);
    emulsify_favicon_assert(is_string($realpath) && is_dir($realpath), 'Expected a real generated package directory.');

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
      emulsify_favicon_assert(is_file($realpath . DIRECTORY_SEPARATOR . $expected_file), sprintf('Missing generated favicon asset %s.', $expected_file));
    }

    $metadata = $generator->readPackageMetadata($definition['path']);
    emulsify_favicon_assert(is_array($metadata), 'Expected metadata.json to decode into an array.');
    emulsify_favicon_assert(($metadata['hash'] ?? '') === $definition['hash'], 'metadata.json should preserve the deterministic package hash.');
    emulsify_favicon_assert(($metadata['source']['filename'] ?? '') === 'portable-release-check.svg', 'metadata.json should preserve the source filename.');

    emulsify_favicon_run_sanitizer_matrix($generator);
    fwrite(STDOUT, "Verified portable favicon regeneration and sanitizer coverage for {$theme_name}.\n");
    return;

  case 'assert-reset':
    // Reset should return theme settings to schema defaults, not just delete
    // files from the public filesystem.
    $settings = emulsify_favicon_load_settings($theme_name, $site_name);
    foreach (emulsify_favicon_default_settings($site_name) as $key => $expected_value) {
      emulsify_favicon_assert($settings[$key] === $expected_value, sprintf('Expected %s to reset to its default value.', $key));
    }
    fwrite(STDOUT, "Verified favicon reset defaults for {$theme_name}.\n");
    return;

  case 'reset':
    // Remove both the stored package path and the deterministic package path
    // derived from the portable source so stale generated directories do not
    // survive a reset operation.
    $settings = emulsify_favicon_load_settings($theme_name, $site_name);
    $package_paths = array_filter([(string) ($settings['favicon_package_path'] ?? '')]);
    $source_svg = FaviconSettings::getPortableSourceSvg($settings);
    if ($source_svg !== '') {
      $definition = $generator->getPackageDefinition($theme_name, $settings, $source_svg);
      $package_paths[] = $definition['path'];
    }

    foreach (array_unique($package_paths) as $package_path) {
      $realpath = \Drupal::service('file_system')->realpath($package_path);
      if ($realpath && is_dir($realpath)) {
        \Drupal::service('file_system')->deleteRecursive($realpath);
      }
    }

    $config = \Drupal::configFactory()->getEditable($theme_name . '.settings');
    foreach (FaviconSettings::DEFAULTS as $key => $value) {
      $config->set($key, $value);
    }
    $config
      ->set('features.favicon', TRUE)
      ->set('favicon.use_default', TRUE)
      ->set('favicon.path', '')
      ->save();
    fwrite(STDOUT, "Reset favicon settings for {$theme_name}.\n");
    return;

  default:
    emulsify_favicon_fail(sprintf('Unsupported portability smoke mode %s.', $mode));
}
