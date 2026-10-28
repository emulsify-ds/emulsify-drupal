<?php

declare(strict_types=1);

namespace Drupal\emulsify\Social;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;

/**
 * Prepares bounded social images while retaining their original source files.
 */
final class SocialShareImageProcessor {

  public const MAX_WIDTH = 1200;
  public const MAX_HEIGHT = 1500;

  /**
   * Creates the native image toolkit processor.
   */
  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly ImageFactory $imageFactory,
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * Returns display metadata, creating an oversized derivative only on request.
   */
  public function display(array $source, bool $create = FALSE): array {
    if ($source === [] || ($source['info']['width'] <= self::MAX_WIDTH && $source['info']['height'] <= self::MAX_HEIGHT)) {
      return $source;
    }

    $path = $source['realpath'];
    $fingerprint = hash('sha256', $path . ':' . filemtime($path) . ':' . filesize($path));
    $cid = 'emulsify:social-share-source:' . $fingerprint;
    $cached = $this->cache->get($cid);
    $source_hash = $cached ? $cached->data : hash_file('sha256', $path);
    if (!is_string($source_hash)) {
      throw new \InvalidArgumentException('The social share image could not be read for resizing.');
    }
    if (!$cached) {
      $this->cache->set($cid, $source_hash, CacheBackendInterface::CACHE_PERMANENT, $source['file'] ? $source['file']->getCacheTags() : []);
    }
    $hash = hash('sha256', $source_hash . ':scale-v1:' . self::MAX_WIDTH . ':' . self::MAX_HEIGHT . ':' . $this->imageFactory->getToolkitId());
    $extension = match ($source['info']['mime']) {
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp',
      'image/gif' => 'gif',
    };
    $directory = 'public://social-share-image/processed';
    $destination = $directory . '/' . $hash . '.' . $extension;
    if (!is_file($destination)) {
      if (!$create) {
        return [];
      }
      if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        throw new \InvalidArgumentException('The social share image resize directory is not writable.');
      }
      $temporary = $this->fileSystem->tempnam($directory, 'social_');
      if (!is_string($temporary)) {
        throw new \InvalidArgumentException('A temporary social share image could not be created.');
      }
      try {
        $image = $this->imageFactory->get($path);
        if (!$image->isValid() || !$image->scale(self::MAX_WIDTH, self::MAX_HEIGHT, FALSE) || !$image->save($temporary)) {
          throw new \InvalidArgumentException('The configured image toolkit could not resize the social share image.');
        }
        $this->fileSystem->move($temporary, $destination, FileExists::Replace);
        Cache::invalidateTags(['rendered']);
      }
      catch (FileException $exception) {
        throw new \InvalidArgumentException('The resized social share image could not be saved.', 0, $exception);
      }
      finally {
        if (is_file($temporary)) {
          $this->fileSystem->delete($temporary);
        }
      }
    }
    $info = @getimagesize($destination);
    if (!is_array($info) || empty($info[0]) || empty($info[1]) || $info[0] > self::MAX_WIDTH || $info[1] > self::MAX_HEIGHT || ($info['mime'] ?? '') !== $source['info']['mime']) {
      throw new \InvalidArgumentException('The resized social share image is invalid. Save the image settings again after checking the image toolkit.');
    }
    return array_replace($source, [
      'uri' => $destination,
      'info' => ['width' => (int) $info[0], 'height' => (int) $info[1], 'mime' => $info['mime']],
    ]);
  }

}
