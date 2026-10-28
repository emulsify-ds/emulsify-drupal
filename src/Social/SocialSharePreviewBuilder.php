<?php

declare(strict_types=1);

namespace Drupal\emulsify\Social;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Builds local social-card previews without requesting remote page metadata.
 */
final class SocialSharePreviewBuilder {

  use StringTranslationTrait;

  /**
   * Display-only values that the theme settings form must not persist.
   */
  public const FORM_KEYS = [
    'emulsify_social_preview_platform',
    'emulsify_social_preview_device',
  ];

  /**
   * Builds approximate platform cards from an already resolved local image.
   */
  public function build(array $settings, string $image_url, array $image_info, string $site_name, string $site_slogan = '', string $site_url = ''): array {
    $escape = static fn ($value): string => Html::escape((string) $value);
    $platforms = [
      'facebook' => $this->t('Facebook'),
      'x' => $this->t('X (Twitter)'),
      'linkedin' => $this->t('LinkedIn'),
      'pinterest' => $this->t('Pinterest'),
    ];
    $title = trim((string) ($settings['social_share_image_title'] ?? ''));
    $description = trim((string) ($settings['social_share_image_description'] ?? ''));
    $page_url = trim((string) ($settings['social_share_image_url'] ?? ''));
    $alt = trim((string) ($settings['social_share_image_alt'] ?? '')) ?: $site_name;
    $width = max(1, (int) ($image_info['width'] ?? 1200));
    $height = max(1, (int) ($image_info['height'] ?? 630));
    $image = $image_url === ''
      ? '<div class="emulsify-social-preview__empty">' . $escape($this->t('Save an image path or upload an image to preview it here.')) . '</div>'
      : sprintf('<img src="%s" alt="%s" width="%d" height="%d" loading="lazy" />', $escape($image_url), $escape($alt), $width, $height);
    $dimensions = $image_url === '' ? '' : $escape((string) $this->t('@width × @height pixels', ['@width' => $width, '@height' => $height]));
    $domain = parse_url($page_url ?: $site_url, PHP_URL_HOST) ?: ($page_url ?: $site_url);

    $markup = '<p class="emulsify-social-preview__platform" data-social-preview-platform>' . $escape((string) $platforms['facebook']) . '</p>'
      . '<article class="emulsify-social-preview__card" aria-label="' . $escape($this->t('Representative social card preview')) . '" data-social-preview-card data-platform="facebook" data-device="desktop">'
      . '<div class="emulsify-social-preview__image">' . $image . '</div>'
      . '<div class="emulsify-social-preview__body">'
      . '<div class="emulsify-social-preview__title" data-social-preview-title' . ($title === '' ? ' hidden' : '') . '>' . $escape($title) . '</div>'
      . '<div class="emulsify-social-preview__domain" data-social-preview-domain>' . $escape($domain) . '</div>'
      . '<div class="emulsify-social-preview__description" data-social-preview-description' . ($description === '' ? ' hidden' : '') . '>' . $escape($description) . '</div>'
      . '</div></article>'
      . '<p class="emulsify-social-preview__help">' . $dimensions . '</p>';

    $preview = [
      '#type' => 'container',
      '#attributes' => ['class' => ['emulsify-social-preview']],
      'intro' => [
        '#markup' => '<p>' . $escape($this->t('Try the saved image in different social cards. Platform layouts differ: X overlays the headline, LinkedIn uses compact link details, and Pinterest uses a portrait pin. These are representative previews; platforms may crop images differently and cache previous metadata.')) . '</p>',
      ],
      'defaults' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Default sharing metadata'),
        'social_share_image_title' => [
          '#type' => 'textfield',
          '#title' => $this->t('Default share title'),
          '#default_value' => $title,
          '#placeholder' => $site_name,
          '#description' => $this->t('Supplies og:title and twitter:title when page-specific metadata does not already provide them. Leave blank to omit these defaults.'),
          '#maxlength' => 200,
          '#attributes' => ['data-social-preview-control' => 'title'],
        ],
        'social_share_image_description' => [
          '#type' => 'textarea',
          '#title' => $this->t('Default share description'),
          '#default_value' => $description,
          '#placeholder' => $site_slogan,
          '#description' => $this->t('Supplies og:description and twitter:description when page-specific metadata does not already provide them. Leave blank to omit these defaults.'),
          '#rows' => 2,
          '#maxlength' => 500,
          '#attributes' => ['data-social-preview-control' => 'description'],
        ],
        'social_share_image_url' => [
          '#type' => 'textfield',
          '#title' => $this->t('Default share page URL'),
          '#default_value' => $page_url,
          '#placeholder' => $site_url,
          '#description' => $this->t('Supplies og:url when page-specific metadata does not already provide it. Leave blank to use each page’s canonical URL.'),
          '#maxlength' => 2048,
          '#attributes' => ['data-social-preview-control' => 'url', 'inputmode' => 'url'],
        ],
        'help' => [
          '#markup' => '<p class="emulsify-social-preview__help">' . $escape($this->t('The card and tag values update as you type. Save the form to publish these fallback defaults. Existing page-specific metadata takes priority. Image URLs and dimensions describe the saved resized image until image changes are saved. Platform and device only affect this preview; no page URL is fetched.')) . '</p>',
        ],
      ],
      'options' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Preview options'),
        'selectors' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['emulsify-social-preview__selectors']],
          'emulsify_social_preview_platform' => [
            '#type' => 'select',
            '#title' => $this->t('Platform'),
            '#options' => $platforms,
            '#default_value' => 'facebook',
            '#attributes' => ['data-social-preview-control' => 'platform'],
          ],
          'emulsify_social_preview_device' => [
            '#type' => 'select',
            '#title' => $this->t('Device'),
            '#options' => ['desktop' => $this->t('Desktop'), 'mobile' => $this->t('Mobile')],
            '#default_value' => 'desktop',
            '#attributes' => ['data-social-preview-control' => 'device'],
          ],
        ],
      ],
      'card' => ['#markup' => Markup::create($markup)],
      'metadata' => [
        '#type' => 'details',
        '#title' => $this->t('Fallback metadata after saving'),
        '#open' => FALSE,
        'table' => $this->buildMetadataTable($settings, $image_url, $image_info, $site_name, $site_url),
      ],
    ];
    $fields = ['#type' => 'container', 'defaults' => $preview['defaults']];
    unset($preview['defaults']);
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['emulsify-social-share'], 'data-social-share-preview' => TRUE, 'data-social-preview-example-url' => $site_url, 'data-social-preview-default-alt' => $site_name, 'data-social-preview-image-alt' => $alt],
      'fields' => $fields,
      'preview' => $preview,
    ];
  }

  /**
   * Shows the same fallback values used by the page attachment builder.
   */
  private function buildMetadataTable(array $settings, string $image_url, array $image_info, string $site_name, string $site_url): array {
    $rows = [];
    foreach (SocialShareMetadata::values($settings, $image_url, $image_info, $site_name, $site_url) as $tag => $value) {
      $empty_value = $tag === 'og:url'
        ? (string) $this->t('Each page’s canonical URL (example: @url)', ['@url' => $site_url])
        : (string) $this->t('Not set');
      if ($tag === 'og:url' && trim((string) ($settings['social_share_image_url'] ?? '')) === '') {
        $value = '';
      }
      $rows[] = [
        ['data' => ['#markup' => '<code>' . Html::escape($tag) . '</code>']],
        ['data' => ['#markup' => Markup::create('<span data-social-meta-value="' . Html::escape($tag) . '" data-empty-value="' . Html::escape($empty_value) . '">' . Html::escape($value !== '' ? $value : $empty_value) . '</span>')]],
      ];
    }
    return [
      '#type' => 'table',
      '#input' => FALSE,
      '#caption' => $this->t('Fallback metadata after saving'),
      '#header' => [$this->t('Meta tag'), $this->t('Default value')],
      '#rows' => $rows,
      '#attributes' => ['class' => ['emulsify-social-preview__metadata']],
    ];
  }

}
