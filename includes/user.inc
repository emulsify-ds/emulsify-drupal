<?php

/**
 * @file
 * Theme hook implementations for Drupal user entities.
 */

/**
 * Implements hook_theme_suggestions_user_alter().
 *
 * Adds template suggestions for rendering user entities by view mode:
 * - user--<view_mode>.html.twig
 *
 * For example, if the user is rendered in the “compact” view mode, this will
 * allow you to create a template named:
 *   user--compact.html.twig
 *
 * @param string[] $suggestions
 *   An array of theme hook suggestion names. We append to this.
 * @param array $variables
 *   An associative array containing:
 *   - elements: The render array for the user entity, including '#view_mode'.
 */
function emulsify_theme_suggestions_user_alter(array &$suggestions, array $variables) {
  // Ensure the view mode is available on the render array.
  if (isset($variables['elements']['#view_mode'])) {
    $view_mode = $variables['elements']['#view_mode'];

    // Build a suggestion key in the form "user__<view_mode>".
    $suggestions[] = "user__{$view_mode}";
  }
}
