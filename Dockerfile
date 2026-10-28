FROM php:8.3.36-apache-bookworm

LABEL org.opencontainers.image.title="MECM Packaging Manager" \
      org.opencontainers.image.description="Catalogue + packaging SCCM/MECM" \
      org.opencontainers.image.version="4.7.0"

# Runtime image: PHP/Apache/outils. Le code applicatif est monté depuis l'hôte
# (voir docker-compose.yml : .:/var/www/html) pour que les éditions persistent.

ENV APP_ROOT=/var/www/html \
    APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PHP_INI_DIR=/usr/local/etc/php \
    RUN_MIGRATIONS=1 \
    ENABLE_SSH=1 \
    SSH_USER=admin \
    SSH_PASSWORD='admin' \
    ENABLE_RENEWAL_CRON=1 \
    RENEWAL_MAIL_CRON="0 8 * * *" \
    TZ=Europe/Paris

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        ca-certificates \
        cron \
        git \
        unzip \
        pkg-config \
        openssh-client \
        openssh-server \
        sshpass \
        zlib1g-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        imagemagick \
        librsvg2-bin \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" zip intl gd opcache \
    && (php -m | grep -qi '^mbstring$' || docker-php-ext-install -j"$(nproc)" mbstring) \
    && mkdir -p "${PHP_INI_DIR}/conf.d" \
    && echo 'extension=gd' > "${PHP_INI_DIR}/conf.d/docker-php-ext-gd.ini" \
    && echo 'extension=intl' > "${PHP_INI_DIR}/conf.d/docker-php-ext-intl.ini" \
    && a2enmod rewrite headers \
    && mkdir -p /var/run/sshd /root/.ssh \
    && php -m | grep -qi '^gd$' \
    && php -m | grep -qi '^intl$' \
    # Bookworm = ImageMagick 6 (convert) ; l’app et le check install attendent « magick » (IM7).
    && if ! command -v magick >/dev/null 2>&1; then \
         ln -sf "$(command -v convert)" /usr/local/bin/magick; \
       fi \
    && command -v magick \
    && command -v rsvg-convert \
    && rm -rf /var/lib/apt/lists/*

# Apache DocumentRoot → public/
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configs par défaut (écrasées au démarrage si présentes dans le projet monté)
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/sshd_config /etc/ssh/sshd_config

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Vendor de secours dans l'image (utilisé seulement si le bind-mount n'a pas encore vendor/)
COPY composer.json composer.lock* ./
RUN composer install \
      --no-dev \
      --prefer-dist \
      --no-interaction \
      --no-progress \
      --optimize-autoloader \
    || composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

COPY . .

RUN mkdir -p \
      storage/packages \
      storage/exports \
      storage/bios \
      storage/bios/hp \
      storage/bios/dell \
      storage/backups \
      storage/logs \
      storage/cache \
      storage/cache/sessions \
      storage/cache/tmp \
      public/assets/uploads/icons \
 && chown -R www-data:www-data storage public/assets/uploads \
 && chmod -R ug+rwX storage public/assets/uploads \
 && find storage public/assets/uploads -type d -exec chmod 2775 {} \; \
 && chmod +x docker/entrypoint.sh bin/*.sh bin/*.php 2>/dev/null || true

# Entrypoint figé dans l'image ; compose peut aussi pointer vers le script monté
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

EXPOSE 80 22

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=5 \
  CMD curl -fsS http://127.0.0.1/login >/dev/null || exit 1

ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]
