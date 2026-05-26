# api-example

REST API with Bearer token authentication, two resources (`users` and `posts`), rate
limiting, HTML sanitization, and permission-based authorization.

## Setup

```bash
cd examples/api-example
composer install
php -S localhost:8080 -t public
```

The SQLite database is created automatically at `var/db.sqlite` on first request.
To create it manually:

```bash
sqlite3 var/db.sqlite < var/schema.sql
```

## Bearer token format

```
Authorization: Bearer {subject_id}|{comma-roles}|{comma-permissions}
```

Examples:

```bash
# Read-only user
Authorization: Bearer 1||users.read,posts.read

# Admin with all permissions
Authorization: Bearer 2|admin|users.read,users.create,users.update,users.delete,posts.read,posts.create,posts.update,posts.delete
```

## Try it

### Users

```bash
# Create a user
curl -s -X POST http://localhost:8080/users \
  -d "name=Ada+Lovelace&email=ada@example.com&status=active" \
  -H "Authorization: Bearer 2|admin|users.create"

# List all users
curl -s http://localhost:8080/users \
  -H "Authorization: Bearer 1||users.read"

# Find by ID
curl -s "http://localhost:8080/users?id=1" \
  -H "Authorization: Bearer 1||users.read"

# Filter by status
curl -s "http://localhost:8080/users?status=active" \
  -H "Authorization: Bearer 1||users.read"

# Update
curl -s -X PUT http://localhost:8080/users \
  -d "id=1&name=Ada+L.&status=inactive" \
  -H "Authorization: Bearer 2|admin|users.update"

# Delete
curl -s -X DELETE http://localhost:8080/users \
  -d "id=1" \
  -H "Authorization: Bearer 2|admin|users.delete"
```

### Posts

```bash
# Create a post
curl -s -X POST http://localhost:8080/posts \
  -d "user_id=1&title=Hello+World&body=My+first+post&status=draft" \
  -H "Authorization: Bearer 2|admin|posts.create"

# List all posts
curl -s http://localhost:8080/posts \
  -H "Authorization: Bearer 1||posts.read"

# Filter by status
curl -s "http://localhost:8080/posts?status=draft" \
  -H "Authorization: Bearer 1||posts.read"

# Bulk-publish all draft posts
curl -s -X PATCH http://localhost:8080/posts \
  -d "_crud_operation=bulk_update&_criteria[status]=draft&status=published" \
  -H "Authorization: Bearer 2|admin|posts.bulk_update"
```

## What's demonstrated

- Two resources (`users`, `posts`) registered in `ResourceRegistry`
- `BearerTokenAuthAdapter` — authentication via `Authorization: Bearer` header
- `AuthorizeMiddleware` — `{resource}.{operation}` permission check
- `RateLimitMiddleware` — 60 requests/minute per client IP
- `SanitizeMiddleware` — HTML-encodes all string input
- Permission denied → HTTP 403, rate limit → HTTP 429

## Next steps

Add role-based policies → see `examples/rbac-example`
