#!/usr/bin/env bash
# Claude side: fetch the latest site-jobs run log and decrypt its result.
# Usage: tools/site/result.sh <path-to-private-key> [run-id]
set -euo pipefail
R=alinamarkova14458/marmar-candles-feed
key=$1
id=${2:-$(gh api "repos/$R/actions/workflows/site-jobs.yml/runs?per_page=1" -q '.workflow_runs[0].id')}
echo "run $id: $(gh api repos/$R/actions/runs/$id -q '"\(.status) \(.conclusion)"')"
jid=$(gh api repos/$R/actions/runs/$id/jobs -q '.jobs[0].id')
gh api repos/$R/actions/jobs/$jid/logs \
  | sed -E 's/^[0-9T:.-]+Z ?//' \
  | sed -n '/-----BEGIN MARMAR RESULT-----/,/-----END MARMAR RESULT-----/p' \
  | sed '1d;$d' \
  | openssl cms -decrypt -inform PEM -inkey "$key"
