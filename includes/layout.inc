<?php

/**
 * @file
 * Theme hook implementations for Drupal Layouts.
 */

/**
 * Implements hook_preprocess_layout().
 *
 * Prepares variables for all layout templates:
 * - Copies the original Drupal attributes into a `container__attributes` variable.
 * - Extracts any CSS classes into `container__additional_classes` for easy use in Twig.
 *
 * @param array $variables
 *   An associative array of variables passed to the template, including:
 *   - attributes: (array) The HTML attributes for the layout container.
 */
function emulsify_preprocess_layout(array &$variables) {
  // Only proceed if 'attributes' exists and is in array form.
  if (!empty($variables['attributes']) && is_array($variables['attributes'])) {
    // Duplicate all attributes for a wrapping container element.
    $variables['container__attributes'] = $variables['attributes'];

    // Pull out just the 'class' entries for additional CSS classes.
    $variables['container__additional_classes'] = $variables['attributes']['class'] ?? [];
  }
}
