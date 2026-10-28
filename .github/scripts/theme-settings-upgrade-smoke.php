<?php

/**
 * @file
 * Verifies current parent settings on a child with 7.0/7.1 config and schema.
 *
 * Run through Drush after installing the legacy_settings fixture theme.
 */

declare(strict_types=1);

use Drupal\Core\Form\FormState;
use Drupal\system\Form\ThemeSettingsForm;

/**
 * Fails an upgrade contract with an actionable message.
 */
function emulsify_settings_upgrade_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$theme = 'legacy_settings';
$config = \Drupal::configFactory()->getEditable($theme . '.settings');
$original = $config->getRawData();
$parent_settings = \Drupal::config('emulsify.settings')->getRawData();
$theme_manager = \Drupal::theme();
$original_theme = $theme_manager->getActiveTheme();

try {
  // Exercise the separate administration theme used by real settings routes.
  $theme_manager->setActiveTheme(\Drupal::service('theme.initialization')->getActiveThemeByName('claro'));
  $form = \Drupal::formBuilder()->getForm(ThemeSettingsForm::class, $theme);
  emulsify_settings_upgrade_assert($theme_manager->getActiveTheme()->getName() === 'claro', 'Building child settings must restore the administrator theme.');
  emulsify_settings_upgrade_assert(isset($form['emulsify_media_embeds']['media_embed_privacy_mode'], $form['emulsify_media_embeds']['media_embed_click_to_load']), 'Legacy children must inherit current media embed controls under an admin theme.');
  emulsify_settings_upgrade_assert($form['emulsify_media_embeds']['#open'] === FALSE, 'Media embeds must be available in its collapsed section.');
  $favicon = $form['emulsify_favicon'];
  emulsify_settings_upgrade_assert(isset($favicon['browser']['dark_mode']['favicon_dark_mode'], $form['emulsify_social_share_image']), 'Legacy children must inherit dark-mode and independent social-image controls.');
  emulsify_settings_upgrade_assert(!isset($favicon['share']), 'Social image controls must be independent of the favicon fieldset.');
  emulsify_settings_upgrade_assert($favicon['favicon_package_enabled']['#default_value'] === FALSE, 'An older child must retain its disabled favicon package default.');
  emulsify_settings_upgrade_assert(isset($favicon['browser']['#states']['visible'][':input[name="favicon_package_enabled"]']), 'Favicon platform settings must appear when the package checkbox is enabled.');
  emulsify_settings_upgrade_assert(!isset($form['favicon']), 'The parent must replace the core legacy favicon form.');
  emulsify_settings_upgrade_assert($favicon['browser']['dark_mode']['favicon_dark_mode']['#default_value'] === 'none', 'Missing dark-mode settings must receive opt-in defaults.');

  // Core saves the new flat fields despite the child's older copied schema.
  $values = array_replace($original, [
    'default_logo' => TRUE,
    'favicon_ios_padding' => 11,
    'favicon_dark_mode' => 'invert',
    'media_embed_privacy_mode' => TRUE,
    'media_embed_click_to_load' => TRUE,
    'media_embed_poster' => 'neutral',
  ]);
  $state = (new FormState())->setValues($values);
  \Drupal::formBuilder()->submitForm(ThemeSettingsForm::class, $state, $theme);
  emulsify_settings_upgrade_assert($state->getErrors() === [], 'A legacy child must accept current settings without replacing its schema or regenerating the theme.');
  \Drupal::configFactory()->reset($theme . '.settings');
  $saved = \Drupal::config($theme . '.settings');
  foreach (['favicon_ios_padding', 'favicon_dark_mode', 'media_embed_privacy_mode', 'media_embed_click_to_load', 'media_embed_poster'] as $key) {
    emulsify_settings_upgrade_assert($saved->get($key) === $values[$key], "The child must save {$key} through the real core form.");
  }
  emulsify_settings_upgrade_assert(\Drupal::config('emulsify.settings')->getRawData() === $parent_settings, 'Saving child settings must preserve parent settings.');
  fwrite(STDOUT, "PASS 7.0/7.1 child settings/schema inherit current controls under Claro, opt-in defaults, conditional favicon controls, and real child-only config saves.\n");
}
finally {
  $config->setData($original)->save();
  $theme_manager->setActiveTheme($original_theme);
}
