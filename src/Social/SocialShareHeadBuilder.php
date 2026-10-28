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
   * Applies the configured theme-wide fallback image.
   */
  public function apply(array &$attachments, array $settings, string $theme_name, string $site_name): void {
    if (empty($settings['social_share_image_enabled'])) {
      return;
    }
    foreach ($attachments['#attached']['html_head'] ?? [] as $item) {
      $attributes = $item[0]['#attributes'] ?? [];
      if (!is_array($attributes)) {
        continue;
      }
      foreach ($attributes as $attribute => $value) {
        if (in_array(strtolower((string) $attribute), ['name', 'property'], TRUE)
          && is_scalar($value) && in_array(strtolower(trim((string) $value)), [
            'og:image', 'og:image:url', 'og:image:secure_url',
            'twitter:image', 'twitter:image:src', 'twitter:card',
          ], TRUE)) {
          return;
        }
      }
    }
    try {
      $image = $this->imageResolver->resolve($settings);
    }
    catch (\InvalidArgumentException) {
      return;
    }
    if ($image === []) {
      return;
    }
    $cache = CacheableMetadata::createFromRenderArray($attachments)
      ->addCacheTags(['config:' . $theme_name . '.settings', 'config:system.site'])
      ->addCacheContexts(['url.site']);
    if ($image['file']) {
      $access = $image['file']->access('download', NULL, TRUE);
      $cache->addCacheableDependency($image['file'])->addCacheableDependency($access);
      $cache->applyTo($attachments);
      if (!$access->isAllowed()) {
        return;
      }
    }
    $url = $this->fileUrlGenerator->generateAbsoluteString($image['uri']);
    $alt = trim((string) ($settings['social_share_image_alt'] ?? '')) ?: $site_name;
    foreach ([
      ['property', 'og:image', $url],
      ['property', 'og:image:type', $image['info']['mime']],
      ['property', 'og:image:width', (string) $image['info']['width']],
      ['property', 'og:image:height', (string) $image['info']['height']],
      ['property', 'og:image:alt', $alt],
      ['name', 'twitter:card', 'summary_large_image'],
      ['name', 'twitter:image', $url],
      ['name', 'twitter:image:alt', $alt],
    ] as [$attribute, $name, $content]) {
      $attachments['#attached']['html_head'][] = [[
        '#tag' => 'meta',
        '#attributes' => [$attribute => $name, 'content' => $content],
      ], 'emulsify_social_share_' . str_replace(':', '_', $name),
      ];
    }
    $cache->applyTo($attachments);
  }

}
