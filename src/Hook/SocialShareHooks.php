<?php

declare(strict_types=1);

namespace Drupal\emulsify\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\emulsify\Social\SocialShareHeadBuilder;
use Drupal\emulsify\Social\SocialShareImageResolver;
use Drupal\emulsify\Social\SocialShareSettings;

/**
 * Supplies social sharing metadata independently of favicon generation.
 */
final class SocialShareHooks {

  /**
   * Creates the social sharing hook handler.
   */
  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
    private readonly ThemeSettingsProvider $themeSettingsProvider,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Handles hook_page_attachments_alter().
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    $theme = $this->themeManager->getActiveTheme()->getName();
    CacheableMetadata::createFromRenderArray($attachments)
      ->addCacheTags(['config:' . $theme . '.settings'])
      ->applyTo($attachments);
    $builder = new SocialShareHeadBuilder($this->fileUrlGenerator, new SocialShareImageResolver($this->fileSystem));
    $builder->apply(
      $attachments,
      SocialShareSettings::loadThemeSettings($theme, $this->themeSettingsProvider),
      $theme,
      (string) $this->configFactory->get('system.site')->get('name'),
    );
  }

}
