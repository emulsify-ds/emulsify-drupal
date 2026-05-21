#!/usr/bin/env bash

set -euo pipefail

# Captures a small, representative set of Drupal-rendered pages from a fixture.
# The generated HTML files are used as smoke-test evidence for parent-theme
# rendering and template coverage. This deliberately avoids screenshot or visual
# diff tooling; the release risk here is whether Drupal can bootstrap and render
# the theme across core template surfaces.
if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <output-dir>" >&2
  exit 1
fi

fixture_dir="$1"
output_dir="$2"

# Allow callers to reuse an existing server URL, but default to a random local
# port so parallel matrix jobs do not collide on the same runner.
if [ "$#" -ge 3 ]; then
  base_url="$3"
  # Extract the port from http://host:port[/path] so the built-in server binds
  # to the same URL that the caller asked this script to capture.
  server_port="${base_url##*:}"
  server_port="${server_port%%/*}"
else
  server_port="${EMULSIFY_RENDER_PORT:-$((8000 + RANDOM % 1000))}"
  base_url="http://127.0.0.1:${server_port}"
fi
web_root="${fixture_dir}/web"
server_log="${output_dir}/php-server.log"
cookie_file="${output_dir}/cookies.txt"

# The output directory also stores transient server logs and cookies. Keeping
# these beside the captured HTML makes failed CI artifacts easier to inspect.
mkdir -p "$output_dir"

# The built-in server is only needed for this capture; always terminate it when
# the script exits, including failures from curl or form parsing.
cleanup() {
  if [ -n "${server_pid:-}" ] && kill -0 "$server_pid" 2>/dev/null; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
}

trap cleanup EXIT

(
  cd "$web_root"
  # Drupal's .ht.router.php lets the PHP built-in server route clean URLs like
  # /node and /user/login without Apache or Nginx.
  php -S "127.0.0.1:${server_port}" .ht.router.php >"$server_log" 2>&1
) &
server_pid="$!"

# Poll a real Drupal route before capturing pages so startup races produce a
# clear timeout error instead of partial or empty fixture output.
ready=0
for _ in $(seq 1 30); do
  if curl -fsS "${base_url}/node" >/dev/null 2>&1; then
    ready=1
    break
  fi
  sleep 1
done

if [ "$ready" -ne 1 ]; then
  echo "Timed out waiting for the PHP built-in server." >&2
  sed -n '1,160p' "$server_log" >&2 || true
  exit 1
fi

# Capture the page list that exercises the baseline templates owned by Emulsify:
# html/page wrappers, fields, views output, forms, users, and messages.
curl -fsSL "${base_url}/node" >"${output_dir}/frontpage-view.html"
# A fixture resolving to the installer means Drupal settings were not loaded;
# fail fast because subsequent diffs would compare installer markup.
if grep -q "Drupal already installed" "${output_dir}/frontpage-view.html"; then
  echo "Fixture site resolved to Drupal's installer instead of the installed site." >&2
  sed -n '1,160p' "$server_log" >&2 || true
  exit 1
fi

curl -fsSL "${base_url}/node/1" >"${output_dir}/node.html"
curl -fsSL \
  -c "$cookie_file" \
  -b "$cookie_file" \
  "${base_url}/user/login" >"${output_dir}/user-login.html"

# Submit the login form with bad credentials to exercise form rebuild/error
# rendering, which catches regressions that a plain GET does not expose.
# The token extraction stays intentionally shell-only so this script can run on
# GitHub-hosted runners without additional parser dependencies.
form_build_id="$(grep -o 'name="form_build_id" value="[^"]*"' "${output_dir}/user-login.html" | sed 's/^.*value="//; s/"$//')"
form_token="$(grep -o 'name="form_token" value="[^"]*"' "${output_dir}/user-login.html" | sed 's/^.*value="//; s/"$//' || true)"

if [ -z "$form_build_id" ]; then
  echo "Unable to extract the login form_build_id from the rendered user login page." >&2
  exit 1
fi

post_args=(
  -fsSL
  -c "$cookie_file"
  -b "$cookie_file"
  --data-urlencode "name=not-a-real-user"
  --data-urlencode "pass=definitely-wrong"
  --data-urlencode "form_build_id=${form_build_id}"
  --data-urlencode "form_id=user_login_form"
  --data-urlencode "op=Log in"
)

# Some Drupal versions/configurations omit form_token for this form, so include
# it only when present instead of making the smoke test version-specific.
if [ -n "$form_token" ]; then
  post_args+=(--data-urlencode "form_token=${form_token}")
fi

# Write the rebuilt form with status/error messages to a separate capture so
# render checks can isolate initial output from validation output.
curl "${post_args[@]}" "${base_url}/user/login" >"${output_dir}/user-login-error.html"
