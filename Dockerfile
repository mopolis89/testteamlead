# сборка фронта
FROM node:22-bookworm-slim AS frontend

WORKDIR /build
# сначала только package*.json: пока зависимости не менялись, слой с npm ci берётся из кэша
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build


# основной образ: Apache + PHP, из первой стадии забираем только собранный фронт
FROM php:8.3-apache-bookworm

# unzip нужен composer'у, mod_rewrite - для перенаправления /api в index.php
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

# зависимости PHP тоже ставим до копирования кода, ради кэша.
# dev-зависимости (phpunit) оставляем, чтобы тесты можно было гонять прямо в контейнере
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --no-autoloader

COPY src/ src/
COPY database/ database/
COPY bin/ bin/
COPY tests/ tests/
COPY phpunit.xml ./
COPY public/index.php public/
COPY openapi.yaml public/
# собранный фронт кладём в public/, рядом с index.php
COPY --from=frontend /build/dist/ public/

# автозагрузка и папка под базу, в которую сможет писать Apache
RUN composer dump-autoload --optimize \
    && mkdir -p storage \
    && chown -R www-data:www-data storage

# перед запуском Apache entrypoint создаёт базу, см. docker/entrypoint.sh
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV DB_PATH=/var/www/html/storage/notes.sqlite

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
