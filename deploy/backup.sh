#!/usr/bin/env bash
#
# Nightly backup of the database and uploaded files (student photos, receipts,
# classwork hand-ins, school crests). deploy/setup-server.sh runs it from cron at 02:30.
#
#   sudo -u deploy bash /var/www/taaleem/deploy/backup.sh
#
# Backups go to ~/backups and are kept for 14 days. A backup on the same server will not
# survive losing the server: set BACKUP_REMOTE to an rclone remote (e.g. "b2:taaleem-backups",
# after running "rclone config") and each night's files are copied there too.
#
# Restore: gunzip -c db-<date>.sql.gz | mysql -u <user> -p <database>
#          tar -xzf files-<date>.tar.gz -C /var/www/taaleem/storage

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%F-%H%M)"

env_value() {
    grep -E "^$1=" "$APP_DIR/.env" | tail -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

echo "$(date '+%F %T') backing up"

MYSQL_PWD="$(env_value DB_PASSWORD)" mysqldump \
    --single-transaction --quick --routines \
    -h "$(env_value DB_HOST)" -P "$(env_value DB_PORT)" -u "$(env_value DB_USERNAME)" \
    "$(env_value DB_DATABASE)" | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz"

tar -czf "$BACKUP_DIR/files-$STAMP.tar.gz" -C "$APP_DIR/storage" app

find "$BACKUP_DIR" -name '*.gz' -mtime +"$KEEP_DAYS" -delete

if [[ -n "${BACKUP_REMOTE:-}" ]]; then
    rclone copy "$BACKUP_DIR" "$BACKUP_REMOTE" --include "*-$STAMP.*"
fi

echo "$(date '+%F %T') done: $(du -ch "$BACKUP_DIR"/*-"$STAMP".* | tail -n 1 | cut -f1)"
