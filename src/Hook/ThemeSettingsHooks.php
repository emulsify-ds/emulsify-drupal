<?php

declare(strict_types=1);

namespace Drupal\emulsify\Hook;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\emulsify\Favicon\FaviconSettingsForm;
use Drupal\emulsify\Media\MediaEmbedSettingsForm;
use Drupal\emulsify\Social\SocialShareSettingsForm;

/**
 * Theme settings hook entrypoint.
 */
final class ThemeSettingsHooks {

  /**
   * Creates the theme settings hook handler.
   */
  public function __construct(
    private readonly ClassResolverInterface $classResolver,
  ) {}

  /**
   * Handles hook_form_system_theme_settings_alter().
   */
  #[Hook('form_system_theme_settings_alter')]
  public function formSystemThemeSettingsAlter(array &$form, FormStateInterface $form_state): void {
    $this->faviconSettingsForm()->alter($form, $form_state);
    $this->mediaEmbedSettingsForm()->alter($form, $form_state);
    $this->classResolver->getInstanceFromDefinition(SocialShareSettingsForm::class)->alter($form, $form_state);
  }

  /**
   * Resolves the dedicated favicon settings form handler.
   */
  private function faviconSettingsForm(): FaviconSettingsForm {
    /** @var \Drupal\emulsify\Favicon\FaviconSettingsForm $form */
    $form = $this->classResolver->getInstanceFromDefinition(FaviconSettingsForm::class);
    return $form;
  }

  /**
   * Resolves the dedicated media embed settings form handler.
   */
  private function mediaEmbedSettingsForm(): MediaEmbedSettingsForm {
    /** @var \Drupal\emulsify\Media\MediaEmbedSettingsForm $form */
    $form = $this->classResolver->getInstanceFromDefinition(MediaEmbedSettingsForm::class);
    return $form;
  }

}
