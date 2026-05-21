<?php

declare(strict_types=1);

namespace Drupal\emulsify\Hook;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\emulsify\Favicon\FaviconSettingsForm;

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
  }

  /**
   * Resolves the dedicated favicon settings form handler.
   */
  private function faviconSettingsForm(): FaviconSettingsForm {
    /** @var \Drupal\emulsify\Favicon\FaviconSettingsForm $form */
    $form = $this->classResolver->getInstanceFromDefinition(FaviconSettingsForm::class);
    return $form;
  }

}
