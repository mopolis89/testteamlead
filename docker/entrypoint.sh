#!/bin/sh
set -e

# создаём базу до старта apache (volume может быть пустой)
mkdir -p /var/www/html/storage
php /var/www/html/bin/migrate.php
# скрипт выше работает от root, а писать в базу будет apache (www-data)
chown -R www-data:www-data /var/www/html/storage

# дальше стандартный entrypoint образа php, он запустит CMD (apache2-foreground)
exec docker-php-entrypoint "$@"
