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
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
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
$resources = [];
$field_variables = [];

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
    $resources[$name] = $resource;
    $variables = [
      'field_type' => 'string',
      'element' => ['#formatter' => 'oembed', '#field_name' => 'field_media_oembed_video', '#entity_type' => 'media', '#items' => $items, 0 => $elements[0]],
      'items' => [['content' => $elements[0]]],
    ];
    (new FieldHooks())->preprocessField($variables);
    $field_variables[$name] = $variables;
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

/**
 * Fails a media contract with a useful message.
 */
function emulsify_oembed_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/**
 * Parses rendered HTML without contacting external resources.
 */
function emulsify_oembed_document(string $html): DOMDocument {
  $document = new DOMDocument();
  $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
  return $document;
}

/**
 * Creates a fixed resource with optional provider markup and hostile title.
 */
function emulsify_oembed_resource(Resource $resource, string $html, string $title = 'Fixture <img src=x onerror=alert(1)> & "title"', int $width = 640, int $height = 360): Resource {
  return Resource::video($html, $width, $height, $resource->getProvider(), $title, 'Fixture author', 'https://fixture.example.test/author', 3600, $resource->getThumbnailUrl()->toString(), $width, $height);
}

$theme_name = (string) \Drupal::config('system.theme')->get('default');
$config = \Drupal::configFactory()->getEditable($theme_name . '.settings');
$original_settings = $config->getRawData();
$theme_manager->setActiveTheme(\Drupal::service('theme.initialization')->initTheme($theme_name));
$container = \Drupal::getContainer();
$original_language_manager = \Drupal::languageManager();
$defaults = \Drupal\emulsify\Media\MediaEmbedSettings::DEFAULTS;
$tags = array_map(static fn(string $name): string => 'config:' . $name . '.settings', array_merge([$theme_name], array_keys($theme_manager->getActiveTheme()->getBaseThemeExtensions())));
$set_settings = static function (array $settings) use ($config, $defaults): void {
  foreach (array_replace($defaults, $settings) as $key => $value) {
    $config->set($key, $value);
  }
  $config->save();
};
$artifacts = getenv('EMULSIFY_OEMBED_ARTIFACT_DIR');
if ($artifacts && !is_dir($artifacts) && !mkdir($artifacts, 0777, TRUE) && !is_dir($artifacts)) {
  throw new RuntimeException('Unable to create poster artifact directory.');
}

try {
  $language_manager = new class(new LanguageDefault(['id' => 'en', 'direction' => LanguageInterface::DIRECTION_LTR])) extends LanguageManager {
    public function getCurrentLanguage($type = LanguageInterface::TYPE_INTERFACE) {
      return match ($type) {
        LanguageInterface::TYPE_INTERFACE => new Language(['id' => 'ar', 'direction' => LanguageInterface::DIRECTION_RTL]),
        LanguageInterface::TYPE_CONTENT => new Language(['id' => 'fr', 'direction' => LanguageInterface::DIRECTION_LTR]),
        default => $this->getDefaultLanguage(),
      };
    }
  };
  $container->set('language_manager', $language_manager);
  emulsify_oembed_assert($language_manager->getDefaultLanguage()->getId() === 'en' && $language_manager->getDefaultLanguage()->getDirection() === 'ltr', 'The language fixture must have a left-to-right English default.');
  emulsify_oembed_assert($language_manager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId() === 'fr' && $language_manager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getDirection() === 'ltr', 'The language fixture must have left-to-right French content.');
  emulsify_oembed_assert($language_manager->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)->getId() === 'ar' && $language_manager->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)->getDirection() === 'rtl', 'The language fixture must have a distinct right-to-left Arabic interface.');
  $hook = \Drupal::service('class_resolver')->getInstanceFromDefinition(\Drupal\emulsify\Hook\MediaEmbedHooks::class);
  $preprocess = static function (Resource $resource) use ($hook): array {
    $variables = ['resource' => $resource, 'media' => IFrameMarkup::create($resource->getHtml()), '#cache' => ['tags' => ['oembed:existing'], 'contexts' => ['user.permissions'], 'max-age' => 3600]];
    $hook->preprocessMediaOembedIframe($variables);
    return $variables;
  };
  $render = static function (Resource $resource) use ($renderer): string {
    $element = ['#theme' => 'media_oembed_iframe', '#resource' => $resource, '#media' => IFrameMarkup::create($resource->getHtml()), '#placeholder_token' => 'fixed-oembed-placeholder-token'];
    return (string) $renderer->renderInIsolation($element);
  };
  emulsify_oembed_assert(\Drupal\emulsify\Media\MediaEmbedSettings::normalize([]) === $defaults, 'Existing child settings without media keys must retain the off defaults.');
  emulsify_oembed_assert(\Drupal\emulsify\Media\MediaEmbedSettings::normalize(['media_embed_poster' => 'invalid'])['media_embed_poster'] === 'provider', 'Unknown poster choices must normalize to provider.');
  foreach ([[], ['media_embed_privacy_mode' => TRUE], ['media_embed_click_to_load' => TRUE]] as $settings) {
    $set_settings($settings);
    foreach ($resources as $name => $resource) {
      $variables = $preprocess($resource);
      emulsify_oembed_assert(array_diff(array_merge(['oembed:existing'], $tags), $variables['#cache']['tags']) === [], 'Inner embeds must retain existing and active/base theme settings cache tags even when off.');
      emulsify_oembed_assert(in_array('user.permissions', $variables['#cache']['contexts'], TRUE) && $variables['#cache']['max-age'] === 3600, 'Inner embeds must preserve existing cache contexts and max-age.');
      emulsify_oembed_assert(in_array('languages:language_interface', $variables['#cache']['contexts'], TRUE) === !empty($settings['media_embed_click_to_load']), 'Only poster documents must vary by interface language.');
      if (empty($settings['media_embed_click_to_load'])) {
        $document = emulsify_oembed_document($render($resource));
        emulsify_oembed_assert(!$document->documentElement->hasAttribute('lang'), 'Documents without a poster must retain their original HTML language attributes.');
        emulsify_oembed_assert(!$document->documentElement->hasAttribute('dir'), 'Documents without a poster must retain their original HTML direction attributes.');
      }
      $outer = $field_variables[$name];
      $outer['#cache']['tags'] = ['oembed:outer'];
      (new FieldHooks())->preprocessField($outer);
      emulsify_oembed_assert(array_diff(array_merge(['oembed:outer'], $tags), $outer['#cache']['tags']) === [], 'Outer embeds must retain existing and active/base theme settings cache tags.');
      $attributes = $outer['items'][0]['content']['#attributes'];
      $expected_attributes = $expected[$name]['formatter_attributes'];
      if ($settings === []) {
        emulsify_oembed_assert($attributes === $expected_attributes, 'Off settings must preserve the exact core formatter attributes.');
      }
      else {
        emulsify_oembed_assert(($attributes['allowfullscreen'] ?? NULL) === TRUE, 'Opted-in outer iframes must allow fullscreen.');
        foreach (['autoplay', 'fullscreen', 'picture-in-picture', 'encrypted-media'] as $permission) {
          emulsify_oembed_assert(preg_match('/(?:^|;\s*)' . preg_quote($permission, '/') . '\s/', $attributes['allow']) === 1, "Outer iframe must delegate {$permission}.");
        }
        foreach (["'src'", 'https://www.youtube.com', 'https://youtube.com', 'https://www.youtube-nocookie.com', 'https://player.vimeo.com'] as $origin) {
          emulsify_oembed_assert(substr_count($attributes['allow'], $origin) >= 4, 'Every required directive must delegate to the wrapper and supported player origins.');
        }
        unset($attributes['allow'], $attributes['allowfullscreen']);
        emulsify_oembed_assert($attributes === $expected_attributes, 'Permissions must preserve every pre-existing formatter attribute.');
      }
    }
  }

  $raw_sources = [
    'youtube' => 'https://www.youtube.com/embed/abcdefghijk?x.y=a%20b&x.y=second&list[]=one&list[]=two&autoplay=0#chapter',
    'vimeo' => 'https://player.vimeo.com/video/123456789?h=abc123&x.y=a%20b&x.y=second&list[]=one&list[]=two&dnt=0#chapter',
  ];
  foreach ($resources as $name => $resource) {
    $source = $raw_sources[$name];
    $custom = emulsify_oembed_resource($resource, '<iframe src="' . $source . '" title="Fixture player"></iframe>');
    $privacy_url = $name === 'youtube' ? str_replace('www.youtube.com', 'www.youtube-nocookie.com', $source) : str_replace('dnt=0', 'dnt=1', $source);
    $set_settings(['media_embed_privacy_mode' => TRUE]);
    $variables = $preprocess($custom);
    emulsify_oembed_assert(emulsify_oembed_document((string) $variables['media'])->getElementsByTagName('iframe')->item(0)->getAttribute('src') === $privacy_url, 'Privacy must preserve raw duplicate, dotted, bracketed query pairs, Vimeo h, and fragments.');
    emulsify_oembed_assert(!isset($variables['emulsify_oembed_poster']), 'Privacy alone must retain the iframe player.');

    foreach (['provider', 'neutral'] as $poster) {
      foreach ([FALSE, TRUE] as $privacy) {
        $set_settings(['media_embed_click_to_load' => TRUE, 'media_embed_privacy_mode' => $privacy, 'media_embed_poster' => $poster]);
        $variables = $preprocess($custom);
        emulsify_oembed_assert($variables['emulsify_oembed_poster']['language_code'] === 'ar', 'Posters must expose the Arabic interface language, not the default or content language, to child templates.');
        emulsify_oembed_assert($variables['emulsify_oembed_poster']['language_direction'] === 'rtl', 'Posters must expose the right-to-left interface language direction to child templates.');
        $player_url = $privacy ? $privacy_url : $source;
        $player_url = str_contains($player_url, 'autoplay=0') ? str_replace('autoplay=0', 'autoplay=1', $player_url) : str_replace('#chapter', '&autoplay=1#chapter', $player_url);
        emulsify_oembed_assert($variables['emulsify_oembed_poster']['player_url'] === $player_url, 'Play links must enable autoplay while preserving all unrelated URL bytes.');
        emulsify_oembed_assert(in_array('emulsify/oembed_poster', $variables['#attached']['library'] ?? [], TRUE), 'Posters must attach the local CSS library.');
        $html = $render($custom);
        $document = emulsify_oembed_document($html);
        emulsify_oembed_assert($document->documentElement->getAttribute('lang') === 'ar', 'Poster documents must declare the Arabic interface language.');
        emulsify_oembed_assert($document->documentElement->getAttribute('dir') === 'rtl', 'Poster documents must declare the right-to-left interface language direction.');
        $xpath = new DOMXPath($document);
        emulsify_oembed_assert($xpath->query('//iframe | //script | //noscript | //@onerror')->length === 0, 'Poster documents must omit the player iframe and escape hostile provider titles.');
        $anchor = $document->getElementsByTagName('a')->item(0);
        emulsify_oembed_assert($anchor !== NULL && $anchor->getAttribute('href') === $player_url && $anchor->getAttribute('referrerpolicy') === 'strict-origin-when-cross-origin', 'A native link must load the player without JavaScript.');
        emulsify_oembed_assert(str_contains($anchor->textContent, $custom->getTitle()), 'The play link must include the complete escaped video title.');
        emulsify_oembed_assert($xpath->query('//svg[@aria-hidden="true" and @focusable="false"]')->length === 1, 'The decorative play SVG must be hidden from assistive technology.');
        $images = $document->getElementsByTagName('img');
        emulsify_oembed_assert($images->length === ($poster === 'provider' ? 1 : 0), 'Only provider thumbnail mode may request a poster image.');
        if ($poster === 'provider') {
          emulsify_oembed_assert($images->item(0)->getAttribute('alt') === '' && $images->item(0)->getAttribute('referrerpolicy') === 'no-referrer', 'Provider thumbnails must be decorative and omit the referrer.');
        }
        else {
          emulsify_oembed_assert($xpath->query('//*[@src] | //link | //object | //embed')->length === 0, 'Neutral posters must not contain third-party resource requests.');
        }
        if ($artifacts && $privacy) {
          file_put_contents($artifacts . '/' . $name . '-' . $poster . '.html', $html);
        }
      }
    }
  }

  foreach ($resources as $name => $resource) {
    foreach ([[200, 113], [320, 180]] as [$width, $height]) {
      $small = emulsify_oembed_resource($resource, $resource->getHtml(), width: $width, height: $height);
      foreach (['provider', 'neutral'] as $poster) {
        $set_settings(['media_embed_click_to_load' => TRUE, 'media_embed_poster' => $poster]);
        $variables = $preprocess($small);
        emulsify_oembed_assert($variables['emulsify_oembed_poster']['aspect_ratio'] === $width . ' / ' . $height, 'Small resources must retain their aspect ratio for child templates.');
        $html = $render($small);
        $document = emulsify_oembed_document($html);
        $anchors = $document->getElementsByTagName('a');
        emulsify_oembed_assert($anchors->length === 1 && !$anchors->item(0)->hasAttribute('style'), 'Small posters must retain one native play link without inline geometry.');
        emulsify_oembed_assert($document->documentElement->getAttribute('lang') === 'ar', 'Small poster documents must declare the Arabic interface language.');
        emulsify_oembed_assert($document->documentElement->getAttribute('dir') === 'rtl', 'Small poster documents must declare the right-to-left interface language direction.');
        if ($artifacts) {
          file_put_contents($artifacts . '/' . $name . '-' . $poster . '-' . $width . 'x' . $height . '.html', $html);
        }
      }
    }
  }

  $set_settings(['media_embed_privacy_mode' => TRUE, 'media_embed_click_to_load' => TRUE]);
  $invalid_markup = [
    '<iframe src="https://www.youtube.com/embed/id"></iframe><p>Extra markup</p>',
    '<iframe src="https://www.youtube.com/embed/id"><iframe>',
    '<iframe src="http://www.youtube.com/embed/id"></iframe>',
    '<iframe src="https://player.vimeo.com/video/123"></iframe>',
    '<iframe src="https://user:pass@www.youtube.com/embed/id"></iframe>',
    '<iframe src="https://www.youtube.com:444/embed/id"></iframe>',
    '<iframe src="https://www.youtube.com/watch?v=id"></iframe>',
  ];
  foreach ($invalid_markup as $html) {
    $variables = $preprocess(emulsify_oembed_resource($resources['youtube'], $html));
    emulsify_oembed_assert((string) $variables['media'] === $html && !isset($variables['emulsify_oembed_poster']), 'Unknown or malformed player markup must pass through unchanged: ' . $html);
  }
  foreach ([
    ['youtube', 'https://youtube.com.evil.example/embed/x'],
    ['youtube', 'https://evil.example/www.youtube.com/embed/x'],
    ['youtube', '//www.youtube.com/embed/x'],
    ['youtube', 'http://www.youtube.com/embed/x'],
    ['youtube', 'https://www.youtube.com@evil.example/embed/x'],
    ['vimeo', 'https://player.vimeo.com.evil.example/video/1'],
  ] as [$provider, $url]) {
    $html = '<iframe src="' . $url . '"></iframe>';
    $variables = $preprocess(emulsify_oembed_resource($resources[$provider], $html));
    emulsify_oembed_assert((string) $variables['media'] === $html && !isset($variables['emulsify_oembed_poster']), 'Invalid player URLs must pass through unchanged without a poster: ' . $url);
  }
  $uppercase_host = $preprocess(emulsify_oembed_resource($resources['youtube'], '<iframe src="https://WWW.YOUTUBE.COM/embed/x"></iframe>'));
  emulsify_oembed_assert(($uppercase_host['emulsify_oembed_poster']['player_url'] ?? NULL) === 'https://www.youtube-nocookie.com/embed/x?autoplay=1', 'Uppercase YouTube hosts must be accepted and receive privacy and autoplay settings.');
  $unknown = new Provider('Other video', 'https://video.example.test/', [['url' => 'https://video.example.test/oembed', 'formats' => ['json']]]);
  $unknown_resource = Resource::video($fixtures['youtube']['html'], 640, 360, $unknown);
  emulsify_oembed_assert((string) $preprocess($unknown_resource)['media'] === $unknown_resource->getHtml(), 'Unknown providers must pass through unchanged.');
  $non_oembed = $field_variables['youtube'];
  $non_oembed['element']['#formatter'] = 'other';
  $unchanged = $non_oembed;
  (new FieldHooks())->preprocessField($non_oembed);
  emulsify_oembed_assert($non_oembed === $unchanged, 'Other field formatters must remain untouched.');

  $form_state = (new \Drupal\Core\Form\FormState())->setBuildInfo(['args' => [$theme_name]]);
  $form = [];
  $form_helper = \Drupal\emulsify\Media\MediaEmbedSettingsForm::create(\Drupal::getContainer());
  $form_helper->alter($form, $form_state);
  $controls = $form['emulsify_media_embeds'];
  emulsify_oembed_assert($controls['media_embed_privacy_mode']['#type'] === 'checkbox' && $controls['media_embed_click_to_load']['#type'] === 'checkbox', 'Media controls must use native, independently opt-in checkboxes.');
  emulsify_oembed_assert(array_keys($controls['media_embed_poster']['#options']) === ['provider', 'neutral'] && isset($controls['media_embed_poster']['#states']['visible']), 'Poster choices must remain available with a conditional form display.');
  $values = array_replace(\Drupal\emulsify\Favicon\FaviconSettings::DEFAULTS, ['favicon_package_enabled' => FALSE, 'default_logo' => TRUE, 'media_embed_privacy_mode' => TRUE, 'media_embed_click_to_load' => TRUE, 'media_embed_poster' => 'neutral']);
  $actual_state = (new \Drupal\Core\Form\FormState())->setValues($values);
  \Drupal::formBuilder()->submitForm(\Drupal\system\Form\ThemeSettingsForm::class, $actual_state, $theme_name);
  emulsify_oembed_assert($actual_state->getErrors() === [], 'The real theme settings form must accept the media options.');
  \Drupal::configFactory()->reset($theme_name . '.settings');
  foreach (array_keys($defaults) as $key) {
    emulsify_oembed_assert(\Drupal::config($theme_name . '.settings')->get($key) === $values[$key], "Core must persist the normalized {$key} value.");
  }
}
finally {
  $container->set('language_manager', $original_language_manager);
  $config->setData($original_settings)->save();
  $theme_manager->setActiveTheme($original_theme);
}
fwrite(STDOUT, "PASS Opt-in oEmbed privacy URLs, provider/neutral native posters, escaped titles, cache dependencies, formatter permissions, and real theme settings save for {$theme_name}.\n");
