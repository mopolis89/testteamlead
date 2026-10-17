<?php

declare(strict_types=1);

namespace App\Http;

// ответ API: статус, данные и дополнительные заголовки. данные уходят в JSON
class Response
{
    public function __construct(
        public readonly int $status,
        public readonly ?array $data = null,
        public readonly array $headers = [],
    ) {
    }

    // все ошибки в одном формате: {"error": {"code": ..., "message": ..., "details": ...}}
    public static function error(int $status, string $code, string $message, array $details = [], array $headers = []): self
    {
        $error = ['code' => $code, 'message' => $message];
        // details только когда есть ошибки по конкретным полям
        if ($details) {
            $error['details'] = $details;
        }

        return new self($status, ['error' => $error], $headers);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // у 204 тела быть не должно
        if ($this->status !== 204) {
            header('Content-Type: application/json; charset=utf-8');
            // UNESCAPED_UNICODE, чтобы кириллица уходила как есть, а не в виде \u0417\u0430...
            echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
