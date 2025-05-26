<?php

/**
 * @file
 * Functions to support theming layout elements.
 */

/**
 * Implements hook_preprocess_layout().
 */
function emulsify_preprocess_layout(&$variables) {
  // Check and merge Drupal attributes with container attributes.
  if (isset($variables['attributes']) && is_array($variables['attributes'])) {
    $variables['container__attributes'] = $variables['attributes'];
    $variables['container__additional_classes'] = $variables['attributes']['class'] ?? [];
  }
}
