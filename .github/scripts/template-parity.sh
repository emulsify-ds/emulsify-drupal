#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <repo-root>" >&2
  exit 1
fi

fixture_dir="$1"
repo_root="$2"
stable9_templates_dir="${fixture_dir}/web/core/themes/stable9/templates"
repo_templates_dir="${repo_root}/templates"
stable9_list="$(mktemp)"
repo_list="$(mktemp)"

cleanup() {
  rm -f "$stable9_list" "$repo_list"
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

echo "Emulsify includes every stable9 template path and no longer depends on stable9."
