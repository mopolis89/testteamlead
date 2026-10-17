<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

// ошибка, которую надо вернуть клиенту с конкретным HTTP-статусом. контроллер превращает её в JSON
class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    // 404, по умолчанию текст про заметку
    public static function notFound(string $message = 'Заметка не найдена.'): self
    {
        return new self(404, 'not_found', $message);
    }

    // 422, в $details ошибки по полям: ['title' => '...']
    public static function validation(array $details, string $message = 'Проверьте переданные данные.'): self
    {
        return new self(422, 'validation_error', $message, $details);
    }
}
