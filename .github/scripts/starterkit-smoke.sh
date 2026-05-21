#!/usr/bin/env bash

set -euo pipefail

# Generates a child theme from the Whisk starter source and proves the result is
# installable. This is intentionally a Drupal Starterkit smoke test, not a
# frontend build test; it guards the PHP/theme metadata contract that release
# consumers hit before Node tooling runs.
if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <output-dir>" >&2
  exit 1
fi

fixture_dir="$1"
output_dir="$2"
generated_theme="emulsify_fixture"
generated_theme_dir="${fixture_dir}/web/themes/custom/${generated_theme}"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

mkdir -p "$output_dir"

(
  cd "$fixture_dir"
  # Use core's own generator so this test tracks Drupal Starterkit behavior
  # directly instead of the Emulsify Tools Drush wrapper.
  php web/core/scripts/drupal generate-theme "$generated_theme" --starterkit whisk --path themes/custom -n
)

test -f "${generated_theme_dir}/${generated_theme}.info.yml"
grep -Eq "^('?base theme'?): emulsify$" "${generated_theme_dir}/${generated_theme}.info.yml"

# Generated themes must be visible/installable and should not carry the source
# theme's private project config marker into consumer projects.
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

# Finish by rendering representative pages through the generated theme. This
# catches missing libraries, broken base-theme inheritance, and template issues
# that pure file assertions cannot see.
bash "${script_dir}/render-reference-pages.sh" "$fixture_dir" "$output_dir"
