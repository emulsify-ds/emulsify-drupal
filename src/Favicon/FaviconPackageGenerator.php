<?php

declare(strict_types=1);

namespace Drupal\emulsify\Favicon;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\file\Entity\File;

/**
 * Generates favicon packages from a single uploaded SVG icon file.
 */
final class FaviconPackageGenerator {

  /**
   * Maximum allowed upload size in bytes.
   */
  public const MAX_FILE_SIZE = 5242880;

  /**
   * Portable SVG config larger than this should be treated as review noise.
   */
  public const PORTABLE_SOURCE_ADVISORY_SIZE = 262144;

  /**
   * The file system service.
   */
  private FileSystemInterface $fileSystem;

  /**
   * The file URL generator.
   */
  private FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The config factory.
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * Cache tag invalidator.
   */
  private CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * Time service used for deterministic package timestamps.
   */
  private TimeInterface $time;

  /**
   * Coordinates package generation across concurrent requests and commands.
   */
  private LockBackendInterface $lock;

  /**
   * Creates a generator instance.
   */
  public function __construct(
    FileSystemInterface $file_system,
    FileUrlGeneratorInterface $file_url_generator,
    ConfigFactoryInterface $config_factory,
    CacheTagsInvalidatorInterface $cache_tags_invalidator,
    TimeInterface $time,
    LockBackendInterface $lock,
  ) {
    $this->fileSystem = $file_system;
    $this->fileUrlGenerator = $file_url_generator;
    $this->configFactory = $config_factory;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->time = $time;
    $this->lock = $lock;
  }

  /**
   * Validates a source file before generation.
   *
   * @return array<string, mixed>
   *   An analysis of the sanitized SVG source.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the icon file is unusable.
   */
  public function validateSourceFile(File $source_file, bool $requires_rasterization = TRUE): array {
    [$source_data, $mime_type, $extension] = $this->loadSourceAsset($source_file);

    return $this->validateSourceAsset(
      $source_data,
      $mime_type,
      $extension,
      $source_file->getSize(),
      $requires_rasterization,
    );
  }

  /**
   * Validates an SVG source string before generation.
   *
   * @return array<string, mixed>
   *   An analysis of the sanitized SVG source.
   */
  public function validateSourceSvg(string $source_data, bool $requires_rasterization = TRUE): array {
    return $this->validateSourceAsset(
      $source_data,
      'image/svg+xml',
      'svg',
      strlen($source_data),
      $requires_rasterization,
    );
  }

  /**
   * Returns the deterministic package hash and directory.
   *
   * @return array{hash: string, path: string, source_hash: string}
   *   The expected package definition.
   */
  public function getPackageDefinition(string $theme_name, array $settings, string $source_data): array {
    $normalized = FaviconSettings::normalize(
      $settings,
      (string) $this->configFactory->get('system.site')->get('name'),
    );
    $analysis = $this->validateSourceSvg($source_data, FALSE);
    $package_hash = $this->buildPackageHash($normalized, (string) $analysis['source_hash']);

    return [
      'hash' => $package_hash,
      'path' => $this->buildPackageDirectory($theme_name, $package_hash),
      'source_hash' => (string) $analysis['source_hash'],
    ];
  }

  /**
   * Determines whether a generated package directory exists.
   */
  public function packageExists(string $package_directory): bool {
    $realpath = $this->fileSystem->realpath($package_directory);
    return is_string($realpath)
      && is_dir($realpath)
      && is_file($realpath . '/metadata.json');
  }

  /**
   * Reads generated metadata for an existing package.
   *
   * @return array<string, mixed>|null
   *   The decoded metadata, or NULL if it is unavailable.
   */
  public function readPackageMetadata(string $package_directory): ?array {
    if (!$this->packageExists($package_directory)) {
      return NULL;
    }

    $metadata = @file_get_contents($package_directory . '/metadata.json');
    if (!is_string($metadata) || $metadata === '') {
      return NULL;
    }

    $decoded = json_decode($metadata, TRUE);
    return is_array($decoded) ? $decoded : NULL;
  }

  /**
   * Reports whether the current PHP environment can rasterize generated assets.
   *
   * @return array{gd: bool, imagick: bool}
   *   Dependency availability keyed by extension name.
   */
  public function getRuntimeDependencyStatus(): array {
    return [
      'gd' => function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor'),
      'imagick' => class_exists('Imagick'),
    ];
  }

  /**
   * Generates a full favicon package from a managed file.
   *
   * @return array{hash: string, path: string, generated_at: int}
   *   Generated package metadata.
   */
  public function generate(string $theme_name, File $source_file, array $settings, bool $overwrite = FALSE): array {
    $analysis = $this->validateSourceFile($source_file, TRUE);

    return $this->generateFromSvg(
      $theme_name,
      (string) $analysis['sanitized_svg'],
      $settings,
      [
        'file_id' => (int) $source_file->id(),
        'filename' => $source_file->getFilename(),
      ],
      $overwrite,
    );
  }

  /**
   * Generates a full favicon package from portable SVG source markup.
   *
   * @param string $theme_name
   *   Theme machine name.
   * @param string $source_data
   *   Portable SVG source markup.
   * @param array<string, mixed> $settings
   *   Favicon package settings.
   * @param array<string, mixed> $source
   *   Optional source metadata such as file ID or filename.
   * @param bool $overwrite
   *   Whether to replace an existing package directory.
   *
   * @return array{hash: string, path: string, generated_at: int}
   *   Generated package metadata.
   */
  public function generateFromSvg(string $theme_name, string $source_data, array $settings, array $source = [], bool $overwrite = FALSE): array {
    $normalized = FaviconSettings::normalize(
      $settings,
      (string) $this->configFactory->get('system.site')->get('name'),
    );
    $analysis = $this->validateSourceSvg($source_data, TRUE);
    $source_svg = (string) $analysis['sanitized_svg'];
    $definition = $this->getPackageDefinition($theme_name, $normalized, $source_svg);
    $package_hash = $definition['hash'];
    $package_directory = $definition['path'];
    $generated_at = $this->time->getRequestTime();
    $lock_id = $this->buildGenerationLockId($theme_name, $package_hash);
    if (!$this->lock->acquire($lock_id, 30.0)) {
      if (!$overwrite && $this->packageExists($package_directory)) {
        return $this->buildExistingPackageResult($package_hash, $package_directory);
      }

      throw new \RuntimeException(sprintf('Favicon package generation is already in progress for theme %s.', $theme_name));
    }

    try {
      if (!$overwrite && $this->packageExists($package_directory)) {
        return $this->buildExistingPackageResult($package_hash, $package_directory);
      }

      $this->resetPackageDirectory($package_directory);

      if (!$this->fileSystem->prepareDirectory($package_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        throw new \RuntimeException(sprintf('Unable to prepare generated favicon directory %s.', $package_directory));
      }

      $browser_padding = min($normalized['favicon_ios_padding'], $normalized['favicon_android_padding']);
      $this->writeBytes(
        $package_directory . '/favicon.svg',
        $this->buildSvgFavicon(
          'image/svg+xml',
          $source_svg,
          $normalized['favicon_background_color'],
          $browser_padding,
          FALSE,
        ),
      );

      $browser_png_32 = $this->renderPng(
        'image/svg+xml',
        $source_svg,
        32,
        $normalized['favicon_background_color'],
        $browser_padding,
      );
      $browser_png_96 = $this->renderPng(
        'image/svg+xml',
        $source_svg,
        96,
        $normalized['favicon_background_color'],
        $browser_padding,
      );
      $this->writeBytes($package_directory . '/favicon.ico', $this->createIcoFile($browser_png_32, 32));
      $this->writeBytes($package_directory . '/favicon-96x96.png', $browser_png_96);

      $this->writeBytes(
        $package_directory . '/apple-touch-icon.png',
        $this->renderPng(
          'image/svg+xml',
          $source_svg,
          180,
          $normalized['favicon_ios_background_color'],
          $normalized['favicon_ios_padding'],
        ),
      );

      $this->writeBytes(
        $package_directory . '/web-app-manifest-192x192.png',
        $this->renderPng(
          'image/svg+xml',
          $source_svg,
          192,
          $normalized['favicon_android_background_color'],
          $normalized['favicon_android_padding'],
        ),
      );

      $this->writeBytes(
        $package_directory . '/web-app-manifest-512x512.png',
        $this->renderPng(
          'image/svg+xml',
          $source_svg,
          512,
          $normalized['favicon_android_background_color'],
          $normalized['favicon_android_padding'],
        ),
      );

      $maskable_padding = max($normalized['favicon_android_padding'], 20);
      $this->writeBytes(
        $package_directory . '/web-app-manifest-512x512-maskable.png',
        $this->renderPng(
          'image/svg+xml',
          $source_svg,
          512,
          $normalized['favicon_android_background_color'],
          $maskable_padding,
        ),
      );

      $manifest = $this->buildManifest($package_directory, $normalized);
      $this->writeBytes(
        $package_directory . '/site.webmanifest',
        $this->encodeJson($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      );

      $metadata = [
        'theme' => $theme_name,
        'hash' => $package_hash,
        'generated_at' => $generated_at,
        'source' => $this->buildSourceMetadata($source, (string) $analysis['source_hash']),
        'settings' => $normalized,
        'warnings' => $analysis['warnings'],
        'files' => [
          'favicon.svg',
          'favicon.ico',
          'favicon-96x96.png',
          'apple-touch-icon.png',
          'web-app-manifest-192x192.png',
          'web-app-manifest-512x512.png',
          'web-app-manifest-512x512-maskable.png',
          'site.webmanifest',
        ],
      ];
      $this->writeBytes(
        $package_directory . '/metadata.json',
        $this->encodeJson($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      );

      $this->cacheTagsInvalidator->invalidateTags(['rendered']);

      return [
        'hash' => $package_hash,
        'path' => $package_directory,
        'generated_at' => $generated_at,
      ];
    }
    finally {
      $this->lock->release($lock_id);
    }
  }

  /**
   * Validates a loaded source asset.
   *
   * @return array<string, mixed>
   *   The normalized source analysis.
   */
  private function validateSourceAsset(
    string $source_data,
    string $mime_type,
    string $extension,
    ?int $size,
    bool $requires_rasterization,
  ): array {
    if ($extension !== 'svg') {
      throw new \InvalidArgumentException('Upload an SVG icon file.');
    }

    if ($size !== NULL && $size > self::MAX_FILE_SIZE) {
      throw new \InvalidArgumentException('Upload an icon file smaller than 5 MB.');
    }

    if ($mime_type !== 'image/svg+xml') {
      throw new \InvalidArgumentException('The uploaded icon file must be an SVG.');
    }

    $analysis = $this->inspectSvgMarkup($source_data);
    if ($requires_rasterization) {
      if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
        throw new \InvalidArgumentException('The GD PHP extension is required to generate favicon PNG assets.');
      }
      if (!class_exists('Imagick')) {
        throw new \InvalidArgumentException('SVG source files require the Imagick PHP extension for PNG generation.');
      }
    }

    return $analysis;
  }

  /**
   * Builds a minimal web app manifest.
   */
  private function buildManifest(string $package_directory, array $settings): array {
    $manifest = [
      'name' => $settings['favicon_manifest_name'],
      'short_name' => $settings['favicon_manifest_short_name'],
      'icons' => [
        [
          'src' => $this->fileUrlGenerator->generateString($package_directory . '/web-app-manifest-192x192.png'),
          'sizes' => '192x192',
          'type' => 'image/png',
          'purpose' => 'any',
        ],
        [
          'src' => $this->fileUrlGenerator->generateString($package_directory . '/web-app-manifest-512x512.png'),
          'sizes' => '512x512',
          'type' => 'image/png',
          'purpose' => 'any',
        ],
      ],
      'theme_color' => $settings['favicon_android_background_color'],
      'background_color' => $settings['favicon_android_background_color'],
      'display' => 'standalone',
    ];

    $manifest['icons'][] = [
      'src' => $this->fileUrlGenerator->generateString($package_directory . '/web-app-manifest-512x512-maskable.png'),
      'sizes' => '512x512',
      'type' => 'image/png',
      'purpose' => 'maskable',
    ];

    return $manifest;
  }

  /**
   * Loads the source file data and normalizes its MIME type.
   *
   * @return array{0: string, 1: string, 2: string}
   *   Source file contents, MIME type, and extension.
   */
  private function loadSourceAsset(File $source_file): array {
    $uri = $source_file->getFileUri();
    $contents = @file_get_contents($uri);
    if ($contents === FALSE || $contents === '') {
      throw new \InvalidArgumentException('The uploaded icon file could not be read.');
    }

    $extension = strtolower(pathinfo($source_file->getFilename(), PATHINFO_EXTENSION));
    $mime_type = strtolower((string) $source_file->getMimeType());

    if ($mime_type === 'image/jpg') {
      $mime_type = 'image/jpeg';
    }
    if ($mime_type === '' || $mime_type === 'application/octet-stream') {
      $mime_type = match ($extension) {
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        default => 'application/octet-stream',
      };
    }

    return [$contents, $mime_type, $extension];
  }

  /**
   * Writes binary or text data to the generated package directory.
   */
  private function writeBytes(string $uri, string $contents): void {
    $written = @file_put_contents($uri, $contents);
    if ($written === FALSE) {
      throw new \RuntimeException(sprintf('Unable to write generated favicon file %s.', $uri));
    }
  }

  /**
   * Encodes structured data as JSON with explicit failure handling.
   */
  private function encodeJson(mixed $data, int $options = 0): string {
    $encoded = json_encode($data, $options | JSON_THROW_ON_ERROR);
    return is_string($encoded) ? $encoded : '';
  }

  /**
   * Builds the deterministic package hash from source and settings.
   */
  private function buildPackageHash(array $settings, string $source_hash): string {
    $hash_settings = $settings;
    // File IDs, stored source copies, and human-facing labels do not change the
    // rendered package output, so exclude them from the deterministic hash.
    unset(
      $hash_settings['favicon_source_fid'],
      $hash_settings['favicon_source_svg'],
      $hash_settings['favicon_source_filename'],
      $hash_settings['favicon_ios_icon_name'],
      $hash_settings['favicon_package_hash'],
      $hash_settings['favicon_package_path'],
      $hash_settings['favicon_package_generated_at'],
    );

    return substr(hash('sha256', $this->encodeJson([
      'source_hash' => $source_hash,
      'settings' => $hash_settings,
    ])), 0, 12);
  }

  /**
   * Builds the package directory URI for a hash.
   */
  private function buildPackageDirectory(string $theme_name, string $package_hash): string {
    return sprintf('public://favicon-package/%s/%s', $theme_name, $package_hash);
  }

  /**
   * Builds the shared lock ID used for deterministic package writes.
   */
  private function buildGenerationLockId(string $theme_name, string $package_hash): string {
    return sprintf(
      'emulsify:favicon_package:%s:%s',
      $theme_name,
      $package_hash,
    );
  }

  /**
   * Returns existing package metadata when already generated.
   */
  private function buildExistingPackageResult(
    string $package_hash,
    string $package_directory,
  ): array {
    $metadata = $this->readPackageMetadata($package_directory);

    return [
      'hash' => $package_hash,
      'path' => $package_directory,
      'generated_at' => (int) ($metadata['generated_at'] ?? $this->time->getRequestTime()),
    ];
  }

  /**
   * Removes an existing or partial package directory before regeneration.
   */
  private function resetPackageDirectory(string $package_directory): void {
    $realpath = $this->fileSystem->realpath($package_directory);
    if (!is_string($realpath) || !is_dir($realpath)) {
      return;
    }

    $this->fileSystem->deleteRecursive($realpath);
  }

  /**
   * Normalizes source metadata written to metadata.json.
   */
  private function buildSourceMetadata(array $source, string $source_hash): array {
    return [
      'file_id' => (int) ($source['file_id'] ?? 0),
      'filename' => (string) ($source['filename'] ?? 'favicon.svg'),
      'mime_type' => 'image/svg+xml',
      'extension' => 'svg',
      'sha256' => $source_hash,
    ];
  }

  /**
   * Generates an SVG favicon wrapper around the uploaded source.
   */
  private function buildSvgFavicon(string $mime_type, string $source_data, string $background_color, int $padding, bool $sanitize_svg): string {
    if ($sanitize_svg) {
      $source_data = $this->sanitizeSvg($source_data);
    }

    $encoded_source = base64_encode($source_data);
    $inset = (int) round(1024 * ($padding / 100));
    $content_size = max(1, 1024 - ($inset * 2));

    return sprintf(
      <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" role="img" aria-hidden="true">
  <rect width="1024" height="1024" rx="192" fill="%s"/>
  <image href="data:%s;base64,%s" x="%d" y="%d" width="%d" height="%d" preserveAspectRatio="xMidYMid meet"/>
</svg>
SVG,
      $background_color,
      $mime_type,
      $encoded_source,
      $inset,
      $inset,
      $content_size,
      $content_size,
    );
  }

  /**
   * Sanitizes SVG uploads while allowing embedded image data URIs.
   */
  private function sanitizeSvg(string $source_data): string {
    return (string) $this->inspectSvgMarkup($source_data)['sanitized_svg'];
  }

  /**
   * Inspects and sanitizes SVG markup.
   *
   * @return array<string, mixed>
   *   Source analysis including sanitized markup and non-fatal warnings.
   */
  private function inspectSvgMarkup(string $source_data): array {
    $document = $this->loadSvgDocument($source_data);
    $root = $document->documentElement;
    if (!$root || strtolower($root->localName ?? $root->nodeName) !== 'svg') {
      throw new \InvalidArgumentException('The uploaded icon file must be an SVG.');
    }

    $view_box = $this->parseViewBox($root->getAttribute('viewBox'));
    if ($view_box === NULL) {
      throw new \InvalidArgumentException('The uploaded SVG must define a viewBox.');
    }

    $declared_dimensions = $this->extractDeclaredDimensions($root);
    $view_box_was_normalized = !$this->isSquare($view_box[2], $view_box[3]);
    $dimensions_were_normalized = $declared_dimensions['width'] !== NULL
      && $declared_dimensions['height'] !== NULL
      && !$this->isSquare($declared_dimensions['width'], $declared_dimensions['height']);

    $has_embedded_raster_images = $this->documentHasRasterImages($document);
    $uses_transparency = $this->documentUsesTransparency($document);

    if ($view_box_was_normalized || $dimensions_were_normalized) {
      $view_box = $this->normalizeSvgCanvas($root, $view_box);
      $declared_dimensions = $this->extractDeclaredDimensions($root);
    }

    $this->stripDisallowedSvgNodes($document);
    $this->stripDisallowedSvgAttributes($document);

    $sanitized = $document->saveXML($document->documentElement) ?: '';
    if ($sanitized === '') {
      throw new \InvalidArgumentException('The uploaded SVG could not be sanitized.');
    }

    $warnings = [];
    if ($view_box_was_normalized || $dimensions_were_normalized) {
      $warnings[] = 'This SVG was centered on a square canvas so generated favicon assets keep the original aspect ratio.';
    }
    if ($uses_transparency) {
      $warnings[] = 'This SVG appears to use transparency. Check the browser, iOS, and Android previews against their configured backgrounds.';
    }
    if ($has_embedded_raster_images) {
      $warnings[] = 'This SVG contains raster <image> content. Generated icons may not scale as cleanly as a fully vector source.';
    }

    return [
      'sanitized_svg' => $sanitized,
      'source_hash' => hash('sha256', $sanitized),
      'view_box' => $view_box,
      'declared_dimensions' => $declared_dimensions,
      'uses_transparency' => $uses_transparency,
      'has_embedded_raster_images' => $has_embedded_raster_images,
      'warnings' => $warnings,
    ];
  }

  /**
   * Centers a non-square SVG root on a square canvas.
   *
   * @param float[] $view_box
   *   Parsed min-x, min-y, width, and height values.
   *
   * @return float[]
   *   The normalized square viewBox.
   */
  private function normalizeSvgCanvas(\DOMElement $root, array $view_box): array {
    $size = max($view_box[2], $view_box[3]);
    $normalized_view_box = [
      $view_box[0] - (($size - $view_box[2]) / 2),
      $view_box[1] - (($size - $view_box[3]) / 2),
      $size,
      $size,
    ];
    $formatted_size = $this->formatSvgNumber($size);

    $root->setAttribute('viewBox', implode(' ', array_map(
      fn (float $value): string => $this->formatSvgNumber($value),
      $normalized_view_box,
    )));
    $root->setAttribute('width', $formatted_size);
    $root->setAttribute('height', $formatted_size);

    return $normalized_view_box;
  }

  /**
   * Formats an SVG number without unnecessary trailing zeroes.
   */
  private function formatSvgNumber(float $value): string {
    $formatted = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    return $formatted === '-0' ? '0' : $formatted;
  }

  /**
   * Loads an SVG document with safe libxml settings.
   */
  private function loadSvgDocument(string $source_data): \DOMDocument {
    $previous = libxml_use_internal_errors(TRUE);
    $document = new \DOMDocument();
    $loaded = $document->loadXML($source_data, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || !$document->documentElement) {
      throw new \InvalidArgumentException('The uploaded SVG could not be parsed.');
    }

    return $document;
  }

  /**
   * Parses a root SVG viewBox into four floats.
   *
   * @return float[]|null
   *   The parsed min-x, min-y, width, and height values.
   */
  private function parseViewBox(string $view_box): ?array {
    $parts = preg_split('/[\s,]+/', trim($view_box)) ?: [];
    if (count($parts) !== 4) {
      return NULL;
    }

    $values = [];
    foreach ($parts as $part) {
      if (!is_numeric($part)) {
        return NULL;
      }
      $values[] = (float) $part;
    }

    return ($values[2] > 0 && $values[3] > 0) ? $values : NULL;
  }

  /**
   * Extracts square dimension hints from root width and height attributes.
   *
   * @return array{width: float|null, height: float|null}
   *   Parsed root dimensions when they can be normalized.
   */
  private function extractDeclaredDimensions(\DOMElement $root): array {
    return [
      'width' => $this->extractNumericLength($root->getAttribute('width')),
      'height' => $this->extractNumericLength($root->getAttribute('height')),
    ];
  }

  /**
   * Parses a numeric SVG length when it uses an absolute unit.
   */
  private function extractNumericLength(string $value): ?float {
    $candidate = trim($value);
    if ($candidate === '') {
      return NULL;
    }

    if (!preg_match('/^(-?(?:\d+|\d*\.\d+))(?:px|pt|pc|mm|cm|in)?$/i', $candidate, $matches)) {
      return NULL;
    }

    return (float) $matches[1];
  }

  /**
   * Determines whether the SVG contains raster image content.
   */
  private function documentHasRasterImages(\DOMDocument $document): bool {
    foreach ($document->getElementsByTagName('image') as $element) {
      foreach (['href', 'xlink:href'] as $attribute_name) {
        $value = trim((string) $element->getAttribute($attribute_name));
        if ($value === '') {
          continue;
        }

        if (preg_match('/^data:image\/(png|gif|jpe?g|webp|bmp|avif);/i', $value)) {
          return TRUE;
        }

        if (!str_starts_with(strtolower($value), 'data:image/svg+xml')) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Determines whether the SVG appears to rely on transparency.
   */
  private function documentUsesTransparency(\DOMDocument $document): bool {
    foreach ($document->getElementsByTagName('*') as $element) {
      $tag_name = strtolower($element->localName ?? $element->nodeName);
      if (in_array($tag_name, ['mask', 'clippath'], TRUE)) {
        return TRUE;
      }

      foreach (['opacity', 'fill-opacity', 'stroke-opacity', 'stop-opacity'] as $attribute_name) {
        if ($element->hasAttribute($attribute_name) && $this->numericOpacityIsTransparent($element->getAttribute($attribute_name))) {
          return TRUE;
        }
      }

      foreach (['fill', 'stroke', 'stop-color', 'flood-color'] as $attribute_name) {
        if ($element->hasAttribute($attribute_name) && $this->colorValueHasTransparency($element->getAttribute($attribute_name))) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Determines whether an opacity attribute implies transparency.
   */
  private function numericOpacityIsTransparent(string $value): bool {
    $candidate = trim(strtolower($value));
    if ($candidate === '') {
      return FALSE;
    }

    if (str_ends_with($candidate, '%')) {
      $numeric = (float) rtrim($candidate, '%');
      return $numeric < 100;
    }

    return is_numeric($candidate) && (float) $candidate < 1;
  }

  /**
   * Determines whether a color token encodes alpha transparency.
   */
  private function colorValueHasTransparency(string $value): bool {
    $candidate = trim(strtolower($value));
    if ($candidate === 'transparent') {
      return TRUE;
    }

    if (preg_match('/^#([0-9a-f]{4}|[0-9a-f]{8})$/i', $candidate, $matches)) {
      $hex = $matches[1];
      if (strlen($hex) === 4) {
        return strtolower($hex[3]) !== 'f';
      }
      return strtolower(substr($hex, 6, 2)) !== 'ff';
    }

    if (preg_match('/^rgba\((.+)\)$/i', $candidate, $matches) || preg_match('/^hsla\((.+)\)$/i', $candidate, $matches)) {
      $parts = preg_split('/\s*,\s*|\s+\/\s*/', trim($matches[1])) ?: [];
      $alpha = end($parts);
      return is_string($alpha) && $this->numericOpacityIsTransparent($alpha);
    }

    return FALSE;
  }

  /**
   * Checks whether two lengths should be treated as square.
   */
  private function isSquare(float $first, float $second): bool {
    return abs($first - $second) < 0.01;
  }

  /**
   * Removes unsafe SVG nodes.
   */
  private function stripDisallowedSvgNodes(\DOMDocument $document): void {
    $removable = [];
    foreach ($document->getElementsByTagName('*') as $element) {
      $tag_name = strtolower($element->localName ?? $element->nodeName);
      if (in_array($tag_name, ['script', 'foreignobject', 'iframe', 'audio', 'video'], TRUE)) {
        $removable[] = $element;
      }
    }

    foreach ($removable as $element) {
      if ($element->parentNode) {
        $element->parentNode->removeChild($element);
      }
    }
  }

  /**
   * Removes unsafe attributes and external references from SVG elements.
   */
  private function stripDisallowedSvgAttributes(\DOMDocument $document): void {
    foreach ($document->getElementsByTagName('*') as $element) {
      $attributes = [];
      if ($element->hasAttributes()) {
        foreach ($element->attributes as $attribute) {
          $attributes[] = $attribute;
        }
      }

      foreach ($attributes as $attribute) {
        $name = strtolower($attribute->nodeName);
        $value = trim((string) $attribute->nodeValue);

        if (str_starts_with($name, 'on')) {
          $element->removeAttributeNode($attribute);
          continue;
        }

        if (in_array($name, ['href', 'xlink:href'], TRUE) && !$this->isSafeSvgHref($value)) {
          $element->removeAttributeNode($attribute);
        }
      }
    }
  }

  /**
   * Checks whether an SVG href value is safe to retain.
   */
  private function isSafeSvgHref(string $href): bool {
    if ($href === '' || str_starts_with($href, '#')) {
      return TRUE;
    }

    if (str_starts_with($href, 'data:')) {
      return (bool) preg_match('/^data:image\/(png|gif|jpe?g|webp|svg\+xml);base64,[A-Za-z0-9+\/]+=*$/', $href);
    }

    return FALSE;
  }

  /**
   * Renders a source asset into a square PNG with padding.
   */
  private function renderPng(string $mime_type, string $source_data, int $size, string $background_color, int $padding): string {
    $source_image = $this->createSourceImage($mime_type, $source_data, $size);
    $source_width = imagesx($source_image);
    $source_height = imagesy($source_image);

    $canvas = imagecreatetruecolor($size, $size);
    if ($canvas === FALSE) {
      throw new \RuntimeException('Unable to allocate the favicon canvas.');
    }

    imagealphablending($canvas, TRUE);
    imagesavealpha($canvas, TRUE);
    [$red, $green, $blue] = $this->hexToRgb($background_color);
    $background = imagecolorallocatealpha($canvas, $red, $green, $blue, 0);
    imagefilledrectangle($canvas, 0, 0, $size, $size, $background);

    // All raster outputs share the same centering and padding rules so the
    // browser, iOS, and Android assets stay visually aligned.
    $inner_size = max(1, $size - (2 * (int) round($size * ($padding / 100))));
    $scale = min($inner_size / $source_width, $inner_size / $source_height);
    $target_width = max(1, (int) round($source_width * $scale));
    $target_height = max(1, (int) round($source_height * $scale));
    $target_x = (int) floor(($size - $target_width) / 2);
    $target_y = (int) floor(($size - $target_height) / 2);

    imagealphablending($source_image, TRUE);
    imagecopyresampled(
      $canvas,
      $source_image,
      $target_x,
      $target_y,
      0,
      0,
      $target_width,
      $target_height,
      $source_width,
      $source_height,
    );

    ob_start();
    imagepng($canvas);
    $png = (string) ob_get_clean();

    unset($source_image, $canvas);

    return $png;
  }

  /**
   * Creates a GD image resource from the uploaded source.
   */
  private function createSourceImage(string $mime_type, string $source_data, int $target_size): \GdImage {
    if ($mime_type === 'image/svg+xml') {
      if (!class_exists('Imagick')) {
        throw new \RuntimeException('The Imagick extension is required to rasterize SVG icons.');
      }

      $image = new \Imagick();
      $image->setBackgroundColor(new \ImagickPixel('transparent'));
      $image->setResolution($target_size * 4, $target_size * 4);
      $image->readImageBlob($source_data);
      $merged = $image->mergeImageLayers(\Imagick::LAYERMETHOD_MERGE);
      $merged->setImageFormat('png32');
      $source_data = (string) $merged->getImageBlob();
      $merged->clear();
      $merged->destroy();
      $image->clear();
      $image->destroy();
    }

    $image = imagecreatefromstring($source_data);
    if ($image === FALSE) {
      throw new \RuntimeException('Unable to decode the uploaded icon file.');
    }

    return $image;
  }

  /**
   * Creates a PNG-backed ICO file.
   */
  private function createIcoFile(string $png_data, int $size): string {
    $width = $size >= 256 ? 0 : $size;
    $height = $size >= 256 ? 0 : $size;
    $directory = pack('vvv', 0, 1, 1);
    $entry = pack('CCCCvvVV', $width, $height, 0, 0, 1, 32, strlen($png_data), 22);

    return $directory . $entry . $png_data;
  }

  /**
   * Converts a hex color to an RGB triplet.
   *
   * @return int[]
   *   Three integers representing red, green, and blue.
   */
  private function hexToRgb(string $color): array {
    $hex = ltrim($color, '#');
    return [
      (int) hexdec(substr($hex, 0, 2)),
      (int) hexdec(substr($hex, 2, 2)),
      (int) hexdec(substr($hex, 4, 2)),
    ];
  }

}
