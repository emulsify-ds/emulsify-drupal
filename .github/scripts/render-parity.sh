#!/usr/bin/env bash

set -euo pipefail

# Verifies that removing Stable9 as the base theme does not materially change
# rendered output for the fixture pages captured by render-reference-pages.sh.
if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <work-dir>" >&2
  exit 1
fi

fixture_dir="$1"
work_dir="$2"

# Keep raw and normalized captures separate so a failed diff can be inspected
# without rerunning the fixture build.
before_dir="${work_dir}/before"
after_dir="${work_dir}/after"
before_normalized_dir="${work_dir}/before-normalized"
after_normalized_dir="${work_dir}/after-normalized"

theme_info_file="${fixture_dir}/web/themes/contrib/emulsify/emulsify.info.yml"
normalizer="${GITHUB_WORKSPACE:-$(pwd)}/.github/scripts/normalize-rendered-html.php"
theme_info_backup="$(mktemp)"

# The script mutates emulsify.info.yml inside the fixture. Restore it and clear
# Drupal caches on every exit so follow-up smoke tests see the original theme.
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

# Remove the full work directory on each run so stale captures cannot mask a
# missing page or failed render from the current run.
rm -rf "$work_dir"
mkdir -p "$before_dir" "$after_dir" "$before_normalized_dir" "$after_normalized_dir"

# Capture the Stable9-backed baseline before mutating the fixture theme.
bash "${GITHUB_WORKSPACE:-$(pwd)}/.github/scripts/render-reference-pages.sh" "$fixture_dir" "$before_dir"

# Drupal 11 requires an explicit base theme key, so replace stable9 with false
# instead of deleting the setting outright.
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
  # Theme discovery caches the base theme setting, so rebuild before capturing
  # the no-Stable9 output.
  ./vendor/bin/drush cr -y
)

# Capture the same pages after the base theme change, normalize expected
# request/runtime noise, and diff the normalized trees.
bash "${GITHUB_WORKSPACE:-$(pwd)}/.github/scripts/render-reference-pages.sh" "$fixture_dir" "$after_dir"

for file in "$before_dir"/*.html; do
  filename="$(basename "$file")"
  # Normalize file pairs under the same basename so diff -ru reports a concise
  # per-page mismatch if markup parity drifts.
  php "$normalizer" "$file" "${before_normalized_dir}/${filename}"
  php "$normalizer" "${after_dir}/${filename}" "${after_normalized_dir}/${filename}"
done

# A non-zero diff means removing Stable9 changed rendered markup materially.
diff -ru "$before_normalized_dir" "$after_normalized_dir"
