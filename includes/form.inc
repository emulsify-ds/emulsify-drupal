<?php

/**
 * @file
 * Theme hook implementations for Drupal forms.
 */

/**
 * Implements hook_theme_suggestions_form_alter().
 *
 * Adds template suggestions for rendering forms by their HTML ID:
 * - form--<form_id>.html.twig.
 *
 * This allows you to create form-specific Twig templates, e.g.:
 *   form--contact_form.html.twig
 *   form--user_login_form.html.twig
 *
 * @param string[] $suggestions
 *   An array of theme hook suggestion names. We append our custom suggestion.
 * @param array $variables
 *   An associative array containing:
 *   - element: The render array for the form, including '#id'.
 */
function emulsify_theme_suggestions_form_alter(array &$suggestions, array $variables) {
  // Check that the form render array has an '#id'.
  if (!empty($variables['element']['#id'])) {
    // Normalize the ID by converting hyphens to underscores.
    $form_id = str_replace('-', '_', $variables['element']['#id']);

    // Build a suggestion key "form__<form_id>".
    $suggestions[] = "form__{$form_id}";
  }
}
