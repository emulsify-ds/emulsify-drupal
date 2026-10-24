<?php

declare(strict_types=1);

namespace Drupal\emulsify\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;
use Drupal\emulsify\Render\FormErrorRenderer;

/**
 * Prepares form controls and associates their inline errors before rendering.
 */
final class FormErrorHooks {

  public function __construct(private readonly ModuleHandlerInterface $moduleHandler) {}

  /**
   * Supplies per-option attributes as arrays for create_attribute() in Twig.
   */
  #[Hook('preprocess_select')]
  public function preprocessSelect(array &$variables): void {
    self::normalizeSelectOptions($variables['options'], $variables['element']['#options_attributes'] ?? []);
  }

  /**
   * Normalizes flat and grouped options without mutating caller-owned objects.
   */
  private static function normalizeSelectOptions(array &$options, array $attributes_by_value): void {
    foreach ($options as &$option) {
      if ($option['type'] === 'optgroup') {
        self::normalizeSelectOptions($option['options'], $attributes_by_value);
      }
      elseif ($option['type'] === 'option') {
        $attributes = $attributes_by_value[$option['value']] ?? [];
        $preprocessed_attributes = $option['attributes'] ?? [];
        if ($attributes instanceof Attribute) {
          $attributes = $attributes->toArray();
        }
        if ($preprocessed_attributes instanceof Attribute) {
          $preprocessed_attributes = $preprocessed_attributes->toArray();
        }
        // Honor explicit preprocessing overrides while retaining other values
        // supplied through the form element's #options_attributes property.
        $option['attributes'] = array_replace($attributes, $preprocessed_attributes);
      }
    }
  }

  /**
   * Handles hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info): void {
    // Core suppresses inline messages unless Inline Form Errors is enabled.
    if (!$this->moduleHandler->moduleExists('inline_form_errors')) {
      return;
    }
    foreach ($info as &$element_info) {
      $element_info['#pre_render'][] = [FormErrorRenderer::class, 'preRenderErrors'];
    }
  }

  /**
   * Restores references that core fieldset preprocessing replaces with help.
   */
  #[Hook('preprocess_fieldset')]
  public function preprocessFieldset(array &$variables): void {
    FormErrorRenderer::preprocessFieldset($variables);
  }

}
