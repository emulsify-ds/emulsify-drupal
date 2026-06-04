<?php

declare(strict_types=1);

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
    $form_id = $variables['element']['#form_id'] ?? $variables['element']['#id'] ?? NULL;

    if ($form_id) {
      // Prefer the stable form_id when present; Twig suggestions use underscores.
      $form_id = str_replace('-', '_', (string) $form_id);

      // Allow form--{form_id}.html.twig templates.
      $suggestions[] = "form__{$form_id}";
    }
  }

}
