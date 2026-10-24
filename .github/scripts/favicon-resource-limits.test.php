<?php

/**
 * @file
 * Exercises process-wide Imagick limits with deterministic failure injection.
 *
 * Re-executes without Imagick when installed, retaining DOM/GD for the test.
 * These tests cover cleanup behavior; the favicon smoke tests use real Imagick.
 */

declare(strict_types=1);

use Drupal\emulsify\Favicon\FaviconPackageGenerator;

if (class_exists('Imagick')) {
  $command = [PHP_BINARY, '-n'];
  foreach (['dom', 'gd'] as $extension) {
    $path = ini_get('extension_dir') . DIRECTORY_SEPARATOR . $extension . '.' . PHP_SHLIB_SUFFIX;
    if (is_file($path)) {
      array_push($command, '-d', 'extension=' . $path);
    }
  }
  $command[] = __FILE__;
  $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
  if (!is_resource($process)) {
    throw new RuntimeException('Unable to start the isolated resource-limit test.');
  }
  exit(proc_close($process));
}

if (!class_exists('DOMDocument') || !function_exists('imagecreatetruecolor')) {
  throw new RuntimeException('DOM and GD are required for the resource-limit test.');
}

/**
 * Minimal rasterizer double that records limits and injects native failures.
 */
final class FaviconResourceTestImagick {

  public const RESOURCETYPE_MEMORY = 1;
  public const RESOURCETYPE_AREA = 2;
  public const RESOURCETYPE_WIDTH = 3;
  public const RESOURCETYPE_HEIGHT = 4;
  public const LAYERMETHOD_MERGE = 1;

  public static array $limits = [];
  public static array $setCalls = [];
  public static array $readLimits = [];
  public static array $failures = [];
  public static int $clearCalls = 0;
  public static string $png;

  public static function getResourceLimit(int $resource): int|float {
    return self::$limits[$resource];
  }

  public static function setResourceLimit(int $resource, int|float $limit): bool {
    self::$setCalls[] = [$resource, $limit];
    self::fail('set:' . count(self::$setCalls));
    self::$limits[$resource] = $limit;
    return TRUE;
  }

  public function setBackgroundColor(object $color): void {}

  public function setResolution(int $width, int $height): void {}

  public function readImageBlob(string $source): void {
    self::$readLimits = self::$limits;
    self::fail('read');
  }

  public function mergeImageLayers(int $method): self {
    return new self();
  }

  public function setImageFormat(string $format): void {}

  public function getImageBlob(): string {
    self::fail('blob');
    return self::$png;
  }

  public function clear(): void {
    self::$clearCalls++;
    self::fail('clear:' . self::$clearCalls);
  }

  private static function fail(string $operation): void {
    if (isset(self::$failures[$operation])) {
      throw self::$failures[$operation];
    }
  }

}

final class FaviconResourceTestImagickPixel {

  public function __construct(string $color) {}

}

class_alias(FaviconResourceTestImagick::class, 'Imagick');
class_alias(FaviconResourceTestImagickPixel::class, 'ImagickPixel');
require_once __DIR__ . '/../../src/Favicon/FaviconPackageGenerator.php';

$fixture = imagecreatetruecolor(1, 1);
ob_start();
imagepng($fixture);
FaviconResourceTestImagick::$png = (string) ob_get_clean();
unset($fixture);

$generator = (new ReflectionClass(FaviconPackageGenerator::class))->newInstanceWithoutConstructor();
$method = new ReflectionMethod(FaviconPackageGenerator::class, 'createSourceImage');
$caps = [
  Imagick::RESOURCETYPE_MEMORY => 64 * 1024 * 1024,
  Imagick::RESOURCETYPE_AREA => 2048 * 2048,
  Imagick::RESOURCETYPE_WIDTH => 2048,
  Imagick::RESOURCETYPE_HEIGHT => 2048,
];
$assertions = 0;

function resource_same(mixed $expected, mixed $actual, string $message): void {
  global $assertions;
  $assertions++;
  if ($expected !== $actual) {
    throw new RuntimeException($message);
  }
}

function resource_run(array $limits, array $failures = []): ?Throwable {
  global $generator, $method;
  Imagick::$limits = $limits;
  Imagick::$setCalls = [];
  Imagick::$readLimits = [];
  Imagick::$clearCalls = 0;
  Imagick::$failures = $failures;
  try {
    $method->invoke($generator, 'image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="16"/></svg>', 96);
  }
  catch (Throwable $exception) {
    return $exception;
  }
  return NULL;
}

foreach ([
  'negative unlimited' => [array_fill_keys(array_keys($caps), -1), $caps],
  'zero unlimited' => [array_fill_keys(array_keys($caps), 0), $caps],
  'positive unlimited' => [array_fill_keys(array_keys($caps), 1.8446744073709552E+19), $caps],
  'looser host limits' => [array_map(fn($limit) => $limit * 2, $caps), $caps],
  'stricter host limits' => [array_map(fn($limit) => intdiv($limit, 2), $caps), array_map(fn($limit) => intdiv($limit, 2), $caps)],
] as $case => [$initial, $effective]) {
  resource_same(NULL, resource_run($initial), "{$case}: rasterization succeeds");
  resource_same($effective, Imagick::$readLimits, "{$case}: expected limits apply during rasterization");
  resource_same($initial, Imagick::$limits, "{$case}: host limits are restored");
  resource_same(2, Imagick::$clearCalls, "{$case}: both pixel caches are released");
}

$initial = array_fill_keys(array_keys($caps), -1);
$restore_calls = array_map(fn($resource) => [$resource, -1], array_keys($caps));
$render_failure = new RuntimeException('Rasterization failed');
$restore_failure = new RuntimeException('Restoring a limit failed');
$clear_failure = new RuntimeException('Releasing a pixel cache failed');

resource_same($render_failure, resource_run($initial, ['read' => $render_failure]), 'Rasterization errors are preserved');
resource_same($initial, Imagick::$limits, 'Rasterization failure restores every host limit');
resource_same(1, Imagick::$clearCalls, 'Rasterization failure releases the source pixel cache');

$clamp_failure = new RuntimeException('Clamping the second limit failed');
resource_same($clamp_failure, resource_run($initial, ['set:2' => $clamp_failure]), 'Partial clamping preserves the original failure');
resource_same([], Imagick::$readLimits, 'Partial clamping does not start rasterization');
resource_same(array_slice($restore_calls, 0, 2), array_slice(Imagick::$setCalls, 2), 'Partial clamping attempts restoration of every saved limit');
resource_same($initial, Imagick::$limits, 'Partial clamping restores host limits');

resource_same($restore_failure, resource_run($initial, ['set:5' => $restore_failure]), 'A cleanup failure is surfaced after otherwise-successful rasterization');
resource_same($restore_calls, array_slice(Imagick::$setCalls, 4), 'One restoration failure does not prevent later restoration attempts');
resource_same(-1, Imagick::$limits[Imagick::RESOURCETYPE_HEIGHT], 'The final resource is restored after an earlier restoration failure');

resource_same($render_failure, resource_run($initial, ['read' => $render_failure, 'set:5' => $restore_failure, 'set:6' => $restore_failure]), 'Restoration failures cannot mask a rasterization failure');
resource_same($restore_calls, array_slice(Imagick::$setCalls, 4), 'Multiple restoration failures do not skip later limits');

resource_same($clamp_failure, resource_run($initial, ['set:2' => $clamp_failure, 'set:3' => $restore_failure]), 'A restoration failure cannot mask a partial-clamping failure');
resource_same(array_slice($restore_calls, 0, 2), array_slice(Imagick::$setCalls, 2), 'Partial-clamping cleanup continues after a restoration failure');

resource_same($clear_failure, resource_run($initial, ['clear:1' => $clear_failure, 'clear:2' => $restore_failure]), 'The first cleanup failure is preserved');
resource_same(2, Imagick::$clearCalls, 'One clear failure does not skip the other pixel cache');
resource_same($initial, Imagick::$limits, 'Pixel-cache cleanup failures do not skip resource restoration');

resource_same($render_failure, resource_run($initial, ['blob' => $render_failure, 'clear:1' => $clear_failure, 'set:5' => $restore_failure]), 'Pixel-cache and limit cleanup failures cannot mask a blob-generation failure');
resource_same(2, Imagick::$clearCalls, 'Both pixel caches are cleared after a blob-generation failure');
resource_same($restore_calls, array_slice(Imagick::$setCalls, 4), 'All resource restorations are attempted after pixel-cache and blob failures');

echo "Favicon resource limits: {$assertions} assertions passed.\n";
