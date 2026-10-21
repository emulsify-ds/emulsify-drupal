<?php

/**
 * @file
 * Verifies core active-link metadata through the rendered menu template.
 *
 * Run through Drush in the installed Emulsify fixture. Current-page attributes
 * are verified on HTTP responses, after Drupal's active-link response filter.
 */

declare(strict_types=1);

use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;

/**
 * Fails with the menu item and expected behavior.
 */
function emulsify_menu_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/**
 * Builds an item with independent list-item and link attributes.
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

$urls = [
  'parent' => Url::fromRoute('entity.node.canonical', ['node' => 1]),
  'target' => Url::fromRoute('entity.node.canonical', ['node' => 2]),
  'query' => Url::fromRoute('entity.node.canonical', ['node' => 2], ['query' => ['b' => '2', 'a' => '1']]),
  'login' => Url::fromRoute('user.login'),
  'front' => Url::fromRoute('<front>'),
  'external' => Url::fromUri('https://example.com/node/2'),
  'nolink' => Url::fromRoute('<nolink>'),
  'button' => Url::fromRoute('<button>'),
];
foreach ($urls as $id => $url) {
  // MenuLinkTree::buildItems() enables this option on every menu URL. Core's
  // link generator then supplies the metadata used by its active-link handling.
  $url->setOption('set_active_class', TRUE);
  $url->setOption('attributes', ['class' => ['fixture-link', $id . '-link'], 'data-existing' => $id]);
}
$originals = array_map(static fn(Url $url) => $url->getOptions(), $urls);
$items = [emulsify_menu_item('parent', $urls['parent'], [emulsify_menu_item('target', $urls['target'])])];
foreach (['query', 'login', 'front', 'external', 'nolink', 'button'] as $id) {
  $items[] = emulsify_menu_item($id, $urls[$id]);
}
$build = ['#theme' => 'menu', '#menu_name' => 'menu-current-smoke', '#items' => $items];
$html = (string) \Drupal::service('renderer')->renderInIsolation($build);
$document = new DOMDocument();
$document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($document);

foreach ([
  'parent' => 'node/1',
  'target' => 'node/2',
  'query' => 'node/2',
  'login' => 'user/login',
  'front' => '<front>',
  'external' => NULL,
  'nolink' => NULL,
  'button' => NULL,
] as $id => $system_path) {
  $links = $xpath->query('//li[@data-menu-item="' . $id . '"]/a | //li[@data-menu-item="' . $id . '"]/span | //li[@data-menu-item="' . $id . '"]/button');
  emulsify_menu_assert($links->length === 1, $id . ': exactly one link or non-link must render.');
  $link = $links->item(0);
  $tag = $id === 'nolink' ? 'span' : ($id === 'button' ? 'button' : 'a');
  emulsify_menu_assert($link->nodeName === $tag, $id . ': core must retain the correct link element.');
  if ($system_path === NULL) {
    emulsify_menu_assert(!$link->hasAttribute('data-drupal-link-system-path'), $id . ': external and non-link items must not acquire a system path.');
  }
  else {
    emulsify_menu_assert($link->getAttribute('data-drupal-link-system-path') === $system_path, $id . ': core active-link system-path metadata must reach the rendered anchor.');
  }
  emulsify_menu_assert($link->getAttribute('data-existing') === $id, $id . ': existing URL data attributes must survive.');
  emulsify_menu_assert(in_array($id . '-link', explode(' ', $link->getAttribute('class')), TRUE), $id . ': existing URL classes must survive.');
  emulsify_menu_assert($urls[$id]->getOptions() === $originals[$id], $id . ': rendering must not change caller-owned URL options.');
}
emulsify_menu_assert($xpath->query('//li[@data-menu-item="parent"]/ul/li[@data-menu-item="target"]/a')->length === 1, 'Nested menu links must retain their hierarchy.');
$query_link = $xpath->query('//li[@data-menu-item="query"]/a')->item(0);
emulsify_menu_assert(json_decode($query_link->getAttribute('data-drupal-link-query'), TRUE) === ['a' => '1', 'b' => '2'], 'Core active-link query metadata must retain and sort URL query parameters.');

print 'PASS Menu active-link metadata: core system paths and queries, nested links, and preserved URL attributes/options on Drupal ' . \Drupal::VERSION . ".\n";
