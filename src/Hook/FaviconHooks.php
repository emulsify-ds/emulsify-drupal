<?php

namespace Drupal\emulsify\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\emulsify\Favicon\FaviconHeadBuilder;
use Drupal\emulsify\Favicon\FaviconThemeManager;
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
  private FaviconThemeManager $faviconThemeManager;

  /**
   * Logger channel for runtime favicon generation failures.
   */
  private LoggerInterface $logger;

  /**
   * Creates the favicon hook handler.
   */
  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
    ThemeSettingsProvider $themeSettingsProvider,
    private readonly ConfigFactoryInterface $configFactory,
    FileSystemInterface $fileSystem,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    FileUrlGeneratorInterface $fileUrlGenerator,
    TimeInterface $time,
    LockBackendInterface $lock,
  ) {
    $this->headBuilder = new FaviconHeadBuilder($fileUrlGenerator);
    $this->faviconThemeManager = new FaviconThemeManager(
      $themeSettingsProvider,
      $configFactory,
      $fileSystem,
      $cacheTagsInvalidator,
      $fileUrlGenerator,
      $time,
      $lock,
    );
    $this->logger = $loggerChannelFactory->get('emulsify');
  }

  /**
   * Handles hook_page_attachments_alter().
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    $theme_name = $this->themeManager->getActiveTheme()->getName();
    $settings = $this->faviconThemeManager->loadThemeSettings($theme_name);

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
      $this->logger->warning('Unable to resolve runtime favicon package for %theme: @message', [
        '%theme' => $theme_name,
        '@message' => $exception->getMessage(),
      ]);

      return !empty($settings['favicon_package_path']) && $this->faviconThemeManager->getPackageGenerator()->packageExists($settings['favicon_package_path']) ? $settings : NULL;
    }
  }

  /**
   * Resolves package settings when a portable SVG source is available.
   */
  private function doResolveRuntimePackageSettings(string $theme_name, array $settings): ?array {
    $source_file = $this->faviconThemeManager->resolveStoredSourceFile($settings);
    $package_status = $this->faviconThemeManager->buildPackageStatus($theme_name, $settings, $source_file);

    if ($package_status['package_exists']) {
      $settings['favicon_package_hash'] = $package_status['hash'];
      $settings['favicon_package_path'] = $package_status['path'];
      $settings['favicon_package_generated_at'] = (int) ($package_status['generated_at'] ?? 0);
      return $settings;
    }

    if (!$package_status['source_available']) {
      return NULL;
    }

    if (!empty($package_status['path'])) {
      $this->logger->notice('Attempting runtime fallback favicon generation for %theme at %path.', [
        '%theme' => $theme_name,
        '%path' => $package_status['path'],
      ]);
    }

    $generation = $this->faviconThemeManager->generatePackage($theme_name, $settings, FALSE);
    $settings = $generation['settings'];

    if (!empty($settings['favicon_package_path'])) {
      $this->logger->notice('Runtime fallback regenerated the missing favicon package for %theme at %path.', [
        '%theme' => $theme_name,
        '%path' => $settings['favicon_package_path'],
      ]);
    }

    return $settings;
  }

}
