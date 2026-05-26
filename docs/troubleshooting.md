# Troubleshooting

---

## Installation

### `composer require bamise/framework` fails with "package not found"

Verify the package name is exactly `bamise/framework` (not `bamise/bamise`):

```bash
composer require bamise/framework
```

If the package is not yet on Packagist, install from a local path or VCS:

```json
{
    "repositories": [
        {"type": "path", "url": "../bamise"}
    ],
    "require": {
        "bamise/framework": "*"
    }
}
```

---

### PHP version error during install

Bamise requires PHP **8.4 or higher**. Check your version:

```bash
php -v
```

If your CLI PHP version is older than your web server PHP, specify the binary:

```bash
php8.4 /usr/local/bin/composer require bamise/framework
```

---

### `vendor/autoload.php` not found

You must run `composer install` (or `composer require`) before including the autoloader:

```bash
composer install
```

---

### `pdo_sqlite` extension missing

Check loaded extensions:

```bash
php -m | grep -i sqlite
```

Install on Debian/Ubuntu:

```bash
sudo apt-get install php8.4-sqlite3
```

On Alpine (Docker):

```bash
apk add php84-pdo_sqlite
```

---

## Runtime errors

### `TypeError: CrudApplication::handle() ... argument #2 ($resourceName) ... missing`

The second argument to `handle()` is required. Pass the resource name explicitly:

```php
// Wrong
$envelope = $app->handle($request);

// Correct
$envelope = $app->handle($request, 'users');
```

---

### HTTP 400 — "Invalid resource name" / `InvalidArgumentException`

The resource name you passed to `handle()` is not registered in `ResourceRegistry`.
Check that the name is spelled exactly the same in both `ResourceRegistry` and
`RepositoryResolver`:

```php
$resourceRegistry   = new ResourceRegistry(['users' => $userDefinition]);
$repositoryResolver = new RepositoryResolver(['users' => $repo]);
//                                            ^^^^^^^ must match

$envelope = $app->handle($request, 'users'); // must match
```

---

### HTTP 403 — permission denied when using BearerTokenAuthAdapter

The Bearer token payload is `{id}|{roles}|{permissions}`. The permission string must
match `{resource}.{operation}` exactly:

```bash
# Correct — permission for 'users' resource, 'read' operation
curl -H "Authorization: Bearer 1||users.read" http://localhost:8080/users

# Wrong — 'user' (no 's') will not match resource name 'users'
curl -H "Authorization: Bearer 1||user.read" http://localhost:8080/users
```

Check the `operation.value` in the response meta to see the exact operation string expected.

---

### HTTP 403 — CSRF token mismatch

Common causes:

1. **Stale token** — the form was submitted twice. CSRF tokens are single-use.
2. **Session mismatch** — `_session_id` in the form does not match the session that
   generated the token. Verify `session_id()` is called after `session_start()`.
3. **Wrong cache** — `InMemoryCache` is per-process. If the token was generated in one
   PHP-FPM worker and validated in another, validation will fail.
   Use a shared cache (Redis) for multi-worker production.
4. **Missing fields** — the form must include both `_session_id` and `_csrf` hidden inputs.

---

### HTTP 422 — `MassAssignmentException`

A field in the request body is not listed in `ResourceDefinitionInterface::fillable()`.
Either add it to `fillable()`:

```php
public function fillable(): array { return ['name', 'email', 'status']; }
```

Or remove it from the request.

---

### HTTP 422 — "Resource not found" on update or delete

The record with the given `id` does not exist. Verify the ID:

```bash
curl -s "http://localhost:8080/users?id=1" -H "Authorization: Bearer 1||users.read"
```

If the record exists but you still get 404, confirm you are sending the primary key using
the correct field name (`id` or the value returned by `ResourceDefinitionInterface::primaryKey()`).

---

### HTTP 429 — rate limit exceeded

You have exceeded `RateLimitConfig::maxAttempts` requests in `windowSeconds` seconds.
In development, restart the server to reset the `InMemoryCache`. In production, wait for
the window to expire or flush the relevant Redis key.

---

### Response body is `null` or empty

Check that your front controller calls `json_encode()` on the envelope fields:

```php
echo json_encode([
    'success' => $envelope->success,
    'data'    => $envelope->data,
    'errors'  => $envelope->errors,
    'meta'    => $envelope->meta,
]);
```

Calling `json_encode($envelope)` on the `ResponseEnvelope` object directly will only
serialize public properties.

---

### All operations return empty `data`

If `findAll()` returns an empty array even though records exist, check:

1. **Table name** — `ResourceDefinitionInterface::table()` must match the actual table name.
2. **Database path** — for SQLite, confirm `var/db.sqlite` exists and the schema was applied:

   ```bash
   sqlite3 var/db.sqlite < var/schema.sql
   sqlite3 var/db.sqlite "SELECT * FROM users LIMIT 5;"
   ```

3. **Connection** — verify the DSN in `ConnectionConfig`:

   ```php
   new ConnectionConfig(
       dsn:    'sqlite:' . __DIR__ . '/../../var/db.sqlite',
       driver: DatabaseDriver::Sqlite,
   )
   ```

---

### Update does nothing / returns HTTP 422

Update requires the primary key in the request body (`id=42` or `user_id=42`). Without it,
`UpdateStrategy` cannot locate the record.

```bash
curl -s -X PUT http://localhost:8080/users \
  -d "id=1&name=Updated+Name" \
  -H "Authorization: Bearer 1||users.update"
```

---

### PUT/PATCH body is empty

PHP does not populate `$_POST` for PUT or PATCH requests. Your `CrudRequestInterface::input()`
must read `php://input` for these methods:

```php
public function input(): array
{
    $method = $this->method();
    if (in_array($method, ['PUT', 'PATCH'], true)) {
        parse_str(file_get_contents('php://input') ?: '', $data);
        return $data;
    }
    if ($method === 'POST') {
        return $_POST;
    }
    return $_GET;
}
```

---

### `JwtAuthAdapter` always returns `null`

`JwtAuthAdapter` requires `firebase/php-jwt` to be installed:

```bash
composer require firebase/php-jwt:^6.10
```

Without the library, the adapter silently returns `null` (fail-closed).

---

### Async listeners throw `RuntimeException`

`subscribeAsync()` requires a `QueuePortInterface` passed to `SyncEventDispatcher`:

```php
$eventDispatcher = new SyncEventDispatcher($listenerRegistry, $queue);
```

Without a queue, calling `subscribeAsync()` followed by dispatching the event throws:

```
RuntimeException: QueuePortInterface is required for async event listeners.
```

Either pass a queue implementation or use `subscribe()` (synchronous).

---

## Debugging tips

### Dump the CrudContext in a middleware

Add a temporary middleware to inspect what arrives at the strategy:

```php
$pipeline = (new PipelineBuilder())
    ->add(new class implements \Bamise\Contract\MiddlewareInterface {
        public function process(
            \Bamise\Contract\ValueObject\CrudContext $context,
            \Bamise\Contract\CrudHandlerInterface $next,
        ): \Bamise\Contract\ValueObject\CrudResult {
            error_log('operation: ' . $context->operation->value);
            error_log('input: '     . json_encode($context->inputData));
            error_log('subject: '   . json_encode($context->subject));
            return $next->handle($context);
        }
    }, 50)
    ->build($terminal);
```

### Check the raw SQL (SQLite)

Enable SQLite query logging:

```bash
sqlite3 var/db.sqlite
> .mode list
> SELECT * FROM users;
```

### Verify autoloader

```bash
php -r "require 'vendor/autoload.php'; echo \Bamise\Application\CrudApplication::class . PHP_EOL;"
# Expected: Bamise\Application\CrudApplication
```

### PHPStan type errors

Run static analysis to catch type mistakes before runtime:

```bash
./vendor/bin/phpstan analyse src --level max
```

---

## Related

- [FAQ](faq.md) — common questions
- [Installation](installation.md) — environment requirements
- [Quick Start](quick-start.md) — minimal working example
