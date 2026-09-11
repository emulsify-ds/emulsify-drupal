#!/usr/bin/env bash

set -euo pipefail

# Generates a child theme from the Whisk starter source and proves the result is
# installable, renderable, and compatible with the Vite-based build workflow
# powered by Emulsify Core 4.
if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <output-dir> [all|generate|enable|render|frontend-install|frontend-inspect|frontend-build|frontend-test|frontend-a11y|storybook-build]" >&2
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
component_inspector_report="${output_dir}/component-inspector.json"
component_inspector_log="${output_dir}/component-inspector.log"

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
  local core_theme_dir="${scenario_output_dir}/core-generated-theme"
  local core_generation_log="${scenario_output_dir}/core-generation.log"
  local drush_generation_log="${scenario_output_dir}/drush-generation.log"
  local status=0

  echo "[generation] Generating ${display_name} (${machine_name}) with Drupal core"
  rm -rf "$theme_dir" "$core_theme_dir"
  mkdir -p "$scenario_output_dir"
  set +e
  (
    cd "$fixture_dir"
    # Drupal 11.4+ exposes the experimental Composer proxy, which supplies the
    # autoloader path needed by this recommended-project fixture.
    local core_command=(php web/core/scripts/drupal)
    if [ -x vendor/bin/dr ]; then
      core_command=(vendor/bin/dr)
    fi
    "${core_command[@]}" generate-theme "$machine_name" \
      --name "$display_name" \
      --description "$description" \
      --starterkit whisk \
      --path themes/custom \
      -n
  ) 2>&1 | tee "$core_generation_log"
  status="${PIPESTATUS[0]}"
  set -e

  if [ "$status" -eq 0 ]; then
    if validate_generated_theme "$machine_name" "$display_name" "$description"; then
      mv "$theme_dir" "$core_theme_dir"
    else
      status="$?"
    fi
  fi

  if [ "$status" -eq 0 ]; then
    echo "[generation] Generating ${display_name} (${machine_name}) with Drush"
    set +e
    (
      cd "$fixture_dir"
      ./vendor/bin/drush emulsify "$machine_name" \
        --name "$display_name" \
        --description "$description"
    ) 2>&1 | tee "$drush_generation_log"
    status="${PIPESTATUS[0]}"
    set -e
  fi

  if [ "$status" -eq 0 ]; then
    if validate_generated_theme "$machine_name" "$display_name" "$description"; then
      if ! diff -qr "$core_theme_dir" "$theme_dir"; then
        echo "FAIL [generation] Drupal core and Drush generated different child themes for ${machine_name}." >&2
        status=1
      fi
    else
      status="$?"
    fi
  fi

  if [ "$status" -eq 0 ] && [ -f "$info_file" ]; then
    cp "$info_file" "${scenario_output_dir}/generated-theme-info.yml"
  fi

  rm -rf "$core_theme_dir"
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
  [ ! -e "${generated_theme_dir}/templates/layout/page.html.twig" ] || fail "A fresh generated child theme must inherit the parent's page template."
  (
    cd "$fixture_dir"
    ./vendor/bin/drush php:eval '
$registry = \Drupal::service("theme.registry")->get();
$parent_path = \Drupal::service("extension.list.theme")->getPath("emulsify") . "/templates/layout";
if (realpath(DRUPAL_ROOT . "/" . $registry["page"]["path"]) !== realpath(DRUPAL_ROOT . "/" . $parent_path)) {
  throw new \RuntimeException("The generated child must resolve the page template from Emulsify.");
}
echo "PASS generated child inherits the parent page template.\n";
'
  )
  # Render representative pages through the generated theme. This catches
  # missing libraries, broken parent-theme inheritance, and template issues that
  # pure file assertions cannot see.
  bash "${script_dir}/render-reference-pages.sh" "$fixture_dir" "$output_dir"
  grep -Fq '<div class="section page">' "${output_dir}/frontpage-view.html" || fail "Missing parent page wrapper in the generated child render."
  grep -Fq '<main class="section main" role="main">' "${output_dir}/frontpage-view.html" || fail "Missing parent main wrapper in the generated child render."
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
    node -p '"Installed @emulsify/core " + require("./node_modules/@emulsify/core/package.json").version'
  )
}

inspect_components() {
  require_generated_theme
  (
    cd "$generated_theme_dir"
    echo "[component inspector] Running: npm run inspect:components -- --json"
    set +e
    npm_config_loglevel=silent npm run inspect:components -- --json \
      >"$component_inspector_report" 2>"$component_inspector_log"
    local status="$?"
    set -e

    if [ "$status" -ne 0 ]; then
      echo "FAIL [component inspector] Command failed with status ${status}." >&2
      show_log_tail "$component_inspector_log"
      exit "$status"
    fi

    cat "$component_inspector_report"
    if ! node -e '
      const fs = require("fs");
      const report = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
      const isObject = (value) => value !== null && typeof value === "object" && !Array.isArray(value);
      if (!isObject(report)
        || !isObject(report.project)
        || !isObject(report.project.namespaceRoots)
        || typeof report.project.platform !== "string"
        || typeof report.project.singleDirectoryComponents !== "boolean"
        || !Array.isArray(report.components)) {
        throw new Error("Expected component inspector JSON with a project object and components array.");
      }
    ' "$component_inspector_report"; then
      fail "Component inspector did not produce a valid JSON report."
    fi

    echo "PASS [component inspector] Valid JSON report with a components array."
  )
}

prepare_frontend_fixture() {
  # Whisk intentionally ships no component library. Add a real project-owned
  # Twig component, story, and styles only inside this disposable consumer.
  local fixture_component="${generated_theme_dir}/components/emulsify-smoke"
  mkdir -p "$fixture_component"
  cp "${script_dir}/../fixtures/consumer-component/"* "$fixture_component/"
}

build_frontend() {
  require_generated_theme
  prepare_frontend_fixture
  (
    cd "$generated_theme_dir"
    run_logged "build" "$npm_build_log" npm run build
  )
  validate_generated_theme "$generated_theme" "$generated_theme_name" "$generated_theme_description" built
}

test_frontend() {
  require_generated_theme
  prepare_frontend_fixture
  (
    cd "$generated_theme_dir"
    run_logged "frontend tests" "$npm_test_log" npm run test
    run_logged "project test discovery" "${output_dir}/jest-project-smoke.log" \
      node "${script_dir}/jest-project-smoke.cjs" "$generated_theme_dir"
  )
}

check_accessibility() {
  require_generated_theme
  prepare_frontend_fixture
  local status=0
  # The existing consumer command builds and audits Storybook. Also run the
  # browser audit after a failure so artifacts retain rule IDs and Drupal data.
  (
    cd "$generated_theme_dir"
    if [ -n "${PUPPETEER_EXECUTABLE_PATH:-}" ]; then
      # Both the published Core audit and rendered audit honor this Puppeteer
      # setting. CI selects runner Chrome, whose Ubuntu sandbox is configured.
      [ -x "$PUPPETEER_EXECUTABLE_PATH" ] || fail "Configured Puppeteer browser is not executable: ${PUPPETEER_EXECUTABLE_PATH}"
      run_logged "browser selection" "${output_dir}/browser-install.log" \
        "$PUPPETEER_EXECUTABLE_PATH" --version
    else
      # npm installations may defer Puppeteer's postinstall script. Reuse the
      # browser cache and download Chrome only when no executable is supplied.
      run_logged "browser install" "${output_dir}/browser-install.log" \
        npx --no-install puppeteer browsers install chrome
    fi
    run_logged "accessibility" "$npm_a11y_log" npm run a11y
  ) || status=$?
  (
    run_logged "rendered WCAG 2.2 AA" "${output_dir}/rendered-a11y.log" \
      node "${script_dir}/rendered-a11y.cjs" "$generated_theme_dir" "$fixture_dir" "$output_dir"
  ) || status=$?
  return "$status"
}

build_storybook() {
  require_generated_theme
  prepare_frontend_fixture
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
  frontend-inspect)
    inspect_components
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
    inspect_components
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
    echo "Usage: $0 <fixture-dir> <output-dir> [all|generate|enable|render|frontend-install|frontend-inspect|frontend-build|frontend-test|frontend-a11y|storybook-build]" >&2
    exit 1
    ;;
esac
