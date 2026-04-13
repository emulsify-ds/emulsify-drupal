<?php

namespace Drupal\emulsify\Hook;

/**
 * Theme hook handlers for layout templates.
 *
 * These methods keep preprocess logic centralized while procedural hook
 * wrappers remain in place for Drupal 10/11 compatibility.
 */
final class LayoutHooks {

  /**
   * Handles hook_preprocess_layout().
   *
   * @param array $variables
   *   Variables passed to layout templates.
   */
  public static function preprocessLayout(array &$variables): void {
    if (!empty($variables['attributes']) && is_array($variables['attributes'])) {
      // Preserve the full attribute bag for wrapper containers in Twig.
      $variables['container__attributes'] = $variables['attributes'];

      // Expose classes separately for easier class merging in templates.
      $variables['container__additional_classes'] = $variables['attributes']['class'] ?? [];
    }
  }

}
