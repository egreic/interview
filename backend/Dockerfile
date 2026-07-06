# Dev runtime image (docker-compose only — production is git-deployed on the
# OVH VPS). PHP pinned to 8.3 per the arbitrated version set.
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libssl-dev libicu-dev $PHPIZE_DEPS \
    && pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis \
    && docker-php-ext-install intl \
    && apt-get purge -y $PHPIZE_DEPS && apt-get autoremove -y && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
