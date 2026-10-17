<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

// все запросы к таблице notes. значения передаём только через параметры, в SQL ничего не склеиваем
class NoteRepository
{
    public function __construct(private PDO $db)
    {
    }

    // одна страница списка + общее количество для пагинации
    public function paginate(int $limit, int $offset, string $search = ''): array
    {
        $where = '';
        $params = [];

        // поиск по подстроке в заголовке и в тексте
        if ($search !== '') {
            $where = 'WHERE title LIKE :q OR content LIKE :q';
            $params[':q'] = '%' . $search . '%';
        }

        // сколько всего заметок подходит под условие
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM notes $where");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // сама страница, сначала новые. при одинаковой дате выше та, что создана позже (больше id)
        $stmt = $this->db->prepare("SELECT * FROM notes $where ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        // limit и offset передаём именно как числа
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
        ];
    }

    // null, если такой заметки нет
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM notes WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    // RETURNING * сразу отдаёт созданную строку вместе с id, второй SELECT не нужен
    public function create(string $title, string $content): array
    {
        // время храним в UTC, в формате ISO 8601
        $now = gmdate('Y-m-d\TH:i:s\Z');

        $stmt = $this->db->prepare(
            'INSERT INTO notes (title, content, created_at, updated_at) VALUES (?, ?, ?, ?) RETURNING *'
        );
        $stmt->execute([$title, $content, $now, $now]);

        return $stmt->fetch();
    }

    // created_at не трогаем, обновляется только updated_at. null - заметки с таким id нет
    public function update(int $id, string $title, string $content): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE notes SET title = ?, content = ?, updated_at = ? WHERE id = ? RETURNING *'
        );
        $stmt->execute([$title, $content, gmdate('Y-m-d\TH:i:s\Z'), $id]);

        return $stmt->fetch() ?: null;
    }

    // true, если строка действительно удалилась
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM notes WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
}
