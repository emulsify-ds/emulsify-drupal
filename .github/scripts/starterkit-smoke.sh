#!/usr/bin/env bash

set -euo pipefail

# Generates a child theme from the Whisk starter source and proves the result is
# installable, renderable, and compatible with the Vite-based build workflow
# powered by Emulsify Core 4.
if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <output-dir> [all|generate|enable|render|frontend-install|frontend-build|frontend-test|frontend-a11y|storybook-build]" >&2
  exit 1
fi

fixture_dir="$1"
output_dir="$2"
phase="${3:-all}"
generated_theme="${EMULSIFY_STARTERKIT_THEME:-emulsify_fixture}"
generated_theme_dir="${fixture_dir}/web/themes/custom/${generated_theme}"
generated_theme_info="${generated_theme_dir}/${generated_theme}.info.yml"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
artifact_info="${output_dir}/generated-theme-info.yml"
npm_install_log="${output_dir}/npm-install.log"
npm_build_log="${output_dir}/npm-build.log"
npm_test_log="${output_dir}/npm-test.log"
npm_a11y_log="${output_dir}/npm-a11y.log"
storybook_build_log="${output_dir}/storybook-build.log"

mkdir -p "$output_dir"
[ -f "$npm_install_log" ] || printf 'npm install has not run yet.\n' >"$npm_install_log"
[ -f "$npm_build_log" ] || printf 'npm run build has not run yet.\n' >"$npm_build_log"
[ -f "$npm_test_log" ] || printf 'npm run test has not run yet.\n' >"$npm_test_log"
[ -f "$npm_a11y_log" ] || printf 'npm run a11y has not run yet.\n' >"$npm_a11y_log"
[ -f "$storybook_build_log" ] || printf 'npm run storybook-build has not run.\n' >"$storybook_build_log"

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

  echo "Running: $*"
  set +e
  "$@" 2>&1 | tee "$log_file"
  local status="${PIPESTATUS[0]}"
  set -e

  if [ "$status" -ne 0 ]; then
    echo "Command failed: $*" >&2
    show_log_tail "$log_file"
    exit "$status"
  fi
}

require_generated_theme() {
  if [ ! -f "$generated_theme_info" ]; then
    fail "Generated theme is missing at ${generated_theme_dir}. Run the generate phase first."
  fi
}

assert_missing_file() {
  local relative_path="$1"

  if [ -e "${generated_theme_dir}/${relative_path}" ]; then
    fail "Starterkit output should not include ${relative_path}."
  fi
}

assert_existing_file() {
  local relative_path="$1"

  if [ ! -f "${generated_theme_dir}/${relative_path}" ]; then
    fail "Starterkit output should include ${relative_path}."
  fi
}

assert_missing_glob() {
  local glob_pattern="$1"

  if compgen -G "${generated_theme_dir}/${glob_pattern}" >/dev/null; then
    fail "Starterkit output should not include files matching ${glob_pattern}."
  fi
}

generate_theme() {
  (
    cd "$fixture_dir"
    rm -rf "$generated_theme_dir"
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

  assert_existing_file "project.emulsify.json"
  assert_missing_file "whisk.starterkit.yml"
  assert_missing_file "whisk.info.emulsify.yml"
  assert_missing_file "${generated_theme}.starterkit.yml"
  assert_missing_file "${generated_theme}.info.emulsify.yml"
  assert_missing_glob "*.starterkit.yml"
  assert_missing_glob "*.info.emulsify.yml"

  if ! grep -q '"platform": "drupal"' "${generated_theme_dir}/project.emulsify.json"; then
    fail "Generated theme project.emulsify.json must preserve the Drupal platform adapter."
  fi

  if ! grep -q '"singleDirectoryComponents": true' "${generated_theme_dir}/project.emulsify.json"; then
    fail "Generated theme project.emulsify.json must preserve SDC behavior."
  fi

  if ! grep -q "\"machineName\": \"${generated_theme}\"" "${generated_theme_dir}/project.emulsify.json"; then
    fail "Generated theme project.emulsify.json must use the generated theme machine name."
  fi
}

enable_theme() {
  require_generated_theme
  (
    cd "$fixture_dir"
    ./vendor/bin/drush theme:enable "$generated_theme" -y
    ./vendor/bin/drush config:set system.theme default "$generated_theme" -y
    ./vendor/bin/drush cr -y
  )
}

render_theme() {
  require_generated_theme
  # Render representative pages through the generated theme. This catches
  # missing libraries, broken parent-theme inheritance, and template issues that
  # pure file assertions cannot see.
  bash "${script_dir}/render-reference-pages.sh" "$fixture_dir" "$output_dir"
}

install_frontend() {
  require_generated_theme
  (
    cd "$generated_theme_dir"
    install_args=(install --no-audit --no-fund)
    if [ -f package-lock.json ]; then
      install_args=(ci --no-audit --no-fund)
    fi

    run_logged "$npm_install_log" npm "${install_args[@]}"
  )
}

build_frontend() {
  require_generated_theme
  (
    cd "$generated_theme_dir"
    run_logged "$npm_build_log" npm run build
  )
}

test_frontend() {
  require_generated_theme
  (
    cd "$generated_theme_dir"
    run_logged "$npm_test_log" npm run test
  )
}

check_accessibility() {
  require_generated_theme
  (
    cd "$generated_theme_dir"
    run_logged "$npm_a11y_log" npm run a11y
  )
}

build_storybook() {
  require_generated_theme
  (
    cd "$generated_theme_dir"
    run_logged "$storybook_build_log" npm run storybook-build
  )
}

case "$phase" in
  generate)
    generate_theme
    ;;
  enable)
    enable_theme
    ;;
  render)
    render_theme
    ;;
  frontend-install)
    install_frontend
    ;;
  frontend-build)
    build_frontend
    ;;
  frontend-test)
    test_frontend
    ;;
  frontend-a11y)
    check_accessibility
    ;;
  storybook-build)
    build_storybook
    ;;
  all)
    generate_theme
    enable_theme
    render_theme
    install_frontend
    build_frontend
    if [ "${EMULSIFY_STARTERKIT_TEST:-0}" = "1" ]; then
      test_frontend
    fi
    if [ "${EMULSIFY_STARTERKIT_STORYBOOK_BUILD:-0}" = "1" ]; then
      build_storybook
    fi
    if [ "${EMULSIFY_STARTERKIT_A11Y:-0}" = "1" ]; then
      check_accessibility
    fi
    ;;
  *)
    echo "Unknown starterkit smoke phase: ${phase}" >&2
    echo "Usage: $0 <fixture-dir> <output-dir> [all|generate|enable|render|frontend-install|frontend-build|frontend-test|frontend-a11y|storybook-build]" >&2
    exit 1
    ;;
esac
