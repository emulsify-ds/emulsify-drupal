<?php

namespace Drupal\emulsify\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook handlers for user templates.
 */
final class UserHooks {

  /**
   * Handles hook_theme_suggestions_user_alter().
   *
   * @param array $suggestions
   *   Theme hook suggestions for user output.
   * @param array $variables
   *   Variables passed to the user suggestion alter hook.
   */
  #[Hook('theme_suggestions_user_alter')]
  public function themeSuggestionsUserAlter(array &$suggestions, array $variables): void {
    if (isset($variables['elements']['#view_mode'])) {
      $view_mode = $variables['elements']['#view_mode'];

      // Allow user--{view_mode}.html.twig templates.
      $suggestions[] = "user__{$view_mode}";
    }
  }

}
