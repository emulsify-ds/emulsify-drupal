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
generated_theme="${EMULSIFY_STARTERKIT_THEME:-example_theme}"
generated_theme_name="${EMULSIFY_STARTERKIT_NAME:-Example Theme}"
generated_theme_description="${EMULSIFY_STARTERKIT_DESCRIPTION:-Release check: generated child-theme metadata, paths & build output.}"
if [[ ! "$generated_theme" =~ ^[a-z][a-z0-9_]*$ ]]; then
  echo "EMULSIFY_STARTERKIT_THEME must be a valid Drupal machine name: ${generated_theme}" >&2
  exit 1
fi
generated_theme_dir="${fixture_dir}/web/themes/custom/${generated_theme}"
generated_theme_info="${generated_theme_dir}/${generated_theme}.info.yml"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/../.." && pwd)"
source_theme_dir="${repo_root}/whisk"
validator="${script_dir}/generated-theme-contract.cjs"
artifact_info="${output_dir}/generated-theme-info.yml"
npm_install_log="${output_dir}/npm-install.log"
npm_build_log="${output_dir}/npm-build.log"
npm_test_log="${output_dir}/npm-test.log"
npm_a11y_log="${output_dir}/npm-a11y.log"
storybook_build_log="${output_dir}/storybook-build.log"

mkdir -p "$output_dir"

for temporary_path in "$fixture_dir" "$output_dir"; do
  resolved_path="$(cd "$temporary_path" && pwd -P)"
  if [[ "$resolved_path" == "/" || "$resolved_path" == "$repo_root" || "$resolved_path" == "$repo_root"/* ]]; then
    echo "Generated child theme smoke paths must be temporary directories outside the repository: ${resolved_path}" >&2
    exit 1
  fi
done

if [ ! -f "${fixture_dir}/web/core/scripts/drupal" ]; then
  echo "Generated child theme smoke fixture is missing web/core/scripts/drupal: ${fixture_dir}" >&2
  exit 1
fi

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
  local section="$1"
  local log_file="$2"
  shift 2

  echo "[${section}] Running: $*"
  set +e
  "$@" 2>&1 | tee "$log_file"
  local status="${PIPESTATUS[0]}"
  set -e

  if [ "$status" -ne 0 ]; then
    echo "FAIL [${section}] Command failed: $*" >&2
    show_log_tail "$log_file"
    exit "$status"
  fi

  echo "PASS [${section}] $*"
}

require_generated_theme() {
  if [ ! -f "$generated_theme_info" ]; then
    fail "Generated theme is missing at ${generated_theme_dir}. Run the generate phase first."
  fi
}

validate_generated_theme() {
  local machine_name="$1"
  local display_name="$2"
  local description="$3"
  local validation_phase="${4:-generated}"
  local scenario_output_dir="${output_dir}/scenarios/${machine_name}"
  local validation_log="${scenario_output_dir}/contract-${validation_phase}.log"
  local validator_command=(node "$validator")

  if [ "$validation_phase" = "built" ]; then
    validator_command+=(--check-built-assets)
  fi

  validator_command+=(
    "${fixture_dir}/web/themes/custom/${machine_name}"
    "$machine_name"
    "$display_name"
    "$description"
    "$source_theme_dir"
  )

  mkdir -p "$scenario_output_dir"
  set +e
  "${validator_command[@]}" 2>&1 | tee "$validation_log"
  local status="${PIPESTATUS[0]}"
  set -e

  return "$status"
}

generate_scenario() {
  local machine_name="$1"
  local display_name="$2"
  local description="$3"
  local keep_theme="$4"
  local theme_dir="${fixture_dir}/web/themes/custom/${machine_name}"
  local info_file="${theme_dir}/${machine_name}.info.yml"
  local scenario_output_dir="${output_dir}/scenarios/${machine_name}"
  local generation_log="${scenario_output_dir}/generation.log"
  local status=0

  echo "[generation] Generating ${display_name} (${machine_name})"
  rm -rf "$theme_dir"
  mkdir -p "$scenario_output_dir"
  set +e
  (
    cd "$fixture_dir"
    # Use core's own generator so this test tracks Drupal Starterkit behavior
    # directly instead of the Emulsify Tools Drush wrapper.
    php web/core/scripts/drupal generate-theme "$machine_name" \
      --name "$display_name" \
      --description "$description" \
      --starterkit whisk \
      --path themes/custom \
      -n
  ) 2>&1 | tee "$generation_log"
  status="${PIPESTATUS[0]}"
  set -e

  if [ "$status" -eq 0 ]; then
    if [ -f "$info_file" ]; then
      cp "$info_file" "${scenario_output_dir}/generated-theme-info.yml"
    fi
    if validate_generated_theme "$machine_name" "$display_name" "$description"; then
      :
    else
      status="$?"
    fi
  fi

  if [ "$keep_theme" != "1" ]; then
    rm -rf "$theme_dir"
  fi

  if [ "$status" -ne 0 ]; then
    echo "FAIL [generation] Generated child theme ${machine_name} failed with status ${status}." >&2
    return "$status"
  fi

  echo "PASS [generation] Generated child theme ${machine_name} (${display_name})"
}

generate_theme() {
  generate_scenario "$generated_theme" "$generated_theme_name" "$generated_theme_description" 1

  if [ "$generated_theme" != "example_theme" ]; then
    generate_scenario \
      "example_theme" \
      "Example Theme" \
      "Example release check: punctuation, paths & metadata." \
      0
  fi

  if [ "$generated_theme" != "civic_portal" ]; then
    generate_scenario \
      "civic_portal" \
      "Civic Portal Theme" \
      "Civic portal release check: spaces, punctuation & a second identity." \
      0
  fi

  cp "$generated_theme_info" "$artifact_info"
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

    run_logged "frontend install" "$npm_install_log" npm "${install_args[@]}"
  )
}

build_frontend() {
  require_generated_theme
  (
    cd "$generated_theme_dir"
    run_logged "build" "$npm_build_log" npm run build
  )
  validate_generated_theme "$generated_theme" "$generated_theme_name" "$generated_theme_description" built
}

test_frontend() {
  require_generated_theme
  (
    cd "$generated_theme_dir"
    run_logged "frontend tests" "$npm_test_log" npm run test
  )
}

check_accessibility() {
  require_generated_theme
  (
    cd "$generated_theme_dir"
    run_logged "accessibility" "$npm_a11y_log" npm run a11y
  )
}

build_storybook() {
  require_generated_theme
  (
    cd "$generated_theme_dir"
    run_logged "Storybook" "$storybook_build_log" npm run storybook-build
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
