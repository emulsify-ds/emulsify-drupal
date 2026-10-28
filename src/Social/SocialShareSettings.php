<?php

declare(strict_types=1);

namespace Drupal\emulsify\Social;

use Drupal\Core\Extension\ThemeSettingsProvider;

/**
 * Loads and normalizes the independent social share image settings.
 */
final class SocialShareSettings {

  public const DEFAULTS = [
    'social_share_image_enabled' => FALSE,
    'social_share_image_path' => '',
    'social_share_image_fid' => [],
    'social_share_image_alt' => '',
  ];

  /**
   * Applies safe types to config and submitted form values.
   */
  public static function normalize(array $settings): array {
    $settings = array_intersect_key($settings, self::DEFAULTS) + self::DEFAULTS;
    $settings['social_share_image_enabled'] = (bool) $settings['social_share_image_enabled'];
    foreach (['social_share_image_path', 'social_share_image_alt'] as $key) {
      $settings[$key] = is_scalar($settings[$key]) ? trim((string) $settings[$key]) : '';
    }
    $ids = (array) $settings['social_share_image_fid'];
    $id = reset($ids);
    $settings['social_share_image_fid'] = is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0 ? [(int) $id] : [];
    return $settings;
  }

  /**
   * Loads the configured theme's effective settings.
   */
  public static function loadThemeSettings(string $theme_name, ThemeSettingsProvider $provider): array {
    $settings = [];
    foreach (self::DEFAULTS as $key => $default) {
      $settings[$key] = $provider->getSetting($key, $theme_name);
    }
    return self::normalize($settings);
  }

}
