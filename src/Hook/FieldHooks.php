<?php

declare(strict_types=1);

namespace Drupal\emulsify\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Theme hook handlers for field templates.
 */
final class FieldHooks {

  /**
   * Handles hook_preprocess_field().
   *
   * @param array $variables
   *   Variables passed to field templates.
   */
  #[Hook('preprocess_field')]
  public function preprocessField(array &$variables): void {
    if (
      $variables['field_type'] === 'entity_reference_revisions'
      && $variables['element']['#items']->getItemDefinition()->getSetting('target_type') === 'paragraph'
    ) {
      $delta = 0;
      foreach ($variables['items'] as &$item) {
        if (
          isset($item['content']['#paragraph'])
          && $item['content']['#paragraph'] instanceof ParagraphInterface
        ) {
          // Persist render order on the render array instead of mutating the
          // paragraph entity with temporary dynamic state.
          $item['content']['#emulsify_paragraph_index'] = $delta++;
        }
      }
      // Break the reference from foreach by-reference iteration.
      unset($item);
    }
  }

  /**
   * Handles hook_theme_suggestions_field_alter().
   *
   * @param array $suggestions
   *   Theme hook suggestions for field output.
   * @param array $variables
   *   Variables passed to the field suggestion alter hook.
   * @param string $hook
   *   The hook name currently being altered.
   */
  #[Hook('theme_suggestions_field_alter')]
  public function themeSuggestionsFieldAlter(array &$suggestions, array $variables, string $hook): void {
    if ($hook !== 'field') {
      return;
    }

    $entity_type = $variables['element']['#entity_type'] ?? '';
    $field_name = $variables['element']['#field_name'] ?? '';
    $bundle = $variables['element']['#bundle'] ?? '';
    $view_mode = $variables['element']['#view_mode'] ?? '';

    if ($entity_type && $field_name) {
      // Support field--{entity_type}--{field_name}.html.twig.
      $suggestions[] = "field__{$entity_type}__{$field_name}";
    }

    if ($entity_type && $field_name && $bundle && $view_mode) {
      // Support field--{entity_type}--{field_name}--{bundle}--{view_mode}.html.twig.
      $suggestions[] = "field__{$entity_type}__{$field_name}__{$bundle}__{$view_mode}";
    }
  }

}
