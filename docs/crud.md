# CRUD Operations

Bamise maps HTTP verbs to operations automatically. Each operation goes through
the middleware pipeline and is executed by a strategy against the repository.

---

## Operation mapping

| HTTP method | Default operation | `OperationType` case |
|-------------|-------------------|----------------------|
| `GET` | Read | `OperationType::Read` |
| `POST` | Create | `OperationType::Create` |
| `PUT` | Update | `OperationType::Update` |
| `PATCH` | Update | `OperationType::Update` |
| `DELETE` | Delete | `OperationType::Delete` |

For bulk operations, send a `_crud_operation` hint (see [Routing](routing.md)).

---

## Create (POST)

Send fillable fields in the POST body. The primary key is guarded and auto-assigned
by the database.

```bash
curl -s -X POST http://localhost:8080/users \
  -d "name=Ada+Lovelace&email=ada@example.com" \
  -H "Authorization: Bearer 1||users.create"
```

Success response (`HTTP 200`):

```json
{
  "success": true,
  "data": {"name": "Ada Lovelace", "email": "ada@example.com", "id": 1},
  "errors": [],
  "meta": {"operation": "create"}
}
```

`CreateStrategy`:
1. Applies `FillableGuard` — any field not in `fillable()` throws `MassAssignmentException` (HTTP 422).
2. Calls `repository->insert($data)`.
3. Returns the inserted data merged with the new primary key.

**HTML form equivalent** (requires CSRF token — see [Security](security.md)):

```html
<form method="POST" action="/users">
    <input type="hidden" name="_session_id" value="<?= htmlspecialchars(session_id()) ?>">
    <input type="hidden" name="_csrf"       value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="text"  name="name"  required>
    <input type="email" name="email" required>
    <button type="submit">Create</button>
</form>
```

---

## Read — list all

Omit the primary key to retrieve a collection. All non-reserved query parameters
become `WHERE` criteria.

```bash
# List all
curl -s http://localhost:8080/users \
  -H "Authorization: Bearer 1||users.read"

# Filter by name
curl -s "http://localhost:8080/users?name=Ada" \
  -H "Authorization: Bearer 1||users.read"
```

Success response:

```json
{
  "success": true,
  "data": {
    "items": [
      {"id": 1, "name": "Ada Lovelace", "email": "ada@example.com"}
    ]
  },
  "errors": [],
  "meta": {"operation": "read", "count": 1}
}
```

Reserved keys excluded from WHERE criteria: `id`, the resource's `primaryKey`, `limit`, `offset`.

---

## Read — find by ID

Send the primary key as a query parameter (or in the POST body for non-GET verbs):

```bash
curl -s "http://localhost:8080/users?id=1" \
  -H "Authorization: Bearer 1||users.read"
```

Success response:

```json
{
  "success": true,
  "data": {"id": 1, "name": "Ada Lovelace", "email": "ada@example.com"},
  "errors": [],
  "meta": {"operation": "read"}
}
```

Not found (`HTTP 422`):

```json
{
  "success": false,
  "data": [],
  "errors": {"message": "Resource not found"},
  "meta": {"operation": "read"}
}
```

---

## Pagination

Use `limit` and `offset` as query parameters:

```bash
# First page of 10
curl -s "http://localhost:8080/users?limit=10&offset=0" \
  -H "Authorization: Bearer 1||users.read"

# Second page of 10
curl -s "http://localhost:8080/users?limit=10&offset=10" \
  -H "Authorization: Bearer 1||users.read"
```

`limit` defaults to `100`; `offset` defaults to `0`. Both are applied as `LIMIT n OFFSET m`
in the compiled SQL.

---

## Update (PUT / PATCH)

Send the primary key plus the fields to change. Non-fillable fields in the body throw
`MassAssignmentException`. The primary key is stripped from the `SET` clause automatically.

```bash
curl -s -X PUT http://localhost:8080/users \
  -d "id=1&name=Ada+L." \
  -H "Authorization: Bearer 1||users.update"
```

Success response:

```json
{
  "success": true,
  "data": {"id": 1, "name": "Ada L.", "email": "ada@example.com"},
  "errors": [],
  "meta": {"operation": "update"}
}
```

`UpdateStrategy`:
1. Extracts `inputData[$primaryKey]` or `inputData['id']` as the WHERE value.
2. Applies `FillableGuard` and removes the primary key from the SET payload.
3. Calls `repository->update(ResourceId, $data)`.
4. Returns the merged result (primary key + updated fields).

If no primary key is in the body → failure (HTTP 422).
If the record does not exist → failure (HTTP 422).

**HTML form for update** (browsers don't support PUT natively — use `_method` override or JavaScript):

```html
<!-- Option A: JavaScript fetch -->
<button onclick="fetch('/users', {
    method: 'PUT',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'id=1&name=Ada+L.'
})">Update</button>

<!-- Option B: pin the operation server-side so POST acts as update -->
<!-- $app->handle($request, 'users', routeConfig: RouteOperationConfig::pin(OperationType::Update)) -->
```

---

## Delete (DELETE)

```bash
curl -s -X DELETE http://localhost:8080/users \
  -d "id=1" \
  -H "Authorization: Bearer 1||users.delete"
```

Success response:

```json
{
  "success": true,
  "data": {"id": 1},
  "errors": [],
  "meta": {"operation": "delete"}
}
```

---

## Bulk update (PATCH with hint)

`BulkUpdateStrategy` uses two conventions in `inputData`:

- `_criteria` — `array<string, mixed>` — the `WHERE` filter. Empty array = update all rows.
- All other fillable keys — the `SET` payload.

```bash
curl -s -X PATCH http://localhost:8080/users \
  -d "_crud_operation=bulk_update&_criteria[status]=pending&status=active" \
  -H "Authorization: Bearer 1||users.bulk_update"
```

Response:

```json
{
  "success": true,
  "data": {"affected": 3},
  "errors": [],
  "meta": {"operation": "bulk_update"}
}
```

---

## Bulk delete (DELETE with hint)

`BulkDeleteStrategy` uses:

- `_criteria` — `array<string, mixed>` — the `WHERE` filter. **Empty = delete all rows.**

```bash
curl -s -X DELETE http://localhost:8080/users \
  -d "_crud_operation=bulk_delete&_criteria[archived]=1" \
  -H "Authorization: Bearer 1||users.bulk_delete"
```

Response:

```json
{
  "success": true,
  "data": {"affected": 5},
  "errors": [],
  "meta": {"operation": "bulk_delete"}
}
```

---

## Mass assignment protection

`FillableGuard` enforces two rules from `ResourceDefinitionInterface`:

| Scenario | Behaviour |
|----------|-----------|
| Column is in `guarded()` | Silently stripped from input before write |
| `fillable()` is non-empty and column is not listed | Throws `MassAssignmentException` (HTTP 422) |
| `fillable()` is empty | All columns pass (dangerous — use only for internal tools) |

```php
// src/Resource/UserDefinition.php
public function fillable(): array { return ['name', 'email']; }  // only these may be written
public function guarded(): array  { return ['id']; }              // id is stripped silently
```

Sending `is_admin=1` in the POST body when `is_admin` is not in `fillable()` returns:

```json
{"success": false, "errors": {"message": "Mass assignment not allowed for field \"is_admin\"."}}
```

---

## Validation rules

`ResourceDefinitionInterface::rules(OperationType $operation)` returns rules per operation.
These are passed to `ValidatorPortInterface` by `ValidateMiddleware`.

Bamise does not ship a validator implementation. You must either:

1. Skip `ValidateMiddleware` and return `[]` from `rules()`.
2. Implement `ValidatorPortInterface` using your preferred validator library.

```php
// Passthrough validator (no validation)
use Bamise\Contract\ValidatorPortInterface;
use Bamise\Contract\ValueObject\ValidationResult;

$validator = new class implements ValidatorPortInterface {
    public function validate(array $data, array $rules): ValidationResult
    {
        return new ValidationResult(valid: true);
    }
};
```

When `ValidateMiddleware` is in the pipeline, add it at priority 500:

```php
use Bamise\Application\Middleware\ValidateMiddleware;

$pipeline = (new PipelineBuilder())
    // ...
    ->add(new ValidateMiddleware(
        $validator,
        $resourceRegistry,
        $fillableGuard,
        $contextFactory,
    ), 500)
    // ...
    ->build($terminal);
```

---

## Resource definition reference

```php
interface ResourceDefinitionInterface
{
    public function table(): string;
    public function primaryKey(): string;
    public function fillable(): array;   // list<string>
    public function guarded(): array;    // list<string>
    public function rules(OperationType $operation): array;  // array<string, mixed>
    public function policyClasses(): array;  // list<class-string>
}
```

---

## ResponseEnvelope structure

Every `CrudApplication::handle()` call returns a `ResponseEnvelope`:

```php
readonly class ResponseEnvelope
{
    public bool $success;
    public array $data;    // array<string, mixed>
    public array $errors;  // array<string, mixed>
    public array $meta;    // array<string, mixed>
    public int $httpStatus;
}
```

HTTP status codes from `ExceptionMapper`:

| Exception | HTTP status |
|-----------|-------------|
| `AuthorizationException` / `InsufficientPermissionException` | 403 |
| `CsrfException` | 403 |
| `RateLimitException` | 429 |
| `ValidationException` / `MassAssignmentException` | 422 |
| `OperationResolutionException` | 400 |
| Any other `BamiseException` | 400 |
| Other `Throwable` | 500 |
| Success | 200 |

---

## Related

- [Query Builder](query-builder.md) — filtering, pagination, custom queries
- [Security](security.md) — protecting operations with auth and CSRF
- [Middleware](middleware.md) — transforming input/output per operation
- Architecture: [06-strategies.md](architecture/06-strategies.md)
