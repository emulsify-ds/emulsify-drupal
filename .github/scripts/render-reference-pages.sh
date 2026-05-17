#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <output-dir>" >&2
  exit 1
fi

fixture_dir="$1"
output_dir="$2"
if [ "$#" -ge 3 ]; then
  base_url="$3"
  server_port="${base_url##*:}"
  server_port="${server_port%%/*}"
else
  server_port="${EMULSIFY_RENDER_PORT:-$((8000 + RANDOM % 1000))}"
  base_url="http://127.0.0.1:${server_port}"
fi
web_root="${fixture_dir}/web"
server_log="${output_dir}/php-server.log"
cookie_file="${output_dir}/cookies.txt"

mkdir -p "$output_dir"

cleanup() {
  if [ -n "${server_pid:-}" ] && kill -0 "$server_pid" 2>/dev/null; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
}

trap cleanup EXIT

(
  cd "$web_root"
  php -S "127.0.0.1:${server_port}" .ht.router.php >"$server_log" 2>&1
) &
server_pid="$!"

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

curl -fsSL "${base_url}/node" >"${output_dir}/frontpage-view.html"
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

if [ -n "$form_token" ]; then
  post_args+=(--data-urlencode "form_token=${form_token}")
fi

curl "${post_args[@]}" "${base_url}/user/login" >"${output_dir}/user-login-error.html"
