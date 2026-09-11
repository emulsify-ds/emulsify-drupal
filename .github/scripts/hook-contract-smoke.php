<?php

/**
 * @file
 * Pins hook output and verifies Drupal's selection of suggestion templates.
 *
 * Run through Drush in a disposable Drupal fixture with Paragraphs available.
 * Entities are not saved and site configuration is not changed.
 */

declare(strict_types=1);

use Drupal\Core\Template\Attribute;
use Drupal\emulsify\Hook\ContainerHooks;
use Drupal\emulsify\Hook\FieldHooks;
use Drupal\emulsify\Hook\FormHooks;
use Drupal\emulsify\Hook\ParagraphHooks;
use Drupal\emulsify\Hook\ViewsHooks;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\views\Entity\View;
use Symfony\Component\HttpFoundation\Request;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

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
  [['#form_id' => 0, '#id' => 'ignored-fallback'], ['base']],
  [['#id' => '0'], ['base']],
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
$suggestions = ['existing'];
$form_hook->themeSuggestionsFormAlter($suggestions, []);
emulsify_hook_same(['existing'], $suggestions, 'Missing form variables preserve existing suggestions');

foreach ([
  [['#form_id' => 'node_page_layout_builder_form'], ['form__layout_builder_form', 'form__node_page_layout_builder_form']],
  [['#form_id' => 'layout_builder_add_block'], ['form__layout_builder_form', 'form__layout_builder_add_block']],
  [['#form_id' => 'layout_builder_form'], ['form__layout_builder_form', 'form__layout_builder_form']],
  [['#form_id' => 'unrelated_layout_builder_dialog'], ['form__unrelated_layout_builder_dialog']],
  [['#id' => 'node_page_layout_builder_form'], ['form__node_page_layout_builder_form']],
  [['#form_id' => NULL, '#id' => 'layout_builder_add_block'], ['form__layout_builder_add_block']],
  [['#form_id' => 'node-page-layout-builder-form'], ['form__node_page_layout_builder_form']],
] as [$element, $expected]) {
  $suggestions = ['existing'];
  $form_hook->themeSuggestionsFormAlter($suggestions, ['element' => $element]);
  emulsify_hook_same(array_merge(['existing'], $expected), $suggestions, 'Layout Builder fallback requires an actual matching form ID and precedes the specific form');
}

$native_view = View::create([
  'id' => 'news',
  'tag' => 'Featured, Public feed',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'display' => [
    'default' => ['id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0, 'display_options' => []],
    'page_1' => ['id' => 'page_1', 'display_title' => 'Page', 'display_plugin' => 'page', 'position' => 1, 'display_options' => []],
  ],
])->getExecutable();
$native_view->setDisplay('page_1');
$exposed_form = [
  '#form_id' => 'views_exposed_form',
  // Use Drupal's native candidates, including tags and display plugin types.
  // The View is neither executed nor saved.
  '#theme' => $native_view->buildThemeFunctions('views_exposed_form'),
];
$exposed_suggestions = [
  'form__views_exposed_form',
  'form__views_exposed_form__news',
  'form__views_exposed_form__page',
  'form__views_exposed_form__news__page',
  'form__views_exposed_form__public_feed',
  'form__views_exposed_form__featured',
  'form__views_exposed_form__page_1',
  'form__views_exposed_form__news__page_1',
];
foreach ([
  [$exposed_form, $exposed_suggestions],
  [$exposed_form + ['#emulsify_block_id' => 'header-search'], array_merge($exposed_suggestions, ['form__views_exposed_form__block__header_search'])],
  [['#form_id' => 'views_exposed_form', '#theme' => 'views_exposed_form__news'], ['form__views_exposed_form', 'form__views_exposed_form__news']],
  [['#form_id' => 'views_exposed_form', '#theme' => ['form__unrelated', 'views_exposed_form', NULL, FALSE, 12, [], 'views_exposed_form__news']], ['form__views_exposed_form', 'form__views_exposed_form__news']],
  [['#form_id' => 'views_exposed_form'], ['form__views_exposed_form']],
  [['#form_id' => 'views_exposed_form', '#emulsify_block_id' => '0'], ['form__views_exposed_form']],
  [array_replace($exposed_form, ['#form_id' => 'other_form', '#emulsify_block_id' => 'header-search']), ['form__other_form']],
  [array_replace($exposed_form, ['#form_id' => NULL, '#id' => 'views_exposed_form', '#emulsify_block_id' => 'header-search']), ['form__views_exposed_form']],
] as [$element, $expected]) {
  $suggestions = ['existing'];
  $form_hook->themeSuggestionsFormAlter($suggestions, ['element' => $element]);
  emulsify_hook_same(array_merge(['existing'], $expected), $suggestions, 'Views outer form suggestions filter theme entries, reverse native candidates, and prioritize the placed block');
}

foreach ([
  [['elements' => ['#id' => 'header-search'], 'content' => $exposed_form], 'header-search'],
  [['elements' => ['#id' => 'footer-search'], 'content' => $exposed_form], 'footer-search'],
  [['elements' => ['#id' => 'header-search'], 'content' => ['#form_id' => 'other_form']], NULL],
  [['elements' => ['#id' => 'header-search'], 'content' => ['#id' => 'views_exposed_form']], NULL],
  [['elements' => ['#id' => ''], 'content' => $exposed_form], NULL],
  [['elements' => ['#id' => '0'], 'content' => $exposed_form], NULL],
  [['content' => $exposed_form], NULL],
  [[], NULL],
] as [$variables, $expected]) {
  $original = $variables;
  $form_hook->preprocessBlock($variables);
  emulsify_hook_same($expected, $variables['content']['#emulsify_block_id'] ?? NULL, 'Only an exposed form inside an identified placed block receives the block ID');
  if ($expected !== NULL) {
    unset($variables['content']['#emulsify_block_id']);
  }
  emulsify_hook_same($original, $variables, 'Block preprocessing preserves every existing variable');
}
emulsify_hook_same(FALSE, isset($exposed_form['#emulsify_block_id']), 'Rendering a block does not leak its ID into the source form');

$container_hook = new ContainerHooks();
$container_element = [
  '#attributes' => ['class' => ['form-wrapper', '', 'form-actions'], 'data-drupal-selector' => 'edit-actions', 'id' => 'checkout-actions'],
  '#array_parents' => ['advanced', 'action-group', 0],
  '#parents' => ['flattened'],
  '#id' => 'ignored-id',
];
$suggestions = ['existing'];
$container_hook->themeSuggestionsContainerAlter($suggestions, ['element' => $container_element]);
emulsify_hook_same([
  'existing',
  'container__class__form_wrapper',
  'container__class__form_actions',
  'container__parents__advanced',
  'container__parents__advanced__action_group',
  'container__parents__advanced__action_group__0',
  'container__selector__edit_actions',
  'container__id__checkout_actions',
], $suggestions, 'Container suggestions append classes, cumulative structural paths, stable selector, then attribute ID');
foreach ([
  [[], []],
  [['#attributes' => ['class' => 'form-actions']], ['container__class__form_actions']],
  [['#attributes' => ['class' => ['', '0', 'Mixed.ID:--x', '0']]], ['container__class__0', 'container__class__Mixed.ID:__x', 'container__class__0']],
  [['#parents' => ['flattened'], '#id' => 'unprocessed'], []],
  [['#array_parents' => [], '#id' => 'form-actions'], ['container__id__form_actions']],
  [['#attributes' => ['id' => 'standalone']], ['container__id__standalone']],
  [['#attributes' => ['id' => ''], '#array_parents' => [], '#id' => 'ignored-fallback'], []],
  [['#attributes' => ['id' => NULL], '#array_parents' => [], '#id' => 'form-actions'], ['container__id__form_actions']],
  [['#attributes' => ['id' => '0', 'data-drupal-selector' => '0']], ['container__id__0']],
] as [$element, $expected]) {
  $suggestions = [];
  $container_hook->themeSuggestionsContainerAlter($suggestions, ['element' => $element]);
  emulsify_hook_same($expected, $suggestions, 'Container attribute, structural path, and ID gates');
}
$selectors = [];
foreach (['edit-actions', 'edit-actions--2'] as $id) {
  $suggestions = [];
  $container_hook->themeSuggestionsContainerAlter($suggestions, ['element' => ['#attributes' => ['data-drupal-selector' => 'edit-actions', 'id' => $id]]]);
  $selectors[] = $suggestions[0];
}
emulsify_hook_same(['container__selector__edit_actions', 'container__selector__edit_actions'], $selectors, 'Unique HTML ID suffixes leave the selector suggestion stable');

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

// Exercise Drupal's hook discovery, suggestion resolution, preprocessors, and
// Twig rendering. Marker templates stand in for child-theme overrides. Keeping
// these in memory avoids installing another theme or changing configuration.
$theme_manager = \Drupal::theme();
emulsify_hook_same('emulsify', $theme_manager->getActiveTheme()->getName(), 'Template checks run with the Emulsify fixture theme active');
$registry = \Drupal::service('theme.registry')->getRuntime();
$twig = \Drupal::service('twig');
$original_loader = $twig->getLoader();
$original_debug = $twig->isDebug();
$original_registry = [];
$templates = [];
$template_hooks = [
  'container' => [
    'container',
    'container__class__form_wrapper',
    'container__class__form_actions',
    'container__parents__advanced',
    'container__parents__advanced__action_group',
    'container__parents__advanced__action_group__0',
    'container__selector__edit_actions',
    'container__id__checkout_actions',
  ],
  'form' => [
    'form',
    'form__fallback_form',
    'form__layout_builder_form',
    'form__node_page_layout_builder_form',
    'form__layout_builder_add_block',
    'form__views_exposed_form',
    'form__views_exposed_form__news',
    'form__views_exposed_form__page',
    'form__views_exposed_form__news__page',
    'form__views_exposed_form__public_feed',
    'form__views_exposed_form__featured',
    'form__views_exposed_form__page_1',
    'form__views_exposed_form__news__page_1',
    'form__views_exposed_form__block__header_search',
    'form__views_exposed_form__block__footer_search',
  ],
];

/**
 * Renders a fresh element through Drupal and checks the selected Twig marker.
 */
function emulsify_hook_render(array $build, string $expected, string $message): void {
  $output = (string) \Drupal::service('renderer')->renderInIsolation($build);
  preg_match_all('/data-emulsify-hook-template="([^"]+)"/', $output, $matches);
  emulsify_hook_same([$expected], $matches[1], $message);
  emulsify_hook_same(TRUE, str_contains($output, 'hook-child-content'), $message . ': child content survives rendering');
}

try {
  foreach ($template_hooks as $base_hook => $hooks) {
    $base_info = $registry->get($base_hook);
    foreach ($hooks as $hook) {
      $original_registry[$hook] = $registry->has($hook) ? $registry->get($hook) : NULL;
      $template = str_replace('_', '-', $hook);
      $info = array_replace($base_info, ['path' => 'emulsify-hook-contract', 'template' => $template]);
      if ($hook !== $base_hook) {
        $info['base hook'] = $base_hook;
      }
      $registry->set($hook, $info);
      $tag = $base_hook === 'form' ? 'form' : 'div';
      $templates['emulsify-hook-contract/' . $template . '.html.twig'] = '<' . $tag . ' data-emulsify-hook-template="' . $template . '"{{ attributes }}>{{ children }}</' . $tag . '>';
    }
  }
  $twig->setLoader(new ChainLoader([new ArrayLoader($templates), $original_loader]));
  $twig->disableDebug();

  $payload = ['payload' => ['#markup' => 'hook-child-content']];
  $container = ['#type' => 'container'] + $container_element + $payload;
  foreach ([
    'container__id__checkout_actions',
    'container__selector__edit_actions',
    'container__parents__advanced__action_group__0',
    'container__parents__advanced__action_group',
    'container__parents__advanced',
    'container__class__form_actions',
    'container__class__form_wrapper',
    'container',
  ] as $hook) {
    emulsify_hook_render($container, str_replace('_', '-', $hook), 'Container selects the most specific available template');
    if ($hook !== 'container') {
      $registry->delete($hook);
    }
  }

  foreach ([
    [['#id' => 'fallback-form'], 'form--fallback-form'],
    [['#form_id' => NULL, '#id' => 'fallback-form'], 'form--fallback-form'],
    [['#form_id' => '', '#id' => 'fallback-form'], 'form'],
    [['#form_id' => '0', '#id' => 'fallback-form'], 'form'],
    [['#form_id' => 'node_page_layout_builder_form'], 'form--node-page-layout-builder-form'],
    [['#form_id' => 'layout_builder_add_block'], 'form--layout-builder-add-block'],
    [['#form_id' => 'node_article_layout_builder_form'], 'form--layout-builder-form'],
    [['#form_id' => 'layout_builder_update_block'], 'form--layout-builder-form'],
    [['#id' => 'node_article_layout_builder_form'], 'form'],
  ] as [$element, $expected]) {
    emulsify_hook_render(['#type' => 'form'] + $element + $payload, $expected, 'Form renders with compatible ID fallback and Layout Builder precedence');
  }
  $registry->delete('form__node_page_layout_builder_form');
  emulsify_hook_render(['#type' => 'form', '#form_id' => 'node_page_layout_builder_form'] + $payload, 'form--layout-builder-form', 'Missing entity-specific Layout Builder override uses the shared template');
  $registry->delete('form__layout_builder_form');
  emulsify_hook_render(['#type' => 'form', '#form_id' => 'node_page_layout_builder_form'] + $payload, 'form', 'Missing Layout Builder overrides retain the core form fallback');

  // Include the real Views inner theme so this checks the outer form wrapper.
  $rendered_exposed_form = ['#type' => 'form', '#info' => []] + $exposed_form + $payload;
  emulsify_hook_render($rendered_exposed_form, 'form--views-exposed-form--news--page-1', 'Views display override wins over its view and generic form templates');

  // Render real block wrappers, allowing Drupal to invoke preprocess_block and
  // propagate each placement's ID before Twig renders the nested exposed form.
  foreach (['header-search', 'footer-search', 'header-search'] as $block_id) {
    $block = [
      '#theme' => 'block',
      '#id' => $block_id,
      '#configuration' => ['provider' => 'views', 'label' => '', 'label_display' => FALSE],
      '#plugin_id' => 'views_exposed_filter_block:news-page_1',
      '#base_plugin_id' => 'views_exposed_filter_block',
      '#derivative_plugin_id' => 'news-page_1',
      'content' => $rendered_exposed_form,
    ];
    emulsify_hook_render($block, 'form--views-exposed-form--block--' . $block_id, 'Separate placements select their own form template on repeated renders');
  }
  emulsify_hook_render($rendered_exposed_form, 'form--views-exposed-form--news--page-1', 'Standalone form remains free of IDs from previously rendered blocks');
  $registry->delete('form__views_exposed_form__block__header_search');
  $block['#id'] = 'header-search';
  emulsify_hook_render($block, 'form--views-exposed-form--news--page-1', 'Missing block template falls back to the Views display');
  foreach ([
    ['form__views_exposed_form__news__page_1', 'form--views-exposed-form--page-1'],
    ['form__views_exposed_form__page_1', 'form--views-exposed-form--featured'],
    ['form__views_exposed_form__featured', 'form--views-exposed-form--public-feed'],
    ['form__views_exposed_form__public_feed', 'form--views-exposed-form--news--page'],
    ['form__views_exposed_form__news__page', 'form--views-exposed-form--page'],
    ['form__views_exposed_form__page', 'form--views-exposed-form--news'],
    ['form__views_exposed_form__news', 'form--views-exposed-form'],
    ['form__views_exposed_form', 'form'],
  ] as [$removed_hook, $expected]) {
    $registry->delete($removed_hook);
    emulsify_hook_render($rendered_exposed_form, $expected, 'Unavailable Views overrides fall back in native display, tag, plugin, View, and generic form order');
  }
}
finally {
  $twig->setLoader($original_loader);
  if ($original_debug) {
    $twig->enableDebug();
  }
  foreach ($original_registry as $hook => $info) {
    if ($info === NULL) {
      $registry->delete($hook);
    }
    else {
      $registry->set($hook, $info);
    }
  }
}
fwrite(STDOUT, "PASS {$assertions} hook contract and Drupal template-selection assertions on PHP " . PHP_VERSION . ".\n");
