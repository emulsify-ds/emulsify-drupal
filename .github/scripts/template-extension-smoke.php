<?php

/**
 * @file
 * Verifies exact template bytes and child inheritance in a disposable fixture.
 *
 * Run through Drush after starterkit-smoke.sh has enabled example_theme.
 */

declare(strict_types=1);

use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Template\Attribute;

/**
 * Supplies fresh attributes and deterministic content for each render.
 */
function emulsify_extension_context(string $case): array {
  $regions = [];
  foreach (['header', 'status', 'breadcrumb', 'highlighted', 'content_top', 'sidebar_first', 'content', 'sidebar_second', 'content_bottom', 'footer'] as $region) {
    $regions[$region] = Markup::create('<div data-region="' . $region . '">' . $region . ' &amp; fixture</div>');
  }
  return match ($case) {
    'page' => ['page' => $regions, 'attributes' => new Attribute()],
    'page-empty' => ['page' => [], 'attributes' => new Attribute()],
    'html' => [
      'html_attributes' => new Attribute(['lang' => 'en', 'dir' => 'ltr']),
      'attributes' => new Attribute(['class' => ['fixture-body']]),
      'head_title' => ['Fixture <title>', 'Emulsify & Drupal'],
      'placeholder_token' => 'fixture-token',
      'page_top' => Markup::create('<div>Page top</div>'),
      'page' => Markup::create('<main id="main-content">Page</main>'),
      'page_bottom' => Markup::create('<div>Page bottom</div>'),
    ],
    'region', 'region-empty' => [
      'attributes' => new Attribute(['id' => 'fixture-region', 'class' => ['fixture-region']]),
      'content' => $case === 'region' ? Markup::create('<p>Region &amp; content</p>') : '',
    ],
    'block', 'block-unlabelled' => [
      'attributes' => new Attribute(['id' => 'fixture-block', 'class' => ['fixture-block'], 'data-value' => 'A & B']),
      'title_attributes' => new Attribute(['class' => ['fixture-title']]),
      'title_prefix' => Markup::create('<!-- title prefix -->'),
      'title_suffix' => Markup::create('<!-- title suffix -->'),
      'label' => $case === 'block' ? 'Fixture <title> & label' : '',
      'content' => Markup::create('<p>Block &amp; content</p>'),
    ],
  };
}

$twig = \Drupal::service('twig');
$twig->disableDebug();
$renderer = \Drupal::service('renderer');
$cases = [
  'page' => 'layout/page',
  'page-empty' => 'layout/page',
  'html' => 'layout/html',
  'region' => 'layout/region',
  'region-empty' => 'layout/region',
  'block' => 'block/block',
  'block-unlabelled' => 'block/block',
];
$golden_dir = __DIR__ . '/../fixtures/template-extension';
$capture = getenv('EMULSIFY_CAPTURE_TEMPLATE_GOLDENS') === '1';
if ($capture && !is_dir($golden_dir)) {
  mkdir($golden_dir, 0777, TRUE);
}

foreach ($cases as $case => $template) {
  $output = $renderer->executeInRenderContext(new RenderContext(), static fn() => $twig->render('@emulsify/templates/' . $template . '.html.twig', emulsify_extension_context($case)));
  $golden_file = $golden_dir . '/' . $case . '.html';
  if ($capture) {
    file_put_contents($golden_file, $output);
  }
  elseif (!is_file($golden_file) || file_get_contents($golden_file) !== $output) {
    throw new RuntimeException($case . ': rendered bytes differ from the pre-block golden file.');
  }
  print ($capture ? 'CAPTURE ' : 'PASS ') . $case . ' (' . strlen($output) . " bytes)\n";
}

if ($capture) {
  return;
}

// Exercise Drupal's theme registry, not only Twig's namespaced loader. Replace
// the disposable generated child's page override for this test and restore it.
$theme = getenv('EMULSIFY_STARTERKIT_THEME') ?: 'example_theme';
$theme_manager = \Drupal::theme();
$active_theme = $theme_manager->getActiveTheme();
if ($active_theme->getName() !== $theme || !isset($active_theme->getBaseThemeExtensions()['emulsify'])) {
  throw new RuntimeException('Enable the generated Emulsify child theme before running the extension smoke test.');
}
$child_file = DRUPAL_ROOT . '/' . $active_theme->getPath() . '/templates/layout/page.html.twig';
$original = is_file($child_file) ? file_get_contents($child_file) : NULL;
$marker = '<!-- child page_content extension -->';
$child_template = '{% extends "@emulsify/templates/layout/page.html.twig" %}{% block page_content %}' . $marker . '{{ parent() }}{% endblock %}';
try {
  if (!is_dir(dirname($child_file))) {
    mkdir(dirname($child_file), 0777, TRUE);
  }
  file_put_contents($child_file, $child_template);
  \Drupal::service('theme.registry')->reset();
  $twig->invalidate();
  $registry = \Drupal::service('theme.registry')->get();
  if (realpath(DRUPAL_ROOT . '/' . $registry['page']['path'] . '/page.html.twig') !== realpath($child_file)) {
    throw new RuntimeException('Drupal did not select the generated child page override.');
  }
  $page = ['#theme' => 'page'];
  foreach (emulsify_extension_context('page')['page'] as $region => $content) {
    $page[$region] = ['#markup' => $content];
  }
  $actual = (string) $renderer->renderInIsolation($page);
  $expected = file_get_contents($golden_dir . '/page.html');
  // The block includes the original content indentation and its trailing LF.
  $expected = str_replace('    <div data-region="content">', $marker . '    <div data-region="content">', $expected);
  if (substr_count($actual, $marker) !== 1 || $actual !== $expected) {
    throw new RuntimeException('The child must override exactly page_content and preserve every other parent byte.');
  }
  print 'PASS generated child registry selection, namespaced extends, parent(), and unchanged surrounding markup on Drupal ' . \Drupal::VERSION . ".\n";
}
finally {
  if ($original === NULL) {
    unlink($child_file);
  }
  else {
    file_put_contents($child_file, $original);
  }
  \Drupal::service('theme.registry')->reset();
  $twig->invalidate();
}
