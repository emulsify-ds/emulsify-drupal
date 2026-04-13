#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <output-dir>" >&2
  exit 1
fi

fixture_dir="$1"
output_dir="$2"
generated_theme="emulsify_fixture"
generated_theme_dir="${fixture_dir}/web/themes/custom/${generated_theme}"

mkdir -p "$output_dir"

(
  cd "$fixture_dir"
  php web/core/scripts/drupal generate-theme "$generated_theme" --starterkit whisk --path web/themes/custom
)

test -f "${generated_theme_dir}/${generated_theme}.info.yml"
grep -q '^base theme: emulsify$' "${generated_theme_dir}/${generated_theme}.info.yml"

if grep -q '^hidden: true$' "${generated_theme_dir}/${generated_theme}.info.yml"; then
  echo "Generated theme should not remain hidden." >&2
  exit 1
fi

if [ -e "${generated_theme_dir}/project.emulsify.json" ]; then
  echo "Starterkit output should not include project.emulsify.json." >&2
  exit 1
fi

(
  cd "$fixture_dir"
  ./vendor/bin/drush theme:enable "$generated_theme" -y
  ./vendor/bin/drush config:set system.theme default "$generated_theme" -y
  ./vendor/bin/drush cr -y
)

bash "${GITHUB_WORKSPACE:-$(pwd)}/.github/scripts/render-reference-pages.sh" "$fixture_dir" "$output_dir"
