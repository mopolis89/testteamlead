<?php

declare(strict_types=1);

namespace App\Http;

// запрос в виде простого объекта. в тестах его можно собрать руками, без $_SERVER и $_GET
class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly string $body = '',
    ) {
    }

    // собираем запрос из того, что дал PHP: метод, путь без query string, $_GET и сырое тело
    public static function fromGlobals(): self
    {
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            $_GET,
            file_get_contents('php://input') ?: '',
        );
    }

    // тело как массив. если JSON битый или это вообще не JSON, сразу 400
    public function json(): array
    {
        $data = json_decode($this->body, true);
        if (!is_array($data)) {
            throw new HttpException(400, 'invalid_json', 'Некорректный JSON.');
        }

        return $data;
    }
}
