#!/usr/bin/env bash

set -euo pipefail

# Verifies Emulsify owns the same template path surface that stable9 provides
# without inheriting stable9 as a base theme. This is a path-contract check:
# templates may intentionally differ in content, but missing stable9 paths would
# reintroduce hidden fallback behavior in Drupal 11/12 readiness testing.
if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <repo-root> [report-file]" >&2
  exit 1
fi

fixture_dir="$1"
repo_root="$2"
report_file="${3:-}"
stable9_templates_dir="${fixture_dir}/web/core/themes/stable9/templates"
repo_templates_dir="${repo_root}/templates"
stable9_list="$(mktemp)"
repo_list="$(mktemp)"
exact_matches_list="$(mktemp)"
modified_matches_list="$(mktemp)"
emulsify_only_list="$(mktemp)"

cleanup() {
  rm -f "$stable9_list" "$repo_list" "$exact_matches_list" "$modified_matches_list" "$emulsify_only_list"
}

trap cleanup EXIT

if [ ! -d "$stable9_templates_dir" ]; then
  echo "Unable to locate stable9 templates in the fixture." >&2
  exit 1
fi

if [ ! -d "$repo_templates_dir" ]; then
  echo "Unable to locate the repo templates directory." >&2
  exit 1
fi

if grep -Eq "^base theme: stable9$" "${repo_root}/emulsify.info.yml"; then
  echo "emulsify.info.yml should not declare stable9 as a base theme." >&2
  exit 1
fi

# Build sorted relative path lists before comparison so the comm-based missing
# path check is deterministic across local macOS and Linux CI filesystems.
(
  cd "$stable9_templates_dir"
  find . -type f -name '*.html.twig' | sort
) >"$stable9_list"

(
  cd "$repo_templates_dir"
  find . -type f -name '*.html.twig' | sort
) >"$repo_list"

missing_templates="$(comm -23 "$stable9_list" "$repo_list" || true)"

if [ -n "$missing_templates" ]; then
  echo "Emulsify is missing stable9 template paths:" >&2
  echo "$missing_templates" >&2
  exit 1
fi

while IFS= read -r relative_path; do
  stable9_path="${stable9_templates_dir}/${relative_path#./}"
  repo_path="${repo_templates_dir}/${relative_path#./}"

  # The report separates exact copies from intentional overrides. Both are
  # acceptable for parity; only a missing stable9 path fails the test.
  if [ ! -f "$stable9_path" ]; then
    echo "$relative_path" >>"$emulsify_only_list"
  elif cmp -s "$stable9_path" "$repo_path"; then
    echo "$relative_path" >>"$exact_matches_list"
  else
    echo "$relative_path" >>"$modified_matches_list"
  fi
done <"$repo_list"

if [ -n "$report_file" ]; then
  mkdir -p "$(dirname "$report_file")"
  {
    echo "# Stable9 template parity report"
    echo
    echo "- Stable9 template paths mirrored: $(wc -l <"$stable9_list" | tr -d ' ')"
    echo "- Exact baseline copies: $(wc -l <"$exact_matches_list" | tr -d ' ')"
    echo "- Modified relative to stable9: $(wc -l <"$modified_matches_list" | tr -d ' ')"
    echo "- Emulsify-only template paths: $(wc -l <"$emulsify_only_list" | tr -d ' ')"
    echo
    echo "## Exact baseline copies"
    echo
    if [ -s "$exact_matches_list" ]; then
      sed 's#^\./#- `#; s#$#`#' "$exact_matches_list"
    else
      echo "None."
    fi
    echo
    echo "## Modified relative to stable9"
    echo
    echo "These paths mirror the stable9 contract but intentionally differ in content."
    echo
    if [ -s "$modified_matches_list" ]; then
      sed 's#^\./#- `#; s#$#`#' "$modified_matches_list"
    else
      echo "None."
    fi
    echo
    echo "## Emulsify-only template paths"
    echo
    echo "These paths are outside the stable9 parity contract."
    echo
    if [ -s "$emulsify_only_list" ]; then
      sed 's#^\./#- `#; s#$#`#' "$emulsify_only_list"
    else
      echo "None."
    fi
  } >"$report_file"
fi

echo "Emulsify includes every stable9 template path and no longer depends on stable9."
