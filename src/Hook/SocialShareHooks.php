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
use Drupal\emulsify\Social\SocialShareMetadata;
use Drupal\emulsify\Social\SocialShareSettings;
use Symfony\Component\HttpFoundation\RequestStack;

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
    private readonly RequestStack $requestStack,
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
    $site = $this->configFactory->get('system.site');
    $builder->apply(
      $attachments,
      SocialShareSettings::loadThemeSettings($theme, $this->themeSettingsProvider),
      $theme,
      (string) $site->get('name'),
      $this->getPageUrl($attachments),
    );
  }

  /**
   * Uses a page's canonical link, or the current URL without tracking queries.
   */
  private function getPageUrl(array $attachments): string {
    foreach ($attachments['#attached']['html_head'] ?? [] as $item) {
      $element = $item[0] ?? [];
      $attributes = $element['#attributes'] ?? [];
      if (strtolower((string) ($element['#tag'] ?? '')) === 'link' && is_array($attributes)
        && strtolower((string) ($attributes['rel'] ?? '')) === 'canonical'
        && is_string($attributes['href'] ?? NULL) && SocialShareMetadata::isValidUrl($attributes['href'])) {
        return $attributes['href'];
      }
    }
    foreach ($attachments['#attached']['html_head_link'] ?? [] as $link) {
      $attributes = $link[0] ?? [];
      if (is_array($attributes) && strtolower((string) ($attributes['rel'] ?? '')) === 'canonical'
        && is_string($attributes['href'] ?? NULL) && SocialShareMetadata::isValidUrl($attributes['href'])) {
        return $attributes['href'];
      }
    }
    $request = $this->requestStack->getCurrentRequest();
    return $request ? $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo() : '';
  }

}
