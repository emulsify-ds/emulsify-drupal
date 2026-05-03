<?php

namespace Drupal\emulsify\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * Theme hook handlers for paragraph templates.
 */
final class ParagraphHooks {

  /**
   * Handles hook_preprocess_paragraph().
   *
   * @param array $variables
   *   Variables passed to paragraph templates.
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    $paragraph = $variables['paragraph'];

    // Index is populated in field preprocess and reused by paragraph templates.
    $variables['paragraph_index'] = $paragraph->index;

    if ($parent = $paragraph->getParentEntity()) {
      // Expose parent metadata for contextual template decisions.
      $variables['parent_type'] = $parent->getEntityTypeId();
      $variables['parent_bundle'] = $parent->bundle();

      if ($parent instanceof NodeInterface) {
        // Node title is commonly used for contextual paragraph rendering.
        $variables['node_title'] = $parent->label();
      }
    }

    if (!empty($variables['attributes']) && is_array($variables['attributes'])) {
      // Keep raw attributes and classes available for container wrappers.
      $variables['container__attributes'] = $variables['attributes'];
      $variables['container__additional_classes'] = $variables['attributes']['class'] ?? [];
    }
  }

}
