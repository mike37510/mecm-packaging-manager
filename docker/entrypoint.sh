#!/bin/bash
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/html}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

echo "[entrypoint] Preparing MECM Packaging Manager runtime dirs…"

# DocumentRoot public/
export APACHE_DOCUMENT_ROOT="${APACHE_DOCUMENT_ROOT:-/var/www/html/public}"
if [[ -f /etc/apache2/envvars ]]; then
  if grep -q '^export APACHE_DOCUMENT_ROOT=' /etc/apache2/envvars 2>/dev/null; then
    sed -i "s|^export APACHE_DOCUMENT_ROOT=.*|export APACHE_DOCUMENT_ROOT=${APACHE_DOCUMENT_ROOT}|" /etc/apache2/envvars
  else
    echo "export APACHE_DOCUMENT_ROOT=${APACHE_DOCUMENT_ROOT}" >> /etc/apache2/envvars
  fi
fi
if [[ -f "$APP_ROOT/docker/apache-vhost.conf" ]]; then
  cp -f "$APP_ROOT/docker/apache-vhost.conf" /etc/apache2/sites-available/000-default.conf || true
fi
a2enmod rewrite headers >/dev/null 2>&1 || true

# Limites PHP uploads
if [[ -f "$APP_ROOT/config/php-uploads.ini" ]]; then
  for confdir in /usr/local/etc/php/conf.d /etc/php/*/apache2/conf.d /etc/php/*/cli/conf.d; do
    for d in $confdir; do
      [[ -d "$d" ]] || continue
      cp -f "$APP_ROOT/config/php-uploads.ini" "$d/99-sccm-uploads.ini" || true
    done
  done
fi

dirs=(
  "$APP_ROOT/storage"
  "$APP_ROOT/storage/data"
  "$APP_ROOT/storage/packages"
  "$APP_ROOT/storage/exports"
  "$APP_ROOT/storage/bios"
  "$APP_ROOT/storage/bios/hp"
  "$APP_ROOT/storage/bios/dell"
  "$APP_ROOT/storage/bios/lenovo"
  "$APP_ROOT/storage/backups"
  "$APP_ROOT/storage/logs"
  "$APP_ROOT/storage/cache"
  "$APP_ROOT/storage/cache/sessions"
  "$APP_ROOT/storage/cache/tmp"
  "$APP_ROOT/public/assets/uploads"
  "$APP_ROOT/public/assets/uploads/icons"
)

for d in "${dirs[@]}"; do
  mkdir -p "$d"
done

for keep in \
  "$APP_ROOT/storage/data/.gitkeep" \
  "$APP_ROOT/storage/packages/.gitkeep" \
  "$APP_ROOT/storage/exports/.gitkeep" \
  "$APP_ROOT/storage/bios/.gitkeep" \
  "$APP_ROOT/storage/bios/hp/.gitkeep" \
  "$APP_ROOT/storage/bios/dell/.gitkeep" \
  "$APP_ROOT/storage/bios/lenovo/.gitkeep" \
  "$APP_ROOT/storage/backups/.gitkeep" \
  "$APP_ROOT/storage/logs/.gitkeep" \
  "$APP_ROOT/storage/cache/.gitkeep" \
  "$APP_ROOT/public/assets/uploads/icons/.gitkeep"
do
  [[ -e "$keep" ]] || touch "$keep"
done

# Vendor : avec bind-mount, l'hôte peut ne pas l'avoir encore
if [[ ! -f "$APP_ROOT/vendor/autoload.php" ]]; then
  echo "[entrypoint] vendor/ missing — running composer install…"
  if command -v composer >/dev/null 2>&1; then
    (
      cd "$APP_ROOT"
      composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
        || composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts
    ) || echo "[entrypoint] WARNING: composer install failed."
  else
    echo "[entrypoint] WARNING: composer not found and vendor/ missing."
  fi
fi

chmod +x "$APP_ROOT/docker/entrypoint.sh" "$APP_ROOT"/bin/*.sh "$APP_ROOT"/bin/*.php 2>/dev/null || true

chown -R "$WEB_USER:$WEB_GROUP" \
  "$APP_ROOT/storage" \
  "$APP_ROOT/public/assets/uploads" \
  2>/dev/null || true

chmod -R ug+rwX \
  "$APP_ROOT/storage" \
  "$APP_ROOT/public/assets/uploads" \
  2>/dev/null || true

find "$APP_ROOT/storage" "$APP_ROOT/public/assets/uploads" -type d -exec chmod 2775 {} \; 2>/dev/null || true
find "$APP_ROOT/storage" "$APP_ROOT/public/assets/uploads" -type f -exec chmod 0664 {} \; 2>/dev/null || true

shopt -s nullglob
for secret in "$APP_ROOT"/config/*.local.php; do
  chown root:"$WEB_GROUP" "$secret" 2>/dev/null || chown "$WEB_USER:$WEB_GROUP" "$secret"
  chmod 640 "$secret"
done
shopt -u nullglob

if [[ "${RUN_MIGRATIONS:-1}" == "1" ]]; then
  echo "[entrypoint] Ensuring JSON store + admin account…"
  php "$APP_ROOT/bin/migrate.php" || echo "[entrypoint] WARNING: migrate failed."
fi

start_sshd() {
  if [[ "${ENABLE_SSH:-1}" != "1" ]]; then
    echo "[entrypoint] SSH disabled (ENABLE_SSH!=1)."
    return 0
  fi

  mkdir -p /var/run/sshd /etc/ssh
  if [[ ! -f /etc/ssh/ssh_host_rsa_key ]]; then
    echo "[entrypoint] Generating SSH host keys…"
    ssh-keygen -A
  fi

  SSH_USER_NAME="${SSH_USER:-admin}"
  SSH_PASS="${SSH_PASSWORD:-admin}"

  if [[ "$SSH_USER_NAME" == "root" ]]; then
    echo "root:${SSH_PASS}" | chpasswd
  else
    if ! id -u "$SSH_USER_NAME" >/dev/null 2>&1; then
      useradd -m -s /bin/bash "$SSH_USER_NAME"
    fi
    echo "${SSH_USER_NAME}:${SSH_PASS}" | chpasswd
    usermod -aG "$WEB_GROUP" "$SSH_USER_NAME" 2>/dev/null || true
  fi

  echo "root:${SSH_PASS}" | chpasswd

  echo "[entrypoint] Starting sshd (user=${SSH_USER_NAME})…"
  /usr/sbin/sshd
}

start_renewal_cron() {
  if [[ "${ENABLE_RENEWAL_CRON:-1}" != "1" ]]; then
    echo "[entrypoint] Renewal mail cron disabled (ENABLE_RENEWAL_CRON!=1)."
    rm -f /etc/cron.d/mecm-renewal-mails
    return 0
  fi

  if [[ ! -x /usr/sbin/cron ]] && ! command -v cron >/dev/null 2>&1; then
    echo "[entrypoint] WARNING: package « cron » absent de l’image — rebuild requis (Dockerfile)."
    return 0
  fi

  local schedule="${RENEWAL_MAIL_CRON:-0 8 * * *}"
  local script="${APP_ROOT}/bin/send-renewal-mails.php"
  local log_file="${APP_ROOT}/storage/logs/renewal-mails.log"

  if [[ ! -f "$script" ]]; then
    echo "[entrypoint] WARNING: $script introuvable — cron non installé."
    return 0
  fi
  chmod +x "$script" 2>/dev/null || true

  mkdir -p "$(dirname "$log_file")"
  touch "$log_file"
  chown "$WEB_USER:$WEB_GROUP" "$log_file" 2>/dev/null || true

  # cron.d : minute hour dom month dow user command + newline finale obligatoire
  cat > /etc/cron.d/mecm-renewal-mails <<EOF
# MECM Packaging Manager — rappels renouvellement (dernier packager)
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
MAILTO=""
TZ=${TZ:-Europe/Paris}
APP_ENV=${APP_ENV:-production}
APP_DEBUG=${APP_DEBUG:-0}
APP_URL=${APP_URL:-}
${schedule} root /usr/local/bin/php ${script} >> ${log_file} 2>&1

EOF
  chmod 0644 /etc/cron.d/mecm-renewal-mails

  # Relancer cron proprement (évite double démon au restart)
  if pgrep -x cron >/dev/null 2>&1 || pgrep -x crond >/dev/null 2>&1; then
    pkill -x cron 2>/dev/null || pkill -x crond 2>/dev/null || true
    sleep 0.2
  fi

  echo "[entrypoint] Starting cron — rappels mailing : « ${schedule} » (TZ=${TZ:-Europe/Paris})"
  if [[ -x /usr/sbin/cron ]]; then
    /usr/sbin/cron
  else
    cron
  fi

  if pgrep -x cron >/dev/null 2>&1 || pgrep -x crond >/dev/null 2>&1; then
    echo "[entrypoint] cron OK — job: ${schedule} → ${script}"
  else
    echo "[entrypoint] WARNING: cron n’a pas démarré."
  fi
}

start_sshd
start_renewal_cron

echo "[entrypoint] Starting Apache…"
exec "$@"
