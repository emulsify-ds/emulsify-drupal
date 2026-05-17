#!/usr/bin/env bash

set -euo pipefail

# Generates a theme from the Whisk starterkit, validates the generated metadata,
# enables it, and renders fixture pages to prove the output is installable.
if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <output-dir>" >&2
  exit 1
fi

fixture_dir="$1"
output_dir="$2"
generated_theme="emulsify_fixture"
generated_theme_dir="${fixture_dir}/web/themes/custom/${generated_theme}"

mkdir -p "$output_dir"

# Run Drupal core's Starterkit generator against the Whisk starterkit shipped in
# this checkout.
(
  cd "$fixture_dir"
  php web/core/scripts/drupal generate-theme "$generated_theme" --starterkit whisk --path themes/custom -n
)

# The generated theme must extend Emulsify and be visible to Drupal immediately.
test -f "${generated_theme_dir}/${generated_theme}.info.yml"
grep -Eq "^('?base theme'?): emulsify$" "${generated_theme_dir}/${generated_theme}.info.yml"

if grep -q '^hidden: true$' "${generated_theme_dir}/${generated_theme}.info.yml"; then
  echo "Generated theme should not remain hidden." >&2
  exit 1
fi

# project.emulsify.json is source metadata and should not leak into generated
# site themes.
if [ -e "${generated_theme_dir}/project.emulsify.json" ]; then
  echo "Starterkit output should not include project.emulsify.json." >&2
  exit 1
fi

# Enable the generated theme as the default theme before rendering through it.
(
  cd "$fixture_dir"
  ./vendor/bin/drush theme:enable "$generated_theme" -y
  ./vendor/bin/drush config:set system.theme default "$generated_theme" -y
  ./vendor/bin/drush cr -y
)

# Rendering the same fixture pages catches generated-theme bootstrap failures.
bash "${GITHUB_WORKSPACE:-$(pwd)}/.github/scripts/render-reference-pages.sh" "$fixture_dir" "$output_dir"
