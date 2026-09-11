<?php

declare(strict_types=1);

/**
 * Checks published-version selection without network requests or Drupal.
 */
$directory = sys_get_temp_dir() . '/emulsify-published-metadata-' . bin2hex(random_bytes(6));
mkdir($directory);
$assertions = 0;

function metadata_same(mixed $expected, mixed $actual, string $message): void {
  global $assertions;
  $assertions++;
  if ($expected !== $actual) {
    throw new RuntimeException($message . ': expected ' . var_export($expected, TRUE) . ', got ' . var_export($actual, TRUE));
  }
}

function metadata_command(array $command, string $directory, ?array $environment = NULL): array {
  $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory, $environment);
  $stdout = stream_get_contents($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  return [proc_close($process), $stdout, $stderr];
}

function metadata_entry(string $version = '7.2.2', string $tools = '^2.2'): array {
  return [
    'version' => $version,
    'require' => ['drupal/core' => '^11.3 || ^12', 'drupal/emulsify_tools' => $tools],
    'dist' => ['type' => 'zip', 'url' => 'https://example.test/theme.zip'],
  ];
}

function metadata_run(string $directory, string $package, array $drupalorg, array $packagist): array {
  foreach (['drupalorg' => ['drupal/emulsify', $drupalorg], 'packagist' => ['emulsify-ds/emulsify-drupal', $packagist]] as $file => [$name, $entries]) {
    file_put_contents("{$directory}/{$file}.json", json_encode(['packages' => [$name => $entries]], JSON_THROW_ON_ERROR));
  }
  file_put_contents("{$directory}/summary.md", '');
  [$status, $stdout, $stderr] = metadata_command(
    [PHP_BINARY, __DIR__ . '/published-package-metadata.php', $directory, $directory, $package],
    $directory,
    array_replace(getenv(), ['GITHUB_ACTIONS' => 'true', 'GITHUB_STEP_SUMMARY' => "{$directory}/summary.md"]),
  );
  return [
    'status' => $status,
    'stdout' => $stdout,
    'stderr' => $stderr,
    'metadata' => json_decode(file_get_contents("{$directory}/metadata.json"), TRUE, flags: JSON_THROW_ON_ERROR),
    'summary' => file_get_contents("{$directory}/summary.md"),
  ];
}

try {
  file_put_contents("{$directory}/composer.json", json_encode(['require' => metadata_entry()['require']], JSON_THROW_ON_ERROR));
  foreach ([
    ['git', 'init', '--quiet'],
    ['git', 'add', 'composer.json'],
    ['git', '-c', 'user.name=Metadata Test', '-c', 'user.email=metadata@example.test', 'commit', '--quiet', '-m', 'Test release metadata'],
    ['git', 'tag', 'v7.2.1'],
    ['git', 'tag', '7.2.2'],
    ['git', 'tag', '9.0.0-beta1'],
  ] as $command) {
    [$status, , $stderr] = metadata_command($command, $directory);
    metadata_same(0, $status, 'Create fixture repository: ' . $stderr);
  }
  // Unreleased requirements must not change checks of an existing release.
  file_put_contents("{$directory}/composer.json", json_encode(['require' => metadata_entry('7.3.0', '^3')['require']], JSON_THROW_ON_ERROR));
  $packagist = [metadata_entry(), metadata_entry('7.2.1')];

  $run = metadata_run($directory, 'drupal/emulsify', [metadata_entry()], [metadata_entry()]);
  metadata_same(0, $run['status'], 'Synchronized published versions pass: ' . $run['stderr']);
  metadata_same("7.2.2\n", $run['stdout'], 'Stdout contains only the selected version');
  metadata_same([], $run['metadata']['warnings'], 'Synchronized versions do not warn');

  $run = metadata_run($directory, 'drupal/emulsify', [metadata_entry('7.2.1')], $packagist);
  metadata_same(0, $run['status'], 'Allowed Drupal.org omission remains testable: ' . $run['stderr']);
  metadata_same("7.2.1\n", $run['stdout'], 'Allowed route selects its available release');
  metadata_same('v7.2.1', $run['metadata']['verified_tag'], 'Version maps to its prefixed repository tag');
  metadata_same('7.2.2', $run['metadata']['release'], 'Evidence retains latest repository release');
  metadata_same('7.2.2', $run['metadata']['packages']['emulsify-ds/emulsify-drupal']['version'], 'Evidence retains other registry version');
  metadata_same('', $run['stderr'], 'An allowed omission emits no warning noise');
  metadata_same([], $run['metadata']['warnings'], 'An allowed omission is not a warning');
  metadata_same(['7.2.2'], $run['metadata']['packages']['drupal/emulsify']['allowed_omissions'], 'Evidence identifies the exact allowed omission');
  metadata_same(TRUE, str_contains($run['summary'], '| `drupal/emulsify` | `7.2.1` | 7.2.2 |'), 'Allowed omission appears as normal summary metadata');

  $run = metadata_run($directory, 'emulsify-ds/emulsify-drupal', [metadata_entry('7.2.1', '^999')], $packagist);
  metadata_same(0, $run['status'], 'Unrelated route requirements do not block selected route');
  metadata_same("7.2.2\n", $run['stdout'], 'Packagist selects its own latest version');

  foreach (['drupal/core', 'drupal/emulsify_tools'] as $dependency) {
    $entry = metadata_entry('7.2.1');
    $entry['require'][$dependency] = '^999';
    $run = metadata_run($directory, 'drupal/emulsify', [$entry], $packagist);
    metadata_same(TRUE, $run['status'] !== 0, "{$dependency} drift fails selected route");
    metadata_same(TRUE, str_contains($run['metadata']['errors'][0], "{$dependency} differs from composer.json at tag v7.2.1"), 'Drift evidence names the matching tag');
  }

  foreach (['7.2.3', '7.1.9'] as $version) {
    $run = metadata_run($directory, 'drupal/emulsify', [metadata_entry($version)], [metadata_entry()]);
    metadata_same(TRUE, $run['status'] !== 0, "Unknown release {$version} fails");
    metadata_same(TRUE, str_contains($run['metadata']['errors'][0], 'no matching repository release tag'), 'Unknown release retains failure evidence');
  }

  foreach (['drupal/emulsify', 'emulsify-ds/emulsify-drupal'] as $selected_route) {
    $run = metadata_run($directory, $selected_route, $packagist, [metadata_entry('7.2.1')]);
    metadata_same(TRUE, $run['status'] !== 0, 'A Drupal.org exception cannot permit Packagist lag');
    metadata_same(TRUE, str_contains(implode("\n", $run['metadata']['errors']), 'emulsify-ds/emulsify-drupal is missing stable repository release 7.2.2'), 'Opposite-direction gap names the package and version');

    $run = metadata_run($directory, $selected_route, [metadata_entry('7.2.3')], $packagist);
    metadata_same(TRUE, $run['status'] !== 0, 'An untagged version fails even when the other route is selected');
    metadata_same(TRUE, str_contains(implode("\n", $run['metadata']['errors']), 'drupal/emulsify 7.2.3 has no matching repository release tag'), 'Unknown registry version names the route');
  }

  foreach (['7.2.3', '7.3.0'] as $tag) {
    [$status, , $stderr] = metadata_command(['git', 'tag', $tag], $directory);
    metadata_same(0, $status, 'Create future release fixture: ' . $stderr);
  }
  foreach (['drupal/emulsify', 'emulsify-ds/emulsify-drupal'] as $selected_route) {
    $run = metadata_run($directory, $selected_route, [metadata_entry('7.2.1')], $packagist);
    metadata_same(TRUE, $run['status'] !== 0, 'Known 7.2.2 omission cannot mask a newer 7.3.0 gap');
    metadata_same(TRUE, str_contains(implode("\n", $run['metadata']['errors']), 'drupal/emulsify is missing stable repository release 7.3.0'), 'Newer Drupal.org omission fails');
    metadata_same(TRUE, str_contains(implode("\n", $run['metadata']['errors']), 'emulsify-ds/emulsify-drupal is missing stable repository release 7.3.0'), 'Repository releases absent from both channels fail');

    $run = metadata_run($directory, $selected_route, [metadata_entry('7.2.1')], [metadata_entry('7.3.0'), ...$packagist]);
    metadata_same(TRUE, $run['status'] !== 0, 'An intermediate unlisted gap fails');
    metadata_same(TRUE, str_contains(implode("\n", $run['metadata']['errors']), 'emulsify-ds/emulsify-drupal is missing stable repository release 7.2.3'), 'Intermediate omissions are checked even when the route latest matches the repository');
  }
  foreach (['7.2.3', '7.3.0'] as $tag) {
    [$status, , $stderr] = metadata_command(['git', 'tag', '--delete', $tag], $directory);
    metadata_same(0, $status, 'Remove future release fixture: ' . $stderr);
  }

  $run = metadata_run($directory, 'drupal/emulsify', [metadata_entry(), metadata_entry('3.1.0')], $packagist);
  metadata_same(0, $run['status'], 'Different legacy histories before the reconciliation window do not fail current publication checks');
  metadata_same('7.2.2', $run['metadata']['reconciliation_from'], 'Evidence records the release comparison window');

  $run = metadata_run($directory, 'drupal/emulsify', [metadata_entry('7.3.0-beta1')], [metadata_entry()]);
  metadata_same(TRUE, $run['status'] !== 0, 'A prerelease cannot substitute for a stable package');
  metadata_same(['drupal/emulsify has no published stable release.'], $run['metadata']['errors'], 'Missing stable package is explicit');

  $run = metadata_run($directory, 'drupal/emulsify', [metadata_entry('7.3.0-beta1'), ['version' => '7.2.2'], ['version' => '7.2.1', 'require' => '__unset']], [metadata_entry()]);
  metadata_same(0, $run['status'], 'Composer minified metadata inherits unchanged requirements');
  metadata_same("7.2.2\n", $run['stdout'], 'Stable selection ignores newer prereleases');
  metadata_same(metadata_entry()['require'], $run['metadata']['packages']['drupal/emulsify']['require'], 'Earlier stable entry is not mutated by subsequent metadata');

  $run = metadata_run($directory, 'drupal/emulsify', [metadata_entry('7.3.0-beta1'), ['version' => '7.2.2', 'require' => '__unset']], [metadata_entry()]);
  metadata_same(TRUE, $run['status'] !== 0, 'Composer __unset cannot inherit removed requirements');
  metadata_same(2, count($run['metadata']['errors']), 'Both missing requirements fail');

  echo "Published package metadata: {$assertions} assertions passed.\n";
}
finally {
  $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($files as $file) {
    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
  }
  rmdir($directory);
}
