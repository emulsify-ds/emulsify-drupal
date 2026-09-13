<?php

/**
 * @file
 * Verifies current-page menu links through preprocessing and Drupal rendering.
 *
 * Run through Drush in the installed Emulsify fixture.
 */

declare(strict_types=1);

use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Routing\NullRouteMatch;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\emulsify\Hook\MenuHooks;
use Symfony\Component\Routing\Route;

/**
 * Fails with the menu case and expected behavior.
 */
function emulsify_menu_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/**
 * Supplies front-page state without changing fixture site configuration.
 */
function emulsify_menu_path_matcher(bool $front): PathMatcherInterface {
  return new class($front) implements PathMatcherInterface {
    public function __construct(private readonly bool $front) {}

    public function matchPath($path, $patterns): bool {
      throw new LogicException('Current menu matching should not use path patterns.');
    }

    public function isFrontPage(): bool {
      return $this->front;
    }
  };
}

/**
 * Builds an active-trail item with independent list-item and link attributes.
 */
function emulsify_menu_item(string $id, Url $url, array $below = []): array {
  return [
    'title' => ucfirst($id),
    'url' => $url,
    'attributes' => new Attribute(['data-menu-item' => $id]),
    'below' => $below,
    'in_active_trail' => TRUE,
    'is_expanded' => $below !== [],
    'is_collapsed' => FALSE,
  ];
}

/**
 * Creates fresh menu variables and caller-owned URLs for each simulated route.
 */
function emulsify_menu_fixture(): array {
  $urls = [
    'parent' => Url::fromRoute('entity.node.canonical', ['node' => 1]),
    'target' => Url::fromRoute('entity.node.canonical', ['node' => 2]),
    'other' => Url::fromRoute('entity.node.canonical', ['node' => 3]),
    'login' => Url::fromRoute('user.login'),
    'revision' => Url::fromRoute('entity.node.revision', ['node_revision' => 5, 'node' => 1]),
    'external' => Url::fromUri('https://example.com/node/2'),
    'nolink' => Url::fromRoute('<nolink>'),
    'button' => Url::fromRoute('<button>'),
    'front' => Url::fromRoute('<front>'),
  ];
  foreach ($urls as $id => $url) {
    $url->setOption('attributes', ['class' => ['fixture-link', $id . '-link'], 'data-existing' => $id]);
  }
  $items = [
    emulsify_menu_item('parent', $urls['parent'], [
      emulsify_menu_item('target', $urls['target']),
      // Reusing the same Url exposes accidental mutation and duplicate marks.
      emulsify_menu_item('duplicate', $urls['target']),
    ]),
  ];
  foreach (['other', 'login', 'revision', 'external', 'nolink', 'button', 'front'] as $id) {
    $items[] = emulsify_menu_item($id, $urls[$id]);
  }
  return [['items' => $items, 'attributes' => new Attribute(), '#cache' => ['contexts' => ['languages:language_interface']]], $urls];
}

/**
 * Checks that the current destination alone is marked and attributes survive.
 */
function emulsify_menu_markup(string $html, ?string $expected, string $case): void {
  $document = new DOMDocument();
  $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
  $xpath = new DOMXPath($document);
  $current = $xpath->query('//*[@aria-current]');
  emulsify_menu_assert($current->length === ($expected === NULL ? 0 : 1), $case . ': mark exactly one destination, or none when absent.');
  if ($expected !== NULL) {
    $link = $current->item(0);
    emulsify_menu_assert($link->nodeName === 'a' && $link->getAttribute('aria-current') === 'page', $case . ': aria-current="page" belongs on an anchor.');
    emulsify_menu_assert($link->parentNode->getAttribute('data-menu-item') === $expected, $case . ': only the matching item should be current.');
  }
  foreach ($xpath->query('//li[@data-menu-item]') as $item) {
    $id = $item->getAttribute('data-menu-item');
    $source = $id === 'duplicate' ? 'target' : $id;
    $link = $xpath->query('./a | ./span | ./button', $item)->item(0);
    emulsify_menu_assert($link !== NULL && $link->getAttribute('data-existing') === $source, $case . ': existing URL data attributes must survive on ' . $id . '.');
    emulsify_menu_assert(in_array($source . '-link', explode(' ', $link->getAttribute('class')), TRUE), $case . ': existing URL classes must survive on ' . $id . '.');
  }
}

$renderer = \Drupal::service('renderer');
$twig = \Drupal::service('twig');
$cases = [
  'nested child, integer URL/string raw parameter' => ['entity.node.canonical', '/node/{node}', ['node' => '2'], FALSE, 'target'],
  'nested child, integer raw parameter' => ['entity.node.canonical', '/node/{node}', ['node' => 2], FALSE, 'target'],
  'parent destination' => ['entity.node.canonical', '/node/{node}', ['node' => '1'], FALSE, 'parent'],
  'same route, different parameter' => ['entity.node.canonical', '/node/{node}', ['node' => '99'], FALSE, NULL],
  'missing raw parameter' => ['entity.node.canonical', '/node/{node}', [], FALSE, NULL],
  'route with no parameters' => ['user.login', '/user/login', [], FALSE, 'login'],
  'parameter order and types' => ['entity.node.revision', '/node/{node}/revisions/{node_revision}/view', ['node' => '1', 'node_revision' => '5'], FALSE, 'revision'],
  'unmatched route with active trail' => ['system.404', '/not-found', [], FALSE, NULL],
  'nolink is not a destination' => ['<nolink>', '/', [], FALSE, NULL],
  'button is not a destination' => ['<button>', '/', [], FALSE, NULL],
  'configured front page' => ['view.frontpage.page_1', '/node', [], TRUE, 'front'],
  'front page link on another page' => ['view.frontpage.page_1', '/node', [], FALSE, NULL],
];
foreach ($cases as $case => [$name, $path, $raw, $front, $expected]) {
  [$variables, $urls] = emulsify_menu_fixture();
  $originals = array_map(static fn(Url $url) => $url->getOptions(), $urls);
  // Processed parameters deliberately differ from raw IDs, as entity
  // upcasting does in a real request. Comparison must use the raw values.
  $processed = array_map(static fn($value) => (object) ['id' => $value], $raw);
  $route_match = new RouteMatch($name, new Route($path), $processed, $raw);
  (new MenuHooks($route_match, emulsify_menu_path_matcher($front)))->preprocessMenu($variables);
  foreach (array_merge($variables['items'], $variables['items'][0]['below']) as $item) {
    emulsify_menu_assert(is_array($item['current_link_attributes'] ?? NULL), $case . ': current link attributes must be arrays for Twig link().');
  }
  foreach (['route', 'url.path.is_front', 'languages:language_interface'] as $context) {
    emulsify_menu_assert(in_array($context, $variables['#cache']['contexts'] ?? [], TRUE), $case . ': cache variation must include ' . $context . '.');
  }
  $html = $renderer->executeInRenderContext(new RenderContext(), static fn() => $twig->render('@emulsify/templates/navigation/menu.html.twig', $variables));
  emulsify_menu_markup($html, $expected, $case);
  foreach ($urls as $id => $url) {
    emulsify_menu_assert($url->getOptions() === $originals[$id], $case . ': caller-owned Url options must not change.');
  }
  // A second pass must also remove a previously calculated current marker.
  (new MenuHooks(new NullRouteMatch(), emulsify_menu_path_matcher(FALSE)))->preprocessMenu($variables);
  $html = $renderer->executeInRenderContext(new RenderContext(), static fn() => $twig->render('@emulsify/templates/navigation/menu.html.twig', $variables));
  emulsify_menu_markup($html, NULL, $case . ' after no-route preprocessing');
}

// ThemeManager, unlike direct Twig rendering, must bubble preprocess cache
// metadata even when there is no matching link in an otherwise active trail.
$build = [
  '#theme' => 'menu',
  '#menu_name' => 'menu-current-smoke',
  '#items' => [emulsify_menu_item('external', Url::fromUri('https://example.com/'))],
];
$context = new RenderContext();
$renderer->executeInRenderContext($context, static function () use ($renderer, &$build): void {
  $renderer->render($build);
});
emulsify_menu_assert(!$context->isEmpty(), 'Menu rendering must bubble cache metadata.');
$cache_contexts = $context->pop()->getCacheContexts();
foreach (['route', 'url.path.is_front'] as $cache_context) {
  emulsify_menu_assert(in_array($cache_context, $cache_contexts, TRUE), 'Actual theme preprocessing must bubble ' . $cache_context . ' even without a match.');
}

print 'PASS Menu current page: nested destinations, duplicate URLs, raw parameters, absent matches, front page, preserved attributes/objects, and cache variation on Drupal ' . \Drupal::VERSION . ".\n";
