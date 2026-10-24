<?php

declare(strict_types=1);

namespace Drupal\emulsify\Media;

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;

/**
 * Normalizes the opt-in media embed settings for a theme.
 */
final class MediaEmbedSettings {

  public const DEFAULTS = [
    'media_embed_privacy_mode' => FALSE,
    'media_embed_click_to_load' => FALSE,
    'media_embed_poster' => 'provider',
  ];

  /**
   * Applies defaults to settings from config or the settings form.
   */
  public static function normalize(array $settings): array {
    $normalized = self::DEFAULTS;
    foreach (self::DEFAULTS as $key => $default) {
      if (isset($settings[$key])) {
        $normalized[$key] = $settings[$key];
      }
    }
    $normalized['media_embed_privacy_mode'] = (bool) $normalized['media_embed_privacy_mode'];
    $normalized['media_embed_click_to_load'] = (bool) $normalized['media_embed_click_to_load'];
    $normalized['media_embed_poster'] = $normalized['media_embed_poster'] === 'neutral' ? 'neutral' : 'provider';
    return $normalized;
  }

  /**
   * Loads the active theme's effective settings through Drupal's provider.
   */
  public static function loadThemeSettings(string $theme_name, ThemeSettingsProvider $provider): array {
    $settings = [];
    foreach (self::DEFAULTS as $key => $default) {
      $settings[$key] = $provider->getSetting($key, $theme_name);
    }
    return self::normalize($settings);
  }

  /**
   * Returns the theme being configured by the settings form.
   */
  public static function resolveThemeName(FormStateInterface $form_state): string {
    $theme = \Drupal::routeMatch()->getParameter('theme');
    if (is_string($theme) && $theme !== '') {
      return $theme;
    }
    if (is_object($theme) && method_exists($theme, 'getName')) {
      return (string) $theme->getName();
    }
    return (string) ($form_state->getBuildInfo()['args'][0] ?? 'emulsify');
  }

}
