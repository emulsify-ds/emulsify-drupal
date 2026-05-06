<?php

namespace Drupal\emulsify\Favicon;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\Entity\File;

/**
 * Builds lightweight admin previews for generated favicon packages.
 */
final class FaviconPreviewBuilder {

  /**
   * The file URL generator.
   */
  private FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Creates a preview builder instance.
   */
  public function __construct(FileUrlGeneratorInterface $file_url_generator) {
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Builds the browser preview.
   */
  public function buildBrowserPreview(array $settings, ?File $source_file = NULL): array {
    return [
      '#markup' => Markup::create($this->buildBrowserMarkup($settings, $source_file)),
    ];
  }

  /**
   * Builds the iOS preview.
   */
  public function buildIosPreview(array $settings, ?File $source_file = NULL): array {
    return [
      '#markup' => Markup::create($this->buildIosMarkup($settings, $source_file)),
    ];
  }

  /**
   * Builds the Android and maskable previews.
   */
  public function buildAndroidPreview(array $settings, ?File $source_file = NULL): array {
    return [
      '#markup' => Markup::create($this->buildAndroidMarkup($settings, $source_file)),
    ];
  }

  /**
   * Builds browser preview markup.
   */
  private function buildBrowserMarkup(array $settings, ?File $source_file): string {
    $source_url = Html::escape($this->resolvePreviewSourceUrl('browser', $settings, $source_file));
    $empty_class = $source_url === '' ? ' emulsify-favicon-preview__canvas--empty' : '';

    return sprintf(
      '<div class="emulsify-favicon-preview emulsify-favicon-preview--browser" data-favicon-preview-group="browser">'
      . '<p class="emulsify-favicon-preview__summary">Browser tabs use the generated SVG favicon and ICO. Compare the framed icon against light and dark tab chrome before you save.</p>'
      . '<div class="emulsify-favicon-preview__grid">'
      . '<div class="emulsify-favicon-preview__card">'
      . '<h4>Light tab</h4>'
      . '<div class="emulsify-favicon-preview__tab">'
      . '<span class="emulsify-favicon-preview__canvas emulsify-favicon-preview__canvas--browser%s" data-preview-canvas="browser" style="--preview-background:%s; --preview-padding:%s%%">'
      . '<span class="emulsify-favicon-preview__art"><img src="%s" alt="Browser favicon preview on a light tab" data-preview-image loading="lazy" /></span>'
      . '</span>'
      . '<span>example.com</span>'
      . '</div>'
      . '</div>'
      . '<div class="emulsify-favicon-preview__card emulsify-favicon-preview__card--dark">'
      . '<h4>Dark tab</h4>'
      . '<div class="emulsify-favicon-preview__tab emulsify-favicon-preview__tab--dark">'
      . '<span class="emulsify-favicon-preview__canvas emulsify-favicon-preview__canvas--browser%s" data-preview-canvas="browser" style="--preview-background:%s; --preview-padding:%s%%">'
      . '<span class="emulsify-favicon-preview__art"><img src="%s" alt="Browser favicon preview on a dark tab" data-preview-image loading="lazy" /></span>'
      . '</span>'
      . '<span>example.com</span>'
      . '</div>'
      . '</div>'
      . '</div>',
      $empty_class,
      Html::escape((string) ($settings['favicon_background_color'] ?? '#ffffff')),
      $this->getBrowserPadding($settings),
      $source_url,
      $empty_class,
      Html::escape((string) ($settings['favicon_background_color'] ?? '#ffffff')),
      $this->getBrowserPadding($settings),
      $source_url,
    );
  }

  /**
   * Builds iOS preview markup.
   */
  private function buildIosMarkup(array $settings, ?File $source_file): string {
    $source_url = Html::escape($this->resolvePreviewSourceUrl('ios', $settings, $source_file));
    $empty_class = $source_url === '' ? ' emulsify-favicon-preview__canvas--empty' : '';
    $icon_name = Html::escape($this->resolveIosPreviewLabel($settings));

    return sprintf(
      '<div class="emulsify-favicon-preview emulsify-favicon-preview--ios" data-favicon-preview-group="ios">'
      . '<p class="emulsify-favicon-preview__summary">Apple touch icons should stay opaque and padded away from rounded corners.</p>'
      . '<div class="emulsify-favicon-preview__card">'
      . '<div class="emulsify-favicon-preview__device">'
      . '<span class="emulsify-favicon-preview__canvas emulsify-favicon-preview__canvas--ios%s" data-preview-canvas="ios" style="--preview-background:%s; --preview-padding:%s%%">'
      . '<span class="emulsify-favicon-preview__art"><img src="%s" alt="iOS icon preview" data-preview-image loading="lazy" /></span>'
      . '</span>'
      . '<span class="emulsify-favicon-preview__app-name" data-preview-label="ios">%s</span>'
      . '</div>'
      . '</div>'
      . '</div>',
      $empty_class,
      Html::escape((string) ($settings['favicon_ios_background_color'] ?? '#ffffff')),
      (int) ($settings['favicon_ios_padding'] ?? 16),
      $source_url,
      $icon_name,
    );
  }

  /**
   * Builds Android preview markup.
   */
  private function buildAndroidMarkup(array $settings, ?File $source_file): string {
    $source_url = Html::escape($this->resolvePreviewSourceUrl('android', $settings, $source_file));
    $empty_class = $source_url === '' ? ' emulsify-favicon-preview__canvas--empty' : '';
    $icon_name = Html::escape($this->resolveAndroidPreviewLabel($settings));

    return sprintf(
      '<div class="emulsify-favicon-preview emulsify-favicon-preview--android" data-favicon-preview-group="android">'
      . '<p class="emulsify-favicon-preview__summary">The Android icon background color also feeds the generated theme-color metadata. The maskable preview highlights the safe circle that should keep important artwork visible.</p>'
      . '<div class="emulsify-favicon-preview__grid">'
      . '<div class="emulsify-favicon-preview__card">'
      . '<h4>Android</h4>'
      . '<div class="emulsify-favicon-preview__device emulsify-favicon-preview__device--android">'
      . '<span class="emulsify-favicon-preview__canvas emulsify-favicon-preview__canvas--android%s" data-preview-canvas="android" style="--preview-background:%s; --preview-padding:%s%%">'
      . '<span class="emulsify-favicon-preview__art"><img src="%s" alt="Android icon preview" data-preview-image loading="lazy" /></span>'
      . '</span>'
      . '<span class="emulsify-favicon-preview__app-name" data-preview-label="android">%s</span>'
      . '</div>'
      . '</div>'
      . '<div class="emulsify-favicon-preview__card">'
      . '<h4>Maskable safe area</h4>'
      . '<div class="emulsify-favicon-preview__device emulsify-favicon-preview__device--maskable">'
      . '<span class="emulsify-favicon-preview__canvas emulsify-favicon-preview__canvas--maskable%s" data-preview-canvas="maskable" style="--preview-background:%s; --preview-padding:%s%%">'
      . '<span class="emulsify-favicon-preview__art"><img src="%s" alt="Maskable icon preview" data-preview-image loading="lazy" /></span>'
      . '<span class="emulsify-favicon-preview__safe-area" aria-hidden="true"></span>'
      . '</span>'
      . '</div>'
      . '</div>'
      . '</div>'
      . '</div>',
      $empty_class,
      Html::escape((string) ($settings['favicon_android_background_color'] ?? '#ffffff')),
      (int) ($settings['favicon_android_padding'] ?? 20),
      $source_url,
      $icon_name,
      $empty_class,
      Html::escape((string) ($settings['favicon_android_background_color'] ?? '#ffffff')),
      max((int) ($settings['favicon_android_padding'] ?? 20), 20),
      $source_url,
    );
  }

  /**
   * Resolves the best image URL to use for live previews.
   */
  private function resolvePreviewSourceUrl(string $platform, array $settings, ?File $source_file): string {
    if ($source_file) {
      return $this->fileUrlGenerator->generateString($source_file->getFileUri());
    }

    if (FaviconSettings::hasPortableSource($settings)) {
      return 'data:image/svg+xml;base64,' . base64_encode(FaviconSettings::getPortableSourceSvg($settings));
    }

    $package_path = $settings['favicon_package_path'] ?? '';
    if ($package_path === '') {
      return '';
    }

    return match ($platform) {
      'browser' => $this->fileUrlGenerator->generateString($package_path . '/favicon.svg'),
      'ios' => $this->fileUrlGenerator->generateString($package_path . '/apple-touch-icon.png'),
      default => $this->fileUrlGenerator->generateString($package_path . '/web-app-manifest-192x192.png'),
    };
  }

  /**
   * Browser preview uses the most conservative padding of the icon outputs.
   */
  private function getBrowserPadding(array $settings): int {
    return min(
      (int) ($settings['favicon_ios_padding'] ?? 16),
      (int) ($settings['favicon_android_padding'] ?? 20),
    );
  }

  /**
   * Resolves the shared launcher label shown in previews.
   */
  private function resolveIosPreviewLabel(array $settings): string {
    $label = trim((string) ($settings['favicon_ios_icon_name'] ?? ''));
    if ($label !== '') {
      return $label;
    }

    $fallback = trim((string) ($settings['favicon_manifest_name'] ?? ''));
    return $fallback !== '' ? $fallback : 'Site name';
  }

  /**
   * Resolves the Android launcher label shown in previews.
   */
  private function resolveAndroidPreviewLabel(array $settings): string {
    $label = trim((string) ($settings['favicon_manifest_short_name'] ?? ''));
    if ($label !== '') {
      return $label;
    }

    $fallback = trim((string) ($settings['favicon_manifest_name'] ?? ''));
    return $fallback !== '' ? $fallback : 'Site name';
  }

}
