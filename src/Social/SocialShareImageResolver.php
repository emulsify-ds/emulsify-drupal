<?php

declare(strict_types=1);

namespace Drupal\emulsify\Social;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;

/**
 * Resolves local, publicly addressable raster images without generating files.
 */
final class SocialShareImageResolver {

  /**
   * Creates the image resolver.
   */
  public function __construct(private readonly FileSystemInterface $fileSystem) {}

  /**
   * Returns the selected image's URI, actual image metadata, and managed file.
   *
   * @throws \InvalidArgumentException
   *   When the configured image cannot safely be shared publicly.
   */
  public function resolve(array $settings): array {
    $file = NULL;
    if (!empty($settings['social_share_image_fid'])) {
      $file = File::load((int) reset($settings['social_share_image_fid']));
      if (!$file) {
        throw new \InvalidArgumentException('The uploaded social share image is missing. Upload an image or enter a valid image path.');
      }
    }
    $path = $file ? $file->getFileUri() : (string) ($settings['social_share_image_path'] ?? '');
    if ($path === '') {
      return [];
    }
    if (str_contains($path, "\0") || str_contains($path, '\\') || preg_match('@(?:^|/)\.{1,2}(?:/|$)@', $path)) {
      throw new \InvalidArgumentException('Use a public image URI or a path inside the Drupal web root without directory traversal.');
    }
    if (str_starts_with($path, 'public://')) {
      $uri = $path;
      $base = $this->fileSystem->realpath('public://');
      $realpath = $this->fileSystem->realpath($uri);
    }
    elseif (!str_contains($path, ':') && !str_starts_with($path, '//')) {
      $uri = ltrim($path, '/');
      $base = realpath(DRUPAL_ROOT);
      $realpath = realpath(DRUPAL_ROOT . '/' . $uri);
      // Like core's logo path, an unqualified filename may refer to public files.
      if (!$realpath && !str_starts_with($path, '/')) {
        $uri = 'public://' . $path;
        $base = $this->fileSystem->realpath('public://');
        $realpath = $this->fileSystem->realpath($uri);
      }
    }
    else {
      throw new \InvalidArgumentException('Use a public:// image URI or a path inside the Drupal web root. Remote URLs and private files are not supported.');
    }
    if (!is_string($base) || !is_string($realpath) || !str_starts_with($realpath, rtrim($base, '/') . '/') || !is_file($realpath) || !is_readable($realpath)) {
      throw new \InvalidArgumentException('The social share image path does not point to a readable public image.');
    }
    $info = @getimagesize($realpath);
    if (!in_array(strtolower(pathinfo($realpath, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], TRUE)
      || !is_array($info) || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], TRUE)
      || empty($info[0]) || empty($info[1])) {
      throw new \InvalidArgumentException('Use a valid JPG, PNG, WebP, or GIF image. SVG files are not supported for social sharing.');
    }
    return [
      'uri' => $uri,
      'realpath' => $realpath,
      'info' => ['width' => (int) $info[0], 'height' => (int) $info[1], 'mime' => $info['mime']],
      'file' => $file,
    ];
  }

}
