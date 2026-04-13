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

export COMPOSER_MEMORY_LIMIT=-1

rm -rf "$fixture_dir"
"$composer_bin" create-project --no-interaction "drupal/recommended-project:${drupal_version}" "$fixture_dir"

cd "$fixture_dir"

"$composer_bin" config repositories.emulsify "{\"type\":\"path\",\"url\":\"${theme_source_dir}\",\"options\":{\"symlink\":false}}"
"$composer_bin" require --no-interaction drush/drush:^13 emulsify-ds/emulsify-drupal:@dev

./vendor/bin/drush site:install standard \
  --db-url=sqlite://sites/default/files/.ht.sqlite \
  --account-name=admin \
  --account-pass=admin \
  -y

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
