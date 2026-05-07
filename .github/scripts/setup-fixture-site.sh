#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <drupal-version> <fixture-dir> [theme-source-dir]" >&2
  exit 1
fi

drupal_version="$1"
fixture_dir="$2"
theme_source_dir="${3:-$(pwd)}"
composer_bin="${COMPOSER_BIN:-composer}"
emulsify_tools_repo="${EMULSIFY_TOOLS_REPOSITORY:-https://github.com/emulsify-ds/emulsify_tools.git}"
emulsify_tools_ref="${EMULSIFY_TOOLS_REF:-release-2}"
theme_dir="${fixture_dir}/web/themes/contrib/emulsify"
emulsify_tools_dir="${fixture_dir}/web/modules/contrib/emulsify_tools"

export COMPOSER_MEMORY_LIMIT=-1

if [ -d "$fixture_dir" ]; then
  chmod -R u+w "$fixture_dir" 2>/dev/null || true
fi
rm -rf "$fixture_dir"
"$composer_bin" create-project --no-interaction "drupal/recommended-project:${drupal_version}" "$fixture_dir"

cd "$fixture_dir"

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

"$composer_bin" require --no-interaction drush/drush:^13

./vendor/bin/drush site:install standard \
  --db-url=sqlite://sites/default/files/.ht.sqlite \
  --account-name=admin \
  --account-pass=admin \
  -y

./vendor/bin/drush en emulsify_tools -y
./vendor/bin/drush theme:enable emulsify -y
./vendor/bin/drush config:set system.theme default emulsify -y
./vendor/bin/drush en contact -y

./vendor/bin/drush php:eval '
use Drupal\node\Entity\Node;

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

./vendor/bin/drush php:eval '
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

./vendor/bin/drush php:eval '
\Drupal::configFactory()
  ->getEditable("system.site")
  ->set("page.front", "/node")
  ->save();
'

./vendor/bin/drush cr -y
