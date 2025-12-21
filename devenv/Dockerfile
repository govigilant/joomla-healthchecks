FROM php:8.3-cli AS build

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git zip unzip libzip-dev libicu-dev libxml2-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install intl mysqli opcache pdo_mysql gd zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG JOOMLA_VERSION=5.4.0
WORKDIR /srv
RUN set -eux; \
    curl -L -o joomla.zip "https://update.joomla.org/releases/${JOOMLA_VERSION}/Joomla_${JOOMLA_VERSION}-Stable-Full_Package.zip"; \
    mkdir -p app; \
    unzip joomla.zip -d app; \
    rm joomla.zip

FROM php:8.3-fpm

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git zip unzip libzip-dev libicu-dev libxml2-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev default-mysql-client netcat-openbsd nginx \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install intl mysqli opcache pdo_mysql gd zip \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=build /srv/app /var/www/html
COPY --from=build /srv/app /usr/src/joomla
COPY devenv/setup.sh /usr/local/bin/joomla-setup.sh
COPY devenv/entrypoint.sh /usr/local/bin/joomla-entrypoint.sh
COPY devenv/nginx.conf /etc/nginx/conf.d/default.conf
COPY devenv/php.ini /usr/local/etc/php/conf.d/zz-joomla.ini
COPY devenv/ensure-language.php /usr/local/bin/ensure-language.php

RUN chmod +x /usr/local/bin/joomla-setup.sh /usr/local/bin/joomla-entrypoint.sh /usr/local/bin/ensure-language.php \
    && mkdir -p /var/www/html/plugins/system/vigilanthealthchecks \
    && chown -R www-data:www-data /var/www/html

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/joomla-entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]
