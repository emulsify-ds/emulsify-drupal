<?php

declare(strict_types=1);

/**
 * Asserts the parent theme contract for working-tree and published installs.
 */
$theme = \Drupal::service('theme_handler')->listInfo()['emulsify'] ?? NULL;
if ($theme === NULL || !$theme->status) {
  throw new RuntimeException('Emulsify theme was not enabled.');
}
if (\Drupal::config('system.theme')->get('default') !== 'emulsify') {
  throw new RuntimeException('Emulsify theme was not set as default.');
}

$libraries = [];
foreach (['global', 'favicon_admin'] as $name) {
  $library = \Drupal::service('library.discovery')->getLibraryByName('emulsify', $name);
  if (!$library) {
    throw new RuntimeException("Missing emulsify/{$name} library.");
  }
  $libraries[] = "emulsify/{$name}";
}
$breakpoints = array_keys(\Drupal::service('breakpoint.manager')->getBreakpointsByGroup('emulsify'));
sort($breakpoints);
$expected = ['emulsify.xsmall', 'emulsify.small', 'emulsify.medium', 'emulsify.large', 'emulsify.xlarge', 'emulsify.xxlarge'];
sort($expected);
if ($breakpoints !== $expected) {
  throw new RuntimeException('Emulsify breakpoint registration differs from its public contract.');
}

echo json_encode([
  'php' => PHP_VERSION,
  'drupal' => \Drupal::VERSION,
  'theme' => $theme->getPath(),
  'enabled' => (bool) $theme->status,
  'libraries' => $libraries,
  'breakpoints' => $breakpoints,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
