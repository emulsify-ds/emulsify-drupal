#!/usr/bin/env bash

set -euo pipefail

# Builds a disposable Drupal fixture for the Theme Readiness workflow.
# The fixture installs Drupal, copies this checkout in as the Emulsify theme,
# enables the theme, and seeds minimal content for render smoke tests.
if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <drupal-version> <fixture-dir> [theme-source-dir]" >&2
  exit 1
fi

drupal_version="$1"
fixture_dir="$2"

# Local callers can pass the checkout path explicitly. In CI this is
# $GITHUB_WORKSPACE, and locally it defaults to the current working directory.
theme_source_dir="${3:-$(pwd)}"
composer_bin="${COMPOSER_BIN:-composer}"
emulsify_tools_constraint="$(php -r '$metadata = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); echo $metadata["require"]["drupal/emulsify_tools"];' "${theme_source_dir}/composer.json")"
theme_dir="${fixture_dir}/web/themes/contrib/emulsify"
export COMPOSER_MEMORY_LIMIT=-1

# Start from a clean fixture so repeated local runs do not reuse stale Drupal
# config, generated files, or copied theme code.
if [ -d "$fixture_dir" ]; then
  chmod -R u+w "$fixture_dir" 2>/dev/null || true
fi
rm -rf "$fixture_dir"
"$composer_bin" create-project --no-interaction --no-install --no-audit --no-security-blocking "drupal/recommended-project:${drupal_version}" "$fixture_dir"

# All subsequent commands run inside the disposable Drupal project, not the
# source checkout.
cd "$fixture_dir"

# Composer 2.9 blocks vulnerable historical Drupal minors by default. Keep the
# fixture behavior explicit so CI tests the requested matrix version.
"$composer_bin" config --no-interaction audit.block-insecure false

# The project template uses caret ranges that can install a newer Drupal minor.
# Pin its core packages to the matrix constraint before resolving dependencies so
# every leg tests the requested minor or prerelease, including after Drush adds
# its own dependencies below. This changes only the disposable fixture.
"$composer_bin" require --no-interaction --no-update \
  "drupal/core-recommended:${drupal_version}" \
  "drupal/core-composer-scaffold:${drupal_version}" \
  "drupal/core-project-message:${drupal_version}" \
  "drupal/core-recipe-unpack:${drupal_version}"
"$composer_bin" update --no-interaction --no-audit --no-security-blocking

# Drupal 12's Symfony 8 and Guzzle 8 dependencies require Drush 14. Until Drush
# 14 and its dependencies have stable releases, allow their development builds
# only in this disposable fixture; prefer tagged releases when available.
drush_constraint="^13"
core_major="$(php -r 'require "vendor/autoload.php"; echo explode(".", \Drupal::VERSION)[0];')"
if [ "$core_major" -ge 12 ]; then
  drush_constraint="^14"
  "$composer_bin" config --no-interaction minimum-stability dev
  "$composer_bin" config --no-interaction prefer-stable true
fi

# Copy the current checkout into the fixture as a contrib theme. This avoids
# path repository edge cases and ensures CI tests the exact PR contents.
mkdir -p "$(dirname "$theme_dir")"
rsync -a \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude 'node_modules/' \
  --exclude 'vendor/' \
  "${theme_source_dir}/" "${theme_dir}/"

# Resolve the published Tools package using this theme's exact declared range.
# An in-flight branch can behave differently from the API consumers install.
"$composer_bin" require --no-interaction --no-audit --no-security-blocking --with-all-dependencies \
  "drush/drush:${drush_constraint}" "drupal/emulsify_tools:${emulsify_tools_constraint}"

php -r '
require "vendor/autoload.php";
$installed = \Composer\InstalledVersions::getPrettyVersion("drupal/core");
if (!\Composer\Semver\Semver::satisfies($installed, $argv[1])) {
  throw new \RuntimeException("Installed Drupal $installed does not satisfy matrix constraint {$argv[1]}.");
}
' "$drupal_version"

# Use SQLite to keep the fixture self-contained on GitHub-hosted runners.
./vendor/bin/drush site:install standard \
  --db-url=sqlite://sites/default/files/.ht.sqlite \
  --account-name=admin \
  --account-pass=admin \
  -y

./vendor/bin/drush en emulsify_tools -y
./vendor/bin/drush theme:enable emulsify -y
./vendor/bin/drush config:set system.theme default emulsify -y

# Keep validation fixtures outside the distributable theme. Its dependency on
# Inline Form Errors makes real submitted errors available to all form wrappers.
mkdir -p "${fixture_dir}/web/modules/custom/form_a11y"
rsync -a "${theme_source_dir}/.github/fixtures/form_a11y/" "${fixture_dir}/web/modules/custom/form_a11y/"
./vendor/bin/drush en form_a11y -y

# Contact is optional across Drupal install profiles/versions. Enable it when
# present so form-render coverage is broader, but do not make the fixture depend
# on the module existing.
if [ -d "${fixture_dir}/web/core/modules/contact" ]; then
  ./vendor/bin/drush en contact -y
fi

# Drupal dev snapshots may change the standard profile's default content types.
# Keep the render fixture independent by ensuring the seeded bundle exists.
./vendor/bin/drush php:eval '
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

$storage = \Drupal::entityTypeManager()->getStorage("node_type");
if (!$storage->load("page")) {
  $type = NodeType::create([
    "type" => "page",
    "name" => "Basic page",
    "description" => "Fixture page content type for Emulsify readiness checks.",
    "display_submitted" => FALSE,
  ]);
  $type->save();
}

// Drupal 11.4+ standard profiles can omit the page body field as well as the
// bundle. Seed real renderable content using APIs that remain in Drupal 12.
if (!FieldStorageConfig::loadByName("node", "body")) {
  FieldStorageConfig::create([
    "entity_type" => "node",
    "field_name" => "body",
    "type" => "text_with_summary",
  ])->save();
}
if (!FieldConfig::loadByName("node", "page", "body")) {
  FieldConfig::create([
    "entity_type" => "node",
    "field_name" => "body",
    "bundle" => "page",
    "label" => "Body",
  ])->save();
}
\Drupal::service("entity_display.repository")
  ->getViewDisplay("node", "page")
  ->setComponent("body", ["label" => "hidden", "type" => "text_default"])
  ->save();
'

# Seed stable pages for render-reference-pages.sh. With one result per page,
# the second promoted page also provides a real pager for accessibility checks.
./vendor/bin/drush php:eval '
use Drupal\node\Entity\Node;

// Create fixture content idempotently so local reruns remain safe if a caller
// points at a pre-existing fixture directory.
$storage = \Drupal::entityTypeManager()->getStorage("node");
if (!$storage->loadByProperties(["title" => "Emulsify fixture page"])) {
  $node = Node::create([
    "type" => "page",
    "title" => "Emulsify fixture page",
    "status" => 1,
    "promote" => 1,
    "body" => [
      "value" => "Fixture body content for template parity checks.",
      "format" => "basic_html",
    ],
  ]);
  $node->save();
}

if (!$storage->loadByProperties(["title" => "Emulsify fixture page 2"])) {
  $node = Node::create([
    "type" => "page",
    "title" => "Emulsify fixture page 2",
    "status" => 1,
    "promote" => 1,
    "body" => [
      "value" => "Second fixture body content for the frontpage view.",
      "format" => "basic_html",
    ],
  ]);
  $node->save();
}
'

# Add a non-admin account so user-template hooks have a real user entity
# available in the fixture.
./vendor/bin/drush php:eval '
// Keep user-template coverage independent from the administrator account that
// site:install creates.
$storage = \Drupal::entityTypeManager()->getStorage("user");
if (!$storage->loadByProperties(["name" => "fixture-user"])) {
  $user = $storage->create([
    "name" => "fixture-user",
    "mail" => "fixture-user@example.com",
    "status" => 1,
  ]);
  $user->save();
}
'

# Route the front page to the node listing captured by the render smoke tests.
./vendor/bin/drush php:eval '
// The render smoke captures /node; accessibility checks also visit its second
// page so they exercise a real view, node teaser, and current pager item.
\Drupal::configFactory()
  ->getEditable("views.view.frontpage")
  ->set("display.default.display_options.pager.options.items_per_page", 1)
  ->save();
\Drupal::configFactory()
  ->getEditable("system.site")
  ->set("page.front", "/node")
  ->save();
'

# Rebuild caches after content/config changes so the render scripts start from a
# stable, warm Drupal state.
./vendor/bin/drush cr -y
