<?php

/**
 * @file
 * Contains Drush hooks. Inspired by Zen and Omega Tools.
 */

/**
 * Implements hook_drush_command().
 */
function emulsify_drush_command() {
  $items = [];

  $items['emulsify'] = [
    'description' => 'Create a Emulsify subtheme.',
    'arguments' => [
      'name'         => 'The name of your subtheme.',
    ],
    'options' => [
      'machine_name' => 'The machine-readable name of your subtheme. This will be auto-generated from the human-readable name if ommited.',
      'description' => 'The description of your subtheme',
      'path' => 'The destination of your subtheme. Defaults to "all" (themes).',
      'kit' => 'The name or url of the starter kit to use. Defaults to "whisk".',
    ],
    'examples' => [
      'drush emulsify "Whisk"' => 'Creates a Emulsify subtheme called "Whisk", using the default options.',
      'drush emulsify "Whisk" --machine_name=whisk' => 'Creates a Emulsify subtheme called "Whisk" with a specific machine name.',
    ],
  ];

  return $items;
}

/**
 * Implements hook_drush_help().
 */
function emulsify_drush_help($section) {
  switch ($section) {
    case 'drush:emulsify':
      return dt('This command will create a Emulsify subtheme. See examples to get started.');
  }
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_emulsify($name = NULL, $machine_name = NULL) {

  // If no $name provided, abort.
  if (!$name) {
    drush_print(dt('Theme name missing. See help using drush emulsify --help.'));
    return;
  }

  // Determine the theme name.
  if (!isset($name)) {
    $name = drush_get_option('name');
  }

  // Determine the machine name.
  if (!isset($machine_name)) {
    $machine_name = drush_get_option('machine_name');
  }
  if (!$machine_name) {
    $machine_name = $name;
  }
  $machine_name = str_replace(' ', '_', strtolower($machine_name));
  $search = [
    // Remove characters not valid in function names.
    '/[^a-z0-9_]/',
    // Functions must begin with an alpha character.
    '/^[^a-z]+/',
  ];
  $machine_name = preg_replace($search, '', $machine_name);

  // Description of subtheme.
  $description = (drush_get_option('description')) ? trim(drush_get_option('description')) : 'A theme based on Emulsify.';

  // Determine the path to the new subtheme.
  $subtheme_path = 'themes';
  if ($path = drush_get_option('path')) {
    $subtheme_path = drush_trim_path($path);
  }

  // Determine the kit to use.
  $kit = (drush_get_option('kit')) ? drush_trim_path(drush_get_option('kit')) : 'whisk';

  drush_emulsify_create($name, $machine_name, $description, $subtheme_path, $kit);
}

/**
 * Create frontend theme.
 */
function drush_emulsify_create($name, $machine_name, $description, $subtheme_path, $kit) {
  $subtheme_path = drush_normalize_path(drush_get_context('DRUSH_DRUPAL_ROOT') . '/' . $subtheme_path . '/' . $machine_name);

  // Make a fresh copy of the kit.
  $kit_path = drush_normalize_path(drush_get_context('DRUSH_DRUPAL_ROOT') . '/' . drupal_get_path('theme', 'emulsify') . '/kits/' . $kit);

  if (!is_dir(dirname($subtheme_path))) {
    drush_die(dt('The directory "!directory" was not found.', ['!directory' => dirname($subtheme_path)]));
  }
  drush_op('drush_copy_dir', $kit_path, $subtheme_path);

  // Alter the contents of the .info file based on the command options.
  $alterations = [
    'EMULSIFY_KIT_NAME' => $name,
    'EMULSIFY_KIT_DESCRIPTION' => $description,
    'emulsify_kit' => $machine_name,
    'hidden: true' => '',
  ];

  // Replace all occurrences of '{{machine_name}}' with the machine name of our
  // sub theme.
  $files_to_replace = emulsify_get_files_to_make_replacements($kit);
  foreach ($files_to_replace as $file_to_replace) {
    drush_op('emulsify_file_str_replace', $subtheme_path . '/' . $file_to_replace, array_keys($alterations), $alterations);
  }

  // Rename files.
  $files_to_rename = [
    '{{kit}}.info.yml',
    '{{kit}}.libraries.yml',
    '{{kit}}.breakpoints.yml',
    '{{kit}}.theme',
    'dev/scss/{{kit}}.scss',
    'dev/js/{{kit}}.js',
    'config/schema/{{kit}}.schema.yml',
  ];

  foreach ($files_to_rename as $file_to_rename_path) {
    $file_original_path = $subtheme_path . '/' . str_replace('{{kit}}', $kit, $file_to_rename_path);
    $file_new_path = $subtheme_path . '/' . str_replace('{{kit}}', $machine_name, $file_to_rename_path);
    drush_op('rename', drush_normalize_path($file_original_path), drush_normalize_path($file_new_path));
  }

  // Batch rename all config files.
  $includes_path = $subtheme_path . '/config/optional/*.yml';
  foreach (glob($includes_path) as $file_to_rename_path) {
    $file_new_path = str_replace($kit, $machine_name, $file_to_rename_path);
    drush_op('rename', drush_normalize_path($file_to_rename_path), drush_normalize_path($file_new_path));
    drush_op('emulsify_file_str_replace', $file_new_path, array_keys($alterations), $alterations);
  }

  // Notify user of the newly created theme.
  $message = 'Successfully created the Emulsify subtheme "!name" created in: !path using the "!kit" kit';

  $message = dt($message . '.', [
    '!name' => $name,
    '!path' => $subtheme_path,
    '!kit' => $kit,
  ]);
  drush_print($message);
}

/**
 * Replace strings in a file.
 */
function emulsify_file_str_replace($file_path, $find, $replace) {
  $file_path = drush_normalize_path($file_path);
  $file_contents = file_get_contents($file_path);
  $file_contents = str_replace($find, $replace, $file_contents);
  file_put_contents($file_path, $file_contents);
}

/**
 * Returns an array of files to make string replacements. Based on kit chosen.
 */
function emulsify_get_files_to_make_replacements($kit) {
  return [
    'config/install/' . $kit . '.settings.yml',
    'config/schema/' . $kit . '.schema.yml',
    'dev/scss/' . $kit . '.scss',
    'dev/js/' . $kit . '.js',
    'dev/bower.json',
    'dev/gulpfile.js',
    'dev/package.json',
    'dev/config/config.json',
    $kit . '.info.yml',
    $kit . '.libraries.yml',
    $kit . '.breakpoints.yml',
    $kit . '.theme',
    'README.md',
  ];
}
