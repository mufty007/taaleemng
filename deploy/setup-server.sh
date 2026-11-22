#!/usr/bin/env bash
#
# One-time setup of a fresh Ubuntu 24.04 VPS for Ta'aleem.
#
# 1. Point your domain's A records (example.ng and www.example.ng) at the server's IP.
# 2. On the server, as root or a sudo user:
#
#      sudo apt-get update && sudo apt-get install -y git
#      sudo git clone <your-repo-url> /var/www/taaleem
#      cd /var/www/taaleem
#      sudo DOMAIN=taaleem.ng EMAIL=you@taaleem.ng DB_PASSWORD='a-long-random-password' bash deploy/setup-server.sh
#
# It installs Nginx, MariaDB, PHP 8.4, Composer, Node 22 and Supervisor, creates the
# database, writes .env, builds the app, sets up HTTPS, the queue worker, the scheduler
# and nightly backups. Everything runs as one "deploy" user (like Laravel Forge), so
# file permissions never fight between the web server, the worker and cron.
#
# Safe to run again: steps that are already done are skipped or refreshed.
#
# Private repo? Clone with HTTPS and a GitHub token the first time. So that deploy.sh can
# pull later, give the deploy user its own read-only key once setup has created it:
#   sudo -u deploy ssh-keygen -t ed25519 -N "" -f /home/deploy/.ssh/id_ed25519
#   (add /home/deploy/.ssh/id_ed25519.pub on GitHub: repo Settings > Deploy keys, read-only)
#   sudo -u deploy git -C /var/www/taaleem remote set-url origin git@github.com:<you>/<repo>.git
#
# Optional: APP_USER (default deploy), DB_NAME / DB_USER (default taaleem),
#           SKIP_HTTPS=1 to set up without a certificate (e.g. DNS not ready yet).

set -euo pipefail

: "${DOMAIN:?Set DOMAIN, e.g. DOMAIN=taaleem.ng}"
: "${EMAIL:?Set EMAIL for Let's Encrypt expiry notices}"
: "${DB_PASSWORD:?Set DB_PASSWORD for the app's database user}"

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_USER="${APP_USER:-deploy}"
DB_NAME="${DB_NAME:-taaleem}"
DB_USER="${DB_USER:-taaleem}"
PHP_VERSION="8.4"
HERE="$APP_DIR/deploy"

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$1"; }
as_app() { sudo -u "$APP_USER" -H bash -c "cd '$APP_DIR' && $1"; }

[[ $EUID -eq 0 ]] || { echo "Run this with sudo." >&2; exit 1; }
[[ "$DB_PASSWORD" =~ ^[A-Za-z0-9._-]{16,}$ ]] || { echo "Use at least 16 letters, digits, dots, dashes or underscores for DB_PASSWORD (try: openssl rand -hex 24)." >&2; exit 1; }
[[ -f "$APP_DIR/artisan" ]] || { echo "Run this from inside the cloned app (deploy/setup-server.sh)." >&2; exit 1; }

step "System packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get install -y -q software-properties-common curl unzip git nginx mariadb-server supervisor certbot python3-certbot-nginx ufw unattended-upgrades
add-apt-repository -y ppa:ondrej/php
apt-get update -q
apt-get install -y -q "php$PHP_VERSION-fpm" "php$PHP_VERSION-cli" "php$PHP_VERSION-mysql" "php$PHP_VERSION-mbstring" \
    "php$PHP_VERSION-xml" "php$PHP_VERSION-curl" "php$PHP_VERSION-zip" "php$PHP_VERSION-gd" "php$PHP_VERSION-intl" \
    "php$PHP_VERSION-bcmath" "php$PHP_VERSION-opcache"
update-alternatives --set php "/usr/bin/php$PHP_VERSION"

if ! command -v node >/dev/null || [[ "$(node -v)" != v22* ]]; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y -q nodejs
fi

if ! command -v composer >/dev/null; then
    expected="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    actual="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [[ "$expected" == "$actual" ]] || { echo "Composer installer checksum mismatch." >&2; exit 1; }
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm /tmp/composer-setup.php
fi

step "Swap (so npm run build doesn't run out of memory on a small server)"
if ! swapon --show | grep -q .; then
    fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

step "Firewall"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

step "App user ($APP_USER)"
id "$APP_USER" >/dev/null 2>&1 || adduser --disabled-password --gecos "" "$APP_USER"
chown -R "$APP_USER:$APP_USER" "$APP_DIR"
git config --system --add safe.directory "$APP_DIR"
echo "$APP_USER ALL=(root) NOPASSWD: /usr/bin/systemctl reload php$PHP_VERSION-fpm" > /etc/sudoers.d/taaleem-deploy
chmod 440 /etc/sudoers.d/taaleem-deploy

step "PHP-FPM runs as $APP_USER"
pool="/etc/php/$PHP_VERSION/fpm/pool.d/www.conf"
sed -i -E "s/^user = .*/user = $APP_USER/; s/^group = .*/group = $APP_USER/; s/^;?listen.owner = .*/listen.owner = www-data/; s/^;?listen.group = .*/listen.group = www-data/" "$pool"
cat > "/etc/php/$PHP_VERSION/fpm/conf.d/99-taaleem.ini" <<'INI'
; Uploads go up to 10 MB (receipts, classwork); leave room for the rest of the form.
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
expose_php = Off
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
INI
systemctl restart "php$PHP_VERSION-fpm"

step "Database"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

step "Environment (.env)"
if [[ ! -f "$APP_DIR/.env" ]]; then
    cat > "$APP_DIR/.env" <<ENV
APP_NAME="Ta'aleem"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://$DOMAIN
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_NG

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=30

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD="$DB_PASSWORD"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=true
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

# Invitations and password resets are emailed: fill these in from your mail provider.
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="hello@$DOMAIN"
MAIL_FROM_NAME="\${APP_NAME}"

VITE_APP_NAME="\${APP_NAME}"

TAALEEM_CONTACT_EMAIL=hello@$DOMAIN
TAALEEM_WHATSAPP_NUMBER=
TAALEEM_PRICE_BASIC=
TAALEEM_PRICE_STANDARD=
TAALEEM_PRICE_PREMIUM=

# Live keys from the Paystack dashboard. Webhook URL: https://$DOMAIN/webhooks/paystack
PAYSTACK_PUBLIC_KEY=
PAYSTACK_SECRET_KEY=
ENV
    chown "$APP_USER:$APP_USER" "$APP_DIR/.env"
    chmod 640 "$APP_DIR/.env"
    as_app "php artisan key:generate --force"
else
    echo ".env already exists; leaving it as it is."
fi

step "Build the app"
as_app "composer install --no-dev --optimize-autoloader --no-interaction"
as_app "npm ci --no-audit --no-fund && npm run build && rm -f public/hot"
as_app "php artisan migrate --force"
as_app "php artisan storage:link || true"
as_app "php artisan optimize"

step "Nginx"
sed -e "s|__DOMAIN__|$DOMAIN|g" -e "s|__APP_DIR__|$APP_DIR|g" -e "s|__PHP_VERSION__|$PHP_VERSION|g" \
    "$HERE/nginx.conf" > /etc/nginx/sites-available/taaleem
ln -sf /etc/nginx/sites-available/taaleem /etc/nginx/sites-enabled/taaleem
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

if [[ "${SKIP_HTTPS:-0}" != "1" ]]; then
    step "HTTPS certificate"
    certbot --nginx --non-interactive --agree-tos --redirect -m "$EMAIL" -d "$DOMAIN" -d "www.$DOMAIN" \
        || echo "Certbot failed (is DNS pointing here yet?). Run later: sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN"
fi

step "Queue worker (student imports, invitation emails)"
sed -e "s|__APP_DIR__|$APP_DIR|g" -e "s|__APP_USER__|$APP_USER|g" "$HERE/supervisor.conf" > /etc/supervisor/conf.d/taaleem.conf
supervisorctl reread
supervisorctl update
supervisorctl restart 'taaleem-worker:*' || true

step "Scheduler and nightly backups"
cat > /etc/cron.d/taaleem <<CRON
* * * * * $APP_USER cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1
30 2 * * * $APP_USER bash $APP_DIR/deploy/backup.sh >> /home/$APP_USER/backup.log 2>&1
CRON
chmod 644 /etc/cron.d/taaleem

step "Done"
cat <<NEXT

Ta'aleem is installed at https://$DOMAIN

Next:
  1. Fill in MAIL_* and PAYSTACK_* in $APP_DIR/.env, then run:
       sudo -u $APP_USER bash -c 'cd $APP_DIR && php artisan optimize'
  2. Create your platform admin (it prints a password once):
       sudo -u $APP_USER bash -c 'cd $APP_DIR && php artisan taaleem:admin you@$DOMAIN --name="Your Name"'
  3. Set the Paystack webhook to https://$DOMAIN/webhooks/paystack
  4. To ship updates later:  sudo -u $APP_USER bash $APP_DIR/deploy/deploy.sh

Never run db:seed here: that is the demo data, where every password is "password".
NEXT
