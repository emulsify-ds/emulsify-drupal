#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <output-dir>" >&2
  exit 1
fi

fixture_dir="$1"
output_dir="$2"
theme_info_file="${fixture_dir}/web/themes/contrib/emulsify/emulsify.info.yml"

php -r '
$file = $argv[1];
$contents = file_get_contents($file);
if ($contents === false) {
  fwrite(STDERR, "Unable to read theme info file.\n");
  exit(1);
}
$updated = preg_replace("/^base theme: stable9\\n/m", "", $contents, 1);
if ($updated === null || $updated === $contents) {
  fwrite(STDERR, "Unable to remove stable9 fallback from theme info.\n");
  exit(1);
}
file_put_contents($file, $updated);
' "$theme_info_file"

(
  cd "$fixture_dir"
  ./vendor/bin/drush cr -y
)

bash "${GITHUB_WORKSPACE:-$(pwd)}/.github/scripts/render-reference-pages.sh" "$fixture_dir" "$output_dir"
