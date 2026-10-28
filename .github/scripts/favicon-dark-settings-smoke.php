<?php

/**
 * @file
 * Exercises dark favicon settings, managed uploads, previews, and Tools state.
 */

declare(strict_types=1);

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\emulsify\Favicon\FaviconPreviewBuilder;
use Drupal\emulsify\Favicon\FaviconSettings;
use Drupal\emulsify\Favicon\FaviconSettingsForm;
use Drupal\emulsify\Favicon\FaviconThemeManager;
use Drupal\file\Entity\File;

/**
 * Fails a contract with a useful message.
 */
function emulsify_dark_settings_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/**
 * Returns the saved light and dark browser preview URLs.
 */
function emulsify_dark_settings_preview_urls(FaviconPreviewBuilder $preview, array $settings): array {
  $document = new DOMDocument();
  $document->loadHTML((string) $preview->buildBrowserPreview($settings)['#markup'], LIBXML_NOERROR | LIBXML_NOWARNING);
  $images = (new DOMXPath($document))->query('//img');
  emulsify_dark_settings_assert($images->length === 2, 'Browser preview should contain one light and one dark tab.');
  return [$images->item(0)->getAttribute('src'), $images->item(1)->getAttribute('src')];
}

$mode = getenv('EMULSIFY_FAVICON_DARK_SETTINGS_MODE') ?: 'test';
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
$light_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="24" fill="#123456"/></svg>';
$dark_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect x="8" y="8" width="48" height="48" fill="#fedcba"/></svg>';
$dark_defaults = [
  'favicon_dark_mode' => 'none',
  'favicon_dark_background_color' => '',
  'favicon_dark_source_fid' => [],
  'favicon_dark_source_svg' => '',
  'favicon_dark_source_filename' => '',
];
$base = FaviconSettings::normalize([
  'favicon_package_enabled' => TRUE,
  'favicon_source_svg' => $light_svg,
  'favicon_source_filename' => 'light-settings-smoke.svg',
], $site_name);

if ($mode === 'prepare-tools') {
  $manager->writeThemeSettings($theme_name, array_replace($base, [
    'favicon_dark_mode' => 'alternate',
    'favicon_dark_source_svg' => $dark_svg,
    'favicon_dark_source_filename' => 'dark-tools-smoke.svg',
    'favicon_dark_background_color' => '#112233',
  ]));
  fwrite(STDOUT, "Prepared portable alternate dark favicon config for published Tools.\n");
  return;
}

if ($mode === 'assert-tools') {
  $settings = $manager->loadThemeSettings($theme_name);
  $path = $settings['favicon_package_path'];
  emulsify_dark_settings_assert($settings['favicon_dark_mode'] === 'alternate' && $settings['favicon_dark_source_svg'] !== '', 'Published Tools must persist portable alternate dark settings.');
  emulsify_dark_settings_assert(is_file($path . '/favicon-dark.svg'), 'Published Tools must generate favicon-dark.svg.');
  emulsify_dark_settings_assert($manager->buildPackageStatus($theme_name, $settings)['state'] === 'current', 'Published Tools must save a current dark package hash/path.');
  $metadata = json_decode(file_get_contents($path . '/metadata.json'), TRUE, 512, JSON_THROW_ON_ERROR);
  emulsify_dark_settings_assert(in_array('favicon-dark.svg', $metadata['files'] ?? [], TRUE), 'Published Tools metadata must list the generated dark SVG.');
  emulsify_dark_settings_assert(emulsify_dark_settings_preview_urls($preview, $settings)[1] === $url_generator->generateString($path . '/favicon-dark.svg'), 'Published Tools package should supply the saved dark preview.');
  $defaults = $manager->resetThemeSettings($theme_name);
  foreach ($dark_defaults as $key => $value) {
    emulsify_dark_settings_assert($defaults[$key] === $value && \Drupal::config($theme_name . '.settings')->get($key) === $value, "Reset must restore {$key}.");
  }
  emulsify_dark_settings_assert(!is_dir($path), 'Reset must remove the generated dark package.');
  fwrite(STDOUT, "PASS Published Tools alternate dark generation/status persistence and reset.\n");
  return;
}

emulsify_dark_settings_assert($mode === 'test', 'Unknown dark settings smoke mode.');
$config = $container->get('config.factory')->getEditable($theme_name . '.settings');
$original = $config->getRawData();
$file = NULL;
$package_path = '';
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
  // Config imported from an older child has no dark keys or schema mappings.
  $legacy = FaviconSettings::normalize(['favicon_source_svg' => $light_svg], $site_name);
  foreach ($dark_defaults as $key => $value) {
    emulsify_dark_settings_assert($legacy[$key] === $value, "Old child settings must receive the disabled/empty {$key} default.");
  }
  $normalized = FaviconSettings::normalize([
    'favicon_dark_mode' => 'invalid',
    'favicon_dark_background_color' => ' #AbC ',
  ]);
  emulsify_dark_settings_assert($normalized['favicon_dark_mode'] === 'none' && $normalized['favicon_dark_background_color'] === '#aabbcc', 'Dark settings should normalize mode and optional colors without inventing white overrides.');

  [$state, $form] = $validate(array_replace($base, [
    'favicon_dark_mode' => 'invert',
    'favicon_dark_background_enabled' => FALSE,
    'favicon_dark_background_color' => '#123456',
  ]));
  emulsify_dark_settings_assert($state->getErrors() === [], 'Invert mode should not require an alternate upload.');
  emulsify_dark_settings_assert(!isset($form['emulsify_favicon']['browser']['dark_mode']['favicon_dark_theme_color_enabled']), 'The retired browser theme-color option must not appear.');
  emulsify_dark_settings_assert($state->getValue('favicon_dark_background_color') === '', 'Unchecked optional colors must clear their stored overrides.');
  $state->cleanValues();
  emulsify_dark_settings_assert(!array_key_exists('favicon_dark_background_enabled', $state->getValues()), 'Optional-color helper checkboxes must never be exported as theme settings.');

  foreach (['missing' => '', 'invalid' => '<svg>not valid'] as $label => $source) {
    [$state] = $validate(array_replace($base, ['favicon_dark_mode' => 'alternate', 'favicon_dark_source_svg' => $source]));
    emulsify_dark_settings_assert(isset($state->getErrors()['favicon_dark_source_fid']), "Alternate mode must report a {$label} dark source on its own upload field.");
  }
  [$state] = $validate(array_replace($base, ['favicon_package_enabled' => FALSE, 'favicon_dark_mode' => 'alternate']));
  emulsify_dark_settings_assert(isset($state->getErrors()['favicon_dark_source_fid']), 'Alternate mode must require its source even when package attachment is disabled.');

  $source_dir = 'public://favicon-source/' . $theme_name . '/dark';
  $file_system->prepareDirectory($source_dir, FileSystemInterface::CREATE_DIRECTORY);
  $uri = $source_dir . '/settings-smoke-' . bin2hex(random_bytes(4)) . '.svg';
  $uploaded_svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:editor="http://www.inkscape.org/namespaces/inkscape" editor:version="1" viewBox="0 0 64 64"><metadata>Editor data</metadata><rect width="64" height="64" fill="#fedcba"/></svg>';
  file_put_contents($uri, $uploaded_svg);
  $file = File::create(['uri' => $uri, 'filename' => basename($uri), 'filemime' => 'image/svg+xml', 'filesize' => strlen($uploaded_svg)]);
  $file->save();
  [$inactive_state, $inactive_form] = $validate(array_replace($base, [
    'favicon_package_enabled' => FALSE,
    'favicon_dark_mode' => 'none',
    'favicon_dark_source_fid' => [(int) $file->id()],
  ]));
  emulsify_dark_settings_assert($inactive_state->getErrors() === [], 'Retained dark uploads should validate while dark mode is off.');
  FaviconSettingsForm::submitFaviconSettings($inactive_form, $inactive_state);
  $file = File::load($file->id());
  emulsify_dark_settings_assert($file->isPermanent() && file_get_contents($uri) === $inactive_state->getValue('favicon_dark_source_svg') && !str_contains(file_get_contents($uri), 'metadata'), 'Retained inactive dark uploads must also be sanitized, rewritten, and made permanent.');
  file_put_contents($uri, $uploaded_svg);
  $file->setTemporary();
  $file->save();

  [$state, $form] = $validate(array_replace($base, [
    'favicon_dark_mode' => 'alternate',
    'favicon_dark_source_fid' => [(int) $file->id()],
    'favicon_dark_background_enabled' => TRUE,
    'favicon_dark_background_color' => '#112233',
  ]));
  emulsify_dark_settings_assert($state->getErrors() === [], 'A valid managed alternate source should validate.');
  $sanitized = $state->getValue('favicon_dark_source_svg');
  emulsify_dark_settings_assert(is_string($sanitized) && str_contains($sanitized, '<rect') && !str_contains($sanitized, 'metadata') && !str_contains($sanitized, 'editor:'), 'Alternate source validation must retain artwork while removing editor metadata.');
  $state->setTriggeringElement(['#name' => 'regenerate_package']);
  FaviconSettingsForm::submitFaviconSettings($form, $state);
  $package_path = (string) $state->getValue('favicon_package_path');
  $file = File::load($file->id());
  emulsify_dark_settings_assert($file->isPermanent() && file_get_contents($uri) === $sanitized, 'Managed alternate upload must be rewritten to the sanitized portable source and made permanent.');
  emulsify_dark_settings_assert($state->getValue('favicon_dark_source_filename') === basename($uri), 'Sanitized alternate filename should be retained for config export.');
  emulsify_dark_settings_assert($package_path !== '' && is_file($package_path . '/favicon-dark.svg'), 'The real form submit must generate the alternate dark package.');

  $settings = FaviconSettings::normalize($state->getValues(), $site_name);
  $manager->writeThemeSettings($theme_name, $settings);
  emulsify_dark_settings_assert($manager->loadThemeSettings($theme_name)['favicon_dark_source_svg'] === $sanitized, 'Theme settings must retain the sanitized portable alternate source.');
  $portable = $manager->resolveDarkSourceContext(array_replace($settings, ['favicon_dark_source_fid' => [PHP_INT_MAX]]), FALSE);
  emulsify_dark_settings_assert($portable['source_file'] === NULL && $portable['source_svg'] === $sanitized, 'A missing managed alternate upload must fall back to portable config.');
  emulsify_dark_settings_assert($manager->buildPackageStatus($theme_name, $settings)['state'] === 'current', 'A form-generated alternate package should be current.');

  $changed_inputs = array_replace($settings, ['favicon_dark_mode' => 'none', 'favicon_dark_source_svg' => $dark_svg]);
  $light_url = $url_generator->generateString($package_path . '/favicon.svg');
  $dark_url = $url_generator->generateString($package_path . '/favicon-dark.svg');
  emulsify_dark_settings_assert(emulsify_dark_settings_preview_urls($preview, $changed_inputs) === [$light_url, $dark_url], 'Saved light/dark previews must use their generated files even when current inputs differ.');
  rename($package_path . '/favicon-dark.svg', $package_path . '/favicon-dark.svg.missing');
  try {
    emulsify_dark_settings_assert(emulsify_dark_settings_preview_urls($preview, $settings) === [$light_url, $light_url], 'Missing saved dark SVG must fall back to the saved light SVG.');
  }
  finally {
    rename($package_path . '/favicon-dark.svg.missing', $package_path . '/favicon-dark.svg');
  }

  $reset = $manager->resetThemeSettings($theme_name);
  foreach ($dark_defaults as $key => $value) {
    emulsify_dark_settings_assert($reset[$key] === $value, "Reset must clear {$key}.");
  }
  emulsify_dark_settings_assert(!is_dir($package_path), 'Reset should remove the current alternate package directory.');
  fwrite(STDOUT, "PASS Dark favicon legacy defaults, optional colors, alternate validation/upload sanitization, portable fallback, saved previews, and reset.\n");
}
finally {
  $config->setData($original)->save();
  if ($file instanceof File) {
    $file->delete();
  }
  if ($package_path !== '' && $package_path !== ($original['favicon_package_path'] ?? '') && is_dir($package_path)) {
    $file_system->deleteRecursive($package_path);
  }
}
