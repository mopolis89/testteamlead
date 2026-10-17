<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\NoteRepository;
use App\Validators\NoteValidator;
use Throwable;

/**
 * Весь API заметок: роутинг и обработчики для /api/notes
 */
class NoteController
{
    public function __construct(private NoteRepository $notes)
    {
    }

    // сюда приходит каждый запрос к /api, на выходе всегда Response
    public function handle(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (HttpException $e) {
            // наши ошибки (404, 422 и т.д.) отдаём клиенту как есть
            return Response::error($e->status, $e->errorCode, $e->getMessage(), $e->details, $e->headers);
        } catch (Throwable $e) {
            // всё остальное - баг или проблема с базой. клиенту общий текст, подробности в лог
            error_log((string) $e);

            return Response::error(500, 'internal_error', 'Внутренняя ошибка сервера.');
        }
    }

    // простой роутер: сначала смотрим на путь, потом выбираем обработчик по методу
    private function dispatch(Request $request): Response
    {
        // список и создание
        if (preg_match('#^/api/notes/?$#', $request->path)) {
            return match ($request->method) {
                'GET' => $this->index($request),
                'POST' => $this->store($request),
                default => throw $this->methodNotAllowed('GET, POST'),
            };
        }

        // одна заметка по id. id только из цифр, всё остальное ниже уйдёт в 404
        if (preg_match('#^/api/notes/(\d+)/?$#', $request->path, $m)) {
            $id = (int) $m[1];

            return match ($request->method) {
                'GET' => $this->show($id),
                'PUT' => $this->update($request, $id),
                'DELETE' => $this->destroy($id),
                default => throw $this->methodNotAllowed('GET, PUT, DELETE'),
            };
        }

        throw HttpException::notFound('Маршрут не найден.');
    }

    // GET /api/notes?limit=20&offset=0&q=...
    private function index(Request $request): Response
    {
        $limit = (int) ($request->query['limit'] ?? 20);
        $offset = (int) ($request->query['offset'] ?? 0);
        $search = $request->query['q'] ?? '';

        // на кривые значения не ругаемся, просто подрезаем до допустимых
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $search = is_string($search) ? trim($search) : '';

        return new Response(200, $this->notes->paginate($limit, $offset, $search));
    }

    // GET /api/notes/{id}
    private function show(int $id): Response
    {
        $note = $this->notes->find($id);
        if (!$note) {
            throw HttpException::notFound();
        }

        return new Response(200, ['data' => $note]);
    }

    // POST /api/notes - создаём и отдаём 201 + Location на новую заметку
    private function store(Request $request): Response
    {
        $data = NoteValidator::validate($request->json());
        $note = $this->notes->create($data['title'], $data['content']);

        return new Response(201, ['data' => $note], ['Location' => '/api/notes/' . $note['id']]);
    }

    // PUT /api/notes/{id} - заголовок и текст заменяются целиком
    private function update(Request $request, int $id): Response
    {
        $data = NoteValidator::validate($request->json());

        $note = $this->notes->update($id, $data['title'], $data['content']);
        if (!$note) {
            throw HttpException::notFound();
        }

        return new Response(200, ['data' => $note]);
    }

    // DELETE /api/notes/{id}, при успехе 204 без тела
    private function destroy(int $id): Response
    {
        if (!$this->notes->delete($id)) {
            throw HttpException::notFound();
        }

        return new Response(204);
    }

    // 405, в заголовке Allow перечисляем, какие методы можно
    private function methodNotAllowed(string $allowed): HttpException
    {
        return new HttpException(405, 'method_not_allowed', 'Метод не поддерживается.', [], ['Allow' => $allowed]);
    }
}
