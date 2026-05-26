CREATE TABLE IF NOT EXISTS uploads (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    original_name   TEXT    NOT NULL,
    stored_filename TEXT    NOT NULL,
    size            INTEGER NOT NULL,
    mime_type       TEXT    NOT NULL,
    uploaded_at     TEXT    NOT NULL
);
