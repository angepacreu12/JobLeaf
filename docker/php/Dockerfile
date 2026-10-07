FROM php:8.4-fpm

# PostgreSQL driver for PDO. sodium (encryption) and Argon2id are already
# built into the official PHP image.
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev unzip \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# Composer, taken from its official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Let PHP see the variables docker compose passes in (env_file / environment)
COPY zz-env.conf /usr/local/etc/php-fpm.d/zz-env.conf

COPY entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

WORKDIR /var/www/app
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
