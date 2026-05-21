#!/usr/bin/env bash

set -euo pipefail

# Maintenance utility for docs/template-map.md. It intentionally shares the
# stable9 path-contract logic with template-parity.sh, but writes an explanatory
# Markdown map instead of acting as a regular PR gate.
if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <repo-root> [output-file]" >&2
  exit 1
fi

fixture_dir="$1"
repo_root="$2"
output_file="${3:-${repo_root}/docs/template-map.md}"
stable9_templates_dir="${fixture_dir}/web/core/themes/stable9/templates"
repo_templates_dir="${repo_root}/templates"
repo_list="$(mktemp)"
stable9_list="$(mktemp)"
exact_matches=0
modified_matches=0
emulsify_only_matches=0

cleanup() {
  rm -f "$repo_list" "$stable9_list"
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

reason_for_template() {
  local relative_path="$1"
  local modified="$2"
  local stable9_path="$3"

  # Keep the reason taxonomy broad and stable. The map is meant to orient
  # maintainers during release review, not to encode every line-level override.
  if [ ! -f "$stable9_path" ]; then
    echo "Outside stable9 parity contract"
    return
  fi

  if [ "$modified" = "No" ]; then
    echo "Parent parity"
    return
  fi

  case "$relative_path" in
    layout/html.html.twig)
      echo "Attach Emulsify document structure"
      ;;
    layout/layout.html.twig)
      echo "Attach Emulsify layout wrappers"
      ;;
    layout/page.html.twig)
      echo "Attach Emulsify page structure"
      ;;
    navigation/*)
      echo "Emulsify navigation override"
      ;;
    form/*)
      echo "Emulsify form override"
      ;;
    views/*)
      echo "Emulsify views override"
      ;;
    admin/*)
      echo "Emulsify admin override"
      ;;
    block/*)
      echo "Emulsify block override"
      ;;
    field/*)
      echo "Emulsify field override"
      ;;
    content*/*)
      echo "Emulsify content override"
      ;;
    media-library/*)
      echo "Emulsify media-library override"
      ;;
    dataset/*)
      echo "Emulsify dataset override"
      ;;
    *)
      echo "Emulsify override"
      ;;
  esac
}

mkdir -p "$(dirname "$output_file")"

{
  echo "# Template Override Map"
  echo
  echo "Generated from Drupal core's stable9 template tree in a local fixture site."
  echo
  while IFS= read -r relative_path; do
    relative_path="${relative_path#./}"
    stable9_path="${stable9_templates_dir}/${relative_path}"
    repo_path="${repo_templates_dir}/${relative_path}"

    if [ -f "$stable9_path" ]; then
      if cmp -s "$stable9_path" "$repo_path"; then
        exact_matches=$((exact_matches + 1))
      else
        modified_matches=$((modified_matches + 1))
      fi
    else
      emulsify_only_matches=$((emulsify_only_matches + 1))
    fi
  done <"$repo_list"

  echo "- Stable9 template paths mirrored: $(wc -l <"$stable9_list" | tr -d ' ')"
  echo "- Exact baseline copies: ${exact_matches}"
  echo "- Modified relative to stable9: ${modified_matches}"
  echo "- Emulsify-only template paths: ${emulsify_only_matches}"
  echo "- Stable9 paths excluded: 0"
  echo
  echo "Reason values are generated audit categories that explain why a path differs at a high level."
  echo
  echo "| Template | Source baseline | Modified? | Reason |"
  echo "|---|---|---:|---|"

  while IFS= read -r relative_path; do
    relative_path="${relative_path#./}"
    stable9_path="${stable9_templates_dir}/${relative_path}"
    repo_path="${repo_templates_dir}/${relative_path}"

    if [ -f "$stable9_path" ]; then
      baseline="\`stable9/core\`"
      if cmp -s "$stable9_path" "$repo_path"; then
        modified="No"
      else
        modified="Yes"
      fi
    else
      baseline="\`Emulsify-only\`"
      modified="Yes"
    fi

    reason="$(reason_for_template "$relative_path" "$modified" "$stable9_path")"
    printf '| `%s` | %s | %s | %s |\n' "templates/${relative_path}" "$baseline" "$modified" "$reason"
  done <"$repo_list"
} >"$output_file"

echo "Wrote template map to ${output_file}."
