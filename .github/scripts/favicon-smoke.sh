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
  ./vendor/bin/drush php:script "${script_dir}/favicon-smoke.php" -- "$theme_name"
)
