<?php

/**
 * @file
 * Theme hook implementations for Drupal paragraph entities.
 */

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Implements hook_preprocess_paragraph().
 *
 * Prepares variables for all paragraph templates:
 * - Exposes the paragraph index in the parent entity reference.
 * - Exposes the parent entity type and bundle.
 * - If the parent is a node, exposes the node's title.
 * - Copies raw attributes into container variables for use in Twig.
 *
 * @param array $variables
 *   An associative array of variables passed to the template.
 */
function emulsify_preprocess_paragraph(array &$variables) {
  /** @var ParagraphInterface $paragraph */
  $paragraph = $variables['paragraph'];

  // Expose the paragraph's zero-based index within its parent entity.
  $variables['paragraph_index'] = $paragraph->index();

  // If the paragraph has a parent entity, expose its type & bundle.
  if ($parent = $paragraph->getParentEntity()) {
    $variables['parent_type']   = $parent->getEntityTypeId();
    $variables['parent_bundle'] = $parent->bundle();

    // When that parent is a node, expose its title.
    if ($parent instanceof NodeInterface) {
      $variables['node_title'] = $parent->label();
    }
  }

  // If raw attributes were provided, copy them for a container wrapper.
  if (!empty($variables['attributes']) && is_array($variables['attributes'])) {
    // Full attributes array for a container element.
    $variables['container__attributes'] = $variables['attributes'];

    // Just the 'class' entries for additional CSS classes.
    $variables['container__additional_classes'] = $variables['attributes']['class'] ?? [];
  }
}
