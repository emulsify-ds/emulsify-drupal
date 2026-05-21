<?php

declare(strict_types=1);

// Normalizes Drupal-rendered HTML before parity diffs. The goal is to compare
// template output while ignoring request-specific assets, tokens, hashes, and
// small Stable9 compatibility differences that do not represent regressions.
if ($argc !== 3) {
  fwrite(STDERR, "Usage: php normalize-rendered-html.php <input> <output>\n");
  exit(1);
}

$input = $argv[1];
$output = $argv[2];

// Read the captured HTML as raw text. These fixtures are intentionally small,
// so full-file normalization keeps the script simple and easy to diff.
$html = file_get_contents($input);

if ($html === false) {
  fwrite(STDERR, "Unable to read {$input}\n");
  exit(1);
}

$replacements = [
  // Asset tags are sensitive to aggregation state and library ordering.
  '/<script\b[^>]*>.*?<\/script>/si' => '',
  '/<link\b[^>]*>/si' => '',
  '/<style\b[^>]*>.*?<\/style>/si' => '',
  '/<meta[^>]+charset[^>]*>/si' => '',

  // Form identifiers and CSRF tokens are regenerated per request.
  '/value="[^"]*"(?=[^>]*name="form_build_id")/i' => 'value="__FORM_BUILD_ID__"',
  '/value="[^"]*"(?=[^>]*name="form_token")/i' => 'value="__FORM_TOKEN__"',
  '/name="form_build_id" value="[^"]*"/i' => 'name="form_build_id" value="__FORM_BUILD_ID__"',
  '/name="form_token" value="[^"]*"/i' => 'name="form_token" value="__FORM_TOKEN__"',
  '/data-drupal-selector="form-[^"]+"/i' => 'data-drupal-selector="form-__FORM_BUILD_ID__"',

  // Views and PHP built-in server details vary between otherwise equivalent
  // captures.
  '/js-view-dom-id-[a-f0-9]+/i' => 'js-view-dom-id-__HASH__',
  '/\?v=[^"\']+/i' => '',
  '/https?:\/\/127\.0\.0\.1:\d+/i' => '__BASE_URL__',

  // Collapse formatting-only whitespace so Twig indentation changes do not
  // overwhelm parity diffs.
  '/>\s+</s' => '><',
  '/\s+/s' => ' ',
];

// Apply broad text substitutions first. Class-specific cleanup happens below
// because classes need tokenization rather than a single regex replacement.
$normalized = preg_replace(array_keys($replacements), array_values($replacements), $html);

if ($normalized === null) {
  fwrite(STDERR, "Normalization failed for {$input}\n");
  exit(1);
}

$normalized = preg_replace_callback(
  '/class="([^"]*)"/i',
  static function (array $matches): string {
    $classes = preg_split('/\s+/', trim($matches[1])) ?: [];
    // Stable9 adds legacy form-type-* wrapper classes in Drupal 10; the
    // no-Stable9 path intentionally drops them.
    $classes = array_filter(
      $classes,
      static fn(string $class): bool => !str_starts_with($class, 'form-type-')
    );

    return 'class="' . implode(' ', $classes) . '"';
  },
  $normalized
);

if ($normalized === null) {
  fwrite(STDERR, "Class normalization failed for {$input}\n");
  exit(1);
}

// End every normalized file with exactly one newline so directory diffs are
// stable across local and CI environments.
file_put_contents($output, trim($normalized) . PHP_EOL);
