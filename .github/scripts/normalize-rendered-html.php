<?php

declare(strict_types=1);

if ($argc !== 3) {
  fwrite(STDERR, "Usage: php normalize-rendered-html.php <input> <output>\n");
  exit(1);
}

$input = $argv[1];
$output = $argv[2];

$html = file_get_contents($input);

if ($html === false) {
  fwrite(STDERR, "Unable to read {$input}\n");
  exit(1);
}

$replacements = [
  '/<script\b[^>]*>.*?<\/script>/si' => '',
  '/<link\b[^>]*>/si' => '',
  '/<style\b[^>]*>.*?<\/style>/si' => '',
  '/<meta[^>]+charset[^>]*>/si' => '',
  '/value="[^"]*"(?=[^>]*name="form_build_id")/i' => 'value="__FORM_BUILD_ID__"',
  '/value="[^"]*"(?=[^>]*name="form_token")/i' => 'value="__FORM_TOKEN__"',
  '/name="form_build_id" value="[^"]*"/i' => 'name="form_build_id" value="__FORM_BUILD_ID__"',
  '/name="form_token" value="[^"]*"/i' => 'name="form_token" value="__FORM_TOKEN__"',
  '/data-drupal-selector="form-[^"]+"/i' => 'data-drupal-selector="form-__FORM_BUILD_ID__"',
  '/js-view-dom-id-[a-f0-9]+/i' => 'js-view-dom-id-__HASH__',
  '/\?v=[^"\']+/i' => '',
  '/https?:\/\/127\.0\.0\.1:\d+/i' => '__BASE_URL__',
  '/>\s+</s' => '><',
  '/\s+/s' => ' ',
];

$normalized = preg_replace(array_keys($replacements), array_values($replacements), $html);

if ($normalized === null) {
  fwrite(STDERR, "Normalization failed for {$input}\n");
  exit(1);
}

$normalized = preg_replace_callback(
  '/class="([^"]*)"/i',
  static function (array $matches): string {
    $classes = preg_split('/\s+/', trim($matches[1])) ?: [];
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

file_put_contents($output, trim($normalized) . PHP_EOL);
