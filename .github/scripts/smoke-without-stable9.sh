#!/usr/bin/env bash

set -euo pipefail

# Smoke-renders the fixture after replacing the Stable9 base theme with false.
# This catches missing templates/hooks before the full parity comparison runs.
if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <output-dir>" >&2
  exit 1
fi

fixture_dir="$1"
output_dir="$2"
theme_info_file="${fixture_dir}/web/themes/contrib/emulsify/emulsify.info.yml"
theme_info_backup="$(mktemp)"

# Keep this smoke test isolated: restore the theme info file and rebuild caches
# so later workflow steps are not affected by the temporary base-theme change.
cleanup() {
  cp "$theme_info_backup" "$theme_info_file" 2>/dev/null || true
  (
    cd "$fixture_dir"
    ./vendor/bin/drush cr -y >/dev/null 2>&1 || true
  )
  rm -f "$theme_info_backup"
}

cp "$theme_info_file" "$theme_info_backup"
trap cleanup EXIT

# Drupal 11 requires an explicit base theme value, so use false rather than
# removing the base theme key.
php -r '
$file = $argv[1];
$contents = file_get_contents($file);
if ($contents === false) {
  fwrite(STDERR, "Unable to read theme info file.\n");
  exit(1);
}
$updated = preg_replace("/^base theme: stable9$/m", "base theme: false", $contents, 1);
if ($updated === null || $updated === $contents) {
  fwrite(STDERR, "Unable to replace stable9 fallback in theme info.\n");
  exit(1);
}
file_put_contents($file, $updated);
' "$theme_info_file"

(
  cd "$fixture_dir"
  ./vendor/bin/drush cr -y
)

# A successful render proves the theme can bootstrap without Stable9 fallback.
bash "${GITHUB_WORKSPACE:-$(pwd)}/.github/scripts/render-reference-pages.sh" "$fixture_dir" "$output_dir"
