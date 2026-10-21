<?php

/**
 * @file
 * Pins legacy oEmbed iframe output using fixed, local provider resources.
 *
 * Run through Drush in a disposable fixture with the core media module enabled.
 * The real core formatter receives in-memory fetcher/resolver doubles, so this
 * smoke never contacts YouTube, Vimeo, or the oEmbed provider registry.
 */

declare(strict_types=1);

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemList;
use Drupal\emulsify\Hook\FieldHooks;
use Drupal\media\IFrameMarkup;
use Drupal\media\IFrameUrlHelper;
use Drupal\media\OEmbed\Provider;
use Drupal\media\OEmbed\Resource;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\OEmbed\UrlResolverInterface;
use Drupal\media\Plugin\Field\FieldFormatter\OEmbedFormatter;

$fixtures = [
  'youtube' => [
    'name' => 'YouTube',
    'provider' => 'https://www.youtube.com/',
    'endpoint' => 'https://www.youtube.com/oembed',
    'url' => 'https://www.youtube.com/watch?v=abcdefghijk',
    'html' => '<iframe width="640" height="360" src="https://www.youtube.com/embed/abcdefghijk?feature=oembed" title="YouTube fixture" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
  ],
  'vimeo' => [
    'name' => 'Vimeo',
    'provider' => 'https://vimeo.com/',
    'endpoint' => 'https://vimeo.com/api/oembed.json',
    'url' => 'https://vimeo.com/123456789',
    'html' => '<iframe src="https://player.vimeo.com/video/123456789?app_id=122963" width="640" height="360" frameborder="0" title="Vimeo fixture" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>',
  ],
];

$theme_manager = \Drupal::service('theme.manager');
$original_theme = $theme_manager->getActiveTheme();
$theme_manager->setActiveTheme(\Drupal::service('theme.initialization')->initTheme('emulsify'));
$context = \Drupal::service('router.request_context');
$original_context = [$context->getHost(), $context->getScheme(), $context->getBaseUrl()];
$context->setHost('fixture.example.test')->setScheme('https')->setBaseUrl('');
$renderer = \Drupal::service('renderer');
$captured = [];

try {
  foreach ($fixtures as $name => $fixture) {
    $provider = new Provider($fixture['name'], $fixture['provider'], [['url' => $fixture['endpoint'], 'formats' => ['json']]]);
    $resource = Resource::video($fixture['html'], 640, 360, $provider, $fixture['name'] . ' fixture & "title"', 'Fixture author', 'https://fixture.example.test/author', 3600, 'https://fixture.example.test/' . $name . '-poster.jpg', 640, 360);
    $fetcher = new class($resource) implements ResourceFetcherInterface {
      public function __construct(private readonly Resource $resource) {}

      public function fetchResource($url) {
        return $this->resource;
      }
    };
    $resolver = new class($provider, $fixture['endpoint']) implements UrlResolverInterface {
      public function __construct(private readonly Provider $provider, private readonly string $endpoint) {}

      public function getProviderByUrl($url) {
        return $this->provider;
      }

      public function getResourceUrl($url, $max_width = NULL, $max_height = NULL) {
        return $this->endpoint;
      }
    };
    $iframe_url_helper = new class() extends IFrameUrlHelper {
      public function __construct() {}

      public function getHash($url, $max_width = NULL, $max_height = NULL) {
        return 'fixed-oembed-fixture-hash';
      }
    };
    $definition = BaseFieldDefinition::create('string')->setName('field_media_oembed_video');
    $items = FieldItemList::createInstance($definition, 'field_media_oembed_video');
    $items->setValue([['value' => $fixture['url']]]);
    $formatter = new OEmbedFormatter('oembed', [], $definition, ['max_width' => 640, 'max_height' => 360, 'loading' => ['attribute' => 'lazy']], 'hidden', 'default', [], \Drupal::messenger(), $fetcher, $resolver, \Drupal::service('logger.factory'), \Drupal::configFactory(), $iframe_url_helper);
    $elements = $formatter->viewElements($items, 'en');
    $variables = [
      'field_type' => 'string',
      'element' => ['#formatter' => 'oembed', '#field_name' => 'field_media_oembed_video', '#entity_type' => 'media', '#items' => $items, 0 => $elements[0]],
      'items' => [['content' => $elements[0]]],
    ];
    (new FieldHooks())->preprocessField($variables);
    if (class_exists(\Drupal\emulsify\Hook\MediaEmbedHooks::class)) {
      \Drupal::service('class_resolver')->getInstanceFromDefinition(\Drupal\emulsify\Hook\MediaEmbedHooks::class)->preprocessField($variables);
    }
    $iframe = [
      '#theme' => 'media_oembed_iframe',
      '#resource' => $resource,
      '#media' => IFrameMarkup::create($resource->getHtml()),
      '#placeholder_token' => 'fixed-oembed-placeholder-token',
    ];
    $captured[$name] = [
      'iframe' => (string) $renderer->renderInIsolation($iframe),
      'formatter_attributes' => $variables['items'][0]['content']['#attributes'],
      'formatter' => (string) $renderer->renderInIsolation($variables['items'][0]['content']),
    ];
  }
}
finally {
  $context->setHost($original_context[0])->setScheme($original_context[1])->setBaseUrl($original_context[2]);
  $theme_manager->setActiveTheme($original_theme);
}

$expected = json_decode(file_get_contents(__DIR__ . '/../fixtures/oembed-baseline.json'), TRUE, 512, JSON_THROW_ON_ERROR);
foreach ($captured as $name => $actual) {
  foreach ($actual as $part => $value) {
    if (($expected[$name][$part] ?? NULL) !== $value) {
      throw new RuntimeException("The legacy {$name} {$part} output changed. Expected " . var_export($expected[$name][$part] ?? NULL, TRUE) . ', got ' . var_export($value, TRUE));
    }
  }
}
fwrite(STDOUT, 'PASS Legacy oEmbed baseline: exact YouTube/Vimeo iframe documents, core formatter attributes, and outer iframe HTML on Drupal ' . \Drupal::VERSION . ' / PHP ' . PHP_VERSION . ".\n");
