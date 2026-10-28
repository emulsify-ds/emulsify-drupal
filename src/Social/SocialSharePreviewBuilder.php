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
   * Local preview values that the theme settings form must not persist.
   */
  public const FORM_KEYS = [
    'emulsify_social_preview_platform',
    'emulsify_social_preview_device',
    'emulsify_social_preview_title',
    'emulsify_social_preview_description',
    'emulsify_social_preview_url',
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
    $alt = trim((string) ($settings['social_share_image_alt'] ?? '')) ?: $site_name;
    $width = max(1, (int) ($image_info['width'] ?? 1200));
    $height = max(1, (int) ($image_info['height'] ?? 630));
    $image = $image_url === ''
      ? '<div class="emulsify-social-preview__empty">' . $escape($this->t('Save an image path or upload an image to preview it here.')) . '</div>'
      : sprintf('<img src="%s" alt="%s" width="%d" height="%d" loading="lazy" />', $escape($image_url), $escape($alt), $width, $height);
    $dimensions = $image_url === '' ? '' : $escape((string) $this->t('@width × @height pixels', ['@width' => $width, '@height' => $height]));
    $domain = parse_url($site_url, PHP_URL_HOST) ?: $site_url;

    $markup = '<p class="emulsify-social-preview__platform" data-social-preview-platform>' . $escape((string) $platforms['facebook']) . '</p>'
      . '<article class="emulsify-social-preview__card" data-social-preview-card data-platform="facebook" data-device="desktop">'
      . '<div class="emulsify-social-preview__image">' . $image . '</div>'
      . '<div class="emulsify-social-preview__body">'
      . '<div class="emulsify-social-preview__domain" data-social-preview-domain>' . $escape($domain) . '</div>'
      . '<div class="emulsify-social-preview__title" data-social-preview-title>' . $escape($site_name) . '</div>'
      . '<div class="emulsify-social-preview__description" data-social-preview-description>' . $escape($site_slogan) . '</div>'
      . '</div></article>'
      . '<p class="emulsify-social-preview__help">' . $dimensions . '</p>';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['emulsify-social-preview'], 'data-social-share-preview' => TRUE],
      'intro' => [
        '#markup' => '<p>' . $escape($this->t('Try the saved image in different social cards. These are approximate layouts; platforms may crop images differently and cache previous metadata.')) . '</p>',
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
        'emulsify_social_preview_title' => [
          '#type' => 'textfield',
          '#title' => $this->t('Preview title'),
          '#default_value' => $site_name,
          '#maxlength' => 200,
          '#attributes' => ['data-social-preview-control' => 'title'],
        ],
        'emulsify_social_preview_description' => [
          '#type' => 'textarea',
          '#title' => $this->t('Preview description'),
          '#default_value' => $site_slogan,
          '#rows' => 2,
          '#maxlength' => 500,
          '#attributes' => ['data-social-preview-control' => 'description'],
        ],
        'emulsify_social_preview_url' => [
          '#type' => 'textfield',
          '#title' => $this->t('Preview page URL'),
          '#default_value' => $site_url,
          '#maxlength' => 2048,
          '#attributes' => ['data-social-preview-control' => 'url', 'inputmode' => 'url'],
        ],
        'help' => [
          '#markup' => '<p class="emulsify-social-preview__help">' . $escape($this->t('Preview text stays in this browser and does not change page metadata or fetch the page URL. Save image changes to update the preview image.')) . '</p>',
        ],
      ],
      'card' => ['#markup' => Markup::create($markup)],
    ];
  }

}
