#!/usr/bin/env bash
# Runs every job listed in tools/site/RUN (one file name from tools/site/jobs/ per line,
# lines starting with # are ignored) and prints the combined output encrypted for Claude.
set -uo pipefail
cd "$(dirname "$0")"
out=$(mktemp)
if [ -z "${WP_ADMIN_USER:-}" ] || [ -z "${WP_ADMIN_PASSWORD:-}" ]; then
  echo "WP_ADMIN_USER / WP_ADMIN_PASSWORD secrets are not set yet — nothing to run."
  exit 0
fi
fail=0
while IFS= read -r line; do
  job=$(echo "$line" | sed 's/#.*//' | xargs)
  [ -z "$job" ] && continue
  echo "=== $job ===" >> "$out"
  if node "jobs/$job" >> "$out" 2>&1; then echo "--- $job: ok" >> "$out"; echo "$job: ok";
  else echo "--- $job: FAILED" >> "$out"; echo "$job: FAILED"; fail=1; fi
done < RUN
echo "-----BEGIN MARMAR RESULT-----"
openssl cms -encrypt -binary -aes256 -in "$out" -outform PEM result-cert.pem
echo "-----END MARMAR RESULT-----"
exit $fail
