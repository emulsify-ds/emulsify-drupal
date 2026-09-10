<?php

declare(strict_types=1);

/**
 * Selects a route's latest stable package and checks its release-tag metadata.
 */
if ($argc !== 4) {
  throw new RuntimeException('Usage: published-package-metadata.php <repo-root> <evidence-dir> <package>');
}

[$script, $repo, $evidence, $package] = $argv;
$routes = ['drupal/emulsify' => 'drupalorg.json', 'emulsify-ds/emulsify-drupal' => 'packagist.json'];
if (!isset($routes[$package])) {
  throw new RuntimeException('Unsupported published package route.');
}
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

$result = ['release' => $release, 'selected_package' => $package, 'packages' => [], 'warnings' => []];
foreach ($routes as $name => $file) {
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
    if (preg_match('/^v?\d+\.\d+\.\d+$/', $previous['version'] ?? '') === 1) {
      $stable[ltrim($previous['version'], 'v')] = $previous;
    }
  }
  uksort($stable, static fn(string $a, string $b): int => version_compare($b, $a));
  $latest = array_key_first($stable);
  if ($latest !== $release) {
    $observed = $latest ?? '(none)';
    $result['warnings'][] = "{$name} latest stable {$observed} differs from repository tag {$release}; publication is not synchronized.";
  }
  $published = $stable[$latest] ?? [];
  $result['packages'][$name] = [
    'version' => $published['version'] ?? NULL,
    'require' => $published['require'] ?? [],
    'dist' => $published['dist'] ?? NULL,
  ];
}

$published = $result['packages'][$package];
$selected = ltrim($published['version'] ?? '', 'v');
$matching_tags = array_values(array_filter($versions, static fn(string $tag): bool => ltrim($tag, 'v') === $selected));
$result['verified_tag'] = $matching_tags[0] ?? NULL;
$errors = [];
if ($selected === '') {
  $errors[] = "{$package} has no published stable release.";
}
elseif (!$matching_tags) {
  $errors[] = "{$package} {$selected} has no matching repository release tag. Checkout must fetch tags.";
}
else {
  exec('git -C ' . escapeshellarg($repo) . ' show ' . escapeshellarg("{$matching_tags[0]}:composer.json"), $manifest_json, $status);
  if ($status !== 0) {
    $errors[] = "Cannot read composer.json at repository tag {$matching_tags[0]}.";
  }
  else {
    $manifest = json_decode(implode("\n", $manifest_json), TRUE, flags: JSON_THROW_ON_ERROR);
    foreach (['drupal/core', 'drupal/emulsify_tools'] as $dependency) {
      if (!isset($manifest['require'][$dependency]) || ($published['require'][$dependency] ?? NULL) !== $manifest['require'][$dependency]) {
        $errors[] = "{$package} {$selected} {$dependency} differs from composer.json at tag {$matching_tags[0]}.";
      }
    }
  }
}
$result['errors'] = $errors;
file_put_contents("{$evidence}/metadata.json", json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
foreach ($result['warnings'] as $warning) {
  fwrite(STDERR, (getenv('GITHUB_ACTIONS') === 'true' ? '::warning::' : 'Warning: ') . $warning . "\n");
}
if ($summary = getenv('GITHUB_STEP_SUMMARY')) {
  $markdown = "### Published theme compatibility\n\nRepository latest stable tag: `{$release}`. Selected route: `{$package}` at `{$selected}`.\n\n| Registry package | Observed latest stable |\n| --- | --- |\n";
  foreach ($result['packages'] as $name => $entry) {
    $markdown .= "| `{$name}` | `" . ($entry['version'] ?? '(none)') . "` |\n";
  }
  foreach (array_merge($result['warnings'], $errors) as $message) {
    $markdown .= "\n- {$message}\n";
  }
  file_put_contents($summary, $markdown . "\n", FILE_APPEND);
}
if ($errors) {
  throw new RuntimeException(implode("\n", $errors));
}
echo "{$selected}\n";
