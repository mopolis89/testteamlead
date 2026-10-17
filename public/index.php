<?php

declare(strict_types=1);

// точка входа API: Apache отправляет сюда все запросы к /api

use App\Controllers\NoteController;
use App\Database;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\NoteRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

// путь к базе берём из окружения (в Docker он задан), иначе файл в storage/
$dbPath = getenv('DB_PATH') ?: dirname(__DIR__) . '/storage/notes.sqlite';

try {
    // собираем всё руками: база -> репозиторий -> контроллер
    $controller = new NoteController(new NoteRepository(Database::connect($dbPath)));
    $controller->handle(Request::fromGlobals())->send();
} catch (Throwable $e) {
    // сюда попадём, если не удалось открыть базу
    error_log((string) $e);
    Response::error(500, 'internal_error', 'Внутренняя ошибка сервера.')->send();
}
