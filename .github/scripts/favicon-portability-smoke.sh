#!/usr/bin/env bash

set -euo pipefail

# Exercises the favicon lifecycle across separate Drush invocations. Keeping
# each mode isolated mirrors deploy-time behavior where config import, package
# regeneration, and reset commands do not share in-memory PHP state.
if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <fixture-dir> [theme-name]" >&2
  exit 1
fi

fixture_dir="$1"
theme_name="${2:-emulsify}"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

run_mode() {
  local mode="$1"

  EMULSIFY_FAVICON_THEME="$theme_name" \
    EMULSIFY_FAVICON_MODE="$mode" \
    ./vendor/bin/drush php:eval "require '${script_dir}/favicon-portability-smoke.php';"
}

(
  cd "$fixture_dir"

  # The second generate/assert pair proves that a config-backed portable SVG can
  # recreate missing package files after deploy or cache/filesystem cleanup.
  for mode in prepare generate assert-generated delete-package generate assert-generated reset assert-reset; do
    run_mode "$mode"
  done
)
