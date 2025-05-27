<?php

/**
 * @file
 * Field-related hook implementations for the Emulsify theme.
 */

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Core\Render\Element;

/**
 * Implements hook_preprocess_field().
 *
 * Adds a zero-based index to each paragraph item in an
 * entity_reference_revisions field referencing paragraphs.
 *
 * @param array $variables
 *   Template variables array, containing:
 *   - field_type: The field’s base type (e.g., 'entity_reference_revisions').
 *   - element: The render array for the field, with '#items'.
 *   - items: An array of rendered items, each with ['content']['#paragraph'].
 */
function emulsify_preprocess_field(array &$variables) {
  // Only target entity_reference_revisions fields that point to paragraphs.
  if (
    $variables['field_type'] === 'entity_reference_revisions'
    && $variables['element']['#items']->getItemDefinition()->getSetting('target_type') === 'paragraph'
  ) {
    $delta = 0;
    foreach ($variables['items'] as $key => &$item) {
      // Each item’s render array should have the paragraph object.
      if (
        isset($item['content']['#paragraph'])
        && $item['content']['#paragraph'] instanceof ParagraphInterface
      ) {
        // Assign the index for use in Twig.
        $item['content']['#paragraph']->index = $delta++;
      }
    }
    unset($item);
  }
}

/**
 * Implements hook_theme_suggestions_field_alter().
 *
 * Adds template suggestions for field renderings based on:
 * - Entity type
 * - Field name
 * - Bundle
 * - View mode
 *
 * Suggestions generated:
 * - field--[entity_type]--[field_name].html.twig
 * - field--[entity_type]--[field_name]--[bundle]--[view_mode].html.twig
 *
 * @param string[] $suggestions
 *   Existing theme hook suggestions; we append ours.
 * @param array $variables
 *   Template variables array, containing:
 *   - element: The render array for the field, with metadata keys:
 *     '#entity_type', '#field_name', '#bundle', '#view_mode'.
 * @param string $hook
 *   The base hook name being invoked (should be 'field').
 */
function emulsify_theme_suggestions_field_alter(array &$suggestions, array $variables, $hook) {
  // Only act on the 'field' hook.
  if ($hook !== 'field') {
    return;
  }

  // Extract metadata from the render array.
  $entity_type = $variables['element']['#entity_type'] ?? '';
  $field_name  = $variables['element']['#field_name']  ?? '';
  $bundle      = $variables['element']['#bundle']      ?? '';
  $view_mode   = $variables['element']['#view_mode']   ?? '';

  // Base suggestion: field--[entity_type]--[field_name].html.twig
  if ($entity_type && $field_name) {
    $suggestions[] = "field__{$entity_type}__{$field_name}";
  }

  // Detailed suggestion: field--[entity_type]--[field_name]--[bundle]--[view_mode].html.twig
  if ($entity_type && $field_name && $bundle && $view_mode) {
    $suggestions[] = "field__{$entity_type}__{$field_name}__{$bundle}__{$view_mode}";
  }
}
