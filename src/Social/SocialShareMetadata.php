<?php

declare(strict_types=1);

namespace Drupal\emulsify\Social;

/**
 * Defines the fallback metadata shared by page output and settings confirmation.
 */
final class SocialShareMetadata {

  /**
   * Returns tag names and their configured fallback values.
   */
  public static function values(array $settings, string $image_url, array $image_info, string $site_name, string $page_url = ''): array {
    $alt = trim((string) ($settings['social_share_image_alt'] ?? '')) ?: $site_name;
    $title = trim((string) ($settings['social_share_image_title'] ?? ''));
    $description = trim((string) ($settings['social_share_image_description'] ?? ''));
    $configured_url = trim((string) ($settings['social_share_image_url'] ?? ''));
    $url = self::isValidUrl($configured_url) ? $configured_url : $page_url;
    return [
      'og:image' => $image_url,
      'og:image:type' => $image_url === '' ? '' : (string) ($image_info['mime'] ?? ''),
      'og:image:width' => $image_url === '' ? '' : (string) ($image_info['width'] ?? ''),
      'og:image:height' => $image_url === '' ? '' : (string) ($image_info['height'] ?? ''),
      'og:image:alt' => $image_url === '' ? '' : $alt,
      'twitter:card' => $image_url === '' ? '' : 'summary_large_image',
      'twitter:image' => $image_url,
      'twitter:image:alt' => $image_url === '' ? '' : $alt,
      'og:title' => $title,
      'twitter:title' => $title,
      'og:description' => $description,
      'twitter:description' => $description,
      'og:url' => self::isValidUrl($url) ? $url : '',
    ];
  }

  /**
   * Accepts absolute HTTP URLs without embedded credentials.
   */
  public static function isValidUrl(string $url): bool {
    $parts = parse_url($url);
    return filter_var($url, FILTER_VALIDATE_URL) !== FALSE
      && is_array($parts)
      && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], TRUE)
      && !empty($parts['host'])
      && !isset($parts['user'])
      && !isset($parts['pass']);
  }

}
