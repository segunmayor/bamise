# FAQ

---

## Why does `CrudApplication::handle()` require a resource name?

`CrudApplication::handle($request, $resourceName)` — the second argument is mandatory.
Bamise does not parse URLs; you extract the resource name from the route in your front
controller and pass it explicitly. This keeps the framework router-agnostic.

```php
// Correct
$envelope = $app->handle($request, 'users');

// Wrong — missing second argument
$envelope = $app->handle($request); // TypeError
```

---

## Why is the response always HTTP 200 on success?

Bamise returns HTTP 200 for all successful operations (create, update, delete). REST purists
expect 201 (created) or 204 (no content), but Bamise uses a consistent `ResponseEnvelope`
shape and a single status code for success. If you need different status codes per operation,
inspect `$envelope->meta['operation']` in your front controller and override the code before
sending the response.

---

## How do I filter results?

Add query parameters to the GET request. All non-reserved query parameters become `WHERE`
criteria with equality (`=`). Reserved parameters are `id`, the resource's `primaryKey`,
`limit`, and `offset`.

```bash
# WHERE status = 'active'
GET /users?status=active

# WHERE status = 'active' AND role = 'editor'
GET /users?status=active&role=editor
```

Range filters (`>`, `<`, `LIKE`) require a custom `RepositoryInterface` implementation.
See [Query Builder](query-builder.md).

---

## How do I paginate?

Use `limit` and `offset` query parameters:

```bash
GET /users?limit=10&offset=0   # first 10
GET /users?limit=10&offset=10  # next 10
```

The default limit is 100. There is no server-side total-count header — add it in a custom
middleware if needed.

---

## Can I use POST parameters in a GET request?

No. `ReadStrategy` reads from `CrudContext::inputData`, which is populated by your
`CrudRequestInterface::input()` implementation. For GET requests, `input()` typically returns
`$_GET`. You can inject additional parameters at the routing layer using a `RouterRequest`
wrapper — see [Routing](routing.md).

---

## How do I protect a route with authentication?

Add `AuthenticationMiddleware` and `AuthorizeMiddleware` to the pipeline. The subject must
carry a permission string of the form `{resource}.{operation}`:

```
Authorization: Bearer 1||users.read
```

If the subject is anonymous (no `Authorization` header), `AuthorizeMiddleware` throws
`InsufficientPermissionException` → HTTP 403. See [Security](security.md).

---

## Can I use Bamise without authentication?

Yes. Omit `AuthenticationMiddleware` and `AuthorizeMiddleware` from the pipeline. The
`PipelineBuilder` is optional — a completely empty pipeline still works:

```php
$pipeline = (new PipelineBuilder())->build($terminal);
```

Only do this for internal tools or scripts that are not exposed to the network.

---

## Why do I get `MassAssignmentException` on create?

Your `ResourceDefinitionInterface::fillable()` method does not include the field you are
posting. Either add the field to `fillable()`, or remove it from the request body.

```json
{"success": false, "errors": {"message": "Mass assignment not allowed for field \"is_admin\"."}}
```

Fields in `guarded()` are silently removed from input. Fields in `fillable()` are the only
ones that may be written. If `fillable()` returns `[]`, all columns are permitted (dangerous
for public-facing endpoints).

---

## Why does CSRF validation fail for API requests?

`CsrfMiddleware` expects two fields: `_session_id` and `_csrf`. API requests that use
Bearer tokens do not send form fields, so they will fail CSRF if `CsrfMiddleware` is in
the pipeline without a bypass.

Options:
1. **Remove `CsrfMiddleware`** from routes that are API-only (no HTML forms).
2. **Implement a bypass** in a custom middleware that skips CSRF when an `Authorization`
   header is present.
3. **Use route-specific pipelines** — build one pipeline with CSRF for form routes and
   another without it for API routes.

---

## What does `InMemoryCache` do in production?

`InMemoryCache` stores data in a PHP array. Each PHP-FPM worker process has its own array —
there is no sharing between workers. This means:

- Rate limiting with `CacheRateLimiter(new InMemoryCache(), ...)` is per-worker, not global.
- CSRF tokens generated in one worker cannot be validated by another.
- Use `RedisRateLimiter` and a Redis-backed CSRF service in production.

---

## Why is `ValidatorPortInterface` not implemented?

Bamise is validation-library-agnostic. It ships a `ValidatorPortInterface` contract but
does not include an implementation to avoid forcing a dependency on a specific library
(Symfony Validator, Laravel Validator, Respect\Validation, etc.).

To skip validation, return `[]` from `ResourceDefinitionInterface::rules()` and omit
`ValidateMiddleware` from the pipeline. To add validation, implement the interface and add
the middleware at priority 500:

```php
$validator = new class implements ValidatorPortInterface {
    public function validate(array $data, array $rules): ValidationResult {
        return new ValidationResult(valid: true);
    }
};
```

---

## Can I register the same resource under multiple names?

Yes. Add it to `ResourceRegistry` and `RepositoryResolver` under both keys:

```php
$resourceRegistry = new ResourceRegistry([
    'users'   => $userDefinition,
    'members' => $userDefinition,   // same definition, different route name
]);
$repositoryResolver = new RepositoryResolver([
    'users'   => $factory->for($userDefinition),
    'members' => $factory->for($userDefinition),
]);
```

---

## Can I use a custom primary key?

Yes. Return the column name from `ResourceDefinitionInterface::primaryKey()`:

```php
public function primaryKey(): string { return 'user_id'; }
```

Then pass the ID as `?user_id=42` in requests instead of `?id=42`. `ReadStrategy` checks
both `inputData[$primaryKey]` and `inputData['id']` — so `?id=42` still works as a shorthand.

---

## How do I add a custom HTTP route (not a CRUD resource)?

Handle it before calling `$app->handle()` in your front controller:

```php
$method  = $request->method();
$path    = $request->path();

// Custom route
if ($method === 'GET' && $path === '/health') {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// CRUD routes
$segment = trim(explode('/', ltrim($path, '/'))[0]);
$envelope = $app->handle($request, $segment);
```

---

## How do I return a different HTTP status on success?

Inspect `$envelope->meta['operation']` after the call and override the status code:

```php
$envelope = $app->handle($request, 'users');

$status = $envelope->httpStatus;
if ($envelope->success && $envelope->meta['operation'] === 'create') {
    $status = 201;
}

http_response_code($status);
header('Content-Type: application/json');
echo json_encode([...]);
```

---

## Related

- [Troubleshooting](troubleshooting.md) — setup and runtime problems
- [CRUD Operations](crud.md) — operation behaviour
- [Security](security.md) — auth, CSRF, permissions
