<?php

namespace Drupal\emulsify\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook handlers for form templates.
 */
final class FormHooks {

  /**
   * Handles hook_theme_suggestions_form_alter().
   *
   * @param array $suggestions
   *   Theme hook suggestions for form output.
   * @param array $variables
   *   Variables passed to the form suggestion alter hook.
   */
  #[Hook('theme_suggestions_form_alter')]
  public function themeSuggestionsFormAlter(array &$suggestions, array $variables): void {
    if (!empty($variables['element']['#id'])) {
      // Drupal form IDs often include dashes; Twig suggestions use underscores.
      $form_id = str_replace('-', '_', $variables['element']['#id']);

      // Allow form--{form_id}.html.twig templates.
      $suggestions[] = "form__{$form_id}";
    }
  }

}
