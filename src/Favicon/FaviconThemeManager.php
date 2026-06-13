<?php

declare(strict_types=1);

namespace Drupal\emulsify\Favicon;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\file\Entity\File;

/**
 * Coordinates theme-level favicon package settings and generation workflows.
 */
final class FaviconThemeManager {

  /**
   * Generates favicon packages from uploaded or portable SVG sources.
   */
  private FaviconPackageGenerator $packageGenerator;

  /**
   * Creates the theme manager.
   */
  public function __construct(
    private readonly ThemeSettingsProvider $themeSettingsProvider,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    FileUrlGeneratorInterface $fileUrlGenerator,
    TimeInterface $time,
    LockBackendInterface $lock,
  ) {
    $this->packageGenerator = new FaviconPackageGenerator(
      $fileSystem,
      $fileUrlGenerator,
      $configFactory,
      $cacheTagsInvalidator,
      $time,
      $lock,
    );
  }

  /**
   * Returns runtime dependency availability for PNG/ICO generation.
   *
   * @return array{gd: bool, imagick: bool}
   *   Dependency availability keyed by extension name.
   */
  public function getRuntimeDependencyStatus(): array {
    return $this->packageGenerator->getRuntimeDependencyStatus();
  }

  /**
   * Loads normalized theme settings for favicon generation.
   */
  public function loadThemeSettings(string $theme_name): array {
    return FaviconSettings::loadThemeSettings(
      $theme_name,
      $this->themeSettingsProvider,
      $this->getSiteName(),
    );
  }

  /**
   * Persists normalized favicon settings to theme config.
   */
  public function writeThemeSettings(string $theme_name, array $settings): void {
    $config = $this->configFactory->getEditable($theme_name . '.settings');
    foreach (FaviconSettings::DEFAULTS as $key => $default) {
      $config->set($key, $settings[$key] ?? $default);
    }
    $config->save();
  }

  /**
   * Resolves a stored managed file source when it still exists.
   */
  public function resolveStoredSourceFile(array $settings): ?File {
    $source_fid = FaviconSettings::getSourceFileId($settings);
    if (!$source_fid) {
      return NULL;
    }

    $source_file = File::load($source_fid);
    return $source_file instanceof File ? $source_file : NULL;
  }

  /**
   * Resolves the current source context from form or config values.
   *
   * @return array<string, mixed>
   *   The resolved source context, or an empty array if no source exists.
   */
  public function resolveSourceContext(array $settings, bool $requires_rasterization): array {
    $source_svg = FaviconSettings::getPortableSourceSvg($settings);
    $source_filename = (string) ($settings['favicon_source_filename'] ?: 'favicon.svg');
    $source_fid = FaviconSettings::getSourceFileId($settings);
    $source_file = $source_fid ? File::load($source_fid) : NULL;
    if ($source_fid && !$source_file && $source_svg === '') {
      throw new \InvalidArgumentException('The uploaded icon file could not be loaded.');
    }

    $analysis = $source_file
      ? $this->resolveSourceFileAnalysis($source_file, $source_svg, $requires_rasterization)
      : NULL;

    if ($analysis !== NULL) {
      $source_filename = $source_file->getFilename();
    }

    if ($analysis === NULL && $source_svg === '') {
      return [];
    }

    if ($analysis === NULL) {
      $source_file = NULL;
      $analysis = $this->packageGenerator->validateSourceSvg($source_svg, $requires_rasterization);
    }

    return [
      'source_file' => $source_file,
      'source_svg' => (string) $analysis['sanitized_svg'],
      'source_filename' => $source_filename,
      'analysis' => $analysis,
    ];
  }

  /**
   * Builds package freshness and source diagnostics for a theme.
   *
   * @return array<string, mixed>
   *   Status and diagnostic metadata for the current theme.
   */
  public function buildPackageStatus(string $theme_name, array $settings, ?File $source_file = NULL): array {
    $portable_source_svg = FaviconSettings::getPortableSourceSvg($settings);
    $status = [
      'state' => 'missing',
      'hash' => (string) ($settings['favicon_package_hash'] ?? ''),
      'path' => (string) ($settings['favicon_package_path'] ?? ''),
      'package_exists' => !empty($settings['favicon_package_path']) && $this->packageGenerator->packageExistsForTheme($theme_name, $settings['favicon_package_path']),
      'source_available' => FALSE,
      'portable_source_missing' => FALSE,
      'portable_source_available' => $portable_source_svg !== '',
      'portable_source_size' => strlen($portable_source_svg),
      'portable_source_large' => strlen($portable_source_svg) > FaviconPackageGenerator::PORTABLE_SOURCE_ADVISORY_SIZE,
      'analysis_warnings' => [],
      'generated_at' => (int) ($settings['favicon_package_generated_at'] ?? 0),
    ];

    try {
      $source_context = [];
      if ($source_file) {
        $source_context = $this->resolveSourceContext($settings, FALSE);
        $status['portable_source_missing'] = !$status['portable_source_available'];
      }
      elseif ($status['portable_source_available']) {
        $source_context = $this->resolveSourceContext($settings, FALSE);
      }

      if ($source_context !== []) {
        $status['source_available'] = TRUE;
        $definition = $this->packageGenerator->getPackageDefinition(
          $theme_name,
          $settings,
          $source_context['source_svg'],
        );
        $status['hash'] = $definition['hash'];
        $status['path'] = $definition['path'];
        $status['package_exists'] = $this->packageGenerator->packageExists($definition['path']);
        $status['portable_source_size'] = strlen((string) $source_context['source_svg']);
        $status['portable_source_large'] = $status['portable_source_size'] > FaviconPackageGenerator::PORTABLE_SOURCE_ADVISORY_SIZE;
        $status['analysis_warnings'] = $source_context['analysis']['warnings'] ?? [];

        if ($status['package_exists'] && $settings['favicon_package_hash'] === $definition['hash'] && $settings['favicon_package_path'] === $definition['path']) {
          $status['state'] = 'current';
        }
        elseif ($status['package_exists']) {
          $status['state'] = 'stale';
        }
        else {
          $status['state'] = 'missing';
        }
      }
      elseif ($status['package_exists']) {
        $status['state'] = 'legacy';
      }

      $metadata = $status['path'] !== '' ? $this->packageGenerator->readPackageMetadataForTheme($theme_name, $status['path']) : NULL;
      if (is_array($metadata) && isset($metadata['generated_at'])) {
        $status['generated_at'] = (int) $metadata['generated_at'];
      }
    }
    catch (\Throwable $exception) {
      $status['state'] = 'invalid';
      $status['error'] = $exception->getMessage();
    }

    return $status;
  }

  /**
   * Generates or refreshes the package for a theme from saved settings.
   *
   * @return array{
   *   generated: bool,
   *   result: array{hash: string, path: string, generated_at: int},
   *   settings: array<string, mixed>,
   *   source_context: array<string, mixed>
   *   }
   *   The generation outcome and normalized settings to persist.
   */
  public function generatePackage(string $theme_name, array $settings, bool $overwrite = FALSE): array {
    $source_context = $this->resolveSourceContext($settings, TRUE);
    if ($source_context === []) {
      throw new \InvalidArgumentException(sprintf('No portable SVG source is available for theme %s.', $theme_name));
    }

    $definition = $this->packageGenerator->getPackageDefinition(
      $theme_name,
      $settings,
      $source_context['source_svg'],
    );
    $metadata = $this->packageGenerator->readPackageMetadata($definition['path']);
    $should_generate = $overwrite
      || $settings['favicon_package_hash'] !== $definition['hash']
      || $settings['favicon_package_path'] !== $definition['path']
      || !$this->packageGenerator->packageExists($definition['path']);

    $result = $should_generate
      ? $this->packageGenerator->generateFromSvg(
        $theme_name,
        $source_context['source_svg'],
        $settings,
        [
          'file_id' => $source_context['source_file'] instanceof File ? (int) $source_context['source_file']->id() : 0,
          'filename' => $source_context['source_filename'],
        ],
        $overwrite,
      )
      : [
        'hash' => $definition['hash'],
        'path' => $definition['path'],
        'generated_at' => (int) ($metadata['generated_at'] ?? 0),
      ];

    $updated_settings = FaviconSettings::normalize($settings, $this->getSiteName());
    $updated_settings['favicon_source_svg'] = $source_context['source_svg'];
    $updated_settings['favicon_source_filename'] = $source_context['source_filename'];
    $updated_settings['favicon_package_hash'] = $result['hash'];
    $updated_settings['favicon_package_path'] = $result['path'];
    $updated_settings['favicon_package_generated_at'] = $result['generated_at'];

    return [
      'generated' => $should_generate,
      'result' => $result,
      'settings' => $updated_settings,
      'source_context' => $source_context,
    ];
  }

  /**
   * Resets favicon settings, deletes packages, and restores theme defaults.
   *
   * @return array<string, mixed>
   *   The default settings that were restored.
   */
  public function resetThemeSettings(string $theme_name): array {
    $settings = $this->loadThemeSettings($theme_name);
    $source_file = $this->resolveStoredSourceFile($settings);
    $package_status = $this->buildPackageStatus($theme_name, $settings, $source_file);

    $package_paths = array_unique(array_filter([
      $settings['favicon_package_path'],
      $package_status['path'],
    ]));
    foreach ($package_paths as $package_path) {
      if (!FaviconPackageGenerator::isManagedPackagePath($package_path, $theme_name)) {
        continue;
      }

      $realpath = $this->fileSystem->realpath($package_path);
      if ($realpath && is_dir($realpath)) {
        $this->fileSystem->deleteRecursive($realpath);
      }
    }

    $config = $this->configFactory->getEditable($theme_name . '.settings');
    foreach (FaviconSettings::DEFAULTS as $key => $value) {
      $config->set($key, $value);
    }
    $config
      ->set('features.favicon', TRUE)
      ->set('favicon.use_default', TRUE)
      ->set('favicon.path', '')
      ->save();

    $this->cacheTagsInvalidator->invalidateTags(['rendered']);

    return FaviconSettings::normalize(FaviconSettings::DEFAULTS, $this->getSiteName());
  }

  /**
   * Validates a managed source file or falls back to stored SVG config.
   *
   * @return array<string, mixed>|null
   *   The validated source analysis, or NULL for config fallback.
   */
  private function resolveSourceFileAnalysis(
    File $source_file,
    string $source_svg,
    bool $requires_rasterization,
  ): ?array {
    try {
      return $this->packageGenerator->validateSourceFile($source_file, $requires_rasterization);
    }
    catch (\InvalidArgumentException $exception) {
      if ($source_svg === '') {
        throw $exception;
      }
    }

    return NULL;
  }

  /**
   * Returns the current site name.
   */
  private function getSiteName(): string {
    return (string) $this->configFactory->get('system.site')->get('name');
  }

}
