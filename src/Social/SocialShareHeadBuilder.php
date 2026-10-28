<?php

declare(strict_types=1);

namespace Drupal\emulsify\Social;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Adds fallback social metadata while preserving page-specific Metatag output.
 */
final class SocialShareHeadBuilder {

  /**
   * Creates the social metadata builder.
   */
  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly SocialShareImageResolver $imageResolver,
  ) {}

  /**
   * Applies configured fallbacks without replacing page-specific metadata.
   */
  public function apply(array &$attachments, array $settings, string $theme_name, string $site_name, string $page_url = ''): void {
    if (empty($settings['social_share_image_enabled'])) {
      return;
    }
    $existing = [];
    foreach ($attachments['#attached']['html_head'] ?? [] as $item) {
      $attributes = $item[0]['#attributes'] ?? [];
      if (!is_array($attributes)) {
        continue;
      }
      foreach ($attributes as $attribute => $value) {
        if (in_array(strtolower((string) $attribute), ['name', 'property'], TRUE) && is_scalar($value)) {
          $existing[strtolower(trim((string) $value))] = TRUE;
        }
      }
    }
    $has_page_image = array_intersect(array_keys($existing), [
      'og:image', 'og:image:url', 'og:image:secure_url',
      'twitter:image', 'twitter:image:src', 'twitter:card',
    ]) !== [];
    $cache = CacheableMetadata::createFromRenderArray($attachments)
      ->addCacheTags(['config:' . $theme_name . '.settings', 'config:system.site'])
      ->addCacheContexts(['url.site']);
    $image = [];
    if (!$has_page_image) {
      try {
        $image = $this->imageResolver->resolve($settings);
        if (!empty($image['file'])) {
          $access = $image['file']->access('download', NULL, TRUE);
          $cache->addCacheableDependency($image['file'])->addCacheableDependency($access);
          $cache->applyTo($attachments);
          if (!$access->isAllowed()) {
            $image = [];
          }
        }
        $image = $this->imageResolver->display($image);
      }
      catch (\InvalidArgumentException) {
        $image = [];
      }
    }
    $url = $image === [] ? '' : $this->fileUrlGenerator->generateAbsoluteString($image['uri']);
    $values = SocialShareMetadata::values($settings, $url, $image['info'] ?? [], $site_name, $page_url);
    $added = FALSE;
    foreach ($values as $name => $content) {
      // X falls back to Open Graph text, so a theme-wide Twitter default must
      // never mask a more specific page's Open Graph title or description.
      $page_open_graph_text = in_array($name, ['twitter:title', 'twitter:description'], TRUE)
        && isset($existing[str_replace('twitter:', 'og:', $name)]);
      if ($content === '' || isset($existing[$name]) || $page_open_graph_text) {
        continue;
      }
      $attribute = str_starts_with($name, 'og:') ? 'property' : 'name';
      $attachments['#attached']['html_head'][] = [[
        '#tag' => 'meta',
        '#attributes' => [$attribute => $name, 'content' => $content],
      ], 'emulsify_social_share_' . str_replace(':', '_', $name),
      ];
      $added = TRUE;
    }
    if ($added) {
      if (!SocialShareMetadata::isValidUrl(trim((string) ($settings['social_share_image_url'] ?? ''))) && ($values['og:url'] ?? '') !== '' && !isset($existing['og:url'])) {
        $cache->addCacheContexts(['url.path', 'url.query_args']);
      }
      $cache->applyTo($attachments);
    }
  }

}
