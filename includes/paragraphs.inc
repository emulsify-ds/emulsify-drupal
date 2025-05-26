<?php

use Drupal\node\NodeInterface;

/**
 * @file
 * Paragraph-related hook implementations.
 */

/**
 * Implements hook_preprocess_paragraph().
 */
function emulsify_preprocess_paragraph(array &$variables) {
  /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
  $paragraph = $variables['paragraph'];

  // Expose the index.
  $variables['paragraph_index'] = $paragraph->index();

  // If its embedded in another entity, expose its type & bundle.
  if ($parent = $paragraph->getParentEntity()) {
    $variables['parent_type']   = $parent->getEntityTypeId();
    $variables['parent_bundle'] = $parent->bundle();

    // Only when that parent is a node do we set node_title.
    if ($parent instanceof NodeInterface) {
      $variables['node_title'] = $parent->label();
    }
  }

  // Copy any raw attributes into a "container" array.
  if (!empty($variables['attributes']) && is_array($variables['attributes'])) {
    $variables['container__attributes']          = $variables['attributes'];
    $variables['container__additional_classes']  = $variables['attributes']['class'] ?? [];
  }
}
