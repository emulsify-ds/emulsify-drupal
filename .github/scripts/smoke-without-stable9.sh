#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <output-dir>" >&2
  exit 1
fi

fixture_dir="$1"
output_dir="$2"
theme_info_file="$(find "${fixture_dir}/web/themes/contrib" -maxdepth 2 -type f -name 'emulsify.info.yml' | head -n 1)"

if [ -z "$theme_info_file" ] || [ ! -f "$theme_info_file" ]; then
  echo "Unable to locate emulsify.info.yml in the fixture." >&2
  exit 1
fi

if grep -Eq "^base theme: stable9$" "$theme_info_file"; then
  echo "Emulsify should not declare stable9 as a base theme." >&2
  exit 1
fi

(
  cd "$fixture_dir"
  ./vendor/bin/drush cr -y
)

bash "${GITHUB_WORKSPACE:-$(pwd)}/.github/scripts/render-reference-pages.sh" "$fixture_dir" "$output_dir"
