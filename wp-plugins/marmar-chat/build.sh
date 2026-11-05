#!/usr/bin/env bash
# Сборка установочного zip: зависимости → php-scoper (префикс MarMarChat\Vendor) → zip.
# Запуск: ./build.sh [выходная_папка]
set -euo pipefail
cd "$(dirname "$0")"
SRC="$(pwd)"
OUT="$(realpath -m "${1:-./dist}")"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# 1. Зависимости плагина.
cp composer.json scoper.inc.php "$WORK/"
( cd "$WORK" && composer install --no-dev --prefer-dist --no-interaction --quiet )

# 2. php-scoper ставим через Composer в отдельную папку.
mkdir -p "$WORK/tools" && ( cd "$WORK/tools" && composer require --quiet --no-interaction humbug/php-scoper:^0.18 )

# 3. Префиксуем vendor/ и пересобираем автозагрузчик с уникальным именем.
( cd "$WORK" && php tools/vendor/bin/php-scoper add-prefix --output-dir=build/vendor --force --quiet )
php -r '$j=json_decode(file_get_contents($argv[1]),true); $j["config"]["autoloader-suffix"]="MarMarChatScoped"; file_put_contents($argv[2], json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));' "$WORK/composer.json" "$WORK/build/composer.json"
( cd "$WORK/build" && composer dump-autoload --classmap-authoritative --no-interaction --quiet )
php "$SRC/tools/rekey-autoload.php" "$WORK/build"

# 4. Пакет.
PKG="$WORK/pkg/marmar-chat"
mkdir -p "$PKG"
cp -r "$SRC/marmar-chat.php" "$SRC/includes" "$SRC/assets" "$SRC/uninstall.php" "$PKG/"
cp -r "$WORK/build/vendor" "$PKG/vendor-prefixed"
mkdir -p "$OUT"
rm -f "$OUT/marmar-chat.zip"
( cd "$WORK/pkg" && zip -qr "$OUT/marmar-chat.zip" marmar-chat )
echo "Built $OUT/marmar-chat.zip"
