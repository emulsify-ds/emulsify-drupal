#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -ne 4 ]; then
  echo 'Usage: published-dependency-check.sh <^11.3|^12> <package> <new-fixture-dir> <evidence-dir>' >&2
  exit 1
fi

core_constraint="$1"
theme_package="$2"
fixture_dir="$3"
evidence_dir="$4"
repo_root="$(cd "$(dirname "$0")/../.." && pwd)"
composer_bin="${COMPOSER_BIN:-composer}"
case "$core_constraint" in
  '^11.3') drush_constraint='^13'; expected_php='8.3' ;;
  '^12') drush_constraint='^14'; expected_php='8.5' ;;
  *) echo 'Unsupported Drupal constraint.' >&2; exit 1 ;;
esac
case "$theme_package" in
  drupal/emulsify|emulsify-ds/emulsify-drupal) ;;
  *) echo 'Unsupported published package route.' >&2; exit 1 ;;
esac
if [ "$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')" != "$expected_php" ]; then
  echo "Drupal ${core_constraint} matrix requires actual PHP ${expected_php}." >&2
  exit 1
fi
if [ -e "$fixture_dir" ]; then
  echo "Fixture path already exists; provide a new disposable path: ${fixture_dir}" >&2
  exit 1
fi
php "${repo_root}/.github/scripts/published-package-metadata.test.php"
mkdir -p "$fixture_dir" "$evidence_dir"
fixture_dir="$(cd "$fixture_dir" && pwd)"
evidence_dir="$(cd "$evidence_dir" && pwd)"
cp "${repo_root}/.github/scripts/publication-skew.json" "${evidence_dir}/publication-skew.json"

curl --fail --silent --show-error --location https://repo.packagist.org/p2/emulsify-ds/emulsify-drupal.json > "${evidence_dir}/packagist.json"
curl --fail --silent --show-error --location https://packages.drupal.org/files/packages/8/p2/drupal/emulsify.json > "${evidence_dir}/drupalorg.json"
theme_version="$(php "${repo_root}/.github/scripts/published-package-metadata.php" "$repo_root" "$evidence_dir" "$theme_package")"

php -r '
[$script, $dir, $core, $drush, $package, $version] = $argv;
$project = [
  "name" => "emulsify-ds/published-dependency-fixture",
  "description" => "Disposable published Emulsify dependency validation site",
  "license" => "GPL-2.0-or-later",
  "type" => "project",
  "repositories" => [["type" => "composer", "url" => "https://packages.drupal.org/8"]],
  "require" => [
    "composer/installers" => "^2.3",
    "drupal/core-recommended" => $core,
    "drupal/core-composer-scaffold" => $core,
    "drush/drush" => $drush,
    $package => "^" . $version,
  ],
  "minimum-stability" => "dev",
  "prefer-stable" => TRUE,
  "config" => ["allow-plugins" => ["composer/installers" => TRUE, "drupal/core-composer-scaffold" => TRUE, "symfony/runtime" => TRUE]],
  "extra" => [
    "drupal-scaffold" => ["locations" => ["web-root" => "web/"]],
    "installer-paths" => [
      "web/core" => ["type:drupal-core"],
      "web/modules/contrib/{\$name}" => ["type:drupal-module"],
      "web/themes/contrib/{\$name}" => ["type:drupal-theme"],
      "web/profiles/contrib/{\$name}" => ["type:drupal-profile"],
    ],
  ],
];
file_put_contents("{$dir}/composer.json", json_encode($project, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
' "$fixture_dir" "$core_constraint" "$drush_constraint" "$theme_package" "$theme_version"

cd "$fixture_dir"
# Keep a normal consumer requirement while resolving the exact observed release.
"$composer_bin" update --with="${theme_package}:${theme_version}" --no-interaction --prefer-dist 2>&1 | tee "${evidence_dir}/install.txt"
php -r '
[$script, $package, $expected] = $argv;
$lock = json_decode(file_get_contents("composer.lock"), TRUE, flags: JSON_THROW_ON_ERROR);
foreach ($lock["packages"] as $dependency) {
  if ($dependency["name"] === $package && ltrim($dependency["version"], "v") === $expected) {
    exit(0);
  }
}
throw new RuntimeException("Installed published theme does not match verified release {$expected}.");
' "$theme_package" "$theme_version"
"$composer_bin" validate --strict 2>&1 | tee "${evidence_dir}/validate.txt"
theme_dir="$(php -r 'require "vendor/autoload.php"; echo Composer\InstalledVersions::getInstallPath($argv[1]);' "$theme_package")"
COMPOSER_ROOT_VERSION="$theme_version" "$composer_bin" --working-dir="$theme_dir" validate --strict 2>&1 | tee "${evidence_dir}/theme-validate.txt"
# Do not hide security failures or change package constraints to obtain green.
"$composer_bin" audit --no-interaction --format=json | tee "${evidence_dir}/composer-audit.json"
cp composer.json composer.lock "$evidence_dir/"
./vendor/bin/drush site:install minimal --db-url=sqlite://sites/default/files/.ht.sqlite --account-name=admin --account-pass=admin -y 2>&1 | tee "${evidence_dir}/site-install.txt"
./vendor/bin/drush en emulsify_tools breakpoint -y
./vendor/bin/drush theme:enable emulsify -y
./vendor/bin/drush config:set system.theme default emulsify -y
./vendor/bin/drush cr -y
./vendor/bin/drush php:script "${repo_root}/.github/scripts/verify-published-theme.php" | tee "${evidence_dir}/registration.json"
