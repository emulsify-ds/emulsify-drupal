<?php

/**
 * @file
 * Mode-driven favicon portability smoke helper.
 */

declare(strict_types=1);

use Drupal\emulsify\Favicon\FaviconPackageGenerator;
use Drupal\emulsify\Favicon\FaviconSettings;

/**
 * Mode-driven favicon portability smoke helper.
 *
 * The shell wrapper runs this script multiple times through Drush so each phase
 * starts with a fresh PHP process. That makes the test closer to real deploy
 * flows where config import, package regeneration, missing-file recovery, and
 * reset operations happen as separate commands.
 */
$mode = getenv('EMULSIFY_FAVICON_MODE') ?: 'assert-generated';
$theme_name = getenv('EMULSIFY_FAVICON_THEME');
$theme_name = is_string($theme_name) && $theme_name !== ''
  ? $theme_name
  : 'emulsify';
$site_name = (string) \Drupal::config('system.site')->get('name');
$svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
  <rect width="512" height="512" rx="96" fill="#0b5d1e"/>
  <path d="M256 112c79.5 0 144 64.5 144 144s-64.5 144-144 144-144-64.5-144-144 64.5-144 144-144Zm0 78c-36.5 0-66 29.5-66 66s29.5 66 66 66 66-29.5 66-66-29.5-66-66-66Z" fill="#ffffff"/>
</svg>
SVG;

if (!class_exists('Imagick')) {
  fwrite(STDERR, "Imagick is required for favicon portability smoke tests.\n");
  exit(1);
}

if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
  fwrite(STDERR, "GD is required for favicon portability smoke tests.\n");
  exit(1);
}

/**
 * Creates the generator used by the smoke assertions.
 */
function emulsify_favicon_generator(): FaviconPackageGenerator {
  return new FaviconPackageGenerator(
    \Drupal::service('file_system'),
    \Drupal::service('file_url_generator'),
    \Drupal::service('config.factory'),
    \Drupal::service('cache_tags.invalidator'),
    \Drupal::service('datetime.time'),
    \Drupal::service('lock'),
  );
}

/**
 * Fails the smoke script with a clear message.
 */
function emulsify_favicon_fail(string $message): void {
  fwrite(STDERR, $message . PHP_EOL);
  exit(1);
}

/**
 * Asserts a condition and exits if it fails.
 */
function emulsify_favicon_assert(bool $condition, string $message): void {
  if (!$condition) {
    emulsify_favicon_fail($message);
  }
}

/**
 * Asserts that a callback throws an InvalidArgumentException.
 */
function emulsify_favicon_assert_invalid(callable $callback, string $expected_message): void {
  try {
    $callback();
  }
  catch (\InvalidArgumentException $exception) {
    emulsify_favicon_assert(
      str_contains($exception->getMessage(), $expected_message),
      sprintf('Expected exception containing "%s", got "%s".', $expected_message, $exception->getMessage()),
    );
    return;
  }

  emulsify_favicon_fail(sprintf('Expected InvalidArgumentException containing "%s".', $expected_message));
}

/**
 * Returns whether source analysis includes a diagnostic warning fragment.
 */
function emulsify_favicon_analysis_has_warning(array $analysis, string $expected_message): bool {
  foreach (($analysis['warnings'] ?? []) as $warning) {
    if (is_string($warning) && str_contains($warning, $expected_message)) {
      return TRUE;
    }
  }

  return FALSE;
}

/**
 * Returns an XPath inspector for the sanitized SVG document.
 */
function emulsify_favicon_sanitized_xpath(array $analysis): \DOMXPath {
  $document = new \DOMDocument();
  emulsify_favicon_assert($document->loadXML((string) ($analysis['sanitized_svg'] ?? ''), LIBXML_NONET), 'Sanitized SVG should remain valid XML.');
  $xpath = new \DOMXPath($document);
  $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');
  return $xpath;
}

/**
 * Loads normalized theme settings.
 */
function emulsify_favicon_load_settings(string $theme_name, string $site_name): array {
  $stored = \Drupal::configFactory()->get($theme_name . '.settings')->getRawData();
  return FaviconSettings::normalize(is_array($stored) ? $stored : [], $site_name);
}

/**
 * Returns normalized default favicon settings for the current site.
 */
function emulsify_favicon_default_settings(string $site_name): array {
  return FaviconSettings::normalize(FaviconSettings::DEFAULTS, $site_name);
}

/**
 * Persists generated package metadata back to theme settings.
 */
function emulsify_favicon_save_generated_state(string $theme_name, array $settings): void {
  $config = \Drupal::configFactory()->getEditable($theme_name . '.settings');
  foreach (FaviconSettings::DEFAULTS as $key => $default) {
    $config->set($key, $settings[$key] ?? $default);
  }
  $config->save();
}

/**
 * Exercises the SVG sanitizer allow/strip/reject matrix.
 */
function emulsify_favicon_run_sanitizer_matrix(FaviconPackageGenerator $generator): void {
  $simple = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#000"/></svg>';
  $symbols = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><symbol id="mark"><circle cx="32" cy="32" r="16"/></symbol><use href="#mark"/></svg>';
  $gradients = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g"><stop offset="0%" stop-color="#000"/><stop offset="100%" stop-color="#fff"/></linearGradient></defs><rect width="64" height="64" fill="url(#g)"/></svg>';
  $raster = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><image href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z5x8AAAAASUVORK5CYII=" width="64" height="64"/></svg>';

  // Allowed SVG features used by real design-system icons.
  $analysis = $generator->validateSourceSvg($simple, FALSE);
  emulsify_favicon_assert(($analysis['sanitized_svg'] ?? '') !== '', 'Simple square SVG should be accepted.');

  $analysis = $generator->validateSourceSvg($symbols, FALSE);
  emulsify_favicon_assert(str_contains((string) $analysis['sanitized_svg'], '<symbol id="mark"'), 'Symbol definitions should be preserved.');
  emulsify_favicon_assert(str_contains((string) $analysis['sanitized_svg'], 'href="#mark"'), 'Symbol/use references should be preserved.');
  emulsify_favicon_assert(!emulsify_favicon_analysis_has_warning($analysis, 'Unsafe SVG markup was removed'), 'Local symbol/use references should not trigger an unsafe-markup warning.');

  $analysis = $generator->validateSourceSvg($gradients, FALSE);
  emulsify_favicon_assert(str_contains((string) $analysis['sanitized_svg'], 'linearGradient'), 'Inline gradients should be preserved.');

  $analysis = $generator->validateSourceSvg($raster, FALSE);
  emulsify_favicon_assert(
    !empty($analysis['has_embedded_raster_images']),
    'Base64 embedded raster images should be detected.',
  );
  emulsify_favicon_assert(str_contains((string) $analysis['sanitized_svg'], '<image href="data:image/png;base64,'), 'Base64 embedded raster image elements and href values should be preserved.');
  emulsify_favicon_assert(!emulsify_favicon_analysis_has_warning($analysis, 'Unsafe SVG markup was removed'), 'Embedded PNG images should not trigger an unsafe-markup warning.');

  // Export wrappers must preserve artwork when their semantics are removed.
  $illustrator = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 64 64">
  <metadata><sfw xmlns="http://ns.adobe.com/SaveForWeb/1.0/"><slices/><sliceSourceBounds width="64" height="64"/></sfw></metadata>
  <switch><g><circle id="illustrator-circle" cx="32" cy="32" r="24" fill="#ff0000"/><path id="illustrator-path" d="M16 32h32" stroke="#000000"/></g></switch>
</svg>
SVG;
  $analysis = $generator->validateSourceSvg($illustrator, FALSE);
  $xpath = emulsify_favicon_sanitized_xpath($analysis);
  emulsify_favicon_assert($xpath->evaluate('count(/svg:svg/svg:g/svg:circle[@id="illustrator-circle"] | /svg:svg/svg:g/svg:path[@id="illustrator-path"])') === 2.0, 'Illustrator switch exports should retain their circle and path artwork.');
  emulsify_favicon_assert($xpath->evaluate('count(//svg:switch)') === 0.0, 'Switch wrappers should be unwrapped.');
  emulsify_favicon_assert($xpath->evaluate('count(//svg:metadata | //*[namespace-uri()="http://ns.adobe.com/SaveForWeb/1.0/"])') === 0.0, 'Illustrator Save-for-Web metadata should be removed.');
  emulsify_favicon_assert(!emulsify_favicon_analysis_has_warning($analysis, 'Unsafe SVG markup was removed'), 'Benign Illustrator switch exports should not trigger an unsafe-markup warning.');

  $linked = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
  <rect id="background" width="64" height="64"/>
  <a href="#mark"><circle id="mark" cx="32" cy="32" r="24"/><switch><path id="detail" d="M16 32h32"/></switch></a>
  <rect id="foreground" width="8" height="8"/>
</svg>
SVG;
  $analysis = $generator->validateSourceSvg($linked, FALSE);
  $xpath = emulsify_favicon_sanitized_xpath($analysis);
  $shape_ids = [];
  foreach ($xpath->query('/svg:svg/svg:rect | /svg:svg/svg:circle | /svg:svg/svg:path') as $shape) {
    $shape_ids[] = $shape->getAttribute('id');
  }
  emulsify_favicon_assert($shape_ids === ['background', 'mark', 'detail', 'foreground'], 'Unwrapping nested link and switch elements should preserve every shape in drawing order.');
  emulsify_favicon_assert($xpath->evaluate('count(//svg:a | //svg:switch)') === 0.0, 'Links and nested switches should be unwrapped.');
  emulsify_favicon_assert(!emulsify_favicon_analysis_has_warning($analysis, 'Unsafe SVG markup was removed'), 'Local artwork links should not trigger an unsafe-markup warning.');

  $pattern = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
  <defs><pattern id="tiles" patternUnits="userSpaceOnUse" width="16" height="16"><rect width="16" height="16" fill="#ff0000"/><rect width="8" height="16" fill="#0000ff"/></pattern></defs>
  <rect id="pattern-artwork" width="64" height="64" fill="url(#tiles)"/>
</svg>
SVG;
  $analysis = $generator->validateSourceSvg($pattern, FALSE);
  $xpath = emulsify_favicon_sanitized_xpath($analysis);
  emulsify_favicon_assert($xpath->evaluate('count(/svg:svg/svg:defs/svg:pattern[@id="tiles"]/svg:rect)') === 2.0, 'Pattern fill definitions should retain their drawing primitives.');
  emulsify_favicon_assert($xpath->evaluate('count(/svg:svg/svg:rect[@id="pattern-artwork" and @fill="url(#tiles)"])') === 1.0, 'Pattern-filled artwork should retain its local fill reference.');
  emulsify_favicon_assert(!emulsify_favicon_analysis_has_warning($analysis, 'Unsafe SVG markup was removed'), 'Local pattern fills should not trigger an unsafe-markup warning.');

  $inkscape = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#" viewBox="0 0 64 64" inkscape:version="1.4" sodipodi:docname="icon.svg">
  <metadata><rdf:RDF><cc:Work rdf:about=""><dc:format>image/svg+xml</dc:format><dc:title>Icon</dc:title></cc:Work></rdf:RDF></metadata>
  <sodipodi:namedview id="namedview1" inkscape:zoom="8"/>
  <defs><inkscape:perspective id="perspective1"/></defs>
  <g inkscape:label="Layer 1" inkscape:groupmode="layer"><circle id="inkscape-artwork" sodipodi:cx="32" cx="32" cy="32" r="24"/></g>
</svg>
SVG;
  $analysis = $generator->validateSourceSvg($inkscape, FALSE);
  $xpath = emulsify_favicon_sanitized_xpath($analysis);
  emulsify_favicon_assert($xpath->evaluate('count(//svg:circle[@id="inkscape-artwork"])') === 1.0, 'Inkscape artwork should survive editor metadata cleanup.');
  emulsify_favicon_assert($xpath->evaluate('count(//svg:metadata | //*[namespace-uri() != "http://www.w3.org/2000/svg"] | //@*[namespace-uri() != ""])') === 0.0, 'Inkscape metadata, editor elements, and editor attributes should be removed.');
  emulsify_favicon_assert(!emulsify_favicon_analysis_has_warning($analysis, 'Unsafe SVG markup was removed'), 'Benign Inkscape exports should produce zero unsafe-markup warnings.');
  emulsify_favicon_assert(($analysis['removed_unsafe_count'] ?? NULL) === 0, 'Benign Inkscape cleanup should record zero unsafe removals.');
  emulsify_favicon_assert(($analysis['removed_metadata_count'] ?? 0) > 0, 'Benign Inkscape cleanup should track editor metadata removals separately.');

  // These inert presentation elements were supported before the allow-list.
  $presentation = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
  <defs>
    <path id="baseline" d="M0 32h64"/>
    <marker id="arrow" markerWidth="8" markerHeight="8"><path d="M0 0l8 4-8 4z"/></marker>
    <filter id="effects">
      <feGaussianBlur stdDeviation="1"/><feColorMatrix type="saturate" values="1"/><feOffset dx="1"/>
      <feBlend in2="SourceGraphic"/><feComposite in2="SourceGraphic"/><feFlood flood-color="#ff0000"/>
      <feMerge><feMergeNode in="SourceGraphic"/></feMerge><feDropShadow dx="1" dy="1"/>
      <feMorphology radius="1"/><feTurbulence baseFrequency="0.1"/><feDisplacementMap in2="SourceGraphic"/>
      <feImage href="#baseline"/><feTile/>
      <feSpecularLighting><fePointLight x="32" y="32" z="10"/></feSpecularLighting>
      <feDiffuseLighting><feDistantLight azimuth="45" elevation="45"/></feDiffuseLighting>
      <feSpecularLighting><feSpotLight x="32" y="32" z="10"/></feSpecularLighting>
      <feComponentTransfer><feFuncR type="identity"/><feFuncG type="identity"/><feFuncB type="identity"/><feFuncA type="identity"/></feComponentTransfer>
      <feConvolveMatrix order="1" kernelMatrix="1"/>
    </filter>
  </defs>
  <rect width="64" height="64" filter="url(#effects)"/>
  <path d="M0 32h64" marker-end="url(#arrow)"/>
  <text><textPath href="#baseline">Icon</textPath></text>
</svg>
SVG;
  $analysis = $generator->validateSourceSvg($presentation, FALSE);
  $xpath = emulsify_favicon_sanitized_xpath($analysis);
  foreach ([
    'filter', 'feGaussianBlur', 'feColorMatrix', 'feOffset', 'feBlend',
    'feComposite', 'feFlood', 'feMerge', 'feMergeNode', 'feDropShadow',
    'feMorphology', 'feTurbulence', 'feDisplacementMap', 'feImage', 'feTile',
    'feSpecularLighting', 'feDiffuseLighting', 'fePointLight', 'feDistantLight',
    'feSpotLight', 'feComponentTransfer', 'feFuncR', 'feFuncG', 'feFuncB',
    'feFuncA', 'feConvolveMatrix', 'marker', 'textPath',
  ] as $element_name) {
    emulsify_favicon_assert($xpath->evaluate('count(//svg:' . $element_name . ')') > 0, $element_name . ' presentation elements should be preserved.');
  }
  emulsify_favicon_assert($xpath->evaluate('count(//svg:marker/svg:path | //svg:rect[@filter="url(#effects)"] | //svg:path[@marker-end="url(#arrow)"] | //svg:textPath[@href="#baseline"])') === 4.0, 'Filter, marker, and textPath artwork and local references should survive.');
  emulsify_favicon_assert(!emulsify_favicon_analysis_has_warning($analysis, 'Unsafe SVG markup was removed'), 'Inert presentation elements should not trigger an unsafe-markup warning.');

  // Every bypass must report cleanup, not just a general source warning.
  $nested_svg = base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
  $unsafe_vectors = [
    'Animated href' => [
      '<a href="#x"><animate attributeName="href" values="javascript:alert(1)"/></a>',
      ['<animate', 'javascript:'],
    ],
    'Set xlink:href' => [
      '<a><set attributeName="xlink:href" to="javascript:alert(1)"/></a>',
      ['<set', 'javascript:'],
    ],
    'Script handler' => [
      '<handler type="text/ecmascript">alert(1)</handler>',
      ['<handler', 'alert(1)'],
    ],
    'Namespaced event handler' => [
      '<rect ev:onload="alert(1)" xmlns:ev="http://www.w3.org/2001/xml-events"/>',
      ['onload=', 'alert(1)'],
    ],
    'Nested SVG data URI' => [
      '<image href="data:image/svg+xml;base64,' . $nested_svg . '"/>',
      ['data:image/svg+xml', $nested_svg],
    ],
    'Remote filter image' => [
      '<filter><feImage href="https://example.com/icon.png"/></filter>',
      ['https://example.com/icon.png'],
    ],
    'Script filter image' => [
      '<filter><feImage href="javascript:alert(1)"/></filter>',
      ['javascript:', 'alert(1)'],
    ],
    'Nested SVG filter image' => [
      '<filter><feImage href="data:image/svg+xml;base64,' . $nested_svg . '"/></filter>',
      ['data:image/svg+xml', $nested_svg],
    ],
    'Namespaced remote filter image' => [
      '<filter><feImage xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="https://example.com/icon.png"/></filter>',
      ['https://example.com/icon.png'],
    ],
    'Unsafe link wrapper' => [
      '<a href="javascript:alert(1)"><circle cx="32" cy="32" r="16"/></a>',
      ['javascript:', 'alert(1)'],
    ],
    'Event on switch wrapper' => [
      '<switch xmlns:ev="http://www.w3.org/2001/xml-events" ev:onload="alert(1)"><circle cx="32" cy="32" r="16"/></switch>',
      ['onload=', 'alert(1)'],
    ],
    'Event on metadata' => [
      '<metadata onload="alert(1)">Editor data</metadata>',
      ['onload=', 'alert(1)'],
    ],
    'Event on editor element' => [
      '<inkscape:perspective xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" onload="alert(1)"/>',
      ['onload=', 'alert(1)'],
    ],
    'Script inside metadata' => [
      '<metadata><script>alert(1)</script></metadata>',
      ['<script', 'alert(1)'],
    ],
  ];
  foreach ($unsafe_vectors as $label => [$markup, $forbidden_fragments]) {
    $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">' . $markup . '<rect width="64" height="64"/></svg>', FALSE);
    foreach ($forbidden_fragments as $fragment) {
      emulsify_favicon_assert(!str_contains((string) $analysis['sanitized_svg'], $fragment), $label . ' payload should be stripped from sanitized SVG output.');
    }
    emulsify_favicon_assert(emulsify_favicon_analysis_has_warning($analysis, 'Unsafe SVG markup was removed'), $label . ' cleanup should produce an unsafe-markup warning.');
  }

  // Dangerous markup should be stripped without rejecting usable SVGs.
  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><script>alert(1)</script><rect width="64" height="64"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains((string) $analysis['sanitized_svg'], '<script'), 'Script tags should be stripped from sanitized SVG output.');
  emulsify_favicon_assert(emulsify_favicon_analysis_has_warning($analysis, 'Unsafe SVG markup was removed'), 'Sanitized SVG cleanup should be reported as a warning.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><foreignObject><div>bad</div></foreignObject><rect width="64" height="64"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains(strtolower((string) $analysis['sanitized_svg']), 'foreignobject'), 'foreignObject nodes should be stripped from sanitized SVG output.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><use href="https://example.com/icon.svg#mark"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains((string) $analysis['sanitized_svg'], 'https://example.com/icon.svg'), 'External href values should be stripped.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><use href="javascript:alert(1)"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains(strtolower((string) $analysis['sanitized_svg']), 'javascript:'), 'javascript: href values should be stripped.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><image href="https://example.com/icon.png" width="64" height="64"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains((string) $analysis['sanitized_svg'], 'https://example.com/icon.png'), 'Remote image href values should be stripped.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" onload="alert(1)"><rect width="64" height="64" onclick="alert(1)"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains(strtolower((string) $analysis['sanitized_svg']), 'onclick='), 'Inline event handlers should be stripped.');
  emulsify_favicon_assert(!str_contains(strtolower((string) $analysis['sanitized_svg']), 'onload='), 'Root event handlers should be stripped.');

  $style_element_svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
  <style>@import url("https://example.com/favicon.css"); rect { fill: url("javascript:alert(1)"); }</style>
  <rect width="64" height="64"/>
</svg>
SVG;
  $analysis = $generator->validateSourceSvg($style_element_svg, FALSE);
  $sanitized_svg = strtolower((string) $analysis['sanitized_svg']);
  emulsify_favicon_assert(!str_contains($sanitized_svg, '<style'), 'Style elements should be stripped from sanitized SVG output.');
  emulsify_favicon_assert(!str_contains($sanitized_svg, '@import'), 'CSS @import rules should be stripped from sanitized SVG output.');
  emulsify_favicon_assert(!str_contains($sanitized_svg, 'https://example.com/favicon.css'), 'External CSS imports should be stripped from sanitized SVG output.');
  emulsify_favicon_assert(!str_contains($sanitized_svg, 'javascript:'), 'CSS javascript: URLs should be stripped from sanitized SVG output.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" style="fill:url(https://example.com/icon.svg#mark);stroke:url(javascript:alert(1))"/></svg>', FALSE);
  $sanitized_svg = strtolower((string) $analysis['sanitized_svg']);
  emulsify_favicon_assert(!str_contains($sanitized_svg, 'style='), 'Inline style attributes should be stripped from sanitized SVG output.');
  emulsify_favicon_assert(!str_contains($sanitized_svg, 'https://example.com/icon.svg'), 'External style attribute URLs should be stripped from sanitized SVG output.');
  emulsify_favicon_assert(!str_contains($sanitized_svg, 'javascript:'), 'javascript: style attribute URLs should be stripped from sanitized SVG output.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g"><stop offset="0%" stop-color="#000"/><stop offset="100%" stop-color="#fff"/></linearGradient></defs><rect width="64" height="64" fill="url(#g)" filter="url(https://example.com/filter.svg#f)"/></svg>', FALSE);
  $sanitized_svg = (string) $analysis['sanitized_svg'];
  emulsify_favicon_assert(!str_contains($sanitized_svg, 'https://example.com/filter.svg'), 'External CSS url() attribute references should be stripped from sanitized SVG output.');
  emulsify_favicon_assert(str_contains($sanitized_svg, 'fill="url(#g)"'), 'Local CSS url() fragment references should be preserved.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" filter="url(javascript:alert(1))"/></svg>', FALSE);
  emulsify_favicon_assert(!str_contains(strtolower((string) $analysis['sanitized_svg']), 'javascript:'), 'javascript: CSS url() attribute references should be stripped from sanitized SVG output.');

  $analysis = $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 32"><rect width="64" height="32"/></svg>', FALSE);
  emulsify_favicon_assert(
    ($analysis['view_box'] ?? []) === [0.0, -16.0, 64.0, 64.0],
    'Non-square SVG sources should be centered on a square viewBox.',
  );
  emulsify_favicon_assert(
    str_contains((string) $analysis['sanitized_svg'], 'width="64" height="64"'),
    'Non-square SVG sources should receive square root dimensions.',
  );

  // Hard rejects protect package generation from excessive input.
  $dimension_limit = FaviconPackageGenerator::MAX_SVG_DIMENSION;
  $analysis = $generator->validateSourceSvg(sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d"><rect width="%1$d" height="%1$d"/></svg>', $dimension_limit), FALSE);
  emulsify_favicon_assert(($analysis['view_box'] ?? []) === [0.0, 0.0, (float) $dimension_limit, (float) $dimension_limit], 'SVG dimensions at the documented limit should be accepted.');

  // FALSE verifies that dimension checks run without attempting rasterization.
  foreach ([
    sprintf('0 0 %d 64', $dimension_limit + 1),
    sprintf('0 0 64 %d', $dimension_limit + 1),
    '0 0 100000000 100000000',
    '0 0 1e5 1e5',
  ] as $view_box) {
    emulsify_favicon_assert_invalid(
      fn() => $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . $view_box . '"><rect width="64" height="64"/></svg>', FALSE),
      'SVG viewBox width and height must not exceed ' . $dimension_limit,
    );
  }
  foreach ([
    sprintf('width="%d" height="64"', $dimension_limit + 1),
    sprintf('width="64" height="%d"', $dimension_limit + 1),
    'width="1e9" height="64"',
  ] as $dimensions) {
    emulsify_favicon_assert_invalid(
      fn() => $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" ' . $dimensions . '><rect width="64" height="64"/></svg>', FALSE),
      'SVG width and height must not exceed ' . $dimension_limit,
    );
  }
  foreach (['0 0 1e309 64', '1e309 0 64 64'] as $view_box) {
    emulsify_favicon_assert_invalid(
      fn() => $generator->validateSourceSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . $view_box . '"><rect width="64" height="64"/></svg>', FALSE),
      'must define a viewBox',
    );
  }

  $oversized_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><desc>' . str_repeat('x', FaviconPackageGenerator::MAX_FILE_SIZE) . '</desc></svg>';
  emulsify_favicon_assert_invalid(
    fn() => $generator->validateSourceSvg($oversized_svg, FALSE),
    'smaller than 5 MB',
  );
}

$generator = emulsify_favicon_generator();

switch ($mode) {
  case 'prepare':
    // Store every portable setting needed to regenerate the package from config
    // alone, then clear package metadata so generate must create fresh files.
    \Drupal::configFactory()
      ->getEditable($theme_name . '.settings')
      ->set('favicon_package_enabled', TRUE)
      ->set('favicon_source_fid', [])
      ->set('favicon_source_svg', $svg)
      ->set('favicon_source_filename', 'portable-release-check.svg')
      ->set('favicon_background_color', '#0b5d1e')
      ->set('favicon_theme_color', '#0b5d1e')
      ->set('favicon_ios_background_color', '#ffffff')
      ->set('favicon_ios_padding', 16)
      ->set('favicon_ios_icon_name', 'Emulsify')
      ->set('favicon_android_background_color', '#0b5d1e')
      ->set('favicon_android_padding', 20)
      ->set('favicon_android_maskable_enabled', TRUE)
      ->set('favicon_manifest_name', $site_name)
      ->set('favicon_manifest_short_name', 'Emulsify')
      ->set('favicon_manifest_display', 'standalone')
      ->set('favicon_package_hash', '')
      ->set('favicon_package_path', '')
      ->set('favicon_package_generated_at', 0)
      ->save();
    fwrite(STDOUT, "Prepared portable favicon config for {$theme_name}.\n");
    return;

  case 'delete-package':
    // Simulate a deploy/filesystem state where config references a package but
    // generated files are missing and need to be recreated.
    $settings = emulsify_favicon_load_settings($theme_name, $site_name);
    $definition = $generator->getPackageDefinition($theme_name, $settings, $svg);
    $realpath = \Drupal::service('file_system')->realpath($definition['path']);
    if ($realpath && is_dir($realpath)) {
      \Drupal::service('file_system')->deleteRecursive($realpath);
    }
    emulsify_favicon_assert(!$generator->packageExists($definition['path']), sprintf('Expected the favicon package at %s to be deleted.', $definition['path']));
    fwrite(STDOUT, "Deleted generated package at {$definition['path']}.\n");
    return;

  case 'generate':
    // Generate from the portable SVG stored in config instead of from an upload
    // entity, which is the critical behavior after config import.
    $settings = emulsify_favicon_load_settings($theme_name, $site_name);
    $source_svg = FaviconSettings::getPortableSourceSvg($settings);
    emulsify_favicon_assert($source_svg !== '', 'Expected a portable SVG source before generation.');

    $result = $generator->generateFromSvg(
      $theme_name,
      $source_svg,
      $settings,
      [
        'filename' => (string) ($settings['favicon_source_filename'] ?: 'portable-release-check.svg'),
      ],
    );
    $settings['favicon_package_hash'] = $result['hash'];
    $settings['favicon_package_path'] = $result['path'];
    $settings['favicon_package_generated_at'] = $result['generated_at'];
    emulsify_favicon_save_generated_state($theme_name, $settings);
    fwrite(STDOUT, "Generated portable favicon package at {$result['path']}.\n");
    return;

  case 'assert-generated':
    // Verify deterministic package metadata and the actual file payload. The
    // sanitizer matrix runs here so it is covered after each generation path.
    $settings = emulsify_favicon_load_settings($theme_name, $site_name);
    $definition = $generator->getPackageDefinition($theme_name, $settings, $svg);
    emulsify_favicon_assert($settings['favicon_package_hash'] === $definition['hash'], 'Theme config should store the deterministic favicon package hash.');
    emulsify_favicon_assert($settings['favicon_package_path'] === $definition['path'], 'Theme config should store the deterministic favicon package path.');
    emulsify_favicon_assert($generator->packageExists($definition['path']), sprintf('Expected generated favicon package at %s.', $definition['path']));

    $realpath = \Drupal::service('file_system')->realpath($definition['path']);
    emulsify_favicon_assert(is_string($realpath) && is_dir($realpath), 'Expected a real generated package directory.');

    $expected_files = [
      'favicon.svg',
      'favicon.ico',
      'favicon-96x96.png',
      'apple-touch-icon.png',
      'web-app-manifest-192x192.png',
      'web-app-manifest-512x512.png',
      'web-app-manifest-512x512-maskable.png',
      'site.webmanifest',
      'metadata.json',
    ];
    foreach ($expected_files as $expected_file) {
      emulsify_favicon_assert(is_file($realpath . DIRECTORY_SEPARATOR . $expected_file), sprintf('Missing generated favicon asset %s.', $expected_file));
    }

    $metadata = $generator->readPackageMetadata($definition['path']);
    emulsify_favicon_assert(is_array($metadata), 'Expected metadata.json to decode into an array.');
    emulsify_favicon_assert(($metadata['hash'] ?? '') === $definition['hash'], 'metadata.json should preserve the deterministic package hash.');
    emulsify_favicon_assert(($metadata['source']['filename'] ?? '') === 'portable-release-check.svg', 'metadata.json should preserve the source filename.');

    emulsify_favicon_run_sanitizer_matrix($generator);
    fwrite(STDOUT, "Verified portable favicon regeneration and sanitizer coverage for {$theme_name}.\n");
    return;

  case 'assert-reset':
    // Reset should return theme settings to schema defaults, not just delete
    // files from the public filesystem.
    $settings = emulsify_favicon_load_settings($theme_name, $site_name);
    foreach (emulsify_favicon_default_settings($site_name) as $key => $expected_value) {
      emulsify_favicon_assert($settings[$key] === $expected_value, sprintf('Expected %s to reset to its default value.', $key));
    }
    fwrite(STDOUT, "Verified favicon reset defaults for {$theme_name}.\n");
    return;

  case 'reset':
    // Remove both the stored package path and the deterministic package path
    // derived from the portable source so stale generated directories do not
    // survive a reset operation.
    $settings = emulsify_favicon_load_settings($theme_name, $site_name);
    $package_paths = array_filter([(string) ($settings['favicon_package_path'] ?? '')]);
    $source_svg = FaviconSettings::getPortableSourceSvg($settings);
    if ($source_svg !== '') {
      $definition = $generator->getPackageDefinition($theme_name, $settings, $source_svg);
      $package_paths[] = $definition['path'];
    }

    foreach (array_unique($package_paths) as $package_path) {
      $realpath = \Drupal::service('file_system')->realpath($package_path);
      if ($realpath && is_dir($realpath)) {
        \Drupal::service('file_system')->deleteRecursive($realpath);
      }
    }

    $config = \Drupal::configFactory()->getEditable($theme_name . '.settings');
    foreach (FaviconSettings::DEFAULTS as $key => $value) {
      $config->set($key, $value);
    }
    $config
      ->set('features.favicon', TRUE)
      ->set('favicon.use_default', TRUE)
      ->set('favicon.path', '')
      ->save();
    fwrite(STDOUT, "Reset favicon settings for {$theme_name}.\n");
    return;

  default:
    emulsify_favicon_fail(sprintf('Unsupported portability smoke mode %s.', $mode));
}
