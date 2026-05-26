# basic-crud

Minimal Bamise CRUD example. No authentication, no CSRF — suitable for local development only.

## Setup

```bash
cd examples/basic-crud
composer install
php -S localhost:8080 -t public
```

The SQLite database is created automatically at `var/db.sqlite` on first request.

## Try it

```bash
# Create
curl -s -X POST http://localhost:8080/users \
  -d "name=Ada+Lovelace&email=ada@example.com"

# List all
curl -s http://localhost:8080/users

# Find by ID
curl -s "http://localhost:8080/users?id=1"

# Filter
curl -s "http://localhost:8080/users?name=Ada+Lovelace"

# Paginate
curl -s "http://localhost:8080/users?limit=5&offset=0"

# Update
curl -s -X PUT http://localhost:8080/users \
  -d "id=1&name=Ada+L."

# Delete
curl -s -X DELETE http://localhost:8080/users \
  -d "id=1"
```

## What's demonstrated

- `ResourceDefinitionInterface` — table name, primary key, fillable columns
- `PdoRepository` via `PdoRepositoryFactory` — SQLite persistence
- `PipelineBuilder` with no middleware (pass-through pipeline)
- `CrudApplication::handle()` dispatching all six operations

## Next steps

Add authentication → see `examples/api-example`
Add role-based access control → see `examples/rbac-example`
