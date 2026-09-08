<?php

/**
 * @file
 * Characterizes installed Emulsify Tools helpers without a Drupal database.
 */

declare(strict_types=1);

use Drupal\Core\Template\Attribute;
use Drupal\emulsify_tools\AddAttributesTwigExtension;
use Drupal\emulsify_tools\BemTwigExtension;
use Drupal\emulsify_tools\TwigAttributeManager;

if (empty($argv[1])) {
  throw new RuntimeException('Pass the installed Drupal fixture directory.');
}
require rtrim($argv[1], '/') . '/vendor/autoload.php';
$tools_path = Composer\InstalledVersions::getInstallPath('drupal/emulsify_tools');
foreach (['TwigAttributeManager', 'BemTwigExtension', 'AddAttributesTwigExtension'] as $class) {
  require_once $tools_path . '/src/' . $class . '.php';
}
$cases = json_decode(file_get_contents(__DIR__ . '/../fixtures/twig-helper-contract.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$manager = new TwigAttributeManager();
$bem = new BemTwigExtension($manager);
$add = new AddAttributesTwigExtension($manager);
$observed = [];
foreach ($cases as $case) {
  $context = ['attributes' => ($case['contextKind'] ?? '') === 'object' ? new Attribute($case['context'] ?? []) : ($case['context'] ?? [])];
  $attributes = $case['helper'] === 'bem'
    ? $bem->bem($context, ...$case['args'])
    : $add->addAttributes($context, ...$case['args']);
  // Preserve divergences: PHP block/element mapping, plain-array context,
  // non-class array merging, unsanitized classes, and leading serialization space.
  $result = [
    'attributes' => (object) $attributes->toArray(),
    'html' => (string) $attributes,
    'contextAfter' => (object) ($context['attributes'] instanceof Attribute ? $context['attributes']->toArray() : $context['attributes']),
  ];
  $observed[$case['id']] = $result;
  if (!in_array('--dump', $argv, TRUE)) {
    $expected = $case['php'];
    $expected['attributes'] = (object) $expected['attributes'];
    $expected['contextAfter'] = (object) $expected['contextAfter'];
    if (json_encode($result, JSON_THROW_ON_ERROR) !== json_encode($expected, JSON_THROW_ON_ERROR)) {
      throw new RuntimeException($case['id'] . ': PHP helper contract changed: ' . json_encode($result, JSON_THROW_ON_ERROR));
    }
  }
}
if (in_array('--dump', $argv, TRUE)) {
  print json_encode($observed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
}
else {
  print 'PASS ' . count($cases) . ' PHP Twig helper cases on PHP ' . PHP_VERSION . ', Tools ' . Composer\InstalledVersions::getPrettyVersion('drupal/emulsify_tools') . ".\n";
}
