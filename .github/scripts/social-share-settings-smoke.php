<?php

/**
 * @file
 * Verifies real core form saving, upload retention, paths, and social previews.
 */

declare(strict_types=1);

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\emulsify\Hook\SocialShareHooks;
use Drupal\emulsify\Social\SocialSharePreviewBuilder;
use Drupal\emulsify\Social\SocialShareImageResolver;
use Drupal\emulsify\Social\SocialShareSettings;
use Drupal\emulsify\Social\SocialShareSettingsForm;
use Drupal\file\Entity\File;
use Drupal\system\Form\ThemeSettingsForm;

/**
 * Fails a contract with an actionable message.
 */
function emulsify_social_settings_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/**
 * Submits through the actual core form and returns any validation errors.
 */
function emulsify_social_settings_submit(array $values): FormState {
  $preview_values = array_combine(SocialSharePreviewBuilder::FORM_KEYS, ['pinterest', 'mobile', 'Preview title', 'Preview description', 'https://preview.example.test/']);
  $state = (new FormState())->setValues($values + ['default_logo' => TRUE, 'favicon_package_enabled' => FALSE] + $preview_values);
  \Drupal::formBuilder()->submitForm(ThemeSettingsForm::class, $state, 'emulsify');
  \Drupal::configFactory()->reset('emulsify.settings');
  return $state;
}

$config = \Drupal::configFactory()->getEditable('emulsify.settings');
$original = $config->getRawData();
$file_settings = \Drupal::configFactory()->getEditable('file.settings');
$original_file_settings = $file_settings->getRawData();
$fs = \Drupal::service('file_system');
$directory = 'public://social-share-settings-' . bin2hex(random_bytes(4));
$fs->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
$uri = $directory . '/share.png';
$image = imagecreatetruecolor(3600, 1890);
imagepng($image, $uri);
$alternate_uri = $directory . '/other.png';
imagepng($image, $alternate_uri);
unset($image);
$source_hash = hash_file('sha256', $uri);
$processed_uri = '';
$file = File::create(['uri' => $uri, 'status' => 0, 'uid' => 1]);
$file->save();
$account_switcher = \Drupal::service('account_switcher');
$account_switcher->switchTo(\Drupal::entityTypeManager()->getStorage('user')->load(1));
$theme_manager = \Drupal::theme();
$original_theme = $theme_manager->getActiveTheme();
$theme_manager->setActiveTheme(\Drupal::service('theme.initialization')->getActiveThemeByName('emulsify'));

try {
  $config->setData(array_replace($original, SocialShareSettings::DEFAULTS))->save();
  $form = \Drupal::formBuilder()->getForm(ThemeSettingsForm::class, 'emulsify');
  $section = $form['emulsify_social_share_image'];
  emulsify_social_settings_assert(isset($section['social_share_image_enabled'], $section['settings']['social_share_image_path'], $section['settings']['social_share_image_fid'], $section['settings']['social_share_image_alt']), 'Social sharing must have its own enable checkbox, path, upload, and alt inputs.');
  emulsify_social_settings_assert(isset($section['settings']['#states']['visible'][':input[name="social_share_image_enabled"]']) && $section['social_share_image_enabled']['#default_value'] === FALSE, 'Social image inputs must be opt-in and hidden while disabled.');
  emulsify_social_settings_assert(isset($section['settings']['social_share_image_fid']['#upload_validators']['FileIsImage']) && !str_contains($section['settings']['social_share_image_fid']['#upload_validators']['FileExtension']['extensions'], 'svg'), 'Uploads must validate raster images and exclude SVG.');
  emulsify_social_settings_assert(isset($section['settings']['preview']), 'The empty preview must offer its local options before an image is saved.');
  foreach (['upload_button', 'remove_button'] as $button) {
    $ajax_state = (new FormState())->setValues(['social_share_image_enabled' => TRUE])->setTriggeringElement(['#array_parents' => ['emulsify_social_share_image', 'settings', 'social_share_image_fid', $button]]);
    SocialShareSettingsForm::validateSocialShareSettings($form, $ajax_state);
    emulsify_social_settings_assert($ajax_state->getErrors() === [], 'Managed-file upload/remove rebuilds must not prematurely require a saved image.');
  }
  $state = emulsify_social_settings_submit(['social_share_image_enabled' => TRUE]);
  emulsify_social_settings_assert(isset($state->getErrors()['social_share_image_path']), 'Enabling without an image must report a connected form error.');
  $values = ['social_share_image_enabled' => TRUE, 'social_share_image_path' => 'https://example.com/ignored.png', 'social_share_image_fid' => ['fids' => (string) $file->id()], 'social_share_image_alt' => 'A shared scene'];
  $state = emulsify_social_settings_submit($values);
  emulsify_social_settings_assert($state->getErrors() === [], 'A managed raster upload must save through the actual core form: ' . json_encode($state->getErrors()));
  $saved = \Drupal::config('emulsify.settings');
  foreach (SocialSharePreviewBuilder::FORM_KEYS as $key) {
    emulsify_social_settings_assert(!array_key_exists($key, $saved->getRawData()), 'Preview options must never be exported as theme settings: ' . $key);
  }
  emulsify_social_settings_assert($saved->get('social_share_image_path') === $uri && $saved->get('social_share_image_fid') === [(int) $file->id()] && $saved->get('social_share_image_alt') === 'A shared scene' && $saved->get('favicon_package_enabled') === FALSE, 'New uploads must take precedence over the path and save independently of favicon generation.');
  $resolver = new SocialShareImageResolver($fs);
  $display = $resolver->display($resolver->resolve(SocialShareSettings::normalize($saved->getRawData())));
  $processed_uri = $display['uri'] ?? '';
  emulsify_social_settings_assert($processed_uri !== '' && $processed_uri !== $uri && $display['info']['width'] === 1200 && $display['info']['height'] === 630, 'A real settings save must prepare a bounded derivative without replacing the configured source.');
  emulsify_social_settings_assert(hash_file('sha256', $uri) === $source_hash, 'Saving a high-resolution upload must preserve its source bytes.');
  $file = File::load($file->id());
  $usage = \Drupal::service('file.usage')->listUsage($file);
  emulsify_social_settings_assert($file->isPermanent() && (int) ($usage['emulsify']['social_share_image']['emulsify'] ?? 0) === 1, 'Saving must retain a managed upload with one file usage: ' . json_encode([$file->isPermanent(), $usage]));
  $attachments = [];
  \Drupal::service('class_resolver')->getInstanceFromDefinition(SocialShareHooks::class)->pageAttachmentsAlter($attachments);
  emulsify_social_settings_assert(count($attachments['#attached']['html_head'] ?? []) === 8, 'The real social hook must attach metadata while generated favicons are disabled.');
  $form = \Drupal::formBuilder()->getForm(ThemeSettingsForm::class, 'emulsify');
  $preview = $form['emulsify_social_share_image']['settings']['preview'] ?? [];
  $markup = (string) \Drupal::service('renderer')->renderInIsolation($preview);
  emulsify_social_settings_assert(str_contains($markup, 'data-social-share-preview') && str_contains($markup, 'A shared scene'), 'The saved image must produce the standalone social preview.');
  $document = new DOMDocument();
  $document->loadHTML('<html><body>' . $markup . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
  $xpath = new DOMXPath($document);
  emulsify_social_settings_assert($xpath->query('//select[contains(@class,"form-select")]')->length === 2 && $xpath->query('//input[contains(@class,"form-text")]')->length === 2 && $xpath->query('//textarea[contains(@class,"form-textarea")]')->length === 1, 'Preview controls must use the standard themed Form API select, textfield, and textarea elements.');
  foreach (SocialSharePreviewBuilder::FORM_KEYS as $key) {
    emulsify_social_settings_assert($xpath->query('//*[@name="' . $key . '"]')->length === 1, 'Every local preview control must render through Form API: ' . $key);
  }
  $values['social_share_image_path'] = $uri;
  $state = emulsify_social_settings_submit($values);
  emulsify_social_settings_assert($state->getErrors() === [] && (int) (\Drupal::service('file.usage')->listUsage($file)['emulsify']['social_share_image']['emulsify'] ?? 0) === 1, 'Repeated saves must not inflate file usage.');
  $file_settings->set('make_unused_managed_files_temporary', TRUE)->save();
  $values['social_share_image_path'] = substr($fs->realpath($uri), strlen(DRUPAL_ROOT) + 1);
  $state = emulsify_social_settings_submit($values);
  $file = File::load($file->id());
  emulsify_social_settings_assert($state->getErrors() === [] && \Drupal::config('emulsify.settings')->get('social_share_image_fid') === [(int) $file->id()] && $file->isPermanent(), 'A local path to the same upload must retain the managed reference and remain permanent when unused files are made temporary.');
  emulsify_social_settings_assert((int) (\Drupal::service('file.usage')->listUsage($file)['emulsify']['social_share_image']['emulsify'] ?? 0) === 1, 'An alias of the same image must retain exactly one file usage.');
  $values['social_share_image_path'] = $alternate_uri;
  $state = emulsify_social_settings_submit($values);
  emulsify_social_settings_assert($state->getErrors() === [] && \Drupal::config('emulsify.settings')->get('social_share_image_fid') === [], 'A path to another image must replace the old upload reference.');
  emulsify_social_settings_assert(empty(\Drupal::service('file.usage')->listUsage($file)['emulsify']['social_share_image']['emulsify']), 'Replacing the upload must release this theme\'s old usage.');
  emulsify_social_settings_assert(File::load($file->id())->isTemporary(), 'Replacing the image must still allow normal unused-file cleanup of the old upload.');
  $values['social_share_image_enabled'] = NULL;
  $state = emulsify_social_settings_submit($values);
  emulsify_social_settings_assert($state->getErrors() === [] && \Drupal::config('emulsify.settings')->get('social_share_image_enabled') === FALSE && \Drupal::config('emulsify.settings')->get('social_share_image_fid') === [(int) $file->id()], 'A disabled social image may retain a newly selected upload for later use: ' . json_encode([$state->getErrors(), \Drupal::config('emulsify.settings')->get('social_share_image_enabled'), \Drupal::config('emulsify.settings')->get('social_share_image_fid')]));
  $values['social_share_image_path'] = $uri;
  $values['social_share_image_fid'] = ['fids' => ''];
  $state = emulsify_social_settings_submit($values);
  emulsify_social_settings_assert($state->getErrors() === [] && \Drupal::config('emulsify.settings')->get('social_share_image_fid') === [] && \Drupal::config('emulsify.settings')->get('social_share_image_path') === '', 'Removing the saved upload must clear its mirrored path.');
  $hostile = '\"><script>alert(1)</script>';
  $preview = (new SocialSharePreviewBuilder())->build(['social_share_image_alt' => $hostile], 'https://example.test/share.png', ['width' => 1200, 'height' => 630], $hostile, $hostile, $hostile);
  $markup = (string) \Drupal::service('renderer')->renderInIsolation($preview['card']);
  $document = new DOMDocument();
  $document->loadHTML('<html><body>' . $markup . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
  $xpath = new DOMXPath($document);
  emulsify_social_settings_assert($xpath->query('//script | //@onerror')->length === 0 && $xpath->query('//img')->length === 1, 'Preview card values must be escaped.');
  fwrite(STDOUT, "PASS Standalone social form: opt-in controls, safe image validation, upload/path precedence, real core config persistence, permanent upload usage including same-file path aliases with unused-file cleanup enabled, independent favicon behavior, and saved-image preview.\n");
}
finally {
  $config->setData($original)->save();
  \Drupal::service('file.usage')->delete($file, 'emulsify', 'social_share_image', 'emulsify', 0);
  $file->delete();
  if ($processed_uri !== '') {
    $fs->delete($processed_uri);
  }
  $fs->deleteRecursive($directory);
  $file_settings->setData($original_file_settings)->save();
  $theme_manager->setActiveTheme($original_theme);
  $account_switcher->switchBack();
}
