# Тихо

Сервис заметок: REST API на чистом PHP (без фреймворков) + SQLite, фронт на Vue 3.

## Запуск

Нужен Docker с Compose v2:

```bash
docker compose up -d
```

Первый раз собирается пару минут. База создаётся сама при старте контейнера.

После запуска:

- http://localhost:8080 - интерфейс
- http://localhost:8080/docs/ - Swagger (грузится с CDN, нужен интернет)
- http://localhost:8080/openapi.yaml - спека

Если порт 8080 занят, поменяйте его в `docker-compose.yml`. Авторизации нет, список заметок общий для всех.

Полезное:

```bash
docker compose ps              # статус
docker compose logs -f app     # логи
docker compose up -d --build   # пересобрать после правок
```

Данные лежат в volume `notes_data`, `docker compose down` их не трогает (`down -v` удалит).

## API

| Метод  | Путь              | Описание                   | Ответ            |
|--------|-------------------|----------------------------|------------------|
| GET    | `/api/notes`      | список, пагинация и поиск  | 200              |
| GET    | `/api/notes/{id}` | одна заметка               | 200              |
| POST   | `/api/notes`      | создать                    | 201 + `Location` |
| PUT    | `/api/notes/{id}` | обновить                   | 200              |
| DELETE | `/api/notes/{id}` | удалить                    | 204              |

Поля заметки:

- `title` обязательный, до 200 символов, пробелы по краям обрезаются
- `content` необязательный, до 20 000 символов, по умолчанию пустая строка

Если что-то не так, вернётся 422 с описанием ошибок по полям. Лишние поля просто игнорируются.

Параметры списка: `limit` (по умолчанию 20, максимум 100), `offset` и `q` для поиска
по заголовку и тексту. Сначала идут новые заметки, даты в UTC.

Примеры:

```bash
# создать
curl -i -X POST http://localhost:8080/api/notes \
  -H 'Content-Type: application/json' \
  -d '{"title":"Покупки","content":"Молоко, хлеб"}'

# список и поиск
curl 'http://localhost:8080/api/notes?limit=10&offset=0'
curl --get http://localhost:8080/api/notes --data-urlencode 'q=Покупки'

# одна заметка
curl http://localhost:8080/api/notes/1

# обновить
curl -X PUT http://localhost:8080/api/notes/1 \
  -H 'Content-Type: application/json' \
  -d '{"title":"Покупки на выходные","content":"Кофе"}'

# удалить
curl -i -X DELETE http://localhost:8080/api/notes/1
```

Заметка:

```json
{
  "data": {
    "id": 1,
    "title": "Покупки",
    "content": "Молоко, хлеб",
    "created_at": "2026-10-16T12:00:00Z",
    "updated_at": "2026-10-16T12:00:00Z"
  }
}
```

Список: `{"data": [...], "meta": {"total": 1, "limit": 20, "offset": 0}}`

Ошибка:

```json
{
  "error": {
    "code": "validation_error",
    "message": "Проверьте переданные данные.",
    "details": { "title": "Введите заголовок." }
  }
}
```

Коды ошибок: `invalid_json` 400, `not_found` 404, `method_not_allowed` 405, `validation_error` 422,
`internal_error` 500.

## Тесты

```bash
docker compose exec app vendor/bin/phpunit
```

Тесты в `tests/NoteApiTest.php`: CRUD, валидация, пагинация и поиск. Контроллер вызывается напрямую,
база в памяти, так что рабочие данные не затрагиваются.

## Структура

```
src/
  Controllers/NoteController.php   роутинг и обработчики
  Repositories/NoteRepository.php  запросы к SQLite
  Validators/NoteValidator.php     валидация
  Http/                            Request, Response, HttpException
  Database.php
public/index.php                   точка входа
database/schema.sql
bin/migrate.php                    создаёт базу, вызывается из entrypoint
router.php                         роутер для php -S
frontend/                          Vue + Vite
docker/                            конфиги apache, php, entrypoint
```

## Без Docker

Понадобятся PHP 8.3+ (`pdo_sqlite`, `mbstring`), Composer и Node.js 22+.

```bash
composer install
php bin/migrate.php

cd frontend && npm ci && npm run build && cd ..
cp -R frontend/dist/. public/
cp openapi.yaml public/

php -S 127.0.0.1:8080 -t public router.php
```

Для работы над фронтом: `npm run dev` в папке `frontend`, vite проксирует `/api` и `/docs` на 127.0.0.1:8080.

Тесты: `composer test`
