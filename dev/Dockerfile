# Kimai with dev dependencies (PHPUnit); the plugin is mounted at runtime.
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libpng-dev libzip-dev libxslt1-dev libonig-dev \
    && docker-php-ext-install intl gd zip xsl pdo_mysql mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG KIMAI_VERSION=2.70.2
RUN git clone --depth 1 --branch "$KIMAI_VERSION" https://github.com/kimai/kimai.git /opt/kimai
WORKDIR /opt/kimai
RUN COMPOSER_MEMORY_LIMIT=-1 composer install --no-interaction --no-progress --no-scripts
