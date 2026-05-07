#!/usr/bin/env bash

set -euo pipefail

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
    EMULSIFY_FAVICON_MODE=prepare \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-portability-smoke.php';"

  EMULSIFY_FAVICON_THEME="$theme_name" \
    EMULSIFY_FAVICON_MODE=generate \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-portability-smoke.php';"

  EMULSIFY_FAVICON_THEME="$theme_name" \
    EMULSIFY_FAVICON_MODE=assert-generated \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-portability-smoke.php';"

  EMULSIFY_FAVICON_THEME="$theme_name" \
    EMULSIFY_FAVICON_MODE=delete-package \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-portability-smoke.php';"

  EMULSIFY_FAVICON_THEME="$theme_name" \
    EMULSIFY_FAVICON_MODE=generate \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-portability-smoke.php';"

  EMULSIFY_FAVICON_THEME="$theme_name" \
    EMULSIFY_FAVICON_MODE=assert-generated \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-portability-smoke.php';"

  EMULSIFY_FAVICON_THEME="$theme_name" \
    EMULSIFY_FAVICON_MODE=reset \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-portability-smoke.php';"

  EMULSIFY_FAVICON_THEME="$theme_name" \
    EMULSIFY_FAVICON_MODE=assert-reset \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-portability-smoke.php';"
)
