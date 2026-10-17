<?php

declare(strict_types=1);

namespace App;

use PDO;

// подключение к SQLite и создание таблицы
class Database
{
    public static function connect(string $path): PDO
    {
        $pdo = new PDO('sqlite:' . $path);
        // ошибки SQL сразу исключениями, строки из базы как ассоциативные массивы
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // WAL, чтобы чтение не ждало запись. базе в памяти (тесты) он не нужен
        if ($path !== ':memory:') {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }

        return $pdo;
    }

    // в schema.sql только CREATE ... IF NOT EXISTS, поэтому можно вызывать хоть при каждом старте
    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    }
}
