<?php

/**
 * @file
 * Seeds nested main-menu links and their block for rendered accessibility tests.
 */

declare(strict_types=1);

use Drupal\block\Entity\Block;
use Drupal\menu_link_content\Entity\MenuLinkContent;

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$links = [];
foreach ([
  'ancestor' => ['Fixture ancestor page', 2, '', -20],
  'current' => ['Fixture current page', 1, 'ancestor', 0],
] as $key => [$title, $node_id, $parent, $weight]) {
  $existing = $storage->loadByProperties(['menu_name' => 'main', 'title' => $title]);
  $link = reset($existing) ?: MenuLinkContent::create(['title' => $title]);
  $link
    ->set('menu_name', 'main')
    ->set('link', [
      'uri' => 'entity:node/' . $node_id,
      'options' => ['attributes' => ['data-menu-fixture' => $key]],
    ])
    ->set('parent', $parent === '' ? '' : $links[$parent]->getPluginId())
    ->set('weight', $weight)
    ->set('enabled', TRUE)
    ->set('expanded', TRUE)
    ->save();
  $links[$key] = $link;
}

// Core copies default-theme blocks when a generated child is first enabled.
// Also support an existing child when this helper is rerun on a local fixture.
foreach (array_unique(['emulsify', \Drupal::config('system.theme')->get('default')]) as $theme) {
  $block_id = $theme . '_fixture_main_navigation';
  foreach (\Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['theme' => $theme]) as $block) {
    if ($block->getPluginId() === 'system_menu_block:main' && $block->id() !== $block_id) {
      $block->disable()->save();
    }
  }
  $block = Block::load($block_id) ?: Block::create([
    'id' => $block_id,
    'theme' => $theme,
    'plugin' => 'system_menu_block:main',
  ]);
  $block
    ->set('region', 'header')
    ->set('weight', -50)
    ->set('visibility', [])
    ->set('settings', [
      'id' => 'system_menu_block:main',
      'label' => 'Fixture main navigation',
      'label_display' => 'visible',
      'provider' => 'system',
      'level' => 1,
      'depth' => 0,
      'expand_all_items' => TRUE,
    ])
    ->enable()
    ->save();
}

echo "Seeded nested main-menu accessibility fixtures.\n";
