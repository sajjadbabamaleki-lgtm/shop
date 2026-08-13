# The storefront, as one container.
#
# Apache with mod_php rather than anything faster on purpose: this image could
# not be built or run in the session that wrote it — the egress policy there
# blocked Docker Hub's blob CDN — so every choice in here is the most
# documented one rather than the best one. It is the recipe with the fewest
# moving parts that can be wrong: no fpm socket, no supervisor, no reverse
# proxy config, no Caddyfile path to get right. Swap it for FrankenPHP or
# php-fpm + nginx once someone has run it once and can measure the difference.
#
# There is no Node stage because the page needs none: it loads the template's
# own stylesheets out of public/assets, and no view carries a @vite directive.
#
# Build from this directory:
#   docker build -t vikyplus-storefront storefront/

# ---- dependencies ----------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

# The lock file decides every version, so nothing is resolved here and the
# platform requirements of the *builder* are beside the point — the runtime
# image below is what has to satisfy them, and it does.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist \
        --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---- runtime ---------------------------------------------------------------
FROM php:8.4-apache AS app

# pdo_pgsql for the catalogue, intl because prices are formatted through ICU
# (see app/Support/persian.php — without it the first Toman on the page is a
# fatal), opcache because this is production.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip opcache; \
    apt-get purge -y --auto-remove libicu-dev libzip-dev; \
    rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/opcache.ini "$PHP_INI_DIR/conf.d/99-opcache.ini"

# Laravel serves out of public/, and its .htaccess needs mod_rewrite.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN set -eux; \
    a2enmod rewrite; \
    sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html

# Apache writes nothing else; these two are the only writable paths, and the
# app fails at boot rather than at the first request if they are not.
RUN set -eux; \
    mkdir -p storage/framework/cache storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Hosts that hand the port over in $PORT are handled in the entrypoint; 80 is
# the default for the ones that do not.
EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
