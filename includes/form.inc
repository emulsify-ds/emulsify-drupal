<?php

/**
 * @file
 * Theme hook wrappers for Drupal forms.
 */

use Drupal\emulsify\Hook\FormHooks;

/**
 * Implements hook_theme_suggestions_form_alter().
 */
function emulsify_theme_suggestions_form_alter(array &$suggestions, array $variables) {
  FormHooks::themeSuggestionsFormAlter($suggestions, $variables);
}
