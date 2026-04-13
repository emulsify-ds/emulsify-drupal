#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <fixture-dir> <output-dir>" >&2
  exit 1
fi

fixture_dir="$1"
output_dir="$2"
base_url="${3:-http://127.0.0.1:8888}"
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
  cd "$fixture_dir"
  php -S 127.0.0.1:8888 -t web web/.ht.router.php >"$server_log" 2>&1
) &
server_pid="$!"

for _ in $(seq 1 30); do
  if curl -fsS "${base_url}/node" >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

curl -fsSL "${base_url}/node" >"${output_dir}/frontpage-view.html"
curl -fsSL "${base_url}/node/1" >"${output_dir}/node.html"
curl -fsSL "${base_url}/user/1" >"${output_dir}/user.html"
curl -fsSL "${base_url}/user/login" >"${output_dir}/user-login.html"
curl -fsSL "${base_url}/contact" >"${output_dir}/contact.html"

form_build_id="$(grep -o 'name="form_build_id" value="[^"]*"' "${output_dir}/user-login.html" | sed 's/^.*value="//; s/"$//')"
form_token="$(grep -o 'name="form_token" value="[^"]*"' "${output_dir}/user-login.html" | sed 's/^.*value="//; s/"$//')"

curl -fsSL \
  -c "$cookie_file" \
  -b "$cookie_file" \
  --data-urlencode "name=not-a-real-user" \
  --data-urlencode "pass=definitely-wrong" \
  --data-urlencode "form_build_id=${form_build_id}" \
  --data-urlencode "form_token=${form_token}" \
  --data-urlencode "form_id=user_login_form" \
  --data-urlencode "op=Log in" \
  "${base_url}/user/login" >"${output_dir}/user-login-error.html"
