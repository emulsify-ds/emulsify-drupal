#!/usr/bin/env bash

set -euo pipefail

# Running through Drush gives the PHP helpers a fully bootstrapped Drupal
# container while keeping the CI workflow shell-readable.
if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <fixture-dir> [theme-name]" >&2
  exit 1
fi

fixture_dir="$1"
theme_name="${2:-emulsify}"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

php "$script_dir/favicon-resource-limits.test.php"
node --test "$script_dir/favicon-preview.test.cjs"

(
  cd "$fixture_dir"
  EMULSIFY_FAVICON_THEME="$theme_name" \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-smoke.php';"
  ./vendor/bin/drush php:script "$script_dir/favicon-preview-smoke.php"

  # Clear package metadata and configure a portable source so the published
  # Tools commands must generate and persist a complete favicon package.
  EMULSIFY_FAVICON_THEME="$theme_name" EMULSIFY_FAVICON_MODE=prepare \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-portability-smoke.php';"
  ./vendor/bin/drush emulsify_tools:favicon-generate "$theme_name"
  ./vendor/bin/drush emulsify_tools:favicon-status "$theme_name"
  EMULSIFY_FAVICON_THEME="$theme_name" EMULSIFY_FAVICON_MODE=assert-generated \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-portability-smoke.php';"
)
