<?php

declare(strict_types=1);

namespace Drupal\whisk;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Theme\StarterKitInterface;

/**
 * Finalizes project-specific documentation during theme generation.
 */
final class StarterKit implements StarterKitInterface {

  /**
   * Documentation placeholders replaced after Drupal generates theme metadata.
   */
  private const DOCUMENTATION_FILES = [
    'README.md',
    'UPGRADING.md',
    'docs/support-information.md',
  ];

  /**
   * {@inheritdoc}
   */
  public static function postProcess(string $working_dir, string $machine_name, string $theme_name): void {
    $info = self::decodeYamlFile("{$working_dir}/{$machine_name}.info.yml");
    $project_file = self::decodeJsonFile("{$working_dir}/project.emulsify.json");
    $package = self::decodeJsonFile("{$working_dir}/package.json");
    $project = $project_file['project'] ?? NULL;

    if (!is_array($project)) {
      throw new \RuntimeException('Generated project.emulsify.json is missing its project metadata.');
    }

    $description = self::requiredString($info, 'description', "{$machine_name}.info.yml");
    if ($description === '') {
      $description = 'No description was supplied during generation.';
    }

    $core_range = $package['dependencies']['@emulsify/core'] ?? NULL;
    if (!is_string($core_range) || trim($core_range) === '') {
      throw new \RuntimeException('Generated package.json is missing dependencies.@emulsify/core.');
    }

    $replacements = [
      '%%EMULSIFY_THEME_NAME%%' => self::oneLine($theme_name),
      '%%EMULSIFY_MACHINE_NAME%%' => self::oneLine($machine_name),
      '%%EMULSIFY_DESCRIPTION%%' => self::oneLine($description),
      '%%EMULSIFY_SOURCE_PROJECT%%' => self::requiredString($project, 'generatedFrom', 'project.emulsify.json'),
      '%%EMULSIFY_SOURCE_VERSION%%' => self::requiredString($project, 'generatedFromVersion', 'project.emulsify.json'),
      '%%EMULSIFY_CORE_RANGE%%' => self::oneLine($core_range),
    ];

    foreach (self::DOCUMENTATION_FILES as $relative_path) {
      $path = "{$working_dir}/{$relative_path}";
      $contents = self::readFile($path);
      $contents = strtr($contents, $replacements);

      if (preg_match('/%%EMULSIFY_[A-Z_]+%%/', $contents, $matches) === 1) {
        throw new \RuntimeException("Unable to replace documentation token {$matches[0]} in {$relative_path}.");
      }
      if (file_put_contents($path, $contents) === FALSE) {
        throw new \RuntimeException("Unable to write generated documentation file {$relative_path}.");
      }
    }
  }

  /**
   * Reads and decodes a required YAML mapping.
   */
  private static function decodeYamlFile(string $path): array {
    $data = Yaml::decode(self::readFile($path));
    if (!is_array($data)) {
      throw new \RuntimeException("Expected a YAML mapping in {$path}.");
    }
    return $data;
  }

  /**
   * Reads and decodes a required JSON object.
   */
  private static function decodeJsonFile(string $path): array {
    try {
      $data = json_decode(self::readFile($path), TRUE, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \RuntimeException("Unable to parse {$path}: {$exception->getMessage()}", 0, $exception);
    }
    if (!is_array($data)) {
      throw new \RuntimeException("Expected a JSON object in {$path}.");
    }
    return $data;
  }

  /**
   * Reads a required file.
   */
  private static function readFile(string $path): string {
    $contents = @file_get_contents($path);
    if ($contents === FALSE) {
      throw new \RuntimeException("Unable to read required generated file {$path}.");
    }
    return $contents;
  }

  /**
   * Gets a required string value from generated metadata.
   */
  private static function requiredString(array $data, string $key, string $source): string {
    if (!array_key_exists($key, $data) || !is_string($data[$key])) {
      throw new \RuntimeException("Generated {$source} is missing string value {$key}.");
    }
    return self::oneLine($data[$key]);
  }

  /**
   * Keeps generated metadata readable in Markdown prose.
   */
  private static function oneLine(string $value): string {
    return trim((string) preg_replace('/\s+/', ' ', $value));
  }

}
