#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <work-dir>" >&2
  exit 1
fi

fixture_dir="$1"
work_dir="$2"
before_dir="${work_dir}/before"
after_dir="${work_dir}/after"
before_normalized_dir="${work_dir}/before-normalized"
after_normalized_dir="${work_dir}/after-normalized"
theme_info_file="${fixture_dir}/web/themes/contrib/emulsify/emulsify.info.yml"
normalizer="${GITHUB_WORKSPACE:-$(pwd)}/.github/scripts/normalize-rendered-html.php"

rm -rf "$work_dir"
mkdir -p "$before_dir" "$after_dir" "$before_normalized_dir" "$after_normalized_dir"

bash "${GITHUB_WORKSPACE:-$(pwd)}/.github/scripts/render-reference-pages.sh" "$fixture_dir" "$before_dir"

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

bash "${GITHUB_WORKSPACE:-$(pwd)}/.github/scripts/render-reference-pages.sh" "$fixture_dir" "$after_dir"

for file in "$before_dir"/*.html; do
  filename="$(basename "$file")"
  php "$normalizer" "$file" "${before_normalized_dir}/${filename}"
  php "$normalizer" "${after_dir}/${filename}" "${after_normalized_dir}/${filename}"
done

diff -ru "$before_normalized_dir" "$after_normalized_dir"
