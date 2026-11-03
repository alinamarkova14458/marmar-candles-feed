#!/usr/bin/env bash
# Local WordPress + WooCommerce + Rank Math + Blocksy on SQLite for marmar-lang tests.
# Idempotent: re-running wipes the database and recreates fixtures.
# Usage: MMWP_DIR=/path/to/dir tests/marmar-lang/setup_wp.sh
set -euo pipefail

DIR="${MMWP_DIR:?set MMWP_DIR}"
PORT="${MMWP_PORT:-8899}"
URL="http://127.0.0.1:${PORT}"
HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"
CACHE="$DIR/.cache"
WP="php $CACHE/wp-cli.phar --path=$DIR/wp --allow-root --quiet"

mkdir -p "$CACHE"
fetch() { [ -s "$CACHE/$2" ] || curl -sSfL "$1" -o "$CACHE/$2"; }
fetch https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar wp-cli.phar
fetch https://wordpress.org/latest.zip wordpress.zip
for p in sqlite-database-integration woocommerce seo-by-rank-math blocksy-companion; do
  fetch "https://downloads.wordpress.org/plugin/$p.latest-stable.zip" "$p.zip"
done
fetch https://downloads.wordpress.org/theme/blocksy.latest-stable.zip blocksy.zip

# Fresh WordPress tree (keeps the download cache).
rm -rf "$DIR/wp"
mkdir -p "$DIR/wp"
unzip -q "$CACHE/wordpress.zip" -d "$DIR/tmp" && mv "$DIR/tmp/wordpress/"* "$DIR/wp/" && rm -rf "$DIR/tmp"
for p in sqlite-database-integration woocommerce seo-by-rank-math blocksy-companion; do
  unzip -q "$CACHE/$p.zip" -d "$DIR/wp/wp-content/plugins/"
done
unzip -q "$CACHE/blocksy.zip" -d "$DIR/wp/wp-content/themes/"
sed "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$DIR/wp/wp-content/plugins/sqlite-database-integration#; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
  "$DIR/wp/wp-content/plugins/sqlite-database-integration/db.copy" > "$DIR/wp/wp-content/db.php"
ln -sfn "$REPO/site/plugins/marmar-lang" "$DIR/wp/wp-content/plugins/marmar-lang"
mkdir -p "$DIR/wp/wp-content/mu-plugins"
cp "$HERE/mu-test-helpers.php" "$DIR/wp/wp-content/mu-plugins/"

cat > "$DIR/router.php" <<'PHP'
<?php
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = __DIR__ . '/wp' . $path;
if ( $path !== '/' && is_file( $file ) && substr( $file, -4 ) !== '.php' ) { return false; }
if ( $path !== '/' && is_file( $file ) ) { chdir( dirname( $file ) ); require $file; return; }
chdir( __DIR__ . '/wp' );
require __DIR__ . '/wp/index.php';
PHP

$WP config create --dbname=wp --dbuser=wp --dbpass=wp --skip-check --force \
  --extra-php <<<"define('WP_HOME','$URL'); define('WP_SITEURL','$URL'); define('WP_ENVIRONMENT_TYPE','local'); define('MMWP_MAIL_LOG','$DIR/mail.log');"
$WP core install --url="$URL" --title="MarMar Test" --admin_user=admin --admin_password=admin \
  --admin_email=admin@example.com --skip-email
$WP rewrite structure '/%postname%/' --hard
$WP plugin activate woocommerce seo-by-rank-math blocksy-companion
$WP theme activate blocksy
$WP language core install lv en_GB
$WP site switch-language lv
$WP language plugin install woocommerce lv en_GB || true
$WP option update woocommerce_coming_soon no
$WP option update woocommerce_default_country LV
$WP option update woocommerce_currency EUR
$WP option update woocommerce_enable_guest_checkout yes
$WP option update woocommerce_cod_settings '{"enabled":"yes","title":"Skaidrā naudā"}' --format=json
$WP plugin activate marmar-lang || true
$WP eval-file "$HERE/fixtures.php"
$WP rewrite flush --hard
$WP user application-password create admin tests --porcelain > "$DIR/.apppass"
echo "ok: $URL"
