<?php

declare(strict_types=1);

namespace Drupal\emulsify\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook handlers for form templates.
 */
final class FormHooks {

  /**
   * Handles hook_preprocess_block().
   *
   * @param array $variables
   *   Variables passed to block templates.
   */
  #[Hook('preprocess_block')]
  public function preprocessBlock(array &$variables): void {
    // Carry the placed block's stable ID into its exposed form before render.
    if (!empty($variables['elements']['#id']) && ($variables['content']['#form_id'] ?? '') === 'views_exposed_form') {
      $variables['content']['#emulsify_block_id'] = $variables['elements']['#id'];
    }
  }

  /**
   * Handles hook_theme_suggestions_form_alter().
   *
   * @param array $suggestions
   *   Theme hook suggestions for form output.
   * @param array $variables
   *   Variables passed to the form suggestion alter hook.
   */
  #[Hook('theme_suggestions_form_alter')]
  public function themeSuggestionsFormAlter(array &$suggestions, array $variables): void {
    $element = $variables['element'] ?? [];
    $form_id = $element['#form_id'] ?? $element['#id'] ?? NULL;

    // Match actual form IDs; DOM IDs remain a fallback for the generic rule.
    $specific_form_id = (string) ($element['#form_id'] ?? '');
    if (str_ends_with($specific_form_id, '_layout_builder_form') || str_starts_with($specific_form_id, 'layout_builder_')) {
      // A form-specific template must take precedence over this shared fallback.
      $suggestions[] = 'form__layout_builder_form';
    }

    if ($form_id) {
      // Prefer the stable form_id when present; Twig suggestions use underscores.
      $form_id = str_replace('-', '_', (string) $form_id);

      // Allow form--{form_id}.html.twig templates.
      $suggestions[] = "form__{$form_id}";
    }

    if ($specific_form_id === 'views_exposed_form') {
      // Views lists the most specific first; alter hooks need it last.
      foreach (array_reverse((array) ($element['#theme'] ?? [])) as $theme) {
        if (is_string($theme) && str_starts_with($theme, 'views_exposed_form__')) {
          $suggestions[] = 'form__' . $theme;
        }
      }

      if (!empty($element['#emulsify_block_id'])) {
        $suggestions[] = 'form__views_exposed_form__block__' . str_replace('-', '_', (string) $element['#emulsify_block_id']);
      }
    }
  }

}
