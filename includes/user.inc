<?php

/**
 * @file
 * Theme hook wrappers for Drupal user entities.
 */

use Drupal\emulsify\Hook\UserHooks;

/**
 * Implements hook_theme_suggestions_user_alter().
 */
function emulsify_theme_suggestions_user_alter(array &$suggestions, array $variables) {
  UserHooks::themeSuggestionsUserAlter($suggestions, $variables);
}
