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
emulsify_tools_repo="${EMULSIFY_TOOLS_REPOSITORY:-https://github.com/emulsify-ds/emulsify_tools.git}"
emulsify_tools_ref="${EMULSIFY_TOOLS_REF:-release-2}"
theme_dir="${fixture_dir}/web/themes/contrib/emulsify"
emulsify_tools_dir="${fixture_dir}/web/modules/contrib/emulsify_tools"
drush_constraint="^13"

if [ "$drupal_version" = "dev-main" ]; then
  # Drupal core's development branch can require newer Drush internals than the
  # current stable core line, so keep the constraint explicit per fixture.
  drush_constraint="^14"
fi

export COMPOSER_MEMORY_LIMIT=-1

# Start from a clean fixture so repeated local runs do not reuse stale Drupal
# config, generated files, or copied theme code.
if [ -d "$fixture_dir" ]; then
  chmod -R u+w "$fixture_dir" 2>/dev/null || true
fi
rm -rf "$fixture_dir"
"$composer_bin" create-project --no-interaction --no-audit --no-security-blocking "drupal/recommended-project:${drupal_version}" "$fixture_dir"

# All subsequent commands run inside the disposable Drupal project, not the
# source checkout.
cd "$fixture_dir"

# Composer 2.9 blocks vulnerable historical Drupal minors by default. Keep the
# fixture behavior explicit so CI tests the requested matrix version.
"$composer_bin" config --no-interaction audit.block-insecure false

# Copy the current checkout into the fixture as a contrib theme. This avoids
# path repository edge cases and ensures CI tests the exact PR contents.
mkdir -p "$(dirname "$theme_dir")" "$(dirname "$emulsify_tools_dir")"
rsync -a \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude 'node_modules/' \
  --exclude 'vendor/' \
  "${theme_source_dir}/" "${theme_dir}/"

# Readiness checks should exercise the local theme code and the in-flight
# Emulsify Tools 2.x branch instead of depending on a published package.
git clone --depth 1 --branch "$emulsify_tools_ref" "$emulsify_tools_repo" "$emulsify_tools_dir"
rm -rf "${emulsify_tools_dir}/.git"

"$composer_bin" require --no-interaction --no-audit --no-security-blocking --with-all-dependencies "drush/drush:${drush_constraint}"

# Use SQLite to keep the fixture self-contained on GitHub-hosted runners.
./vendor/bin/drush site:install standard \
  --db-url=sqlite://sites/default/files/.ht.sqlite \
  --account-name=admin \
  --account-pass=admin \
  -y

./vendor/bin/drush en emulsify_tools -y
./vendor/bin/drush theme:enable emulsify -y
./vendor/bin/drush config:set system.theme default emulsify -y

# Contact is optional across Drupal install profiles/versions. Enable it when
# present so form-render coverage is broader, but do not make the fixture depend
# on the module existing.
if [ -d "${fixture_dir}/web/core/modules/contact" ]; then
  ./vendor/bin/drush en contact -y
fi

# Seed stable pages for render-reference-pages.sh. The second promoted page
# keeps the frontpage node listing from collapsing to a single-item edge case.
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
// The render smoke captures /node because it exercises list, node teaser, view,
// and pager-adjacent template surfaces in a compact request.
\Drupal::configFactory()
  ->getEditable("system.site")
  ->set("page.front", "/node")
  ->save();
'

# Rebuild caches after content/config changes so the render scripts start from a
# stable, warm Drupal state.
./vendor/bin/drush cr -y
