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
    ], 'emulsify_favicon_ico',
    ];

    $attachments['#attached']['html_head_link'][] = [[
      'rel' => 'icon',
      'type' => 'image/svg+xml',
      'href' => $this->fileUrlGenerator->generateString($package_path . '/favicon.svg'),
    ], 'emulsify_favicon_svg',
    ];

    $attachments['#attached']['html_head_link'][] = [[
      'rel' => 'apple-touch-icon',
      'href' => $this->fileUrlGenerator->generateString($package_path . '/apple-touch-icon.png'),
    ], 'emulsify_favicon_ios',
    ];

    $attachments['#attached']['html_head_link'][] = [[
      'rel' => 'manifest',
      'href' => $this->fileUrlGenerator->generateString($package_path . '/site.webmanifest'),
    ], 'emulsify_favicon_manifest',
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
      $attachments['#attached']['html_head_link'] = array_values(array_filter(
        $attachments['#attached']['html_head_link'],
        static function (array $item): bool {
          $attributes = $item[0] ?? [];
          $rel = strtolower((string) ($attributes['rel'] ?? ''));
          return !in_array($rel, ['shortcut icon', 'icon', 'apple-touch-icon', 'manifest'], TRUE);
        },
      ));
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

}
