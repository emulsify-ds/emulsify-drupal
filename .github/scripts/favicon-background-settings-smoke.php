<?php

/**
 * @file
 * Verifies inherited platform colors and explicit overrides through real saves.
 */

declare(strict_types=1);

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormState;
use Drupal\emulsify\Favicon\FaviconPackageGenerator;
use Drupal\emulsify\Favicon\FaviconSettings;
use Drupal\system\Form\ThemeSettingsForm;

/**
 * Fails a background inheritance contract with an actionable message.
 */
function emulsify_background_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/**
 * Saves the actual Drupal settings form without generating a package.
 */
function emulsify_background_save(array $values): array {
  // Core's programmatic checkbox API uses NULL for an unchecked input.
  foreach (['favicon_ios_background_custom', 'favicon_android_background_custom'] as $key) {
    if (($values[$key] ?? NULL) === FALSE) {
      $values[$key] = NULL;
    }
  }
  $state = (new FormState())->setValues($values + [
    'default_logo' => TRUE,
    'favicon_package_enabled' => FALSE,
    'social_share_image_enabled' => FALSE,
  ]);
  \Drupal::formBuilder()->submitForm(ThemeSettingsForm::class, $state, 'emulsify');
  emulsify_background_assert($state->getErrors() === [], 'Background values must save without form errors: ' . json_encode($state->getErrors()));
  return \Drupal::config('emulsify.settings')->getRawData();
}

$provider = \Drupal::service(ThemeSettingsProvider::class);
$site_name = (string) \Drupal::config('system.site')->get('name');
$config = \Drupal::configFactory()->getEditable('emulsify.settings');
$original = $config->getRawData();

try {
  $inherited = FaviconSettings::normalize(['favicon_background_color' => '#123456'], $site_name);
  foreach (['ios', 'android'] as $platform) {
    emulsify_background_assert($inherited['favicon_' . $platform . '_background_color'] === '#123456' && $inherited['favicon_' . $platform . '_background_custom'] === FALSE, 'Missing platform colors must inherit the browser color.');
  }
  $legacy = FaviconSettings::normalize([
    'favicon_background_color' => '#123456',
    'favicon_ios_background_color' => '#ffffff',
    'favicon_android_background_color' => '#005b99',
  ], $site_name);
  emulsify_background_assert($legacy['favicon_ios_background_color'] === '#123456' && $legacy['favicon_ios_background_custom'] === FALSE, 'Legacy white defaults must inherit.');
  emulsify_background_assert($legacy['favicon_android_background_color'] === '#005b99' && $legacy['favicon_android_background_custom'] === TRUE, 'Legacy non-white colors must remain customized.');

  $config->setData(array_replace($original, FaviconSettings::DEFAULTS, ['social_share_image_enabled' => FALSE]))->save();
  $values = [
    'favicon_background_color' => '#123456',
    'favicon_ios_background_custom' => FALSE,
    'favicon_ios_background_color' => '#ffffff',
    'favicon_android_background_custom' => FALSE,
    'favicon_android_background_color' => '#ffffff',
  ];
  $saved = emulsify_background_save($values);
  foreach (['ios', 'android'] as $platform) {
    emulsify_background_assert($saved['favicon_' . $platform . '_background_color'] === '#123456' && $saved['favicon_' . $platform . '_background_custom'] === FALSE, 'Saving must retain inheritance intent alongside effective colors.');
  }
  $values['favicon_background_color'] = '#abcdef';
  $saved = emulsify_background_save($values);
  $loaded = FaviconSettings::loadThemeSettings('emulsify', $provider, $site_name);
  emulsify_background_assert($loaded['favicon_ios_background_color'] === '#abcdef' && $loaded['favicon_android_background_color'] === '#abcdef' && $loaded['favicon_theme_color'] === '#abcdef', 'Saved and reloaded inherited colors and Android metadata must follow a later browser color change.');

  $values += ['favicon_package_enabled' => FALSE];
  $values['favicon_ios_background_custom'] = TRUE;
  $values['favicon_ios_background_color'] = '#ffffff';
  $values['favicon_android_background_custom'] = TRUE;
  $values['favicon_android_background_color'] = '#005b99';
  $saved = emulsify_background_save($values);
  $values['favicon_background_color'] = '#112233';
  $saved = emulsify_background_save($values);
  emulsify_background_assert($saved['favicon_ios_background_color'] === '#ffffff' && $saved['favicon_android_background_color'] === '#005b99', 'Explicit white and non-white overrides must survive later browser color changes.');
  $form = \Drupal::formBuilder()->getForm(ThemeSettingsForm::class, 'emulsify');
  foreach (['ios', 'android'] as $platform) {
    $section = $form['emulsify_favicon'][$platform];
    emulsify_background_assert($section['favicon_' . $platform . '_background_custom']['#default_value'] === TRUE && isset($section['favicon_' . $platform . '_background_color']['#states']['visible']), 'Platform overrides must have persistent native checkboxes and conditional color inputs.');
  }
  $values['favicon_ios_background_custom'] = FALSE;
  $values['favicon_android_background_custom'] = FALSE;
  $saved = emulsify_background_save($values);
  emulsify_background_assert($saved['favicon_ios_background_color'] === '#112233' && $saved['favicon_android_background_color'] === '#112233', 'Clearing customization must immediately restore inheritance: ' . json_encode(array_intersect_key($saved, array_flip(['favicon_background_color', 'favicon_ios_background_color', 'favicon_ios_background_custom', 'favicon_android_background_color', 'favicon_android_background_custom']))));

  $generator = new FaviconPackageGenerator(\Drupal::service('file_system'), \Drupal::service('file_url_generator'), \Drupal::service('config.factory'), \Drupal::service('cache_tags.invalidator'), \Drupal::service('datetime.time'), \Drupal::service('lock'));
  $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="16" fill="#000000"/></svg>';
  $explicit_same = array_replace($inherited, ['favicon_ios_background_custom' => TRUE, 'favicon_android_background_custom' => TRUE]);
  emulsify_background_assert($generator->getPackageDefinition('emulsify', $inherited, $svg)['hash'] === $generator->getPackageDefinition('emulsify', $explicit_same, $svg)['hash'], 'Customization flags must not change hashes when effective output colors are identical.');
  $manifest_method = new ReflectionMethod(FaviconPackageGenerator::class, 'buildManifest');
  $manifest = $manifest_method->invoke($generator, 'public://favicon-package/emulsify/0123456789ab', $inherited);
  emulsify_background_assert($manifest['background_color'] === '#123456' && $manifest['theme_color'] === '#123456', 'The Android manifest must use the inherited effective background.');
  fwrite(STDOUT, "PASS Favicon background inheritance: legacy defaults/custom colors, real save/reload, explicit white overrides, reset to inheritance, conditional controls, stable output hashes, and Android manifest colors.\n");
}
finally {
  $config->setData($original)->save();
}
