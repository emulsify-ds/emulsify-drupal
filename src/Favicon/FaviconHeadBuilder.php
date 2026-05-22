<?php

declare(strict_types=1);

namespace Drupal\emulsify\Favicon;

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

    $attachments['#attached']['html_head'][] = [[
      '#tag' => 'meta',
      '#attributes' => [
        'name' => 'theme-color',
        'content' => $theme_color,
      ],
    ], 'emulsify_favicon_theme_color',
    ];

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
