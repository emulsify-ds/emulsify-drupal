<?php

declare(strict_types=1);

/**
 * @file
 * Runs the same Drupal generation command used by starterkit-smoke.sh.
 */

use Drupal\Core\Command\GenerateTheme;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

require $argv[1];

$application = new Application();
$application->setAutoExit(FALSE);
$application->add(new GenerateTheme(NULL, $argv[2]));
$input = new ArrayInput([
  'command' => 'generate-theme',
  'machine-name' => 'audit_wrapper_theme',
  '--name' => 'Audit Wrapper Theme',
  '--description' => 'Generated audit wrapper regression fixture.',
  '--starterkit' => 'whisk',
  '--path' => 'themes/custom',
  '--no-interaction' => TRUE,
]);
exit($application->run($input));
