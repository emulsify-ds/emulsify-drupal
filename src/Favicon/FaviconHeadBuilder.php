<?php

declare(strict_types=1);

namespace Drupal\emulsify\Favicon;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Builds and attaches favicon head tags.
 */
final class FaviconHeadBuilder {

  /**
   * The file URL generator.
   */
  private FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Creates a head builder instance.
   */
  public function __construct(FileUrlGeneratorInterface $file_url_generator) {
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Applies favicon head tags to page attachments.
   */
  public function apply(array &$attachments, array $settings): void {
    if (empty($settings['favicon_package_path'])) {
      return;
    }

    $package_path = $settings['favicon_package_path'];
    $theme_color = $settings['favicon_android_background_color'] ?? '#ffffff';
    $icon_name = trim((string) ($settings['favicon_ios_icon_name'] ?? ''));

    $this->removeConflictingLinks($attachments);

    $attachments['#attached']['html_head_link'][] = [[
      'rel' => 'icon',
      'href' => $this->fileUrlGenerator->generateString($package_path . '/favicon.ico'),
      'sizes' => 'any',
    ], FALSE,
    ];

    $attachments['#attached']['html_head_link'][] = [[
      'rel' => 'icon',
      'type' => 'image/svg+xml',
      'href' => $this->fileUrlGenerator->generateString($package_path . '/favicon.svg'),
    ], FALSE,
    ];

    $attachments['#attached']['html_head_link'][] = [[
      'rel' => 'apple-touch-icon',
      'href' => $this->fileUrlGenerator->generateString($package_path . '/apple-touch-icon.png'),
    ], FALSE,
    ];

    $attachments['#attached']['html_head_link'][] = [[
      'rel' => 'manifest',
      'href' => $this->fileUrlGenerator->generateString($package_path . '/site.webmanifest'),
    ], FALSE,
    ];

    $theme_color_attributes = [
      'name' => 'theme-color',
      'content' => $theme_color,
    ];
    $dark_theme_color = (string) ($settings['favicon_dark_theme_color'] ?? '');
    if ($dark_theme_color !== '') {
      $theme_color_attributes['media'] = '(prefers-color-scheme: light)';
    }
    $attachments['#attached']['html_head'][] = [[
      '#tag' => 'meta',
      '#attributes' => $theme_color_attributes,
    ], 'emulsify_favicon_theme_color',
    ];
    if ($dark_theme_color !== '') {
      $attachments['#attached']['html_head'][] = [[
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'theme-color',
          'content' => $dark_theme_color,
          'media' => '(prefers-color-scheme: dark)',
        ],
      ], 'emulsify_favicon_dark_theme_color',
      ];
    }

    if ($icon_name !== '') {
      $attachments['#attached']['html_head'][] = [[
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'apple-mobile-web-app-title',
          'content' => $icon_name,
        ],
      ], 'emulsify_favicon_ios_title',
      ];
    }
    $this->applyShareImage($attachments, $settings);
  }

  /**
   * Supplies fallback sharing metadata without overriding existing metadata.
   */
  private function applyShareImage(array &$attachments, array $settings): void {
    $package_path = (string) ($settings['favicon_package_path'] ?? '');
    if (empty($settings['favicon_package_enabled']) || empty($settings['favicon_share_image_enabled'])
      || !FaviconPackageGenerator::isManagedPackagePath($package_path, basename(dirname($package_path)))
      || !is_file($package_path . '/metadata.json') || !is_file($package_path . '/share-image.png')) {
      return;
    }
    foreach ($attachments['#attached']['html_head'] ?? [] as $item) {
      $attributes = $item[0]['#attributes'] ?? [];
      if (!is_array($attributes)) {
        continue;
      }
      foreach ($attributes as $attribute => $value) {
        if (in_array(strtolower((string) $attribute), ['name', 'property'], TRUE)
          && is_scalar($value) && in_array(strtolower(trim((string) $value)), ['og:image', 'twitter:image', 'twitter:card'], TRUE)) {
          return;
        }
      }
    }

    $url = $this->fileUrlGenerator->generateAbsoluteString($package_path . '/share-image.png');
    $alt = trim((string) ($settings['favicon_share_image_alt'] ?? ''));
    if ($alt === '') {
      $alt = (string) \Drupal::config('system.site')->get('name');
    }
    foreach ([
      ['property', 'og:image', $url],
      ['property', 'og:image:type', 'image/png'],
      ['property', 'og:image:width', '1200'],
      ['property', 'og:image:height', '630'],
      ['property', 'og:image:alt', $alt],
      ['name', 'twitter:card', 'summary_large_image'],
      ['name', 'twitter:image', $url],
      ['name', 'twitter:image:alt', $alt],
    ] as [$attribute, $name, $content]) {
      $attachments['#attached']['html_head'][] = [[
        '#tag' => 'meta',
        '#attributes' => [$attribute => $name, 'content' => $content],
      ], 'emulsify_favicon_share_' . str_replace(':', '_', $name),
      ];
    }
    CacheableMetadata::createFromRenderArray($attachments)
      ->addCacheTags(['config:' . basename(dirname($package_path)) . '.settings', 'config:system.site'])
      ->addCacheContexts(['url.site'])
      ->applyTo($attachments);
  }

  /**
   * Removes default Drupal favicon links before custom ones are attached.
   */
  private function removeConflictingLinks(array &$attachments): void {
    if (!empty($attachments['#attached']['html_head_link'])) {
      $head_links = [];
      foreach ($attachments['#attached']['html_head_link'] as $item) {
        $normalized = $this->normalizeHeadLinkAttachment($item);
        if ($normalized === NULL) {
          continue;
        }

        $rel = strtolower((string) $normalized[0]['rel']);
        if (!in_array($rel, ['shortcut icon', 'icon', 'apple-touch-icon', 'manifest'], TRUE)) {
          $head_links[] = $normalized;
        }
      }
      $attachments['#attached']['html_head_link'] = $head_links;
    }

    if (!empty($attachments['#attached']['html_head'])) {
      $attachments['#attached']['html_head'] = array_values(array_filter(
        $attachments['#attached']['html_head'],
        static function (array $item): bool {
          $element = $item[0] ?? [];
          $name = strtolower((string) (($element['#attributes']['name'] ?? '')));
          return !in_array($name, ['theme-color', 'apple-mobile-web-app-title'], TRUE);
        },
      ));
    }
  }

  /**
   * Normalizes one html_head_link attachment to Drupal core's expected shape.
   *
   * @return array{0: array<string, mixed>, 1: bool}|null
   *   A normalized link attachment, or NULL when the item is unusable.
   */
  private function normalizeHeadLinkAttachment(mixed $item): ?array {
    if (!is_array($item) || !isset($item[0]) || !is_array($item[0])) {
      return NULL;
    }

    $attributes = $item[0];
    if (empty($attributes['rel']) || empty($attributes['href'])) {
      return NULL;
    }

    $add_header = $item[1] ?? FALSE;

    return [
      $attributes,
      is_bool($add_header) ? $add_header : FALSE,
    ];
  }

}
