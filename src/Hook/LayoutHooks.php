<?php

namespace Drupal\emulsify\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook handlers for layout templates.
 */
final class LayoutHooks {

  /**
   * Handles hook_preprocess_layout().
   *
   * @param array $variables
   *   Variables passed to layout templates.
   */
  #[Hook('preprocess_layout')]
  public function preprocessLayout(array &$variables): void {
    if (!empty($variables['attributes']) && is_array($variables['attributes'])) {
      // Preserve the full attribute bag for wrapper containers in Twig.
      $variables['container__attributes'] = $variables['attributes'];

      // Expose classes separately for easier class merging in templates.
      $variables['container__additional_classes'] = $variables['attributes']['class'] ?? [];
    }
  }

}
