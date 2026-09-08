<?php

declare(strict_types=1);

/**
 * Checks registry metadata against the latest stable repository release.
 */
if ($argc !== 3) {
  throw new RuntimeException('Usage: published-package-metadata.php <repo-root> <evidence-dir>');
}

[$script, $repo, $evidence] = $argv;
$manifest = json_decode(file_get_contents("{$repo}/composer.json"), TRUE, flags: JSON_THROW_ON_ERROR);
exec('git -C ' . escapeshellarg($repo) . ' tag --list', $tags, $status);
if ($status !== 0) {
  throw new RuntimeException('Cannot read release tags. Checkout must fetch tags.');
}
$versions = array_values(array_filter($tags, static fn(string $tag): bool => preg_match('/^v?\d+\.\d+\.\d+$/', $tag) === 1));
usort($versions, static fn(string $a, string $b): int => version_compare(ltrim($b, 'v'), ltrim($a, 'v')));
$release = ltrim($versions[0] ?? '', 'v');
if ($release === '') {
  throw new RuntimeException('No stable release tag found.');
}

$result = ['release' => $release, 'packages' => []];
foreach (['drupal/emulsify' => 'drupalorg.json', 'emulsify-ds/emulsify-drupal' => 'packagist.json'] as $name => $file) {
  $data = json_decode(file_get_contents("{$evidence}/{$file}"), TRUE, flags: JSON_THROW_ON_ERROR);
  $previous = [];
  $stable = [];
  foreach ($data['packages'][$name] ?? [] as $entry) {
    // Composer v2 metadata omits unchanged fields from subsequent versions.
    $previous = array_replace($previous, $entry);
    foreach ($previous as $key => $value) {
      if ($value === '__unset') {
        unset($previous[$key]);
      }
    }
    if (preg_match('/^v?\d+\.\d+\.\d+$/', $previous['version']) === 1) {
      $stable[ltrim($previous['version'], 'v')] = $previous;
    }
  }
  uksort($stable, static fn(string $a, string $b): int => version_compare($b, $a));
  $latest = array_key_first($stable);
  if ($latest !== $release) {
    throw new RuntimeException("{$name} latest stable {$latest} differs from repository tag {$release}.");
  }
  $published = $stable[$release];
  foreach (['drupal/core', 'drupal/emulsify_tools'] as $dependency) {
    if (($published['require'][$dependency] ?? NULL) !== ($manifest['require'][$dependency] ?? NULL)) {
      throw new RuntimeException("{$name} {$release} {$dependency} differs from composer.json.");
    }
  }
  $result['packages'][$name] = [
    'version' => $published['version'],
    'require' => $published['require'],
    'dist' => $published['dist'],
  ];
}
file_put_contents("{$evidence}/metadata.json", json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
echo "{$release}\n";
