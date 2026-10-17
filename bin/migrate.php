<?php

// создаёт базу и таблицу, если их ещё нет.
// в Docker запускается из entrypoint.sh при каждом старте контейнера

use App\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = getenv('DB_PATH') ?: dirname(__DIR__) . '/storage/notes.sqlite';

// SQLite сама создаст файл, но папка под него должна существовать
if ($path !== ':memory:' && !is_dir(dirname($path))) {
    mkdir(dirname($path), 0770, true);
}

Database::migrate(Database::connect($path));

echo "Database is ready: $path\n";
