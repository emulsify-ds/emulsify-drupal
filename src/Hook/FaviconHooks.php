<?php

declare(strict_types=1);

namespace Drupal\emulsify\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\emulsify\Favicon\FaviconHeadBuilder;
use Drupal\emulsify\Favicon\FaviconPackageGenerator;
use Drupal\emulsify\Favicon\FaviconSettings;

/**
 * Theme hook handlers for generated favicon packages.
 */
final class FaviconHooks {

  /**
   * Builds head tags for generated favicon packages.
   */
  private FaviconHeadBuilder $headBuilder;

  /**
   * The file system service.
   */
  private FileSystemInterface $fileSystem;

  /**
   * Creates the favicon hook handler.
   */
  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
    private readonly ThemeSettingsProvider $themeSettingsProvider,
    private readonly ConfigFactoryInterface $configFactory,
    FileSystemInterface $fileSystem,
    FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    $this->headBuilder = new FaviconHeadBuilder($fileUrlGenerator);
    $this->fileSystem = $fileSystem;
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

    if (
      empty($settings['favicon_package_path'])
      || !FaviconPackageGenerator::isManagedPackagePath($settings['favicon_package_path'], $theme_name)
      || !$this->packageExists($settings['favicon_package_path'])
    ) {
      return;
    }

    $this->headBuilder->apply($attachments, $settings);
  }

  /**
   * Determines whether a generated package directory exists.
   */
  private function packageExists(string $package_directory): bool {
    $realpath = $this->fileSystem->realpath($package_directory);
    return is_string($realpath)
      && is_dir($realpath)
      && is_file($realpath . '/metadata.json');
  }

  /**
   * Returns the current site name.
   */
  private function getSiteName(): string {
    return (string) $this->configFactory->get('system.site')->get('name');
  }

}
