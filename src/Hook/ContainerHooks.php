<?php

declare(strict_types=1);

namespace Drupal\emulsify\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook handlers for container templates.
 */
final class ContainerHooks {

  /**
   * Handles hook_theme_suggestions_container_alter().
   *
   * Appends class, structural form path, selector, and ID suggestions in order
   * of precedence, with the most specific suggestion last.
   *
   * @param array $suggestions
   *   Theme hook suggestions for container output.
   * @param array $variables
   *   Variables passed to the container suggestion alter hook.
   */
  #[Hook('theme_suggestions_container_alter')]
  public function themeSuggestionsContainerAlter(array &$suggestions, array $variables): void {
    $element = $variables['element'];
    $attributes = $element['#attributes'] ?? [];

    foreach ((array) ($attributes['class'] ?? []) as $class) {
      if ($class !== '') {
        $suggestions[] = 'container__class__' . str_replace('-', '_', (string) $class);
      }
    }

    // Use the structural form path: #parents can flatten when #tree is FALSE.
    $path = 'container__parents';
    foreach ($element['#array_parents'] ?? [] as $parent) {
      $path .= '__' . str_replace('-', '_', (string) $parent);
      $suggestions[] = $path;
    }

    // The selector stays stable when Drupal adds unique suffixes to form IDs.
    if (!empty($attributes['data-drupal-selector'])) {
      $suggestions[] = 'container__selector__' . str_replace('-', '_', (string) $attributes['data-drupal-selector']);
    }

    // Container preprocessing copies #id to attributes only for form elements.
    $id = $attributes['id'] ?? (isset($element['#array_parents']) ? ($element['#id'] ?? '') : '');
    if ($id !== '') {
      $suggestions[] = 'container__id__' . str_replace('-', '_', (string) $id);
    }
  }

}
