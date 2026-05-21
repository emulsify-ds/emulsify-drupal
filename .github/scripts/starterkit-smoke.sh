#!/usr/bin/env bash

set -euo pipefail

# Generates a child theme from the Whisk starter source and proves the result is
# installable, renderable, and compatible with the Vite-based build workflow
# powered by Emulsify Core 4.
if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <output-dir>" >&2
  exit 1
fi

fixture_dir="$1"
output_dir="$2"
generated_theme="${EMULSIFY_STARTERKIT_THEME:-emulsify_fixture}"
generated_theme_dir="${fixture_dir}/web/themes/custom/${generated_theme}"
generated_theme_info="${generated_theme_dir}/${generated_theme}.info.yml"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
artifact_info="${output_dir}/generated-theme-info.yml"
npm_install_log="${output_dir}/npm-install.log"
npm_build_log="${output_dir}/npm-build.log"
storybook_build_log="${output_dir}/storybook-build.log"

mkdir -p "$output_dir"
printf 'npm install has not run yet.\n' >"$npm_install_log"
printf 'npm run build has not run yet.\n' >"$npm_build_log"
printf 'npm run storybook-build has not run.\n' >"$storybook_build_log"

fail() {
  echo "$1" >&2
  exit 1
}

show_log_tail() {
  local log_file="$1"

  if [ -f "$log_file" ]; then
    echo "--- ${log_file} tail ---" >&2
    tail -n 160 "$log_file" >&2 || true
  fi
}

run_logged() {
  local log_file="$1"
  shift

  if ! "$@" >"$log_file" 2>&1; then
    echo "Command failed: $*" >&2
    show_log_tail "$log_file"
    exit 1
  fi
}

assert_missing_file() {
  local relative_path="$1"

  if [ -e "${generated_theme_dir}/${relative_path}" ]; then
    fail "Starterkit output should not include ${relative_path}."
  fi
}

assert_missing_glob() {
  local glob_pattern="$1"

  if compgen -G "${generated_theme_dir}/${glob_pattern}" >/dev/null; then
    fail "Starterkit output should not include files matching ${glob_pattern}."
  fi
}

(
  cd "$fixture_dir"
  # Use core's own generator so this test tracks Drupal Starterkit behavior
  # directly instead of the Emulsify Tools Drush wrapper.
  php web/core/scripts/drupal generate-theme "$generated_theme" --starterkit whisk --path themes/custom -n
)

test -f "$generated_theme_info"
cp "$generated_theme_info" "$artifact_info"

if ! grep -Eq "^('?base theme'?): emulsify$" "$generated_theme_info"; then
  fail "Generated theme must use emulsify as its runtime parent theme."
fi

# Generated themes must be visible/installable and should not carry the source
# theme's private starter metadata into consumer projects.
if grep -Eq '^hidden:[[:space:]]*true[[:space:]]*$' "$generated_theme_info"; then
  fail "Generated theme should not remain hidden."
fi

assert_missing_file "project.emulsify.json"
assert_missing_file "whisk.starterkit.yml"
assert_missing_file "whisk.info.emulsify.yml"
assert_missing_file "${generated_theme}.starterkit.yml"
assert_missing_file "${generated_theme}.info.emulsify.yml"
assert_missing_glob "*.starterkit.yml"
assert_missing_glob "*.info.emulsify.yml"

(
  cd "$fixture_dir"
  ./vendor/bin/drush theme:enable "$generated_theme" -y
  ./vendor/bin/drush config:set system.theme default "$generated_theme" -y
  ./vendor/bin/drush cr -y
)

# Finish by rendering representative pages through the generated theme. This
# catches missing libraries, broken parent-theme inheritance, and template issues
# that pure file assertions cannot see.
bash "${script_dir}/render-reference-pages.sh" "$fixture_dir" "$output_dir"

(
  cd "$generated_theme_dir"
  install_args=(install --no-audit --no-fund)
  if [ -f package-lock.json ]; then
    install_args=(ci --no-audit --no-fund)
  fi

  run_logged "$npm_install_log" npm "${install_args[@]}"
  run_logged "$npm_build_log" npm run build

  if [ "${EMULSIFY_STARTERKIT_STORYBOOK_BUILD:-0}" = "1" ]; then
    run_logged "$storybook_build_log" npm run storybook-build
  fi
)
