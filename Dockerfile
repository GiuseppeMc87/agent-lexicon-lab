FROM composer:2.8.12 AS composer
FROM node:24.22.1-bookworm-slim AS node

FROM php:8.5.12-fpm-bookworm AS application

ARG APP_UID=1000
ARG APP_GID=1000

ENV COMPOSER_HOME=/tmp/composer

RUN apt-get update \
    && apt-get install --no-install-recommends --yes \
        ca-certificates \
        git \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pcntl \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-agent-lexicon.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-agent-lexicon.conf
COPY docker/app/entrypoint.sh /usr/local/bin/agent-lexicon-entrypoint

RUN groupadd --gid "${APP_GID}" app \
    && useradd --uid "${APP_UID}" --gid app --create-home --shell /bin/bash app \
    && chmod 0755 /usr/local/bin/agent-lexicon-entrypoint \
    && mkdir -p /tmp/composer \
    && chown -R app:app /tmp/composer /home/app

WORKDIR /var/www/html

USER app

ENTRYPOINT ["agent-lexicon-entrypoint"]
CMD ["php-fpm", "--nodaemonize"]

FROM application AS frontend

USER root

COPY --from=node /usr/local/ /usr/local/

USER app

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
