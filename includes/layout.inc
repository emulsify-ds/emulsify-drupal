<?php

/**
 * @file
 * Theme hook wrappers for Drupal layouts.
 */

use Drupal\emulsify\Hook\LayoutHooks;

/**
 * Implements hook_preprocess_layout().
 */
function emulsify_preprocess_layout(array &$variables) {
  LayoutHooks::preprocessLayout($variables);
}
