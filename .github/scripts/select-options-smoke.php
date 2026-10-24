<?php

/**
 * @file
 * Verifies select option attributes through Form API and the actual template.
 *
 * Run through Drush in the installed form_a11y fixture.
 */

declare(strict_types=1);

use Drupal\Core\Form\FormPreprocess;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Template\Attribute;
use Drupal\emulsify\Hook\FormErrorHooks;
use Drupal\form_a11y\Form\ValidationErrorsForm;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * Fails with the select case and expected behavior.
 */
function emulsify_select_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/**
 * Checks raw attribute uniqueness and the resulting native option semantics.
 */
function emulsify_select_markup(string $html, string $source_prefix = ''): void {
  emulsify_select_assert((bool) preg_match('/<select\b[^>]*\bid="fixture-option-attributes"[^>]*>(.*?)<\/select>/s', $html, $select), 'The fixture select must render.');
  preg_match_all('/<option\b[^>]*>/i', $select[1], $tags);
  emulsify_select_assert(count($tags[0]) === 4, 'All four flat and grouped options must render.');
  foreach ($tags[0] as $tag) {
    emulsify_select_assert(preg_match_all('/\svalue\s*=/i', $tag) === 1, 'Each option must have exactly one value attribute: ' . $tag);
    emulsify_select_assert(preg_match_all('/\sselected(?:\s*=|\s|>)/i', $tag) <= 1, 'An option must not duplicate its selected attribute: ' . $tag);
    emulsify_select_assert(!str_contains($tag, 'spoof-'), 'Caller attributes must not override the canonical option value.');
  }
  $document = new DOMDocument();
  $document->loadHTML($select[0], LIBXML_NOERROR | LIBXML_NOWARNING);
  $xpath = new DOMXPath($document);
  foreach ([
    'flat_array' => ['flat-array', TRUE, FALSE, FALSE],
    'flat_object' => ['flat-object', FALSE, TRUE, FALSE],
    'group_array' => ['group-array', FALSE, TRUE, TRUE],
    'group_object' => ['group-object', TRUE, FALSE, TRUE],
  ] as $value => [$source, $selected, $disabled, $grouped]) {
    $matches = $xpath->query('//option[@value="' . $value . '"]');
    emulsify_select_assert($matches->length === 1, $value . ': canonical value must survive.');
    $option = $matches->item(0);
    emulsify_select_assert($option->getAttribute('data-option-source') === $source_prefix . $source, $value . ': option data must survive.');
    emulsify_select_assert($option->hasAttribute('selected') === $selected, $value . ': selected must come from Form API.');
    emulsify_select_assert($option->hasAttribute('disabled') === $disabled, $value . ': disabled must reach the native option.');
    emulsify_select_assert(($option->parentNode->nodeName === 'optgroup') === $grouped, $value . ': flat and optgroup placement must survive.');
    if ($grouped) {
      emulsify_select_assert($option->parentNode->getAttribute('label') === 'Collection options', 'The optgroup label must survive.');
    }
    if ($source_prefix !== '') {
      emulsify_select_assert($option->getAttribute('data-existing') === 'retained', $value . ': preprocessed attributes must survive merging.');
    }
  }
}

$renderer = \Drupal::service('renderer');
$form = \Drupal::formBuilder()->getForm(ValidationErrorsForm::class);
emulsify_select_assert(isset($form['option_attributes']), 'Install the current form_a11y fixture before running select coverage.');
$element = $form['option_attributes'];
$objects = [];
foreach ($element['#options_attributes'] as $attributes) {
  if ($attributes instanceof Attribute) {
    $objects[] = [$attributes, $attributes->toArray()];
  }
}

// This render exercises core option construction, hook discovery, and theme
// selection, including #options_attributes supplied as arrays and objects.
$html = (string) $renderer->renderInIsolation($form);
emulsify_select_markup($html);

// Core versions differ in their create_attribute() parameter types. Supply
// preprocessed Attribute objects in both branches, then render the real Twig
// with an array-only signature so this regression cannot hide on newer core.
$variables = ['element' => $element];
\Drupal::service(FormPreprocess::class)->preprocessSelect($variables);
$options = [
  &$variables['options'][0],
  &$variables['options'][1],
  &$variables['options'][2]['options'][0],
  &$variables['options'][2]['options'][1],
];
foreach ($options as &$option) {
  $attributes = [
    'data-option-source' => 'existing-' . str_replace('_', '-', $option['value']),
    'data-existing' => 'retained',
  ];
  if (str_ends_with($option['value'], '_object')) {
    $attributes = new Attribute($attributes);
    $objects[] = [$attributes, $attributes->toArray()];
  }
  $option['attributes'] = $attributes;
}
unset($option);
\Drupal::service('class_resolver')->getInstanceFromDefinition(FormErrorHooks::class)->preprocessSelect($variables);
foreach ($options as $option) {
  emulsify_select_assert(is_array($option['attributes']), $option['value'] . ': preprocessing must normalize option attributes to arrays.');
}
$variables['attributes'] = new Attribute($variables['attributes']);
$twig = new Environment(\Drupal::service('twig')->getLoader(), ['cache' => FALSE, 'autoescape' => 'html']);
$twig->addExtension(\Drupal::service('twig.extension'));
$twig->addFunction(new TwigFunction('create_attribute', static fn(array $attributes = []): Attribute => new Attribute($attributes)));
$html = $renderer->executeInRenderContext(new RenderContext(), static fn() => $twig->render('@emulsify/templates/form/select.html.twig', $variables));
emulsify_select_markup($html, 'existing-');
foreach ($objects as [$object, $original]) {
  emulsify_select_assert($object->toArray() === $original, 'Form API and template rendering must not mutate caller-owned Attribute objects.');
}

print 'PASS Select options: Form API attributes, flat/optgroup array and object inputs, preprocessing precedence, canonical value/selected, and object preservation on Drupal ' . \Drupal::VERSION . ".\n";
