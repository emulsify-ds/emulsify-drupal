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

$allowed_omissions = json_decode(file_get_contents(__DIR__ . '/publication-skew.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$result = ['release' => $release, 'selected_package' => $package, 'packages' => [], 'warnings' => []];
$stable_versions = [];
$errors = [];
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
  $stable_versions[$name] = array_keys($stable);
  if ($latest === NULL) {
    $errors[] = "{$name} has no published stable release.";
  }
  $published = $stable[$latest] ?? [];
  $result['packages'][$name] = [
    'version' => $published['version'] ?? NULL,
    'require' => $published['require'] ?? [],
    'dist' => $published['dist'] ?? NULL,
    'allowed_omissions' => [],
  ];
}

// Older package histories differ between the registries. Reconcile every
// release from the older route's current stable release onward, not only the
// newest tag: an allowed omission must never hide another intervening gap.
$latest_versions = array_values(array_filter(array_map(static fn(array $entry): string => ltrim($entry['version'] ?? '', 'v'), $result['packages'])));
usort($latest_versions, 'version_compare');
$window_start = $latest_versions[0] ?? $release;
$tag_versions = array_values(array_unique(array_map(static fn(string $tag): string => ltrim($tag, 'v'), $versions)));
$result['reconciliation_from'] = $window_start;
foreach ($stable_versions as $name => $published_versions) {
  foreach ($published_versions as $version) {
    if (version_compare($version, $window_start, '>=') && !in_array($version, $tag_versions, TRUE)) {
      $errors[] = "{$name} {$version} has no matching repository release tag. Checkout must fetch tags.";
    }
  }
  foreach ($tag_versions as $version) {
    if (version_compare($version, $window_start, '<') || in_array($version, $published_versions, TRUE)) {
      continue;
    }
    if (in_array($version, $allowed_omissions[$name] ?? [], TRUE)) {
      $result['packages'][$name]['allowed_omissions'][] = $version;
    }
    else {
      $errors[] = "{$name} is missing stable repository release {$version}; this omission is not allowed by publication-skew.json.";
    }
  }
}

$published = $result['packages'][$package];
$selected = ltrim($published['version'] ?? '', 'v');
$matching_tags = array_values(array_filter($versions, static fn(string $tag): bool => ltrim($tag, 'v') === $selected));
$result['verified_tag'] = $matching_tags[0] ?? NULL;
if ($selected !== '' && !$matching_tags) {
  $errors[] = "{$package} {$selected} has no matching repository release tag. Checkout must fetch tags.";
}
elseif ($matching_tags) {
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
$errors = array_values(array_unique($errors));
$result['errors'] = $errors;
file_put_contents("{$evidence}/metadata.json", json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
if ($summary = getenv('GITHUB_STEP_SUMMARY')) {
  $markdown = "### Published theme compatibility\n\nRepository latest stable tag: `{$release}`. Selected route: `{$package}` at `{$selected}`. Release reconciliation starts at `{$window_start}`.\n\n| Registry package | Observed latest stable | Allowed omissions |\n| --- | --- | --- |\n";
  foreach ($result['packages'] as $name => $entry) {
    $markdown .= "| `{$name}` | `" . ($entry['version'] ?? '(none)') . "` | " . (implode(', ', $entry['allowed_omissions']) ?: '(none)') . " |\n";
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
