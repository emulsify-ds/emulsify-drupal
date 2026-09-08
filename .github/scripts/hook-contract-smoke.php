<?php

/**
 * @file
 * Pins existing hook output without normalizing or rewriting helpers.
 *
 * Run through Drush in a disposable Drupal fixture with Paragraphs available.
 * Entities are not saved and site configuration is not changed.
 */

declare(strict_types=1);

use Drupal\Core\Template\Attribute;
use Drupal\emulsify\Hook\FieldHooks;
use Drupal\emulsify\Hook\FormHooks;
use Drupal\emulsify\Hook\ParagraphHooks;
use Drupal\emulsify\Hook\ViewsHooks;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\HttpFoundation\Request;

global $assertions;
$assertions = 0;
/**
 * Compares exact output with an independently specified expected value.
 */
function emulsify_hook_same(mixed $expected, mixed $actual, string $message): void {
  global $assertions;
  $assertions++;
  if ($expected !== $actual) {
    throw new RuntimeException($message . ': expected ' . var_export($expected, TRUE) . ', got ' . var_export($actual, TRUE));
  }
}

/**
 * Supplies the entity methods read by the paragraph preprocessor.
 */
function emulsify_hook_paragraph(?object $parent): object {
  return new class($parent) {
    public function __construct(private readonly ?object $parent) {}

    public function getParentEntity(): ?object {
      return $this->parent;
    }
  };
}

$paragraph_hook = new ParagraphHooks();
$variables = ['paragraph' => emulsify_hook_paragraph(NULL), 'attributes' => []];
$paragraph_hook->preprocessParagraph($variables);
emulsify_hook_same(NULL, $variables['paragraph_index'], 'Standalone paragraph index');
emulsify_hook_same([], array_intersect_key($variables, array_flip(['parent_type', 'parent_bundle', 'node_title'])), 'Missing parent exposes no new metadata');
emulsify_hook_same(FALSE, array_key_exists('container__attributes', $variables), 'Empty attribute array does not produce container variables');

$node = new class extends Node {
  public function __construct() {}

  public function getEntityTypeId(): string { return 'node'; }

  public function bundle(): string { return 'Landing-Page!'; }

  public function label(): string { return 'Parent <title> & "quoted"'; }
};
$attributes = ['class' => ['hero', 'u:wide'], 'data-count' => 0];
$variables = ['paragraph' => emulsify_hook_paragraph($node), 'elements' => ['#emulsify_paragraph_index' => 0], 'attributes' => $attributes];
$paragraph_hook->preprocessParagraph($variables);
emulsify_hook_same(0, $variables['paragraph_index'], 'Zero index is preserved');
emulsify_hook_same('node', $variables['parent_type'], 'Node parent type');
emulsify_hook_same('Landing-Page!', $variables['parent_bundle'], 'Parent bundle punctuation remains unchanged');
emulsify_hook_same('Parent <title> & "quoted"', $variables['node_title'], 'Node title is raw label output');
emulsify_hook_same($attributes, $variables['container__attributes'], 'Container receives the original nonempty attribute array');
emulsify_hook_same(['hero', 'u:wide'], $variables['container__additional_classes'], 'Container receives the exact class array');

$nested_parent = new class {
  public function getEntityTypeId(): string { return 'paragraph'; }

  public function bundle(): string { return 'Nested:Group'; }
};
$variables = ['paragraph' => emulsify_hook_paragraph($nested_parent), 'elements' => ['#emulsify_paragraph_index' => '03'], 'attributes' => ['id' => 'wrapper']];
$paragraph_hook->preprocessParagraph($variables);
emulsify_hook_same('03', $variables['paragraph_index'], 'Index is copied without coercion');
emulsify_hook_same('paragraph', $variables['parent_type'], 'Nested paragraph uses immediate parent');
emulsify_hook_same('Nested:Group', $variables['parent_bundle'], 'Nested bundle is preserved');
emulsify_hook_same(FALSE, array_key_exists('node_title', $variables), 'Nested paragraph does not walk up to a node title');
emulsify_hook_same([], $variables['container__additional_classes'], 'Nonempty attributes without class expose an empty class array');

foreach ([new Attribute(['class' => ['hero']]), [], NULL] as $attributes) {
  $variables = ['paragraph' => emulsify_hook_paragraph(NULL), 'attributes' => $attributes, 'node_title' => 'Already supplied'];
  $paragraph_hook->preprocessParagraph($variables);
  emulsify_hook_same(FALSE, array_key_exists('container__attributes', $variables), 'Attribute objects and empty values do not produce container variables');
  emulsify_hook_same('Already supplied', $variables['node_title'], 'Missing parent does not clear existing values');
}
$variables = ['paragraph' => emulsify_hook_paragraph(NULL), 'attributes' => ['class' => 'one two']];
$paragraph_hook->preprocessParagraph($variables);
emulsify_hook_same('one two', $variables['container__additional_classes'], 'A raw string class stays a string');

$form_hook = new FormHooks();
foreach ([
  [['#form_id' => 'node-edit-form', '#id' => 'ignored-id'], ['base', 'form__node_edit_form']],
  [['#id' => 'fallback-form:ID'], ['base', 'form__fallback_form:ID']],
  [['#form_id' => '', '#id' => 'ignored-fallback'], ['base']],
  [['#form_id' => NULL, '#id' => 'fallback-form'], ['base', 'form__fallback_form']],
  [['#form_id' => '0'], ['base']],
  [['#form_id' => 'Mixed.ID: 12--x'], ['base', 'form__Mixed.ID: 12__x']],
  [[], ['base']],
] as [$element, $expected]) {
  $suggestions = ['base'];
  $form_hook->themeSuggestionsFormAlter($suggestions, ['element' => $element]);
  emulsify_hook_same($expected, $suggestions, 'Form ID precedence and hyphen-only substitution');
}
$suggestions = ['form__duplicate'];
$form_hook->themeSuggestionsFormAlter($suggestions, ['element' => ['#form_id' => 'duplicate']]);
emulsify_hook_same(['form__duplicate', 'form__duplicate'], $suggestions, 'Form suggestions preserve duplicates');

$field_hook = new FieldHooks();
$element = ['#entity_type' => 'node', '#field_name' => 'field-Hero', '#bundle' => 'Landing.Page', '#view_mode' => 'full-card'];
$suggestions = ['base'];
$field_hook->themeSuggestionsFieldAlter($suggestions, ['element' => $element], 'field');
emulsify_hook_same(['base', 'field__node__field-Hero', 'field__node__field-Hero__Landing.Page__full-card'], $suggestions, 'Field suggestions preserve punctuation and append in order');
foreach ([
  [[], 'field', []],
  [$element, 'other', []],
  [array_replace($element, ['#view_mode' => '']), 'field', ['field__node__field-Hero']],
  [array_replace($element, ['#bundle' => '0']), 'field', ['field__node__field-Hero']],
  [array_replace($element, ['#field_name' => '']), 'field', []],
] as [$fields, $hook, $expected]) {
  $suggestions = [];
  $field_hook->themeSuggestionsFieldAlter($suggestions, ['element' => $fields], $hook);
  emulsify_hook_same($expected, $suggestions, 'Field hook and empty-value gates');
}

// Use the real Paragraphs entity/interface for the index-producing branch.
// Composer installs these test-only modules without enabling them in the site.
$module_loader = new \Composer\Autoload\ClassLoader();
foreach (['paragraphs', 'entity_reference_revisions'] as $module) {
  if (\Composer\InstalledVersions::isInstalled('drupal/' . $module)) {
    $module_loader->addPsr4('Drupal\\' . $module . '\\', \Composer\InstalledVersions::getInstallPath('drupal/' . $module) . '/src');
  }
}
$module_loader->register();
if (!class_exists(Paragraph::class)) {
  throw new RuntimeException('Hook characterization requires published drupal/paragraphs in the disposable fixture.');
}
$paragraph_entity = (new ReflectionClass(Paragraph::class))->newInstanceWithoutConstructor();
$field_items = new class {
  public function getItemDefinition(): object { return $this; }

  public function getSetting(string $name): string { return 'paragraph'; }
};
$variables = ['field_type' => 'entity_reference_revisions', 'element' => ['#items' => $field_items], 'items' => [
  2 => ['content' => ['#paragraph' => $paragraph_entity]],
  4 => ['content' => ['#markup' => 'non-paragraph']],
  8 => ['content' => ['#paragraph' => $paragraph_entity]],
]];
$field_hook->preprocessField($variables);
emulsify_hook_same(0, $variables['items'][2]['content']['#emulsify_paragraph_index'], 'Paragraph render order starts at zero despite sparse field deltas');
emulsify_hook_same(FALSE, isset($variables['items'][4]['content']['#emulsify_paragraph_index']), 'Nonparagraph items are not indexed');
emulsify_hook_same(1, $variables['items'][8]['content']['#emulsify_paragraph_index'], 'Only matching paragraph items increment the index');
emulsify_hook_same(FALSE, property_exists($paragraph_entity, '#emulsify_paragraph_index'), 'Field indexing does not mutate the paragraph entity');
$variables = ['field_type' => 'string', 'items' => [['content' => ['#paragraph' => $paragraph_entity]]]];
$original = $variables;
$field_hook->preprocessField($variables);
emulsify_hook_same($original, $variables, 'Other field types do not receive paragraph indexes');
$variables = ['field_type' => 'entity_reference_revisions', 'element' => ['#items' => new class {
  public function getItemDefinition(): object { return $this; }

  public function getSetting(string $name): string { return 'node'; }
}], 'items' => [['content' => ['#paragraph' => $paragraph_entity]]]];
$original = $variables;
$field_hook->preprocessField($variables);
emulsify_hook_same($original, $variables, 'Other reference target types do not receive paragraph indexes');

/**
 * Supplies the methods and properties read by Views hooks.
 */
function emulsify_hook_view(string $id, string $display, ?object $handler = NULL, string $title = 'View <title>'): object {
  return new class($id, $display, $handler, $title) {
    public function __construct(private string $viewId, public string $current_display, public ?object $display_handler, private string $title) {}

    public function id(): string { return $this->viewId; }

    public function getRequest(): Request { return Request::create('/news/archive?category=3'); }

    public function getTitle(): string { return $this->title; }
  };
}
$views_hook = new ViewsHooks();
$handler = new class {
  public function getPluginId(): string { return 'page-type'; }
};
$view = emulsify_hook_view('News-View:ID', 'block-one', $handler);
$suggestions = ['views_view__News_View:ID'];
$views_hook->themeSuggestionsViewsViewAlter($suggestions, ['view' => $view]);
emulsify_hook_same(['views_view__News_View:ID', 'views_view__News_View:ID__page_type', 'views_view__News_View:ID__block_one'], $suggestions, 'Views wrapper normalizes hyphens and suppresses existing duplicates');
$suggestions = [];
$views_hook->themeSuggestionsViewsViewAlter($suggestions, ['view' => emulsify_hook_view('list', 'page', new class {
  public array $display = ['display_plugin' => 'page'];
})]);
emulsify_hook_same(['views_view__list', 'views_view__list__page'], $suggestions, 'Legacy handler property and display-type/display duplicate collapse');
$suggestions = [];
$views_hook->themeSuggestionsViewsViewAlter($suggestions, []);
$views_hook->themeSuggestionsViewsViewAlter($suggestions, ['view' => new stdClass()]);
$views_hook->themeSuggestionsViewsViewAlter($suggestions, ['view' => emulsify_hook_view('', 'page')]);
emulsify_hook_same([], $suggestions, 'Missing views or falsey IDs add no wrapper suggestions');

// Intentional divergence: unformatted Views suggestions keep hyphens and
// duplicates, unlike the normalized, deduplicated main Views wrapper.
$suggestions = ['views_view_unformatted__News-View:ID'];
$views_hook->themeSuggestionsViewsViewUnformattedAlter($suggestions, ['view' => $view]);
emulsify_hook_same(['views_view_unformatted__News-View:ID', 'views_view_unformatted__News-View:ID', 'views_view_unformatted__News-View:ID__block-one'], $suggestions, 'Unformatted suggestions preserve raw IDs and duplicates');
$suggestions = [];
$views_hook->themeSuggestionsViewsViewUnformattedAlter($suggestions, ['view' => emulsify_hook_view('', '')]);
emulsify_hook_same(['views_view_unformatted__', 'views_view_unformatted____'], $suggestions, 'Unformatted suggestions append even for empty IDs');
$suggestions = [];
$views_hook->themeSuggestionsViewsMiniPagerAlter($suggestions, ['view' => $view]);
emulsify_hook_same(['views_mini_pager__News_View:ID', 'views_mini_pager__News_View:ID__block_one'], $suggestions, 'Mini pager normalizes hyphens');
$views_hook->themeSuggestionsViewsMiniPagerAlter($suggestions, ['view' => emulsify_hook_view('ignored', '')]);
emulsify_hook_same(2, count($suggestions), 'Mini pager requires a nonempty display');
$suggestions = [];
$views_hook->themeSuggestionsViewsMiniPagerAlter($suggestions, ['view' => emulsify_hook_view('', 'page-one')]);
emulsify_hook_same(['views_mini_pager__', 'views_mini_pager____page_one'], $suggestions, 'Mini pager does not require a nonempty view ID');
$views_hook->themeSuggestionsViewsMiniPagerAlter($suggestions, ['view' => emulsify_hook_view('', 'page-one')]);
emulsify_hook_same(4, count($suggestions), 'Mini pager preserves duplicate suggestions');

foreach ([
  [[], ['#markup' => 'View <title>']],
  ['Existing', 'Existing'],
  ['0', ['#markup' => 'View <title>']],
] as [$title, $expected]) {
  $variables = ['view' => $view, 'title' => $title];
  $views_hook->preprocessViewsView($variables);
  emulsify_hook_same('/news/archive', $variables['path'], 'Views path excludes query string');
  emulsify_hook_same($expected, $variables['title'], 'Views title is wrapped only for an empty incoming title');
}
$variables = ['view' => emulsify_hook_view('empty', 'page', NULL, ''), 'title' => []];
$views_hook->preprocessViewsView($variables);
emulsify_hook_same([], $variables['title'], 'Empty view title does not invent markup');
fwrite(STDOUT, "PASS {$assertions} hook characterization assertions on PHP " . PHP_VERSION . "; runtime hooks unchanged.\n");
