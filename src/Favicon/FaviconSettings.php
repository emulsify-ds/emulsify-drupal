<?php

namespace Drupal\emulsify\Favicon;

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;

/**
 * Normalizes and loads favicon package theme settings.
 */
final class FaviconSettings {

  /**
   * Default settings for the favicon generator.
   */
  public const DEFAULTS = [
    'favicon_package_enabled' => FALSE,
    'favicon_source_fid' => [],
    'favicon_source_svg' => '',
    'favicon_source_filename' => '',
    'favicon_background_color' => '#ffffff',
    'favicon_theme_color' => '#ffffff',
    'favicon_ios_background_color' => '#ffffff',
    'favicon_ios_padding' => 16,
    'favicon_ios_icon_name' => '',
    'favicon_android_background_color' => '#ffffff',
    'favicon_android_padding' => 20,
    'favicon_android_maskable_enabled' => TRUE,
    'favicon_manifest_name' => '',
    'favicon_manifest_short_name' => '',
    'favicon_manifest_display' => 'standalone',
    'favicon_package_hash' => '',
    'favicon_package_path' => '',
    'favicon_package_generated_at' => 0,
  ];

  /**
   * Loads stored settings for a given theme and applies defaults.
   */
  public static function loadThemeSettings(string $theme_name, ThemeSettingsProvider $provider, string $site_name): array {
    $settings = [];
    foreach (self::DEFAULTS as $key => $default) {
      $settings[$key] = $provider->getSetting($key, $theme_name);
    }

    return self::normalize($settings, $site_name);
  }

  /**
   * Normalizes settings from config or form values.
   */
  public static function normalize(array $settings, string $site_name = ''): array {
    $normalized = self::DEFAULTS;

    foreach (self::DEFAULTS as $key => $default) {
      if (array_key_exists($key, $settings) && $settings[$key] !== NULL) {
        $normalized[$key] = $settings[$key];
      }
    }

    $normalized['favicon_package_enabled'] = (bool) $normalized['favicon_package_enabled'];
    $normalized['favicon_android_maskable_enabled'] = TRUE;
    $normalized['favicon_source_fid'] = self::normalizeFileIds($normalized['favicon_source_fid']);
    $normalized['favicon_source_svg'] = trim((string) $normalized['favicon_source_svg']);
    $normalized['favicon_source_filename'] = trim((string) $normalized['favicon_source_filename']);
    $normalized['favicon_ios_padding'] = self::clampPadding($normalized['favicon_ios_padding']);
    $normalized['favicon_android_padding'] = self::clampPadding($normalized['favicon_android_padding']);
    $normalized['favicon_background_color'] = self::normalizeColor($normalized['favicon_background_color']);
    $normalized['favicon_ios_background_color'] = self::normalizeColor($normalized['favicon_ios_background_color']);
    $normalized['favicon_ios_icon_name'] = trim((string) $normalized['favicon_ios_icon_name']);
    $normalized['favicon_android_background_color'] = self::normalizeColor($normalized['favicon_android_background_color']);
    // The Android background color also drives the manifest and theme-color
    // metadata so the UI only needs one color control for that platform.
    $normalized['favicon_theme_color'] = $normalized['favicon_android_background_color'];
    $normalized['favicon_manifest_short_name'] = trim((string) $normalized['favicon_manifest_short_name']);
    $normalized['favicon_package_hash'] = trim((string) $normalized['favicon_package_hash']);
    $normalized['favicon_package_path'] = trim((string) $normalized['favicon_package_path']);
    $normalized['favicon_package_generated_at'] = (int) $normalized['favicon_package_generated_at'];

    $normalized['favicon_manifest_display'] = self::DEFAULTS['favicon_manifest_display'];

    // The full manifest name follows the live site name and is not intended to
    // diverge in theme settings.
    $normalized['favicon_manifest_name'] = $site_name;
    if ($normalized['favicon_manifest_short_name'] === '') {
      $normalized['favicon_manifest_short_name'] = $site_name;
    }
    if ($normalized['favicon_ios_icon_name'] === '') {
      $normalized['favicon_ios_icon_name'] = $normalized['favicon_manifest_short_name'];
    }

    return $normalized;
  }

  /**
   * Extracts the theme name being configured.
   */
  public static function resolveThemeName(FormStateInterface $form_state): string {
    $route_theme = \Drupal::routeMatch()->getParameter('theme');
    if (is_string($route_theme) && $route_theme !== '') {
      return $route_theme;
    }
    if (is_object($route_theme) && method_exists($route_theme, 'getName')) {
      return (string) $route_theme->getName();
    }

    $args = $form_state->getBuildInfo()['args'] ?? [];
    if (!empty($args[0]) && is_string($args[0])) {
      return $args[0];
    }

    return 'emulsify';
  }

  /**
   * Returns the configured source file ID.
   */
  public static function getSourceFileId(array $settings): ?int {
    $file_ids = self::normalizeFileIds($settings['favicon_source_fid'] ?? []);
    return $file_ids[0] ?? NULL;
  }

  /**
   * Returns the exportable SVG source stored in config.
   */
  public static function getSourceSvg(array $settings): string {
    return trim((string) ($settings['favicon_source_svg'] ?? ''));
  }

  /**
   * Indicates whether an exportable SVG source is available.
   */
  public static function hasExportableSource(array $settings): bool {
    return self::getSourceSvg($settings) !== '';
  }

  /**
   * Converts a package URI into a display string.
   */
  public static function summarizePackageLocation(array $settings): string {
    if (empty($settings['favicon_package_path'])) {
      return 'No generated package yet.';
    }

    if (empty($settings['favicon_package_hash'])) {
      return (string) $settings['favicon_package_path'];
    }

    return $settings['favicon_package_path'] . ' (' . $settings['favicon_package_hash'] . ')';
  }

  /**
   * Normalizes a saved file ID value from theme settings storage.
   *
   * @param mixed $value
   *   Stored file ID value.
   *
   * @return int[]
   *   A list containing zero or one file IDs.
   */
  private static function normalizeFileIds(mixed $value): array {
    if (is_scalar($value) && $value !== '') {
      return [(int) $value];
    }

    if (!is_array($value)) {
      return [];
    }

    $normalized = [];
    foreach ($value as $candidate) {
      if ($candidate !== NULL && $candidate !== '') {
        $normalized[] = (int) $candidate;
      }
    }

    return $normalized ? [reset($normalized)] : [];
  }

  /**
   * Normalizes a hex color.
   */
  private static function normalizeColor(mixed $value): string {
    $candidate = strtolower(trim((string) $value));
    if (preg_match('/^#[0-9a-f]{6}$/', $candidate)) {
      return $candidate;
    }
    if (preg_match('/^#[0-9a-f]{3}$/', $candidate)) {
      return sprintf(
        '#%s%s%s%s%s%s',
        $candidate[1],
        $candidate[1],
        $candidate[2],
        $candidate[2],
        $candidate[3],
        $candidate[3],
      );
    }

    return '#ffffff';
  }

  /**
   * Keeps padding values in a safe, human-editable range.
   */
  private static function clampPadding(mixed $value): int {
    $padding = (int) $value;
    return max(0, min(40, $padding));
  }

}
