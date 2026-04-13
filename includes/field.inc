<?php

/**
 * @file
 * Theme hook wrappers for Drupal fields.
 */

use Drupal\emulsify\Hook\FieldHooks;

/**
 * Implements hook_preprocess_field().
 */
function emulsify_preprocess_field(array &$variables) {
  FieldHooks::preprocessField($variables);
}

/**
 * Implements hook_theme_suggestions_field_alter().
 */
function emulsify_theme_suggestions_field_alter(array &$suggestions, array $variables, $hook) {
  FieldHooks::themeSuggestionsFieldAlter($suggestions, $variables, $hook);
}
