<?php

/**
 * @file
 * Exercises social share image settings, saved previews, and Tools workflows.
 */

declare(strict_types=1);

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormState;
use Drupal\emulsify\Favicon\FaviconPreviewBuilder;
use Drupal\emulsify\Favicon\FaviconSettings;
use Drupal\emulsify\Favicon\FaviconSettingsForm;
use Drupal\emulsify\Favicon\FaviconThemeManager;
use Drupal\system\Form\ThemeSettingsForm;
use Symfony\Component\HttpFoundation\Request;

/**
 * Fails a contract with a useful message.
 */
function emulsify_share_settings_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/**
 * Parses saved social preview markup.
 */
function emulsify_share_settings_preview_document(array $preview): DOMDocument {
  $document = new DOMDocument();
  $document->loadHTML((string) ($preview['#markup'] ?? ''), LIBXML_NOERROR | LIBXML_NOWARNING);
  return $document;
}

$mode = getenv('EMULSIFY_FAVICON_SHARE_SETTINGS_MODE') ?: 'test';
$theme_name = getenv('EMULSIFY_FAVICON_THEME') ?: 'emulsify';
$container = \Drupal::getContainer();
$file_system = $container->get('file_system');
$url_generator = $container->get('file_url_generator');
$site_name = (string) \Drupal::config('system.site')->get('name');
$manager = new FaviconThemeManager(
  $container->get(ThemeSettingsProvider::class),
  $container->get('config.factory'),
  $file_system,
  $container->get('cache_tags.invalidator'),
  $url_generator,
  $container->get('datetime.time'),
  $container->get('lock'),
);
$preview = new FaviconPreviewBuilder($url_generator);
$share_defaults = [
  'favicon_share_image_enabled' => FALSE,
  'favicon_share_image_background_color' => '#ffffff',
  'favicon_share_image_padding' => 20,
  'favicon_share_image_alt' => '',
];
$base = FaviconSettings::normalize([
  'favicon_package_enabled' => TRUE,
  'favicon_source_svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="24" fill="#16324f"/></svg>',
  'favicon_source_filename' => 'share-settings-smoke.svg',
  'favicon_manifest_short_name' => 'Share smoke',
  'favicon_share_image_enabled' => TRUE,
  'favicon_share_image_background_color' => '#18324f',
  'favicon_share_image_padding' => 17,
  'favicon_share_image_alt' => 'Published Tools share image',
], $site_name);

if ($mode === 'prepare-tools') {
  $manager->writeThemeSettings($theme_name, $base);
  fwrite(STDOUT, "Prepared portable social share image config for published Tools.\n");
  return;
}

if ($mode === 'assert-tools') {
  $settings = $manager->loadThemeSettings($theme_name);
  $path = $settings['favicon_package_path'];
  foreach (array_keys($share_defaults) as $key) {
    emulsify_share_settings_assert($settings[$key] === $base[$key], "Published Tools must preserve {$key}.");
  }
  emulsify_share_settings_assert(is_file($path . '/share-image.png'), 'Published Tools must generate share-image.png.');
  $dimensions = getimagesize($path . '/share-image.png');
  emulsify_share_settings_assert($dimensions[0] === 1200 && $dimensions[1] === 630, 'Published Tools must generate a 1200x630 share image.');
  emulsify_share_settings_assert($manager->buildPackageStatus($theme_name, $settings)['state'] === 'current', 'Published Tools must persist the current social package hash/path.');
  $metadata = json_decode(file_get_contents($path . '/metadata.json'), TRUE, 512, JSON_THROW_ON_ERROR);
  emulsify_share_settings_assert(in_array('share-image.png', $metadata['files'] ?? [], TRUE), 'Published Tools metadata must list share-image.png.');
  $document = emulsify_share_settings_preview_document($preview->buildSharePreview($settings));
  emulsify_share_settings_assert($document->getElementsByTagName('img')->item(0)->getAttribute('src') === $url_generator->generateString($path . '/share-image.png'), 'Published Tools must supply the saved share preview PNG.');
  $manager->resetThemeSettings($theme_name);
  foreach ($share_defaults as $key => $value) {
    emulsify_share_settings_assert(\Drupal::config($theme_name . '.settings')->get($key) === $value, "Reset must restore {$key}.");
  }
  emulsify_share_settings_assert(!is_dir($path), 'Reset must remove the generated social package.');
  fwrite(STDOUT, "PASS Published Tools social generation/status persistence, dimensions, saved preview, and reset.\n");
  return;
}

emulsify_share_settings_assert($mode === 'test', 'Unknown share settings smoke mode.');
$config = $container->get('config.factory')->getEditable($theme_name . '.settings');
$original = $config->getRawData();
$site_config = $container->get('config.factory')->getEditable('system.site');
$package_paths = [];
$form_helper = FaviconSettingsForm::create($container);
$validate = static function (array $values) use ($theme_name, $form_helper): array {
  $state = (new FormState())->setBuildInfo(['args' => [$theme_name]])->setValues($values);
  $state->clearErrors();
  $form = [];
  $form_helper->alter($form, $state);
  FaviconSettingsForm::validateFaviconSettings($form, $state);
  return [$state, $form];
};

try {
  $legacy = FaviconSettings::normalize([], $site_name);
  emulsify_share_settings_assert($legacy['favicon_share_image_enabled'] === FALSE, 'Old child settings must keep social share generation disabled.');
  foreach ($share_defaults as $key => $value) {
    emulsify_share_settings_assert(FaviconSettings::DEFAULTS[$key] === $value, "Social setting {$key} must have its documented default.");
  }
  foreach ([-1 => 0, 90 => 40] as $input => $expected) {
    $normalized = FaviconSettings::normalize(['favicon_share_image_padding' => $input]);
    emulsify_share_settings_assert($normalized['favicon_share_image_padding'] === $expected, 'Social padding must normalize within 0–40 percent.');
  }
  $normalized = FaviconSettings::normalize(['favicon_share_image_background_color' => ' #AbC ']);
  emulsify_share_settings_assert($normalized['favicon_share_image_background_color'] === '#aabbcc', 'Social background colors should use existing color normalization.');

  $generation = $manager->generatePackage($theme_name, $base);
  $active = $generation['settings'];
  $path = $active['favicon_package_path'];
  $package_paths[] = $path;
  $manager->writeThemeSettings($theme_name, $active);
  [$state, $form] = $validate($active);
  $share_form = $form['emulsify_favicon']['share'];
  emulsify_share_settings_assert($share_form['favicon_share_image_enabled']['#type'] === 'checkbox', 'Social share generation must have its own opt-in checkbox.');
  emulsify_share_settings_assert($share_form['settings']['favicon_share_image_background_color']['#type'] === 'color', 'Social share background needs its own color control.');
  $padding = $share_form['settings']['favicon_share_image_padding'];
  emulsify_share_settings_assert($padding['#type'] === 'number' && $padding['#min'] === 0 && $padding['#max'] === 40, 'Social padding form bounds must be 0–40 percent.');
  emulsify_share_settings_assert($share_form['settings']['favicon_share_image_alt']['#maxlength'] === 200, 'Social alt text must be limited to 200 characters in the form.');

  // Image inputs regenerate the package; alt text updates metadata only.
  foreach ([
    'favicon_share_image_enabled' => FALSE,
    'favicon_share_image_background_color' => '#abcdef',
    'favicon_share_image_padding' => 9,
    'favicon_share_image_alt' => 'Changed social image description',
  ] as $key => $value) {
    $manager->writeThemeSettings($theme_name, $active);
    [$state, $form] = $validate(array_replace($active, [$key => $value]));
    emulsify_share_settings_assert($state->getErrors() === [], "Changing {$key} should validate.");
    FaviconSettingsForm::submitFaviconSettings($form, $state);
    $changed_path = (string) $state->getValue('favicon_package_path');
    $package_paths[] = $changed_path;
    $same_package = $state->getValue('favicon_package_hash') === $active['favicon_package_hash'];
    emulsify_share_settings_assert($changed_path !== '' && $same_package === ($key === 'favicon_share_image_alt'), "Changing {$key} must preserve the image-input/alt-text hash contract.");
    emulsify_share_settings_assert(is_file($changed_path . '/share-image.png') === ($key !== 'favicon_share_image_enabled'), "Changing {$key} must respect the social image opt-in.");
    $manager->writeThemeSettings($theme_name, FaviconSettings::normalize($state->getValues(), $site_name));
    emulsify_share_settings_assert($manager->loadThemeSettings($theme_name)[$key] === $value, "Saving {$key} must preserve its normalized value.");
    if ($key === 'favicon_share_image_enabled') {
      $cache = $container->get('cache.render');
      $cache_id = 'emulsify_share_settings:' . bin2hex(random_bytes(8));
      $cache->set($cache_id, 'Page cached while sharing was disabled', -1, ['rendered']);
      try {
        emulsify_share_settings_assert($cache->get($cache_id) !== FALSE, 'The disabled-page render cache probe must be stored before re-enabling sharing.');
        $re_enabled = FaviconSettings::normalize($state->getValues(), $site_name);
        $re_enabled['favicon_share_image_enabled'] = TRUE;
        $reused = $manager->generatePackage($theme_name, $re_enabled);
        emulsify_share_settings_assert($reused['result']['hash'] === $active['favicon_package_hash'], 'Re-enabling sharing must reuse the existing enabled package.');
        emulsify_share_settings_assert($cache->get($cache_id) === FALSE, 'Reusing an enabled package must invalidate pages cached while sharing was disabled.');
      }
      finally {
        $cache->delete($cache_id);
      }
    }
  }

  $manager->writeThemeSettings($theme_name, $active);
  // Exercise Drupal's real callback sequence and config save, without manually
  // persisting generated values after the form submits.
  $actual_state = (new FormState())->setValues(array_replace($active, [
    'favicon_share_image_background_color' => '#fedcba',
    'default_logo' => TRUE,
  ]));
  \Drupal::formBuilder()->submitForm(ThemeSettingsForm::class, $actual_state, $theme_name);
  emulsify_share_settings_assert($actual_state->getErrors() === [], 'The real theme settings form must accept the social image change.');
  $container->get('config.factory')->reset($theme_name . '.settings');
  $saved = \Drupal::config($theme_name . '.settings');
  $actual_path = (string) $saved->get('favicon_package_path');
  $package_paths[] = $actual_path;
  emulsify_share_settings_assert($saved->get('favicon_share_image_enabled') === TRUE && $saved->get('favicon_share_image_background_color') === '#fedcba', 'The real form must save the submitted social settings.');
  emulsify_share_settings_assert($saved->get('favicon_package_hash') !== $active['favicon_package_hash'] && $actual_path !== '' && is_file($actual_path . '/share-image.png'), 'The real form must generate and save the new package hash/path before core persists settings.');
  $manager->writeThemeSettings($theme_name, $active);
  $image_url = $url_generator->generateString($path . '/share-image.png');
  $document = emulsify_share_settings_preview_document($preview->buildSharePreview(array_replace($active, ['favicon_source_svg' => '', 'favicon_share_image_padding' => 0])));
  $image = $document->getElementsByTagName('img')->item(0);
  emulsify_share_settings_assert($image->getAttribute('src') === $image_url && $image->getAttribute('width') === '1200' && $image->getAttribute('height') === '630', 'Social preview must use only the saved 1200x630 PNG despite changed generation inputs.');
  foreach ([
    'package off' => ['favicon_package_enabled' => FALSE],
    'share off' => ['favicon_share_image_enabled' => FALSE],
    'no saved path' => ['favicon_package_path' => ''],
    'unmanaged path' => ['favicon_package_path' => $file_system->realpath($path)],
  ] as $label => $overrides) {
    emulsify_share_settings_assert($preview->buildSharePreview(array_replace($active, $overrides)) === [], "$label must suppress the social preview.");
  }
  rename($path . '/share-image.png', $path . '/share-image.png.missing');
  try {
    emulsify_share_settings_assert($preview->buildSharePreview($active) === [], 'A missing saved PNG must suppress the social preview.');
  }
  finally {
    rename($path . '/share-image.png.missing', $path . '/share-image.png');
  }

  $unsafe_site = 'Share <strong>Site</strong> & "name"';
  $unsafe_domain = 'share.example.test"><script>alert(1)</script>';
  $unsafe_alt = 'Share "alt" <img src=x onerror=alert(1)>';
  $site_config->set('name', $unsafe_site)->save();
  $request = new class($unsafe_domain) extends Request {
    public function __construct(private readonly string $host) {
      parent::__construct(server: ['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/']);
    }

    public function getHost(): string {
      return $this->host;
    }
  };
  $request_stack = $container->get('request_stack');
  $request_stack->push($request);
  try {
    $document = emulsify_share_settings_preview_document($preview->buildSharePreview(array_replace($active, ['favicon_share_image_alt' => $unsafe_alt])));
    $xpath = new DOMXPath($document);
    emulsify_share_settings_assert($xpath->query('//img')->length === 1 && $xpath->query('//script | //@onerror')->length === 0, 'Site/domain/alt text must not inject preview HTML.');
    emulsify_share_settings_assert($document->getElementsByTagName('img')->item(0)->getAttribute('alt') === $unsafe_alt && str_contains($document->textContent, $unsafe_site) && str_contains($document->textContent, $unsafe_domain), 'Escaped preview values must retain their exact human-readable content.');
    $fallback = emulsify_share_settings_preview_document($preview->buildSharePreview(array_replace($active, ['favicon_share_image_alt' => ''])));
    emulsify_share_settings_assert($fallback->getElementsByTagName('img')->item(0)->getAttribute('alt') === $unsafe_site, 'Blank social alt text must use the current saved site name.');
  }
  finally {
    $request_stack->pop();
  }
  fwrite(STDOUT, "PASS Social share legacy defaults, four form inputs, image regeneration and alt-only hash stability, reused-package render cache invalidation, real core form submission/config persistence, saved-PNG preview gates, escaped site/domain/alt, and site-name fallback.\n");
}
finally {
  $config->setData($original)->save();
  $site_config->set('name', $site_name)->save();
  foreach (array_unique($package_paths) as $package_path) {
    if ($package_path !== '' && $package_path !== ($original['favicon_package_path'] ?? '') && is_dir($package_path)) {
      $file_system->deleteRecursive($package_path);
    }
  }
}
