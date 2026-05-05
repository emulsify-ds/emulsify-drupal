<?php

namespace Drupal\emulsify\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\emulsify\Favicon\FaviconHeadBuilder;
use Drupal\emulsify\Favicon\FaviconPackageGenerator;
use Drupal\emulsify\Favicon\FaviconSettings;
use Drupal\file\Entity\File;
use Psr\Log\LoggerInterface;

/**
 * Theme hook handlers for generated favicon packages.
 */
final class FaviconHooks {

  /**
   * Builds head tags for generated favicon packages.
   */
  private FaviconHeadBuilder $headBuilder;

  /**
   * Generates missing runtime packages from portable SVG config.
   */
  private FaviconPackageGenerator $packageGenerator;

  /**
   * Logger channel for runtime favicon generation failures.
   */
  private LoggerInterface $logger;

  /**
   * Creates the favicon hook handler.
   */
  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
    private readonly ThemeSettingsProvider $themeSettingsProvider,
    private readonly ConfigFactoryInterface $configFactory,
    FileSystemInterface $fileSystem,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    FileUrlGeneratorInterface $fileUrlGenerator,
    TimeInterface $time,
  ) {
    $this->headBuilder = new FaviconHeadBuilder($fileUrlGenerator);
    $this->packageGenerator = new FaviconPackageGenerator(
      $fileSystem,
      $fileUrlGenerator,
      $configFactory,
      $cacheTagsInvalidator,
      $time,
    );
    $this->logger = $loggerChannelFactory->get('emulsify');
  }

  /**
   * Handles hook_page_attachments_alter().
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    $theme_name = $this->themeManager->getActiveTheme()->getName();
    $settings = FaviconSettings::loadThemeSettings(
      $theme_name,
      $this->themeSettingsProvider,
      $this->getSiteName(),
    );

    if (empty($settings['favicon_package_enabled'])) {
      return;
    }

    $settings = $this->resolveRuntimePackageSettings($theme_name, $settings);
    if ($settings === NULL || empty($settings['favicon_package_path'])) {
      return;
    }

    $this->headBuilder->apply($attachments, $settings);
  }

  /**
   * Resolves the package that should be attached for the active theme.
   */
  private function resolveRuntimePackageSettings(string $theme_name, array $settings): ?array {
    try {
      return $this->doResolveRuntimePackageSettings($theme_name, $settings);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Unable to resolve runtime favicon package for %theme: @message', [
        '%theme' => $theme_name,
        '@message' => $exception->getMessage(),
      ]);

      return !empty($settings['favicon_package_path']) && $this->packageGenerator->packageExists($settings['favicon_package_path']) ? $settings : NULL;
    }
  }

  /**
   * Resolves package settings when a portable SVG source is available.
   */
  private function doResolveRuntimePackageSettings(string $theme_name, array $settings): ?array {
    $source_svg = FaviconSettings::getSourceSvg($settings);
    $source_metadata = [
      'filename' => (string) ($settings['favicon_source_filename'] ?? 'favicon.svg'),
    ];
    $source_fid = FaviconSettings::getSourceFileId($settings);
    $source_file = $source_fid ? File::load($source_fid) : NULL;
    $source_analysis = $source_file ? $this->resolveRuntimeSourceFileAnalysis($source_file, $source_svg) : NULL;

    if ($source_analysis !== NULL) {
      $source_svg = (string) $source_analysis['sanitized_svg'];
      $source_metadata['file_id'] = (int) $source_file->id();
      $source_metadata['filename'] = $source_file->getFilename();
    }

    if ($source_svg !== '') {
      $analysis = $this->packageGenerator->validateSourceSvg($source_svg, FALSE);
      $source_svg = (string) $analysis['sanitized_svg'];
    }

    if ($source_svg === '') {
      return !empty($settings['favicon_package_path']) && $this->packageGenerator->packageExists($settings['favicon_package_path'])
        ? $settings
        : NULL;
    }

    $definition = $this->packageGenerator->getPackageDefinition($theme_name, $settings, $source_svg);
    $settings['favicon_package_hash'] = $definition['hash'];
    $settings['favicon_package_path'] = $definition['path'];

    if (!$this->packageGenerator->packageExists($definition['path'])) {
      $result = $this->packageGenerator->generateFromSvg($theme_name, $source_svg, $settings, $source_metadata);
      $settings['favicon_package_generated_at'] = $result['generated_at'];
      return $settings;
    }

    $metadata = $this->packageGenerator->readPackageMetadata($definition['path']);
    if (is_array($metadata) && isset($metadata['generated_at'])) {
      $settings['favicon_package_generated_at'] = (int) $metadata['generated_at'];
    }

    return $settings;
  }

  /**
   * Validates a managed source file or falls back to stored SVG config.
   *
   * @return array<string, mixed>|null
   *   The validated source analysis, or NULL when config fallback should be used.
   */
  private function resolveRuntimeSourceFileAnalysis(File $source_file, string $source_svg): ?array {
    try {
      return $this->packageGenerator->validateSourceFile($source_file, FALSE);
    }
    catch (\InvalidArgumentException $exception) {
      if ($source_svg === '') { throw $exception; }
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
