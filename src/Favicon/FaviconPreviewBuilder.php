<?php

declare(strict_types=1);

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
    if (empty($settings['favicon_package_enabled']) || empty($settings['favicon_package_path'])) {
      return [];
    }

    return [
      '#markup' => Markup::create($this->buildBrowserMarkup($settings, $source_file)),
    ];
  }

  /**
   * Builds the iOS preview.
   */
  public function buildIosPreview(array $settings, ?File $source_file = NULL): array {
    if (empty($settings['favicon_package_enabled']) || empty($settings['favicon_package_path'])) {
      return [];
    }

    return [
      '#markup' => Markup::create($this->buildIosMarkup($settings, $source_file)),
    ];
  }

  /**
   * Builds the Android and maskable previews.
   */
  public function buildAndroidPreview(array $settings, ?File $source_file = NULL): array {
    if (empty($settings['favicon_package_enabled']) || empty($settings['favicon_package_path'])) {
      return [];
    }

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
      . '<p class="emulsify-favicon-preview__summary">Browser tabs use the generated SVG favicon and ICO. These previews show the saved generated assets. Save changes to update them.</p>'
      . '<div class="emulsify-favicon-preview__grid">'
      . '<div class="emulsify-favicon-preview__card">'
      . '<h4>Light tab</h4>'
      . '<div class="emulsify-favicon-preview__tab">'
      . '<span class="emulsify-favicon-preview__canvas emulsify-favicon-preview__canvas--browser%s" data-preview-canvas="browser" style="--preview-background:transparent; --preview-padding:0%%">'
      . '<span class="emulsify-favicon-preview__art"><img src="%s" alt="Browser favicon preview on a light tab" data-preview-image loading="lazy" /></span>'
      . '</span>'
      . '<span>example.com</span>'
      . '</div>'
      . '</div>'
      . '<div class="emulsify-favicon-preview__card emulsify-favicon-preview__card--dark">'
      . '<h4>Dark tab</h4>'
      . '<div class="emulsify-favicon-preview__tab emulsify-favicon-preview__tab--dark">'
      . '<span class="emulsify-favicon-preview__canvas emulsify-favicon-preview__canvas--browser%s" data-preview-canvas="browser" style="--preview-background:transparent; --preview-padding:0%%">'
      . '<span class="emulsify-favicon-preview__art"><img src="%s" alt="Browser favicon preview on a dark tab" data-preview-image loading="lazy" /></span>'
      . '</span>'
      . '<span>example.com</span>'
      . '</div>'
      . '</div>'
      . '</div>'
      . '</div>',
      $empty_class,
      $source_url,
      $empty_class,
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
      . '<span class="emulsify-favicon-preview__canvas emulsify-favicon-preview__canvas--ios%s" data-preview-canvas="ios" style="--preview-background:transparent; --preview-padding:0%%">'
      . '<span class="emulsify-favicon-preview__art"><img src="%s" alt="iOS icon preview" data-preview-image loading="lazy" /></span>'
      . '</span>'
      . '<span class="emulsify-favicon-preview__app-name" data-preview-label="ios">%s</span>'
      . '</div>'
      . '</div>'
      . '</div>',
      $empty_class,
      $source_url,
      $icon_name,
    );
  }

  /**
   * Builds Android preview markup.
   */
  private function buildAndroidMarkup(array $settings, ?File $source_file): string {
    $source_url = Html::escape($this->resolvePreviewSourceUrl('android', $settings, $source_file));
    $maskable_url = Html::escape($this->resolvePreviewSourceUrl('maskable', $settings, $source_file));
    $empty_class = $source_url === '' ? ' emulsify-favicon-preview__canvas--empty' : '';
    $icon_name = Html::escape($this->resolveAndroidPreviewLabel($settings));

    return sprintf(
      '<div class="emulsify-favicon-preview emulsify-favicon-preview--android" data-favicon-preview-group="android">'
      . '<p class="emulsify-favicon-preview__summary">The Android icon background color also feeds the generated theme-color metadata. The maskable preview highlights the safe circle that should keep important artwork visible.</p>'
      . '<div class="emulsify-favicon-preview__grid">'
      . '<div class="emulsify-favicon-preview__card">'
      . '<h4>Android</h4>'
      . '<div class="emulsify-favicon-preview__device emulsify-favicon-preview__device--android">'
      . '<span class="emulsify-favicon-preview__canvas emulsify-favicon-preview__canvas--android%s" data-preview-canvas="android" style="--preview-background:transparent; --preview-padding:0%%">'
      . '<span class="emulsify-favicon-preview__art"><img src="%s" alt="Android icon preview" data-preview-image loading="lazy" /></span>'
      . '</span>'
      . '<span class="emulsify-favicon-preview__app-name" data-preview-label="android">%s</span>'
      . '</div>'
      . '</div>'
      . '<div class="emulsify-favicon-preview__card">'
      . '<h4>Maskable safe area</h4>'
      . '<div class="emulsify-favicon-preview__device emulsify-favicon-preview__device--maskable">'
      . '<span class="emulsify-favicon-preview__canvas emulsify-favicon-preview__canvas--maskable%s" data-preview-canvas="maskable" style="--preview-background:transparent; --preview-padding:0%%">'
      . '<span class="emulsify-favicon-preview__art"><img src="%s" alt="Maskable icon preview" data-preview-image loading="lazy" /></span>'
      . '<span class="emulsify-favicon-preview__safe-area" aria-hidden="true"></span>'
      . '</span>'
      . '</div>'
      . '</div>'
      . '</div>'
      . '</div>',
      $empty_class,
      $source_url,
      $icon_name,
      $empty_class,
      $maskable_url,
    );
  }

  /**
   * Resolves the generated asset used by the saved head links or manifest.
   *
   * The optional source file is retained for caller compatibility. Uploaded and
   * portable sources are generation inputs, never saved output previews.
   */
  private function resolvePreviewSourceUrl(string $platform, array $settings, ?File $source_file): string {
    $package_path = $settings['favicon_package_path'] ?? '';
    if ($package_path === '') {
      return '';
    }

    $filename = match ($platform) {
      'browser' => 'favicon.svg',
      'ios' => 'apple-touch-icon.png',
      'maskable' => 'web-app-manifest-512x512-maskable.png',
      default => 'web-app-manifest-192x192.png',
    };
    return $this->fileUrlGenerator->generateString($package_path . '/' . $filename);
  }

  /**
   * Uses the same saved title as FaviconHeadBuilder, without invented labels.
   */
  private function resolveIosPreviewLabel(array $settings): string {
    return trim((string) ($settings['favicon_ios_icon_name'] ?? ''));
  }

  /**
   * Reads the launcher label from the manifest referenced by the saved package.
   */
  private function resolveAndroidPreviewLabel(array $settings): string {
    $package_path = (string) ($settings['favicon_package_path'] ?? '');
    if (!FaviconPackageGenerator::isManagedPackagePath($package_path, basename(dirname($package_path)))) {
      return '';
    }
    $manifest_path = $package_path . '/site.webmanifest';
    if (!is_readable($manifest_path)) {
      return '';
    }
    $manifest = json_decode((string) file_get_contents($manifest_path), TRUE);
    return is_array($manifest) ? (string) ($manifest['short_name'] ?? $manifest['name'] ?? '') : '';
  }

}
