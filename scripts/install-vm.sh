#!/usr/bin/env bash
# Слага restrstat на машината на tc-sales (Debian 13, nginx, PHP 8.4-FPM, MariaDB и FreeTDS вече са там).
# Пуска се като root:
#     bash install-vm.sh
# Може да се пуска повторно — пропуска готовото.
#
# Как се отваря: https://sales.tours-club.com/restrstat/
#   прокси (.50) → nginx на tc-sales → location /restrstat/ → 127.0.0.1:8081 → nginx на restrstat → PHP-FPM (пул restrstat)
set -euo pipefail
# при „su“ без „-“ PATH-ът е на обикновения потребител — без /usr/sbin (useradd, nginx, php-fpm)
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:$PATH"

APP_USER="${APP_USER:-restrstat}"
APP_DIR="${APP_DIR:-/srv/restrstat}"
REPO_URL="${REPO_URL:-https://github.com/giorceto/restrstat.git}"
BRANCH="${BRANCH:-claude/affectionate-fermat-idbcu8}"
APP_URL="${APP_URL:-https://sales.tours-club.com/restrstat}"
PORT="${PORT:-8081}"                                  # само вътре в машината
DB_NAME="${DB_NAME:-restrstat}"
DB_USER="${DB_USER:-restrstat}"
PHP="${PHP_VERSION:-8.4}"
TC_SITE="${TC_SITE:-/etc/nginx/sites-available/tc-sales}"
TC_ENV="${TC_ENV:-/srv/tc-sales/.env}"                # оттам се копира връзката към SAMO (същият потребител само за четене)

say() { printf '\n== %s\n' "$*"; }
[ "$(id -u)" -eq 0 ] || { echo "Пусни го като root." >&2; exit 1; }

say "Пакети"
export DEBIAN_FRONTEND=noninteractive
apt-get install -y -q --no-install-recommends php$PHP-sybase freetds-bin >/dev/null   # pdo_dblib за SAMO
php -m | grep -qi pdo_dblib || { echo "Няма pdo_dblib (php$PHP-sybase)." >&2; exit 1; }
grep -q '^\[samo\]' /etc/freetds/freetds.conf || echo "ВНИМАНИЕ: няма раздел [samo] в /etc/freetds/freetds.conf — SAMO няма да се вижда." >&2

say "Потребител $APP_USER"
command -v useradd >/dev/null || apt-get install -y -q passwd >/dev/null
id "$APP_USER" >/dev/null 2>&1 || useradd --system --create-home --shell /bin/bash "$APP_USER"
install -d -o "$APP_USER" -g "$APP_USER" -m 750 "$APP_DIR"
usermod -aG "$APP_USER" www-data

say "Код"
if [ ! -d "$APP_DIR/.git" ]; then
    sudo -u "$APP_USER" git clone -q -b "$BRANCH" "$REPO_URL" "$APP_DIR"
fi

say "База данни"
ENV="$APP_DIR/.env"
if [ ! -f "$ENV" ]; then
    DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-28)"
    mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
    say "Настройки (.env)"
    sudo -u "$APP_USER" cp "$APP_DIR/.env.example" "$ENV"
    set_env() { sed -i "s|^#\?\s*$1=.*|$1=$2|" "$ENV"; grep -q "^$1=" "$ENV" || echo "$1=$2" >> "$ENV"; }
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "$APP_URL"
    set_env DB_PASSWORD "'$DB_PASS'"
    # връзката към SAMO — от tc-sales (паролата не минава през екрана)
    if [ -f "$TC_ENV" ]; then
        for k in SAMO_DB_HOST SAMO_DB_PORT SAMO_DB_DATABASE SAMO_DB_USERNAME SAMO_DB_PASSWORD; do
            line="$(grep -E "^$k=" "$TC_ENV" | tail -1 || true)"
            [ -n "$line" ] && set_env "$k" "${line#*=}"
        done
    fi
    chmod 640 "$ENV"; chown "$APP_USER:$APP_USER" "$ENV"
fi

say "PHP-FPM — пул $APP_USER"
cat > /etc/php/$PHP/fpm/pool.d/restrstat.conf <<POOL
[restrstat]
user = $APP_USER
group = $APP_USER
listen = /run/php/restrstat.sock
listen.owner = www-data
listen.group = www-data
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 30s
php_admin_value[expose_php] = Off
php_admin_value[memory_limit] = 256M
POOL
systemctl restart php$PHP-fpm

say "nginx"
cat > /etc/nginx/sites-available/restrstat <<NGINX
# restrstat — само за nginx на tc-sales (127.0.0.1), отвън се стига през https://sales.tours-club.com/restrstat/
server {
    listen 127.0.0.1:$PORT;
    server_name _;
    root $APP_DIR/public;
    index index.php;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "same-origin" always;
    add_header X-Robots-Tag "noindex, nofollow" always;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ ^/index\.php(/|\$) {
        fastcgi_pass unix:/run/php/restrstat.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)\$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 120s;
    }
    location ~ \.php\$ { try_files /nonexistent /index.php?\$query_string; }
    location ~ /\.(?!well-known) { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/restrstat /etc/nginx/sites-enabled/restrstat

# парчето за сайта на tc-sales: /restrstat/ → 127.0.0.1:PORT
mkdir -p /etc/nginx/snippets
cat > /etc/nginx/snippets/restrstat.conf <<NGINX
location = /restrstat { return 301 /restrstat/; }
location ^~ /restrstat/ {
    proxy_pass http://127.0.0.1:$PORT/;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto https;
    proxy_set_header X-Forwarded-Prefix /restrstat;
    proxy_read_timeout 120s;
}
NGINX
if [ -f "$TC_SITE" ] && ! grep -q 'snippets/restrstat.conf' "$TC_SITE"; then
    cp "$TC_SITE" "$TC_SITE.bak-restrstat"
    # след всеки server_name в сайта на tc-sales
    sed -i '/^\s*server_name /a\    include /etc/nginx/snippets/restrstat.conf;' "$TC_SITE"
    echo "Добавено в $TC_SITE (копие: $TC_SITE.bak-restrstat)."
fi
if ! nginx -t; then
    [ -f "$TC_SITE.bak-restrstat" ] && cp "$TC_SITE.bak-restrstat" "$TC_SITE"
    echo "nginx -t не мина — върнах сайта на tc-sales както беше." >&2
    exit 1
fi
systemctl reload nginx

say "Приложение (deploy.sh)"
sudo -u "$APP_USER" bash -c "cd '$APP_DIR' && ./deploy.sh"

say "Cron и архив"
BACKUP_DIR=/var/backups/restrstat
install -d -o root -g root -m 700 "$BACKUP_DIR"
cat > /etc/cron.d/restrstat <<CRON
# тегленето от SAMO (всеки час, ако SAMO_SYNC=true в .env)
* * * * * $APP_USER cd $APP_DIR && php artisan schedule:run >/dev/null 2>&1
# архив на базата всяка нощ, пази 14 дни
40 2 * * * root mariadb-dump --single-transaction $DB_NAME | gzip > $BACKUP_DIR/$DB_NAME-\$(date +\%F).sql.gz && find $BACKUP_DIR -name '*.sql.gz' -mtime +14 -delete
CRON
chmod 644 /etc/cron.d/restrstat

say "Готово"
cat <<DONE
Адрес:     $APP_URL/
Код:       $APP_DIR (потребител $APP_USER), клон $BRANCH

Следващо (виж DEPLOY.md):
  1. вход:            sudo -u $APP_USER php $APP_DIR/artisan user:create имейл "Име"
  2. SAMO:            sudo -u $APP_USER php $APP_DIR/artisan samo:check
  3. първо теглене:   sudo -u $APP_USER php $APP_DIR/artisan samo:sync --all
  4. всеки час:       SAMO_SYNC=true в $APP_DIR/.env, после sudo -u $APP_USER php $APP_DIR/artisan config:cache
DONE
