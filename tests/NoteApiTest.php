<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\NoteController;
use App\Database;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\NoteRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// контроллер вызываем напрямую, без http. база в памяти, на каждый тест своя
class NoteApiTest extends TestCase
{
    private NoteController $controller;

    protected function setUp(): void
    {
        $db = Database::connect(':memory:');
        Database::migrate($db);

        $this->controller = new NoteController(new NoteRepository($db));
    }

    // отправляет запрос в контроллер так, будто он пришёл по HTTP
    private function call(string $method, string $path = '/api/notes', ?array $body = null, array $query = []): Response
    {
        $json = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE);

        return $this->controller->handle(new Request($method, $path, $query, $json));
    }

    private function createNote(string $title = 'Заметка', string $content = ''): array
    {
        return $this->call('POST', body: ['title' => $title, 'content' => $content])->data['data'];
    }

    public function testCreateNote(): void
    {
        // пробелы вокруг заголовка должны обрезаться
        $response = $this->call('POST', body: ['title' => '  Покупки  ', 'content' => 'Молоко']);

        $this->assertSame(201, $response->status);

        $note = $response->data['data'];
        $this->assertSame('Покупки', $note['title']);
        $this->assertSame('Молоко', $note['content']);
        $this->assertSame('/api/notes/' . $note['id'], $response->headers['Location']);
    }

    public function testContentIsOptional(): void
    {
        $response = $this->call('POST', body: ['title' => 'Без текста']);

        $this->assertSame(201, $response->status);
        $this->assertSame('', $response->data['data']['content']);
    }

    public function testShowNote(): void
    {
        $note = $this->createNote('Покупки', 'Молоко');

        $response = $this->call('GET', '/api/notes/' . $note['id']);

        $this->assertSame(200, $response->status);
        $this->assertSame($note, $response->data['data']);
    }

    public function testListNewestFirst(): void
    {
        // обе создаются в одну секунду, так что порядок решает id
        $this->createNote('Первая');
        $this->createNote('Вторая');

        $response = $this->call('GET');

        $this->assertSame(200, $response->status);
        $this->assertSame(2, $response->data['meta']['total']);
        $this->assertSame('Вторая', $response->data['data'][0]['title']);
    }

    public function testUpdateNote(): void
    {
        $note = $this->createNote('Старый', 'текст');

        $response = $this->call('PUT', '/api/notes/' . $note['id'], ['title' => 'Новый', 'content' => '']);

        $this->assertSame(200, $response->status);
        $this->assertSame('Новый', $response->data['data']['title']);
        $this->assertSame('', $response->data['data']['content']);
        // дата создания при обновлении не меняется
        $this->assertSame($note['created_at'], $response->data['data']['created_at']);
    }

    public function testDeleteNote(): void
    {
        $path = '/api/notes/' . $this->createNote()['id'];

        $this->assertSame(204, $this->call('DELETE', $path)->status);
        $this->assertSame(404, $this->call('GET', $path)->status);
        $this->assertSame(404, $this->call('DELETE', $path)->status);
    }

    #[DataProvider('invalidNotes')]
    public function testValidation(array $body, string $field): void
    {
        $response = $this->call('POST', body: $body);

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey($field, $response->data['error']['details']);
        // при ошибке в базу ничего не попало
        $this->assertSame(0, $this->call('GET')->data['meta']['total']);
    }

    public static function invalidNotes(): array
    {
        return [
            'no title' => [['content' => 'текст'], 'title'],
            'empty title' => [['title' => ''], 'title'],
            'spaces only' => [['title' => '   '], 'title'],
            'title not string' => [['title' => ['a']], 'title'],
            'title too long' => [['title' => str_repeat('a', 201)], 'title'],
            'content not string' => [['title' => 'Ok', 'content' => 123], 'content'],
            'content too long' => [['title' => 'Ok', 'content' => str_repeat('a', 20001)], 'content'],
        ];
    }

    public function testLengthInCharacters(): void
    {
        // 200 букв "Я" = 400 байт, но лимит в символах
        $response = $this->call('POST', body: ['title' => str_repeat('Я', 200)]);

        $this->assertSame(201, $response->status);
    }

    public function testInvalidJson(): void
    {
        $response = $this->controller->handle(new Request('POST', '/api/notes', [], '{"title":'));

        $this->assertSame(400, $response->status);
        $this->assertSame('invalid_json', $response->data['error']['code']);
    }

    #[DataProvider('itemMethods')]
    public function testMissingNote(string $method): void
    {
        $body = $method === 'PUT' ? ['title' => 'Ok'] : null;

        $this->assertSame(404, $this->call($method, '/api/notes/999', $body)->status);
    }

    public static function itemMethods(): array
    {
        return [['GET'], ['PUT'], ['DELETE']];
    }

    // нечисловой id - это тоже 404, такого маршрута просто нет
    public function testUnknownRoutes(): void
    {
        foreach (['/api/notes/abc', '/api/notes/-1', '/api/notes/1/edit', '/api/other'] as $path) {
            $this->assertSame(404, $this->call('GET', $path)->status, $path);
        }
    }

    public function testMethodNotAllowed(): void
    {
        $response = $this->call('PATCH', '/api/notes/1');

        $this->assertSame(405, $response->status);
        $this->assertSame('GET, PUT, DELETE', $response->headers['Allow']);
    }

    public function testPagination(): void
    {
        $this->createNote('Альфа');
        $this->createNote('Бета');
        $this->createNote('Гамма');

        $page = $this->call('GET', query: ['limit' => '1', 'offset' => '1']);

        $this->assertSame(['total' => 3, 'limit' => 1, 'offset' => 1], $page->data['meta']);
        $this->assertSame('Бета', $page->data['data'][0]['title']);
    }

    // слишком большой limit и отрицательный offset не ошибка, их просто подрезаем
    public function testPaginationLimits(): void
    {
        $meta = $this->call('GET', query: ['limit' => '1000', 'offset' => '-5'])->data['meta'];

        $this->assertSame(100, $meta['limit']);
        $this->assertSame(0, $meta['offset']);
    }

    // ищем и по заголовку, и по тексту
    public function testSearch(): void
    {
        $this->createNote('Покупки', 'молоко, хлеб');
        $this->createNote('Работа', 'купить молоко по дороге');
        $this->createNote('Идеи');

        $this->assertSame(2, $this->call('GET', query: ['q' => 'молоко'])->data['meta']['total']);
        $this->assertSame(1, $this->call('GET', query: ['q' => 'Идеи'])->data['meta']['total']);
        $this->assertSame(0, $this->call('GET', query: ['q' => 'нет такого'])->data['meta']['total']);
    }

    // SQL и HTML сохраняются как обычный текст, таблица при этом цела
    public function testSqlAndHtmlStoredAsIs(): void
    {
        $body = ['title' => "'); DROP TABLE notes; --", 'content' => '<script>alert(1)</script>'];

        $note = $this->call('POST', body: $body)->data['data'];

        $this->assertSame($body['title'], $note['title']);
        $this->assertSame($body['content'], $note['content']);
        $this->assertSame(1, $this->call('GET')->data['meta']['total']);
    }
}
