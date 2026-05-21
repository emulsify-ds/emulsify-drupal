#!/usr/bin/env bash

set -euo pipefail

# Compares Emulsify's parent-owned baseline templates with the installed
# Stable9/core source templates in a fixture site. The only expected upstream
# differences across Drupal 10.3 and 11.3 are non-rendering Twig comments
# such as docblocks and @see references, so comments are stripped before diffing
# to keep this check focused on rendered markup parity.
if [ "$#" -ne 1 ]; then
  echo "Usage: $0 <fixture-dir>" >&2
  exit 1
fi

fixture_dir="$1"
web_root="${fixture_dir}/web"
theme_root="${web_root}/themes/contrib/emulsify"

# Store normalized upstream and Emulsify templates outside the fixture so the
# comparison never mutates installed Drupal or theme files.
work_dir="$(mktemp -d)"

cleanup() {
  rm -rf "$work_dir"
}

trap cleanup EXIT

normalize_template() {
  local source="$1"
  local output="$2"

  # Twig comments differ across Drupal minors and do not affect rendered markup.
  # Strip them before diffing so the check protects output, not copied docblocks.
  php -r '
$source = $argv[1];
$output = $argv[2];

$template = file_get_contents($source);
if ($template === false) {
  fwrite(STDERR, "Unable to read {$source}\n");
  exit(1);
}

$template = preg_replace("/\{#.*?#\}/s", "", $template);
if ($template === null) {
  fwrite(STDERR, "Unable to normalize Twig comments in {$source}\n");
  exit(1);
}

file_put_contents($output, $template);
' "$source" "$output"
}

compare_template() {
  local label="$1"
  local upstream="$2"
  local emulsify="$3"

  # Use label-specific filenames so a failed run leaves readable diff paths in
  # the error output.
  local expected="${work_dir}/${label}.expected.twig"
  local actual="${work_dir}/${label}.actual.twig"

  if [ ! -f "$upstream" ]; then
    echo "Missing upstream template for ${label}: ${upstream}" >&2
    exit 1
  fi

  if [ ! -f "$emulsify" ]; then
    echo "Missing Emulsify template for ${label}: ${emulsify}" >&2
    exit 1
  fi

  normalize_template "$upstream" "$expected"
  normalize_template "$emulsify" "$actual"

  # diff -u keeps failures reviewable in GitHub Actions logs without requiring
  # artifact downloads.
  if ! diff_output="$(diff -u "$expected" "$actual")"; then
    echo "Template parity mismatch for ${label}." >&2
    echo "Upstream: ${upstream}" >&2
    echo "Emulsify: ${emulsify}" >&2
    printf '%s\n' "$diff_output" >&2
    exit 1
  fi
}

# Stable9 owns these layout/field/form templates in the supported 6.x runtime,
# so Emulsify copies must stay render-equivalent while Stable9 remains fallback.
compare_template \
  "field" \
  "${web_root}/core/themes/stable9/templates/field/field.html.twig" \
  "${theme_root}/templates/field/field.html.twig"

compare_template \
  "html" \
  "${web_root}/core/themes/stable9/templates/layout/html.html.twig" \
  "${theme_root}/templates/layout/html.html.twig"

compare_template \
  "page" \
  "${web_root}/core/themes/stable9/templates/layout/page.html.twig" \
  "${theme_root}/templates/layout/page.html.twig"

compare_template \
  "form" \
  "${web_root}/core/themes/stable9/templates/form/form.html.twig" \
  "${theme_root}/templates/form/form.html.twig"

compare_template \
  "status-messages" \
  "${web_root}/core/modules/system/templates/status-messages.html.twig" \
  "${theme_root}/templates/misc/status-messages.html.twig"
