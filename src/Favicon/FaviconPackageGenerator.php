<?php

namespace Drupal\emulsify\Favicon;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
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
   * Creates a generator instance.
   */
  public function __construct(
    FileSystemInterface $file_system,
    FileUrlGeneratorInterface $file_url_generator,
    ConfigFactoryInterface $config_factory,
    CacheTagsInvalidatorInterface $cache_tags_invalidator,
    TimeInterface $time,
  ) {
    $this->fileSystem = $file_system;
    $this->fileUrlGenerator = $file_url_generator;
    $this->configFactory = $config_factory;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->time = $time;
  }

  /**
   * Validates a source file before generation.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the icon file is unusable.
   */
  public function validateSourceFile(File $source_file, bool $requires_rasterization = TRUE): void {
    if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
      throw new \InvalidArgumentException('The GD PHP extension is required to generate favicon PNG assets.');
    }

    [$source_data, $mime_type, $extension] = $this->loadSourceAsset($source_file);

    if ($extension !== 'svg') {
      throw new \InvalidArgumentException('Upload an SVG icon file.');
    }

    if ($source_file->getSize() > self::MAX_FILE_SIZE) {
      throw new \InvalidArgumentException('Upload an icon file smaller than 5 MB.');
    }

    if ($mime_type !== 'image/svg+xml') {
      throw new \InvalidArgumentException('The uploaded icon file must be an SVG.');
    }

    $this->sanitizeSvg($source_data);
    if ($requires_rasterization && !class_exists('Imagick')) {
      throw new \InvalidArgumentException('SVG source files require the Imagick PHP extension for PNG generation.');
    }
  }

  /**
   * Generates a full favicon package and returns its metadata.
   *
   * @return array{hash: string, path: string, generated_at: int}
   *   Generated package metadata.
   */
  public function generate(string $theme_name, File $source_file, array $settings): array {
    $normalized = FaviconSettings::normalize($settings, (string) $this->configFactory->get('system.site')->get('name'));
    $this->validateSourceFile($source_file, TRUE);

    [$source_data, $mime_type, $extension] = $this->loadSourceAsset($source_file);
    $source_hash = hash('sha256', $source_data);
    $hash_settings = $normalized;
    unset(
      $hash_settings['favicon_source_fid'],
      $hash_settings['favicon_ios_icon_name'],
      $hash_settings['favicon_package_hash'],
      $hash_settings['favicon_package_path'],
      $hash_settings['favicon_package_generated_at'],
    );
    $package_hash = substr(hash('sha256', $this->encodeJson([
      'source_hash' => $source_hash,
      'settings' => $hash_settings,
    ])), 0, 12);
    $package_directory = sprintf('public://favicon-package/%s/%s', $theme_name, $package_hash);
    $generated_at = $this->time->getRequestTime();

    $this->fileSystem->prepareDirectory($package_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $browser_padding = min($normalized['favicon_ios_padding'], $normalized['favicon_android_padding']);
    $svg_markup = $this->buildSvgFavicon(
      $mime_type,
      $source_data,
      $normalized['favicon_background_color'],
      $browser_padding,
      $mime_type === 'image/svg+xml',
    );
    $this->writeBytes($package_directory . '/favicon.svg', $svg_markup);

    $browser_png_32 = $this->renderPng(
      $mime_type,
      $source_data,
      32,
      $normalized['favicon_background_color'],
      $browser_padding,
    );
    $browser_png_96 = $this->renderPng(
      $mime_type,
      $source_data,
      96,
      $normalized['favicon_background_color'],
      $browser_padding,
    );
    $this->writeBytes($package_directory . '/favicon.ico', $this->createIcoFile($browser_png_32, 32));
    $this->writeBytes($package_directory . '/favicon-96x96.png', $browser_png_96);

    $this->writeBytes(
      $package_directory . '/apple-touch-icon.png',
      $this->renderPng(
        $mime_type,
        $source_data,
        180,
        $normalized['favicon_ios_background_color'],
        $normalized['favicon_ios_padding'],
      ),
    );

    $this->writeBytes(
      $package_directory . '/web-app-manifest-192x192.png',
      $this->renderPng(
        $mime_type,
        $source_data,
        192,
        $normalized['favicon_android_background_color'],
        $normalized['favicon_android_padding'],
      ),
    );

    $this->writeBytes(
      $package_directory . '/web-app-manifest-512x512.png',
      $this->renderPng(
        $mime_type,
        $source_data,
        512,
        $normalized['favicon_android_background_color'],
        $normalized['favicon_android_padding'],
      ),
    );

    $maskable_padding = max($normalized['favicon_android_padding'], 20);
    $this->writeBytes(
      $package_directory . '/web-app-manifest-512x512-maskable.png',
      $this->renderPng(
        $mime_type,
        $source_data,
        512,
        $normalized['favicon_android_background_color'],
        $maskable_padding,
      ),
    );

    $manifest = $this->buildManifest($package_directory, $normalized);
    $this->writeBytes($package_directory . '/site.webmanifest', $this->encodeJson($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $metadata = [
      'theme' => $theme_name,
      'hash' => $package_hash,
      'generated_at' => $generated_at,
      'source' => [
        'file_id' => (int) $source_file->id(),
        'filename' => $source_file->getFilename(),
        'mime_type' => $mime_type,
        'extension' => $extension,
        'sha256' => $source_hash,
      ],
      'settings' => $normalized,
      'files' => [
        'favicon.svg',
        'favicon.ico',
        'favicon-96x96.png',
        'apple-touch-icon.png',
        'web-app-manifest-192x192.png',
        'web-app-manifest-512x512.png',
        'site.webmanifest',
      ],
    ];
    $metadata['files'][] = 'web-app-manifest-512x512-maskable.png';
    $this->writeBytes($package_directory . '/metadata.json', $this->encodeJson($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->cacheTagsInvalidator->invalidateTags(['rendered']);

    return [
      'hash' => $package_hash,
      'path' => $package_directory,
      'generated_at' => $generated_at,
    ];
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
    $previous = libxml_use_internal_errors(TRUE);
    $document = new \DOMDocument();
    $loaded = $document->loadXML($source_data, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || !$document->documentElement) {
      throw new \InvalidArgumentException('The uploaded SVG could not be parsed.');
    }

    $this->stripDisallowedSvgNodes($document);
    $this->stripDisallowedSvgAttributes($document);

    $sanitized = $document->saveXML($document->documentElement) ?: '';
    if ($sanitized === '') {
      throw new \InvalidArgumentException('The uploaded SVG could not be sanitized.');
    }

    return $sanitized;
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

    imagedestroy($source_image);
    imagedestroy($canvas);

    return $png;
  }

  /**
   * Creates a GD image resource from the uploaded source.
   */
  private function createSourceImage(string $mime_type, string $source_data, int $target_size) {
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
