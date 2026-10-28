<?php

/**
 * @file
 * Checks real raster pixels for SVG currentColor favicon artwork.
 */

declare(strict_types=1);

use Drupal\emulsify\Favicon\FaviconPackageGenerator;

if (!extension_loaded('imagick') || !extension_loaded('gd')) {
  throw new RuntimeException('The currentColor raster test requires GD and Imagick.');
}

require_once __DIR__ . '/../../src/Favicon/FaviconPackageGenerator.php';
$generator = (new ReflectionClass(FaviconPackageGenerator::class))->newInstanceWithoutConstructor();
$render = new ReflectionMethod(FaviconPackageGenerator::class, 'renderPng');
$cases = [
  'default black with a none root fill' => [
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none"><path d="M0 0h16v16H0z" fill="currentColor"/></svg>',
    [[32, 32, 0x000000]],
  ],
  'inherited color' => [
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" color="#123456"><rect width="16" height="16" fill="currentColor"/></svg>',
    [[32, 32, 0x123456]],
  ],
  'inherited paint with child color overrides' => [
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" color="#123456"><g fill="currentColor"><rect width="8" height="16" color="#ff0000"/><g color="#0000ff"><rect x="8" width="8" height="16"/></g></g></svg>',
    [[16, 32, 0xff0000], [48, 32, 0x0000ff]],
  ],
  'initial color and explicit paint' => [
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" color="#ff0000"><rect width="8" height="16" color="initial" fill="currentColor"/><rect x="8" width="8" height="16" color="#0000ff" fill="#123456"/></svg>',
    [[16, 32, 0x000000], [48, 32, 0x123456]],
  ],
];

foreach ($cases as $label => [$svg, $pixels]) {
  $png = $render->invoke($generator, 'image/svg+xml', $svg, 64, '#ffffff', 0);
  $image = imagecreatefromstring($png);
  foreach ($pixels as [$x, $y, $expected]) {
    $actual = imagecolorat($image, $x, $y) & 0xffffff;
    if ($actual !== $expected) {
      throw new RuntimeException(sprintf('%s: expected #%06x at %d,%d, got #%06x.', $label, $expected, $x, $y, $actual));
    }
  }
  unset($image);
}
fwrite(STDOUT, "PASS SVG currentColor raster pixels: default black, inherited color and paint, child overrides, initial color, and explicit paint.\n");
