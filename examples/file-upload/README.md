# file-upload

Stores file metadata in SQLite via standard Bamise CRUD. Physical file handling
(validation, moving to disk) is done by a custom `FileUploadMiddleware` before
the strategy runs.

## What it demonstrates

- **Custom `MiddlewareInterface`** — intercepts Create operations, handles `$_FILES`,
  replaces raw file input with extracted metadata before the strategy runs
- **Custom `CrudRequestInterface`** — `PhpRequest` merges `$_FILES` info into `input()`
  under the `_uploaded_file` key so middleware can detect uploads
- **Standard CRUD storage** — file metadata (name, size, type, path) stored via
  the built-in `CreateStrategy` / `PdoRepository`

## Setup

```bash
cd examples/file-upload
composer install
mkdir -p var/uploads
php -S localhost:8080 -t public
```

## Try it

### Browser (HTML form)

```
open http://localhost:8080/uploads/form
```

### curl

```bash
# Upload a file
curl -s -X POST http://localhost:8080/uploads \
  -F "file=@/path/to/image.jpg"

# Expected response:
# {
#   "success": true,
#   "data": {
#     "original_name": "image.jpg",
#     "stored_filename": "abc123.jpg",
#     "size": 45678,
#     "mime_type": "image/jpeg",
#     "uploaded_at": "2026-01-01 12:00:00",
#     "id": 1
#   },
#   "errors": [],
#   "meta": {"operation": "create"}
# }

# List all uploads
curl -s http://localhost:8080/uploads

# Find by ID
curl -s "http://localhost:8080/uploads?id=1"

# Delete a record (does not delete the physical file in this example)
curl -s -X DELETE http://localhost:8080/uploads \
  -d "id=1"
```

## Allowed file types

`image/jpeg`, `image/png`, `image/gif`, `application/pdf` — maximum 5 MB.

Disallowed types return HTTP 400:

```json
{"success": false, "errors": {"message": "File type not allowed."}, "meta": {...}}
```

## Architecture

```
POST /uploads (multipart/form-data)
    ↓
PhpRequest::input() → merges $_FILES info under '_uploaded_file'
    ↓
FileUploadMiddleware (priority 300)
    → validates type and size
    → moves to var/uploads/{random}.ext
    → replaces inputData with {original_name, stored_filename, size, mime_type, uploaded_at}
    ↓
CreateStrategy → PdoRepository::insert()
    ↓
ResponseEnvelope with stored metadata
```
