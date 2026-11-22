#!/usr/bin/env bash
#
# Ship the latest code to the live server. Run on the server as the app user:
#
#   sudo -u deploy bash /var/www/taaleem/deploy/deploy.sh
#
# Puts the site in maintenance mode, pulls, installs, builds, migrates, caches, reloads
# PHP and restarts the queue worker. If any step fails the site stays in maintenance
# mode, so nobody uses half-updated code; fix the problem, then run it again.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

trap 'echo "Deploy failed. The site is still in maintenance mode: fix the error and run deploy.sh again (or php artisan up to reopen as it is)." >&2' ERR

echo "==> Maintenance mode"
php artisan down --retry=15 --refresh=15

echo "==> Code"
git pull --ff-only

echo "==> PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Front end"
npm ci --no-audit --no-fund
npm run build
rm -f public/hot

echo "==> Database"
php artisan migrate --force

echo "==> Caches"
php artisan optimize

echo "==> Reload PHP and restart the queue worker"
sudo -n systemctl reload php8.4-fpm || echo "Could not reload PHP-FPM; run: sudo systemctl reload php8.4-fpm"
php artisan queue:restart

php artisan up
echo "==> Live: $(git log -1 --format='%h %s')"
