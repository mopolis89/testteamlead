-- даты храним строкой в UTC (2026-10-17T12:00:00Z), такие строки нормально сортируются.
-- VARCHAR(200) для SQLite просто TEXT, длину на самом деле проверяет NoteValidator
CREATE TABLE IF NOT EXISTS notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

-- список всегда сортируется по дате создания
CREATE INDEX IF NOT EXISTS idx_notes_created_at ON notes (created_at);
