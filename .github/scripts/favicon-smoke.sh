#!/usr/bin/env bash

set -euo pipefail

# Thin wrapper around favicon-smoke.php. Running through Drush gives the PHP
# script a fully bootstrapped Drupal container while keeping the CI workflow
# shell-readable.
if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <fixture-dir> [theme-name]" >&2
  exit 1
fi

fixture_dir="$1"
theme_name="${2:-emulsify}"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

(
  cd "$fixture_dir"
  EMULSIFY_FAVICON_THEME="$theme_name" \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-smoke.php';"
)
