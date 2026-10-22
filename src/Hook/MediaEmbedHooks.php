<?php

declare(strict_types=1);

namespace Drupal\emulsify\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\emulsify\Media\MediaEmbedSettings;
use Drupal\media\IFrameMarkup;
use Drupal\media\OEmbed\Resource;

/**
 * Applies optional privacy and poster behavior within core's oEmbed iframe.
 */
final class MediaEmbedHooks {

  /**
   * Creates the media embed hook handler.
   */
  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
    private readonly ThemeSettingsProvider $themeSettingsProvider,
  ) {}

  /**
   * Supplies player permissions on core's containing oEmbed iframe.
   */
  public function preprocessField(array &$variables): void {
    if (($variables['element']['#formatter'] ?? '') !== 'oembed') {
      return;
    }
    $settings = $this->loadSettings($variables);
    if ((!$settings['media_embed_privacy_mode'] && !$settings['media_embed_click_to_load']) || !is_array($variables['items'] ?? NULL)) {
      return;
    }

    $permissions = ['autoplay', 'fullscreen', 'picture-in-picture', 'encrypted-media'];
    // The poster navigates this frame, so 'src' alone excludes its new origin.
    $origins = "'src' https://www.youtube.com https://youtube.com https://www.youtube-nocookie.com https://player.vimeo.com";
    $required = array_map(static fn(string $permission): string => $permission . ' ' . $origins, $permissions);
    foreach ($variables['items'] as &$item) {
      if (($item['content']['#tag'] ?? '') !== 'iframe') {
        continue;
      }
      $attributes = $item['content']['#attributes'] ?? [];
      if (!is_array($attributes)) {
        continue;
      }
      $existing = array_map('trim', explode(';', (string) ($attributes['allow'] ?? '')));
      $existing = array_filter($existing, static fn(string $permission): bool => $permission !== '' && !in_array(preg_split('/\s+/', $permission)[0], $permissions, TRUE));
      $attributes['allow'] = implode('; ', array_merge($existing, $required));
      $attributes['allowfullscreen'] = TRUE;
      $item['content']['#attributes'] = $attributes;
    }
    unset($item);
  }

  /**
   * Alters only recognized YouTube and Vimeo iframe documents.
   */
  #[Hook('preprocess_media_oembed_iframe')]
  public function preprocessMediaOembedIframe(array &$variables): void {
    $variables['emulsify_oembed_poster'] = NULL;
    $settings = $this->loadSettings($variables);
    if (!$settings['media_embed_privacy_mode'] && !$settings['media_embed_click_to_load']) {
      return;
    }
    $resource = $variables['resource'] ?? NULL;
    if (!$resource instanceof Resource || !(($variables['media'] ?? NULL) instanceof IFrameMarkup)) {
      return;
    }
    $provider = strtolower((string) $resource->getProvider()?->getName());
    if (!in_array($provider, ['youtube', 'vimeo'], TRUE)) {
      return;
    }
    $document = $this->loadPlayerDocument((string) $variables['media']);
    if ($document === NULL) {
      return;
    }
    $iframe = $document->getElementsByTagName('iframe')->item(0);
    $source = $iframe->getAttribute('src');
    $parts = parse_url($source);
    $hosts = $provider === 'youtube'
      ? ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com']
      : ['player.vimeo.com'];
    $path_prefix = $provider === 'youtube' ? '/embed/' : '/video/';
    if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
      || !in_array(strtolower($parts['host'] ?? ''), $hosts, TRUE)
      || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)
      || !str_starts_with($parts['path'] ?? '', $path_prefix) || strlen($parts['path']) <= strlen($path_prefix)
      || preg_match('/[\x00-\x20\x7f]/', $source)) {
      return;
    }

    $player_url = $source;
    if ($settings['media_embed_privacy_mode']) {
      $player_url = $provider === 'youtube'
        ? preg_replace('#^https://[^/?\#]+#i', 'https://www.youtube-nocookie.com', $player_url)
        : $this->setPlayerParameter($player_url, 'dnt');
      if ($player_url !== $source) {
        $iframe->setAttribute('src', $player_url);
        // Retain core's trust boundary: this markup stays in its iframe page.
        $variables['media'] = IFrameMarkup::create(Html::serialize($document));
      }
    }
    if (!$settings['media_embed_click_to_load']) {
      return;
    }

    $width = (int) $resource->getWidth();
    $height = (int) $resource->getHeight();
    $thumbnail_url = '';
    if ($settings['media_embed_poster'] === 'provider') {
      try {
        $candidate = $resource->getThumbnailUrl()?->toString() ?? '';
        if (strtolower((string) parse_url($candidate, PHP_URL_SCHEME)) === 'https' && parse_url($candidate, PHP_URL_HOST)) {
          $thumbnail_url = $candidate;
        }
      }
      catch (\InvalidArgumentException) {
        // An unusable thumbnail still leaves a working neutral play link.
      }
    }
    $variables['emulsify_oembed_poster'] = [
      'player_url' => $this->setPlayerParameter($player_url, 'autoplay'),
      'title' => (string) ($resource->getTitle() ?: $iframe->getAttribute('title')),
      'provider' => $provider === 'youtube' ? 'YouTube' : 'Vimeo',
      'thumbnail_url' => $thumbnail_url,
      'thumbnail_width' => max(0, (int) $resource->getThumbnailWidth()),
      'thumbnail_height' => max(0, (int) $resource->getThumbnailHeight()),
      'aspect_ratio' => $width > 0 && $height > 0 ? $width . ' / ' . $height : '16 / 9',
      'language_code' => \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)->getId(),
    ];
    CacheableMetadata::createFromRenderArray($variables)
      ->addCacheContexts(['languages:language_interface'])
      ->applyTo($variables);
    $variables['#attached']['library'][] = 'emulsify/oembed_poster';
  }

  /**
   * Loads active-theme settings and preserves their cache dependencies.
   */
  private function loadSettings(array &$variables): array {
    $theme = $this->themeManager->getActiveTheme();
    $theme_names = array_merge([$theme->getName()], array_keys($theme->getBaseThemeExtensions()));
    CacheableMetadata::createFromRenderArray($variables)
      ->addCacheTags(array_map(static fn(string $name): string => 'config:' . $name . '.settings', $theme_names))
      ->applyTo($variables);
    return MediaEmbedSettings::loadThemeSettings($theme->getName(), $this->themeSettingsProvider);
  }

  /**
   * Rejects repaired or compound HTML before editing a single iframe.
   */
  private function loadPlayerDocument(string $markup): ?\DOMDocument {
    if (str_contains($markup, "\0") || !preg_match('/^\s*<iframe\b.*<\/iframe\s*>\s*$/isD', $markup)) {
      return NULL;
    }
    $previous = libxml_use_internal_errors(TRUE);
    libxml_clear_errors();
    $validator = new \DOMDocument();
    // The HTML4 validator must not reject raw query-string ampersands, which
    // the actual HTML5 parser below preserves correctly in attribute values.
    $loaded = $validator->loadHTML(str_replace('&', '&amp;', $markup), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded || $errors !== []) {
      return NULL;
    }
    $document = Html::load($markup);
    $iframes = $document->getElementsByTagName('iframe');
    if ($iframes->length !== 1) {
      return NULL;
    }
    foreach ($document->getElementsByTagName('body')->item(0)->childNodes as $node) {
      if ($node !== $iframes->item(0) && !($node instanceof \DOMText && trim($node->textContent) === '')) {
        return NULL;
      }
    }
    return $document;
  }

  /**
   * Replaces only one named parameter, preserving all other raw query pairs.
   */
  private function setPlayerParameter(string $url, string $name): string {
    $fragment_parts = explode('#', $url, 2);
    $query_parts = explode('?', $fragment_parts[0], 2);
    $pairs = isset($query_parts[1]) && $query_parts[1] !== '' ? explode('&', $query_parts[1]) : [];
    $replaced = FALSE;
    foreach ($pairs as &$pair) {
      if (rawurldecode(explode('=', $pair, 2)[0]) === $name) {
        $pair = $name . '=1';
        $replaced = TRUE;
      }
    }
    unset($pair);
    if (!$replaced) {
      $pairs[] = $name . '=1';
    }
    return $query_parts[0] . '?' . implode('&', $pairs) . (isset($fragment_parts[1]) ? '#' . $fragment_parts[1] : '');
  }

}
