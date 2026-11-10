#!/usr/bin/env bash
# Update the live MASI app from GitHub. Run on the server:  bash ~/masi/deploy.sh
# Never edit files directly on the server: make changes locally, commit, push, then run this.
set -e

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
# Find PHP 8.3+: CloudLinux (/opt/alt), cPanel EasyApache (/opt/cpanel), else the default php.
if [ -z "$PHP" ]; then
  for p in /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php \
           /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php; do
    [ -x "$p" ] && PHP="$p" && break
  done
  PHP="${PHP:-php}"
fi
if ! $PHP -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
  echo "PHP 8.3 or newer is needed; $PHP is $($PHP -r 'echo PHP_VERSION;'). Set PHP 8.3 in cPanel (Select PHP Version) or run: PHP=/path/to/php83 bash deploy.sh" >&2
  exit 1
fi
COMPOSER="$(command -v composer || echo "$APP_DIR/composer.phar")"

cd "$APP_DIR"
echo "==> Using $($PHP -r 'echo PHP_VERSION;')"

$PHP artisan down --retry=30 || true
trap '$PHP artisan up' EXIT

echo "==> Pulling latest code"
git pull --ff-only origin master

echo "==> Installing PHP packages"
# allow_url_fopen is off on this host; Composer needs it, so switch it on for Composer only.
$PHP -d allow_url_fopen=On -d memory_limit=-1 "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction

echo "==> Database changes"
$PHP artisan migrate --force

echo "==> Refreshing caches"
$PHP artisan optimize:clear
$PHP artisan optimize

echo "==> Done. Site is back up."
