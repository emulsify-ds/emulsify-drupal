<?php

declare(strict_types=1);

namespace Drupal\emulsify\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\Template\AttributeValueBase;

/**
 * Associates inline errors before form controls are rendered.
 */
final class FormErrorHooks {

  public function __construct(private readonly ModuleHandlerInterface $moduleHandler) {}

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
      $element_info['#pre_render'][] = [self::class, 'preRenderErrors'];
    }
  }

  /**
   * Adds error references after validation and before children are rendered.
   */
  #[TrustedCallback]
  public static function preRenderErrors(array $element): array {
    if (empty($element['#errors']) || !empty($element['#error_no_message']) || empty($element['#id'])) {
      return $element;
    }
    foreach ($element['#theme_wrappers'] ?? [] as $key => $wrapper) {
      $hook = explode('__', is_string($key) ? $key : $wrapper)[0];
      if (in_array($hook, ['form_element', 'details', 'fieldset', 'datetime_wrapper'], TRUE)) {
        $error_id = $element['#id'] . '--error';
        $element['#attributes']['aria-describedby'] = self::mergeDescriptions($element['#attributes']['aria-describedby'] ?? '', $error_id);
        if ($hook === 'details') {
          $element['#summary_attributes']['aria-describedby'] = self::mergeDescriptions($element['#summary_attributes']['aria-describedby'] ?? '', $error_id);
        }
        self::describeChildren($element, $error_id);
        break;
      }
    }
    return $element;
  }

  /**
   * Connects composite controls to their group's message as well as their help.
   */
  private static function describeChildren(array &$element, string $error_id): void {
    foreach (Element::children($element) as $key) {
      $child = &$element[$key];
      if (($child['#access'] ?? TRUE) === FALSE) {
        continue;
      }
      if (!empty($child['#input']) && !in_array($child['#type'], ['hidden', 'submit', 'button'], TRUE)) {
        $child['#attributes']['aria-describedby'] = self::mergeDescriptions($child['#attributes']['aria-describedby'] ?? '', $error_id);
      }
      self::describeChildren($child, $error_id);
    }
  }

  /**
   * Restores references that core fieldset preprocessing replaces with help.
   */
  #[Hook('preprocess_fieldset')]
  public function preprocessFieldset(array &$variables): void {
    if (!empty($variables['errors'])) {
      $descriptions = explode(' ', self::mergeDescriptions($variables['element']['#attributes']['aria-describedby'] ?? ''));
      if (!empty($variables['description']['content'])) {
        // Composite controls move their description to the fieldset wrapper ID.
        // Core's current attributes already contain that rendered description.
        $descriptions = array_diff($descriptions, [$variables['element']['#id'] . '--description']);
      }
      $variables['attributes']['aria-describedby'] = self::mergeDescriptions(
        $variables['attributes']['aria-describedby'] ?? '',
        $descriptions,
      );
    }
  }

  /**
   * Merges ID references without replacing help text or repeating tokens.
   */
  private static function mergeDescriptions(mixed ...$descriptions): string {
    $ids = [];
    foreach ($descriptions as $description) {
      if ($description instanceof AttributeValueBase) {
        $description = $description->value();
      }
      $text = is_array($description) ? implode(' ', $description) : (string) $description;
      array_push($ids, ...preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY));
    }
    return implode(' ', array_unique($ids));
  }

}
