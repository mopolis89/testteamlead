<?php

declare(strict_types=1);

namespace App\Validators;

use App\Http\HttpException;

// проверка тела POST/PUT
class NoteValidator
{
    /**
     * Возвращает очищенные title и content, а если что-то не так, кидает 422 с ошибками по полям
     */
    public static function validate(array $data): array
    {
        $errors = [];

        // заголовок обязателен, пробелы по краям обрезаем.
        // длину считаем через mb_strlen, то есть в символах, а не в байтах
        $title = $data['title'] ?? '';
        $title = is_string($title) ? trim($title) : '';
        if ($title === '') {
            $errors['title'] = 'Введите заголовок.';
        } elseif (mb_strlen($title) > 200) {
            $errors['title'] = 'Заголовок должен быть не длиннее 200 символов.';
        }

        // текст можно не передавать, тогда будет пустая строка
        $content = $data['content'] ?? '';
        if (!is_string($content)) {
            $errors['content'] = 'Текст заметки должен быть строкой.';
        } elseif (mb_strlen($content) > 20000) {
            $errors['content'] = 'Текст должен быть не длиннее 20 000 символов.';
        }

        // отдаём все ошибки сразу, чтобы фронт подсветил все поля за один раз
        if ($errors) {
            throw HttpException::validation($errors);
        }

        return ['title' => $title, 'content' => $content];
    }
}
