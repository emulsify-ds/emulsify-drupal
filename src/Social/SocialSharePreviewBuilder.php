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
    $options = '';
    foreach ($platforms as $value => $label) {
      $options .= '<option value="' . $value . '">' . $escape((string) $label) . '</option>';
    }
    $alt = trim((string) ($settings['social_share_image_alt'] ?? '')) ?: $site_name;
    $width = max(1, (int) ($image_info['width'] ?? 1200));
    $height = max(1, (int) ($image_info['height'] ?? 630));
    $image = $image_url === ''
      ? '<div class="emulsify-social-preview__empty">' . $escape($this->t('Save an image path or upload an image to preview it here.')) . '</div>'
      : sprintf('<img src="%s" alt="%s" width="%d" height="%d" loading="lazy" />', $escape($image_url), $escape($alt), $width, $height);
    $dimensions = $image_url === '' ? '' : $escape((string) $this->t('@width × @height pixels', ['@width' => $width, '@height' => $height]));
    $domain = parse_url($site_url, PHP_URL_HOST) ?: $site_url;

    $markup = '<div class="emulsify-social-preview" data-social-share-preview>'
      . '<p>' . $escape($this->t('Try the saved image in different social cards. These are approximate layouts; platforms may crop images differently and cache previous metadata.')) . '</p>'
      . '<fieldset class="emulsify-social-preview__controls"><legend>' . $escape($this->t('Preview options')) . '</legend>'
      . '<div class="emulsify-social-preview__selectors">'
      . '<label>' . $escape($this->t('Platform')) . '<select data-social-preview-control="platform">' . $options . '</select></label>'
      . '<label>' . $escape($this->t('Device')) . '<select data-social-preview-control="device"><option value="desktop">' . $escape($this->t('Desktop')) . '</option><option value="mobile">' . $escape($this->t('Mobile')) . '</option></select></label>'
      . '</div>'
      . '<label>' . $escape($this->t('Preview title')) . '<input type="text" maxlength="200" value="' . $escape($site_name) . '" data-social-preview-control="title" /></label>'
      . '<label>' . $escape($this->t('Preview description')) . '<textarea rows="2" maxlength="500" data-social-preview-control="description">' . $escape($site_slogan) . '</textarea></label>'
      . '<label>' . $escape($this->t('Preview page URL')) . '<input type="text" inputmode="url" maxlength="2048" value="' . $escape($site_url) . '" data-social-preview-control="url" /></label>'
      . '<p class="emulsify-social-preview__help">' . $escape($this->t('Preview text stays in this browser and does not change page metadata or fetch the page URL. Save image changes to update the preview image.')) . '</p>'
      . '</fieldset>'
      . '<p class="emulsify-social-preview__platform" data-social-preview-platform>' . $escape((string) $platforms['facebook']) . '</p>'
      . '<article class="emulsify-social-preview__card" data-social-preview-card data-platform="facebook" data-device="desktop">'
      . '<div class="emulsify-social-preview__image">' . $image . '</div>'
      . '<div class="emulsify-social-preview__body">'
      . '<div class="emulsify-social-preview__domain" data-social-preview-domain>' . $escape($domain) . '</div>'
      . '<div class="emulsify-social-preview__title" data-social-preview-title>' . $escape($site_name) . '</div>'
      . '<div class="emulsify-social-preview__description" data-social-preview-description>' . $escape($site_slogan) . '</div>'
      . '</div></article>'
      . '<p class="emulsify-social-preview__help">' . $dimensions . '</p>'
      . '</div>';

    return ['#markup' => Markup::create($markup)];
  }

}
