<?php

declare(strict_types=1);

namespace Drupal\emulsify\Media;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds privacy and loading controls for media embeds in theme settings.
 */
final class MediaEmbedSettingsForm implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Creates the media embed settings form helper.
   */
  public function __construct(
    private readonly ThemeSettingsProvider $themeSettingsProvider,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get(ThemeSettingsProvider::class));
  }

  /**
   * Adds media embed settings for the theme being configured.
   */
  public function alter(array &$form, FormStateInterface $form_state): void {
    $settings = MediaEmbedSettings::loadThemeSettings(
      MediaEmbedSettings::resolveThemeName($form_state),
      $this->themeSettingsProvider,
    );

    $form['emulsify_media_embeds'] = [
      '#type' => 'details',
      '#title' => $this->t('Media embeds'),
      '#open' => FALSE,
    ];
    $media = &$form['emulsify_media_embeds'];
    $media['media_embed_privacy_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use privacy-enhanced embeds'),
      '#default_value' => $settings['media_embed_privacy_mode'],
      '#description' => $this->t('Reduces provider tracking where supported. The provider still receives data when the video plays.'),
    ];
    $media['media_embed_click_to_load'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require a click to load embedded media'),
      '#default_value' => $settings['media_embed_click_to_load'],
      '#description' => $this->t('Your browser does not contact the media provider until you click to load, except for a provider thumbnail when that option is selected.'),
    ];
    $media['media_embed_poster'] = [
      '#type' => 'select',
      '#title' => $this->t('Placeholder image'),
      '#options' => [
        'provider' => $this->t('Provider thumbnail'),
        'neutral' => $this->t('Neutral placeholder'),
      ],
      '#default_value' => $settings['media_embed_poster'],
      '#description' => $this->t('A provider thumbnail makes one image request to the provider. The neutral placeholder does not load a third-party image.'),
      '#states' => [
        'visible' => [
          ':input[name="media_embed_click_to_load"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['#validate'][] = [self::class, 'validateMediaEmbedSettings'];
  }

  /**
   * Normalizes flat values before core saves the theme settings form.
   */
  public static function validateMediaEmbedSettings(array &$form, FormStateInterface $form_state): void {
    foreach (MediaEmbedSettings::normalize($form_state->getValues()) as $key => $value) {
      $form_state->setValue($key, $value);
    }
  }

}
