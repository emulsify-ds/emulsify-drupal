#!/usr/bin/env bash

set -euo pipefail

# Verifies Emulsify covers the templates that stable9 provides without
# inheriting stable9 as a parent theme. Drupal discovers templates by basename,
# so directory relocations preserve coverage while missing basenames would
# reintroduce hidden fallback behavior in Drupal 11/12 readiness testing.
if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <repo-root> [report-file]" >&2
  exit 1
fi

fixture_dir="$(cd "$1" && pwd -P)"
repo_root="$(cd "$2" && pwd -P)"
report_file="${3:-}"
stable9_templates_dir="${fixture_dir}/web/core/themes/stable9/templates"
contrib_stable9_templates_dir="${fixture_dir}/web/themes/contrib/stable9/templates"
repo_templates_dir="${repo_root}/templates"
whisk_templates_dir="${repo_root}/whisk/templates"
composer_bin="${COMPOSER_BIN:-composer}"
stable9_source="Drupal core"
stable9_list="$(mktemp "${TMPDIR:-/tmp}/emulsify-stable9.XXXXXX")"
repo_list="$(mktemp "${TMPDIR:-/tmp}/emulsify-templates.XXXXXX")"
exact_matches_list="$(mktemp "${TMPDIR:-/tmp}/emulsify-exact.XXXXXX")"
modified_matches_list="$(mktemp "${TMPDIR:-/tmp}/emulsify-modified.XXXXXX")"
emulsify_only_list="$(mktemp "${TMPDIR:-/tmp}/emulsify-only.XXXXXX")"
composer_output="$(mktemp "${TMPDIR:-/tmp}/emulsify-composer.XXXXXX")"

cleanup() {
  rm -f "$stable9_list" "$repo_list" "$exact_matches_list" "$modified_matches_list" "$emulsify_only_list" "$composer_output"
}

trap cleanup EXIT

if [ ! -d "$repo_templates_dir" ]; then
  echo "Unable to locate the repo templates directory." >&2
  exit 1
fi

if grep -Eq "^base theme: stable9$" "${repo_root}/emulsify.info.yml"; then
  echo "emulsify.info.yml should not declare stable9 as its parent theme." >&2
  exit 1
fi

# Any templates shipped by the starter must match their parent counterparts.
# Generated child themes normally inherit these files instead of copying them.
if [ -d "$whisk_templates_dir" ]; then
  while IFS= read -r -d '' whisk_path; do
    relative_path="${whisk_path#"$whisk_templates_dir"/}"
    parent_path="${repo_templates_dir}/${relative_path}"
    if [ ! -f "$parent_path" ]; then
      echo "Whisk template ${relative_path} has no parent counterpart at templates/${relative_path}." >&2
      exit 1
    fi
    if ! cmp -s "$whisk_path" "$parent_path"; then
      echo "Whisk template ${relative_path} differs from its parent counterpart at templates/${relative_path}." >&2
      exit 1
    fi
  done < <(find "$whisk_templates_dir" -type f -print0)
fi

if [ ! -d "$stable9_templates_dir" ]; then
  stable9_templates_dir="$contrib_stable9_templates_dir"
  stable9_source="Contrib drupal/stable9"
fi
if [ ! -d "$stable9_templates_dir" ]; then
  # Only the disposable site may acquire this comparison baseline. Keep the
  # fixture's core/platform constraints intact while Composer finds a release.
  case "${fixture_dir}/" in
    "${repo_root}/"*)
      echo "Stable9 fallback installation requires a disposable fixture outside the source checkout." >&2
      exit 1
      ;;
  esac
  composer_status=0
  (
    cd "$fixture_dir" || exit 1
    "$composer_bin" require --dev --no-interaction --prefer-dist --with-all-dependencies 'drupal/stable9:*@stable'
  ) >"$composer_output" 2>&1 || composer_status="$?"
  if [ "$composer_status" -eq 0 ]; then
    cat "$composer_output"
  else
    cat "$composer_output" >&2
  fi
  if [ "$composer_status" -eq 2 ]; then
    skip_message="SKIP Stable9 template parity: no stable contrib drupal/stable9 release resolves under this fixture's dependency and platform constraints. Whisk-to-parent template checks passed; baseline coverage was not verified."
    echo "$skip_message"
    if [ -n "$report_file" ]; then
      mkdir -p "$(dirname "$report_file")"
      printf '# Stable9 template parity report\n\n%s\n' "$skip_message" >"$report_file"
    fi
    exit 0
  elif [ "$composer_status" -ne 0 ]; then
    echo "Stable9 baseline installation failed with Composer status ${composer_status}." >&2
    exit "$composer_status"
  fi
  if [ ! -d "$stable9_templates_dir" ]; then
    echo "Composer succeeded but contrib stable9 templates are missing at ${stable9_templates_dir}." >&2
    exit 1
  fi
fi

# Compare sorted basenames for coverage and retain repo paths for reporting.
(
  cd "$stable9_templates_dir"
  find . -type f -name '*.html.twig' | sed 's#.*/##' | sort -u
) >"$stable9_list"

if [ ! -s "$stable9_list" ]; then
  echo "The stable9 baseline contains no .html.twig templates." >&2
  exit 1
fi

(
  cd "$repo_templates_dir"
  find . -type f -name '*.html.twig' | sort
) >"$repo_list"

missing_templates="$(comm -23 "$stable9_list" <(sed 's#.*/##' "$repo_list" | sort -u))"

if [ -n "$missing_templates" ]; then
  echo "Emulsify is missing stable9 template basenames:" >&2
  echo "$missing_templates" >&2
  exit 1
fi

while IFS= read -r relative_path; do
  stable9_path="$(find "$stable9_templates_dir" -type f -name "${relative_path##*/}" -print -quit)"
  repo_path="${repo_templates_dir}/${relative_path#./}"

  # The report separates exact copies from intentional overrides. Both are
  # acceptable for parity; only a missing stable9 basename fails the test.
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
    echo "- Stable9 source: ${stable9_source}"
    echo "- Stable9 templates covered: $(wc -l <"$stable9_list" | tr -d ' ')"
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
    echo "These templates cover the stable9 contract but intentionally differ in content."
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

echo "Emulsify covers every stable9 template basename and no longer depends on stable9."
