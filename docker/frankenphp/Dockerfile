FROM dunglas/frankenphp:1.12.4-php8.5-alpine

# Install mlocati/docker-php-extension-installer for easier extension management
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install system dependencies and PHP extensions
# hadolint ignore=DL3018,DL3019
RUN apk upgrade --no-cache musl musl-utils c-ares \
    && apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    && install-php-extensions \
    pdo_pgsql \
    intl \
    opcache \
    zip \
    bcmath

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV SERVER_NAME=:80

# Fetch and extract vendor packages first, cached independently of
# application source changes as long as composer.json/composer.lock/
# symfony.lock don't change. No autoloader yet: the full source tree
# (needed for the classmap) isn't copied in until the next step.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader

# Copy application
COPY . .

# Copy PHP configuration
COPY docker/frankenphp/php.prod.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/frankenphp/Caddyfile.prod /etc/caddy/Caddyfile

# Re-run install against the full source tree: packages are already
# fetched (cached above), so this only builds the optimized autoloader,
# then warm the cache and fix permissions.
RUN export APP_SECRET=build-secret \
    DATABASE_URL='postgresql://insee:insee@localhost:5432/insee_city?serverVersion=16&charset=utf8' \
    && composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction --no-scripts \
    && php bin/console cache:warmup --env=prod --no-debug \
    && chown -R www-data:www-data /app/var

# Expose ports (HTTP/HTTPS/HTTP3)
EXPOSE 80 443 443/udp

# FrankenPHP server
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
