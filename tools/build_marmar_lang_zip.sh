#!/usr/bin/env bash
# Build dist/marmar-lang-<version>.zip for upload via Spraudņi → Pievienot jaunu → Augšupielādēt.
set -euo pipefail
cd "$(dirname "$0")/.."
VER=$(grep -oP "MM_LANG_VERSION = '\K[^']+" site/plugins/marmar-lang/marmar-lang.php)
mkdir -p dist
rm -f "dist/marmar-lang-$VER.zip"
(cd site/plugins && zip -qr "../../dist/marmar-lang-$VER.zip" marmar-lang -x '*/.*')
echo "dist/marmar-lang-$VER.zip"
