<?php

/**
 * @file
 * Compares saved favicon previews with real Drupal head and manifest output.
 *
 * Run with vendor/bin/drush php:script /path/to/favicon-preview-smoke.php in a
 * disposable installed Drupal fixture. No site configuration is changed.
 */

declare(strict_types=1);

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\emulsify\Favicon\FaviconHeadBuilder;
use Drupal\emulsify\Favicon\FaviconPreviewBuilder;
use Drupal\emulsify\Favicon\FaviconSettings;
use Drupal\emulsify\Favicon\FaviconSettingsForm;

/**
 * Fails the smoke check with a useful assertion message.
 */
function emulsify_preview_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/**
 * Parses a preview inside a containing element to detect wrapper leakage.
 */
function emulsify_preview_document(array $preview): DOMXPath {
  $markup = (string) ($preview['#markup'] ?? '');
  emulsify_preview_assert(substr_count($markup, '<div') === substr_count($markup, '</div>'), 'Preview div wrappers must balance.');
  $document = new DOMDocument();
  $document->loadHTML('<html><body><section id="fixture">' . $markup . '</section><aside id="sibling"></aside></body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
  $xpath = new DOMXPath($document);
  emulsify_preview_assert($xpath->query('/html/body/aside[@id="sibling"]')->length === 1, 'Preview markup must not swallow the next form element.');
  foreach ($xpath->query('//*[@data-preview-canvas]') as $canvas) {
    emulsify_preview_assert($canvas->getAttribute('style') === '--preview-background:transparent; --preview-padding:0%', 'Generated assets must not receive background or padding twice.');
  }
  return $xpath;
}

$container = \Drupal::getContainer();
$file_system = $container->get('file_system');
$url_generator = $container->get('file_url_generator');
$head = new FaviconHeadBuilder($url_generator);
$preview = new FaviconPreviewBuilder($url_generator);
$theme_name = 'emulsify_preview_' . bin2hex(random_bytes(4));
$package_path = 'public://favicon-package/' . $theme_name . '/0123456789ab';
$file_system->prepareDirectory($package_path, FileSystemInterface::CREATE_DIRECTORY);
$manifest = [
  'name' => 'Saved application',
  'short_name' => 'Saved & installed',
  'icons' => [
    ['src' => $url_generator->generateString($package_path . '/web-app-manifest-192x192.png'), 'purpose' => 'any'],
    ['src' => $url_generator->generateString($package_path . '/web-app-manifest-512x512-maskable.png'), 'purpose' => 'maskable'],
  ],
];
file_put_contents($package_path . '/metadata.json', '{}');
file_put_contents($package_path . '/site.webmanifest', json_encode($manifest, JSON_THROW_ON_ERROR));
$source_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M0 0h16v16H0z"/></svg>';

try {
  $defaults = FaviconSettings::DEFAULTS;
  $full = FaviconSettings::normalize([
    'favicon_package_enabled' => TRUE,
    'favicon_package_path' => $package_path,
    'favicon_source_svg' => $source_svg,
    'favicon_ios_icon_name' => 'Saved <iOS>',
    'favicon_manifest_short_name' => 'New setting does not rewrite an old manifest',
    'favicon_ios_padding' => 9,
    'favicon_android_padding' => 12,
    'favicon_background_color' => '#123456',
    'favicon_ios_background_color' => '#654321',
    'favicon_android_background_color' => '#abcdef',
  ], 'Fixture site');
  $partial = ['favicon_package_enabled' => TRUE, 'favicon_package_path' => $package_path];
  foreach (['defaults' => $defaults, 'fully configured' => $full, 'partially configured' => $partial] as $label => $settings) {
    $attachments = [];
    if (!empty($settings['favicon_package_enabled'])) {
      $head->apply($attachments, $settings);
    }
    $browser = $preview->buildBrowserPreview($settings);
    $ios = $preview->buildIosPreview($settings);
    $android = $preview->buildAndroidPreview($settings);
    if ($label === 'defaults') {
      emulsify_preview_assert($attachments === [] && $browser === [] && $ios === [] && $android === [], 'Defaults must emit neither generated head tags nor generated previews.');
      continue;
    }
    $links = array_column(array_column($attachments['#attached']['html_head_link'], 0), 'href', 'rel');
    $browser_dom = emulsify_preview_document($browser);
    foreach ($browser_dom->query('//img') as $image) {
      emulsify_preview_assert($image->getAttribute('src') === $links['icon'], "$label: browser preview must use the emitted SVG head link, never a source SVG.");
    }
    $ios_dom = emulsify_preview_document($ios);
    emulsify_preview_assert($ios_dom->query('//img')->item(0)->getAttribute('src') === $links['apple-touch-icon'], "$label: iOS preview must use the emitted Apple touch head link.");
    emulsify_preview_assert($ios_dom->query('//*[@data-preview-label="ios"]')->item(0)->textContent === trim((string) ($settings['favicon_ios_icon_name'] ?? '')), "$label: iOS preview must use the exact saved head title.");
    $android_dom = emulsify_preview_document($android);
    $images = $android_dom->query('//img');
    emulsify_preview_assert($links['manifest'] === $url_generator->generateString($package_path . '/site.webmanifest'), 'Android preview must refer to the saved manifest package.');
    foreach ($manifest['icons'] as $index => $icon) {
      emulsify_preview_assert($images->item($index)->getAttribute('src') === $icon['src'], "$label: Android and maskable previews must match their distinct manifest icons.");
    }
    emulsify_preview_assert($android_dom->query('//*[@data-preview-label="android"]')->item(0)->textContent === $manifest['short_name'], 'Android label must come from the saved manifest, including when saved config has newer inputs.');
  }

  // Readable manifests outside the managed URI contract must not supply labels.
  foreach ([
    'absolute path' => $file_system->realpath($package_path),
    'parent traversal' => 'public://favicon-package/' . $theme_name . '/../' . $theme_name . '/0123456789ab',
  ] as $label => $unmanaged_path) {
    emulsify_preview_assert(is_readable($unmanaged_path . '/site.webmanifest'), "$label: fixture manifest must be readable to exercise path validation.");
    $android_dom = emulsify_preview_document($preview->buildAndroidPreview([
      'favicon_package_enabled' => TRUE,
      'favicon_package_path' => $unmanaged_path,
    ]));
    emulsify_preview_assert($android_dom->query('//*[@data-preview-label="android"]')->item(0)->textContent === '', "$label: unmanaged manifests must not supply preview labels.");
  }

  // The real form must apply the same saved-package gate as FaviconHooks. A
  // valid portable source computes a different, missing candidate directory.
  foreach ([
    'saved package with changed source' => [$full, TRUE],
    'disabled package' => [array_replace($full, ['favicon_package_enabled' => FALSE]), FALSE],
    'source only' => [array_replace($full, ['favicon_package_path' => '']), FALSE],
    'missing package' => [array_replace($full, ['favicon_package_path' => str_replace('0123456789ab', 'abcdefabcdef', $package_path)]), FALSE],
    'unmanaged path' => [array_replace($full, ['favicon_package_path' => 'public://unmanaged']), FALSE],
  ] as $label => [$settings, $expected_preview]) {
    $provider = new class($settings) extends ThemeSettingsProvider {
      public function __construct(private readonly array $settings) {}

      public function getSetting(string $setting_name, ?string $theme = NULL): mixed {
        return $this->settings[$setting_name] ?? NULL;
      }
    };
    $form_helper = new FaviconSettingsForm(
      $provider,
      $container->get('config.factory'),
      $file_system,
      $container->get('cache_tags.invalidator'),
      $container->get('messenger'),
      $container->get('logger.factory'),
      $url_generator,
      $container->get('datetime.time'),
      $container->get('lock'),
    );
    $state = (new FormState())->setBuildInfo(['args' => [$theme_name]]);
    $form = [];
    $form_helper->alter($form, $state);
    foreach (['browser', 'ios', 'android'] as $platform) {
      $output = $form['emulsify_favicon'][$platform]['preview'] ?? [];
      emulsify_preview_assert(($output !== []) === $expected_preview, "$label: $platform form preview gate must match runtime.");
      if ($expected_preview) {
        emulsify_preview_assert(str_contains((string) $output['#markup'], '0123456789ab'), 'Preview must retain saved package path when candidate path differs.');
      }
    }
    emulsify_preview_assert(isset($form['emulsify_favicon']['source']['portable_source_notice'], $form['emulsify_favicon']['package_status_notice']), 'Existing package and portable-source diagnostics must remain available.');
  }
  fwrite(STDOUT, "PASS Favicon preview/head/manifest contracts: defaults, full, partial, saved-source changes, missing/disabled/unmanaged packages, balanced wrappers, and diagnostics on PHP " . PHP_VERSION . ".\n");
}
finally {
  $file_system->deleteRecursive('public://favicon-package/' . $theme_name);
}
