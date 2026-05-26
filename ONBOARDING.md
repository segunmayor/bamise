# Bamise Framework — Developer Onboarding Guide

Bamise is a PHP 8.4+ library that gives you secure, structured CRUD operations through a
hexagonal architecture. You bring your own HTTP framework (Symfony, Laravel, Slim, plain PHP).
Bamise wires the database, middleware, auth, events, and security together in the middle.

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Installation](#2-installation)
3. [Folder Structure](#3-folder-structure)
4. [How Bamise Works (5-minute mental model)](#4-how-bamise-works)
5. [Step-by-Step Setup](#5-step-by-step-setup)
6. [Database Configuration](#6-database-configuration)
7. [Resource Definition](#7-resource-definition)
8. [Implementing CrudRequestInterface](#8-implementing-crudrequestinterface)
9. [Bootstrap: Wiring Everything Together](#9-bootstrap-wiring-everything-together)
10. [public/index.php](#10-publicindexphp)
11. [First GET Example](#11-first-get-example)
12. [First HTML POST Form](#12-first-html-post-form)
13. [First Update & Delete Examples](#13-first-update--delete-examples)
14. [First Query Builder Example](#14-first-query-builder-example)
15. [First Event Example](#15-first-event-example)
16. [First Middleware Example](#16-first-middleware-example)
17. [Security Examples](#17-security-examples)
18. [Full Working Project](#18-full-working-project)
19. [Troubleshooting](#19-troubleshooting)
20. [FAQ](#20-faq)
21. [Minimal Copy-Paste Example](#21-minimal-copy-paste-example)

---

## 1. Prerequisites

| Requirement | Minimum version | Notes |
|---|---|---|
| PHP | 8.4 | Requires constructor property promotion, readonly classes, enums |
| PDO extension | bundled with PHP | Always available |
| pdo_sqlite | bundled | For local development without a server |
| pdo_mysql | optional | For MySQL / MariaDB production use |
| pdo_pgsql | optional | For PostgreSQL production use |
| Composer | 2.x | Package manager |

Check your PHP version and extensions:

```bash
php -v
php -m | grep -i pdo
```

You should see output like:

```
PHP 8.4.x
PDO
pdo_mysql
pdo_pgsql
pdo_sqlite
```

---

## 2. Installation

```bash
composer require bamise/framework
```

For development tools (static analysis, code style, tests):

```bash
composer require --dev bamise/framework
```

Bamise's only production dependencies are `psr/container ^2.0` and `psr/log ^3.0`. It does
not pull in any HTTP framework, template engine, or ORM.

---

## 3. Folder Structure

This is the recommended layout for a plain-PHP project using Bamise. Adapt it to your
framework's conventions.

```
my-app/
├── composer.json
├── public/
│   └── index.php           ← entry point — handles every HTTP request
├── src/
│   ├── Bootstrap/
│   │   └── container.php   ← wires all Bamise objects together; returns $app
│   ├── Http/
│   │   └── PhpRequest.php  ← your implementation of CrudRequestInterface
│   ├── Resource/
│   │   ├── UserDefinition.php   ← describes the "users" table
│   │   └── PostDefinition.php   ← describes the "posts" table
│   └── Listener/
│       └── AuditSubscriber.php  ← optional event subscriber
└── var/
    └── db.sqlite           ← SQLite file (development only)
```

---

## 4. How Bamise Works

Every HTTP request flows through this chain:

```
HTTP Request
    │
    ▼
CrudRequestInterface        ← you adapt your framework's request to this interface
    │
    ▼
CrudApplication::handle()   ← receives request + resource name string
    │
    ├─ OperationResolver    ← maps HTTP verb (POST/GET/PUT/DELETE) → OperationType
    │
    ├─ MiddlewarePipeline    ← runs middleware in priority order (lower = earlier)
    │     ├─ RateLimitMiddleware   (priority 100)
    │     ├─ AuthenticationMiddleware (200)
    │     ├─ CsrfMiddleware   (300)
    │     ├─ SanitizeMiddleware (400)
    │     ├─ ValidateMiddleware (500)
    │     ├─ AuthorizeMiddleware (600)
    │     └─ AuditMiddleware  (900)
    │
    ├─ CrudOrchestrator     ← dispatches before/after lifecycle events
    │
    └─ StrategyDispatchHandler → ReadStrategy / CreateStrategy / etc.
                                      │
                                      ▼
                                PdoRepository   ← executes parameterised SQL
                                      │
                                      ▼
                                ResponseEnvelope ← returned to your controller
```

**HTTP verb → operation mapping** (built in, cannot be changed):

| HTTP method | Default operation |
|---|---|
| `GET` | `Read` |
| `POST` | `Create` |
| `PUT` or `PATCH` | `Update` |
| `DELETE` | `Delete` |

---

## 5. Step-by-Step Setup

Five files are all you need for a working application. We will build them in order:

1. `src/Http/PhpRequest.php` — bridges PHP's `$_SERVER`/`$_POST` into Bamise
2. `src/Resource/UserDefinition.php` — describes the `users` table
3. `src/Bootstrap/container.php` — wires every Bamise object and returns `$app`
4. `public/index.php` — routes every request through `$app->handle()`
5. `var/schema.sql` — your table DDL (SQLite shown; MySQL equivalent included)

---

## 6. Database Configuration

### Supported drivers

Bamise ships dialects for four databases. Pass the matching enum case:

| Database | `DatabaseDriver` case | DSN example |
|---|---|---|
| SQLite (file) | `DatabaseDriver::Sqlite` | `sqlite:/path/to/db.sqlite` |
| SQLite (memory) | `DatabaseDriver::Sqlite` | `sqlite::memory:` |
| MySQL 8+ | `DatabaseDriver::Mysql` | `mysql:host=127.0.0.1;dbname=myapp;charset=utf8mb4` |
| MariaDB | `DatabaseDriver::Mariadb` | `mysql:host=127.0.0.1;dbname=myapp;charset=utf8mb4` |
| PostgreSQL | `DatabaseDriver::Postgres` | `pgsql:host=127.0.0.1;dbname=myapp` |

### Creating a connection

```php
use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;

// SQLite — no server required, ideal for local development
$config = new ConnectionConfig(
    dsn:      'sqlite:' . __DIR__ . '/../../var/db.sqlite',
    user:     '',
    password: '',
    driver:   DatabaseDriver::Sqlite,
);
$connection = PdoConnection::fromConfig($config);

// MySQL — production
$config = new ConnectionConfig(
    dsn:      'mysql:host=127.0.0.1;dbname=myapp;charset=utf8mb4',
    user:     'myapp_user',
    password: 'secret',
    driver:   DatabaseDriver::Mysql,
);
$connection = PdoConnection::fromConfig($config);
```

`PdoConnection::fromConfig()` sets `PDO::ATTR_ERRMODE` to `PDO::ERRMODE_EXCEPTION`
automatically. All queries will throw `PDOException` on error.

### Schema

Create your table before running the app. For SQLite:

```sql
-- var/schema.sql
CREATE TABLE IF NOT EXISTS users (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    name  TEXT    NOT NULL,
    email TEXT    NOT NULL UNIQUE
);
```

For MySQL:

```sql
CREATE TABLE IF NOT EXISTS users (
    id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Apply it once:

```bash
sqlite3 var/db.sqlite < var/schema.sql
# or for MySQL:
mysql -u myapp_user -p myapp < var/schema.sql
```

---

## 7. Resource Definition

A **resource definition** tells Bamise how a table is shaped. Create one class per table.

```php
<?php
// src/Resource/UserDefinition.php
declare(strict_types=1);

namespace App\Resource;

use Bamise\Contract\Crud\ResourceDefinitionInterface;
use Bamise\Contract\Enum\OperationType;

final class UserDefinition implements ResourceDefinitionInterface
{
    public function table(): string
    {
        return 'users';
    }

    public function primaryKey(): string
    {
        return 'id';
    }

    /**
     * Columns that may be written via CRUD operations.
     * Any column not listed here will trigger a MassAssignmentException.
     */
    public function fillable(): array
    {
        return ['name', 'email'];
    }

    /**
     * Columns that are always stripped from input (never written by clients).
     * Return [] if you have no guarded columns.
     */
    public function guarded(): array
    {
        return ['id'];
    }

    /**
     * Validation rules per operation.
     * Return [] to skip validation, or implement ValidatorPortInterface to enforce rules.
     */
    public function rules(OperationType $operation): array
    {
        return match ($operation) {
            OperationType::Create => [
                'name'  => 'required|string|max:255',
                'email' => 'required|email',
            ],
            OperationType::Update => [
                'name'  => 'string|max:255',
                'email' => 'email',
            ],
            default => [],
        };
    }

    /**
     * Policy classes to evaluate for this resource.
     * Return [] for no policy checks.
     */
    public function policyClasses(): array
    {
        return [];
    }
}
```

**Rules about fillable vs guarded:**

- If `fillable()` returns a non-empty array, only those columns are allowed through.
  Any other column in the request body causes `MassAssignmentException` (HTTP 422).
- `guarded()` lists columns that are stripped from output (they never reach the database write).
  The primary key is typically guarded.
- If `fillable()` returns `[]`, all columns are allowed (dangerous — use only for internal tools).

---

## 8. Implementing CrudRequestInterface

Bamise knows nothing about Symfony, Laravel, Slim, or plain PHP. You must write a thin
adapter that bridges your framework's request object to `CrudRequestInterface`.

**The interface contract:**

| Method | Returns | Purpose |
|---|---|---|
| `method()` | `string` | HTTP verb: `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| `path()` | `string` | Request path: `/users`, `/users/42` |
| `input()` | `array<string, mixed>` | Parsed body (for POST/PUT) or query string (for GET) |
| `all()` | `array<string, mixed>` | Same as `input()` in most cases |
| `headers()` | `array<string, list<string>\|string>` | All HTTP headers |
| `clientIp()` | `?string` | Client IP address, or null |

### Plain PHP adapter

```php
<?php
// src/Http/PhpRequest.php
declare(strict_types=1);

namespace App\Http;

use Bamise\Contract\Http\CrudRequestInterface;

final class PhpRequest implements CrudRequestInterface
{
    private string $method;
    /** @var array<string, mixed> */
    private array $input;
    /** @var array<string, list<string>|string> */
    private array $headers;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // For PUT and PATCH, PHP does not populate $_POST automatically.
        // Parse php://input instead.
        if (in_array($this->method, ['PUT', 'PATCH'], true)) {
            parse_str(file_get_contents('php://input') ?: '', $parsed);
            $this->input = $parsed;
        } elseif ($this->method === 'POST') {
            $this->input = $_POST;
        } else {
            $this->input = $_GET;
        }

        $this->headers = $this->parseHeaders();
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) ? $path : '/';
    }

    public function input(): array
    {
        return $this->input;
    }

    public function all(): array
    {
        return $this->input;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function clientIp(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /** @return array<string, list<string>|string> */
    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = is_string($value) ? $value : (string) $value;
            }
        }
        // Content-Type and Content-Length are not prefixed with HTTP_
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }
}
```

### Laravel adapter

```php
<?php
declare(strict_types=1);

namespace App\Http;

use Bamise\Contract\Http\CrudRequestInterface;
use Illuminate\Http\Request;

final class LaravelRequest implements CrudRequestInterface
{
    public function __construct(private readonly Request $request) {}

    public function method(): string
    {
        return $this->request->method();
    }

    public function path(): string
    {
        return '/' . ltrim($this->request->path(), '/');
    }

    public function input(): array
    {
        return $this->request->all();
    }

    public function all(): array
    {
        return $this->request->all();
    }

    public function headers(): array
    {
        $result = [];
        foreach ($this->request->headers->all() as $name => $values) {
            $result[$name] = count($values) === 1 ? $values[0] : $values;
        }
        return $result;
    }

    public function clientIp(): ?string
    {
        return $this->request->ip();
    }
}
```

---

## 9. Bootstrap: Wiring Everything Together

This file creates all Bamise objects and returns a ready-to-use `CrudApplication`. It is the
most important file to understand. Read it top to bottom.

```php
<?php
// src/Bootstrap/container.php
declare(strict_types=1);

use App\Resource\UserDefinition;
use Bamise\Application\Config\ApplicationConfig;
use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Context\SubjectFactory;
use Bamise\Application\CrudApplication;
use Bamise\Application\Handler\CrudOrchestrator;
use Bamise\Application\Handler\StrategyDispatchHandler;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Application\Middleware\CsrfMiddleware;
use Bamise\Application\Middleware\PipelineBuilder;
use Bamise\Application\Middleware\RateLimitMiddleware;
use Bamise\Application\Middleware\SanitizeMiddleware;
use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Response\ExceptionMapper;
use Bamise\Application\Response\ResponseMapper;
use Bamise\Application\Strategy\OperationStrategyFactory;
use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Domain\Event\LifecycleEventFactory;
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Domain\Service\OperationResolver;
use Bamise\Domain\Service\PermissionEvaluator;
use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;
use Bamise\Infrastructure\Persistence\Repository\PdoRepositoryFactory;
use Bamise\Infrastructure\Security\Csrf\CsrfConfig;
use Bamise\Infrastructure\Security\Csrf\CsrfTokenGenerator;
use Bamise\Infrastructure\Security\Csrf\SessionCsrfService;
use Bamise\Infrastructure\Security\Policy\CallablePolicy;
use Bamise\Infrastructure\Security\RateLimit\CacheRateLimiter;
use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use Bamise\Infrastructure\Security\Auth\BearerTokenAuthAdapter;
use Bamise\Infrastructure\Security\Sanitizer\HtmlSanitizer;
use Bamise\Infrastructure\Security\Sanitizer\SanitizerConfig;

// ── 1. Database connection ────────────────────────────────────────────────────

$dbConnection = PdoConnection::fromConfig(new ConnectionConfig(
    dsn:      'sqlite:' . __DIR__ . '/../../var/db.sqlite',
    user:     '',
    password: '',
    driver:   DatabaseDriver::Sqlite,
));

// ── 2. Resource definitions ───────────────────────────────────────────────────

$userDefinition = new UserDefinition();

// ── 3. Repository factory + resolver ─────────────────────────────────────────
// PdoRepositoryFactory creates a PdoRepository for each resource definition.
// RepositoryResolver maps resource names to repository instances.

$repoFactory = new PdoRepositoryFactory($dbConnection);

$repositoryResolver = new RepositoryResolver([
    'users' => $repoFactory->for($userDefinition),
]);

// ── 4. Resource registry ──────────────────────────────────────────────────────
// ResourceRegistry maps resource names to their definitions.

$resourceRegistry = new ResourceRegistry([
    'users' => $userDefinition,
]);

// ── 5. Core services ──────────────────────────────────────────────────────────

$fillableGuard    = new FillableGuard();
$contextFactory   = new CrudContextFactory();
$subjectFactory   = new SubjectFactory();
$operationMapper  = new OperationTypeMapper();
$operationResolver = new OperationResolver($operationMapper);

// ── 6. Event dispatcher ───────────────────────────────────────────────────────
// SyncEventDispatcher fires listeners inline, before the HTTP response is sent.
// Pass null as the second argument when you don't need async (queued) listeners.

$listenerRegistry = new ListenerRegistry();
$eventDispatcher  = new SyncEventDispatcher($listenerRegistry);

// ── 7. Terminal handler chain ─────────────────────────────────────────────────
// StrategyDispatchHandler → picks the right CRUD strategy (Read/Create/Update/Delete).
// CrudOrchestrator wraps it to fire before/after lifecycle events.

$strategyFactory = new OperationStrategyFactory(
    $repositoryResolver,
    $resourceRegistry,
    $fillableGuard,
);

$terminal = new CrudOrchestrator(
    $eventDispatcher,
    new LifecycleEventFactory(),
    new StrategyDispatchHandler($strategyFactory),
);

// ── 8. Cache (dev only — not shared across PHP-FPM workers) ──────────────────
// For production, replace InMemoryCache with a Redis or APCu backed implementation.

$cache = new InMemoryCache();

// ── 9. Security services ──────────────────────────────────────────────────────

$csrfService = new SessionCsrfService(
    $cache,
    new CsrfTokenGenerator(),
    new CsrfConfig(
        fieldName:        '_csrf',
        tokenLength:      32,
        ttlSeconds:       3600,
        sessionField:     '_session_id',
        defaultSessionId: 'default',
    ),
);

$rateLimiter = new CacheRateLimiter(
    $cache,
    new RateLimitConfig(maxAttempts: 60, windowSeconds: 60),
);

$sanitizer = new HtmlSanitizer(new SanitizerConfig(allowedTags: [], encodeEntities: true));

// Auth: BearerTokenAuthAdapter reads "Authorization: Bearer {id}|roles|perms"
// Use SessionAuthAdapter for session-based HTML forms instead.
$authAdapter = new BearerTokenAuthAdapter();

// Policy: allow every authenticated subject (replace with real logic for production)
$policy = new CallablePolicy(
    static fn (\Bamise\Contract\Enum\OperationType $op, ?object $subject, string $resource): bool =>
        $subject !== null
);

// ── 10. Middleware pipeline ───────────────────────────────────────────────────
// PipelineBuilder assembles middleware in priority order (lower number = runs first).

$pipeline = (new PipelineBuilder())
    ->add(new RateLimitMiddleware($rateLimiter), 100)
    ->add(new AuthenticationMiddleware($authAdapter, $subjectFactory, $contextFactory), 200)
    ->add(new CsrfMiddleware($csrfService), 300)
    ->add(new SanitizeMiddleware($sanitizer, $contextFactory), 400)
    ->add(new AuthorizeMiddleware(
        new PermissionEvaluator(),
        new PolicyEvaluator($policy, $operationMapper),
    ), 600)
    ->build($terminal);

// ── 11. CrudApplication ───────────────────────────────────────────────────────

$app = new CrudApplication(
    $resourceRegistry,
    $contextFactory,
    $operationResolver,
    $pipeline,
    new ResponseMapper(),
    new ExceptionMapper(),
    new ApplicationConfig(),
);

return $app;
```

---

## 10. public/index.php

This file is your front controller. Every HTTP request hits it. It reads the URL, decides
which resource name to pass to Bamise, and converts the `ResponseEnvelope` to an HTTP response.

```php
<?php
// public/index.php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\PhpRequest;
use Bamise\Application\CrudApplication;
use Bamise\Application\DTO\ResponseEnvelope;

/** @var CrudApplication $app */
$app = require __DIR__ . '/../src/Bootstrap/container.php';

$request = new PhpRequest();

// Simple URL-based routing: /users → 'users' resource, /posts → 'posts' resource
$path    = $request->path();
$segment = trim(explode('/', ltrim($path, '/'))[0]);

$resourceName = match ($segment) {
    'users' => 'users',
    'posts' => 'posts',
    default => null,
};

if ($resourceName === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['message' => 'Not found']]);
    exit;
}

$envelope = $app->handle($request, $resourceName);

sendResponse($envelope);

function sendResponse(ResponseEnvelope $envelope): void
{
    http_response_code($envelope->httpStatus);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $envelope->success,
        'data'    => $envelope->data,
        'errors'  => $envelope->errors,
        'meta'    => $envelope->meta,
    ]);
}
```

### Serving locally

```bash
php -S localhost:8080 -t public
```

---

## 11. First GET Example

### List all users

```http
GET /users HTTP/1.1
Authorization: Bearer 1||users.read
```

`BearerTokenAuthAdapter` parses `Bearer {id}|{comma-roles}|{comma-permissions}`.
- `1` = subject id
- (empty) = no roles
- `users.read` = one permission

**Permission format: `{resourceName}.{operation}`** (e.g., `users.read`, `users.create`).

With curl:

```bash
curl -s http://localhost:8080/users \
  -H "Authorization: Bearer 1||users.read"
```

Response:

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

### Find a single user by ID

Pass the primary key as a query parameter:

```bash
curl -s "http://localhost:8080/users?id=1" \
  -H "Authorization: Bearer 1||users.read"
```

Response:

```json
{
  "success": true,
  "data": {"id": 1, "name": "Ada Lovelace", "email": "ada@example.com"},
  "errors": [],
  "meta": {"operation": "read"}
}
```

**How it works internally:** `ReadStrategy` checks if `id` (or the resource's `primaryKey`) is
present in `inputData`. If yes, it calls `repository->find(new ResourceId($id))`. If no id is
present, it calls `repository->findAll($criteria, $limit, $offset)`.

### Filtering and pagination

Query parameters that are not `id`, `limit`, or `offset` become `WHERE` criteria:

```bash
# Filter by name
curl -s "http://localhost:8080/users?name=Ada" \
  -H "Authorization: Bearer 1||users.read"

# Pagination: second page of 10
curl -s "http://localhost:8080/users?limit=10&offset=10" \
  -H "Authorization: Bearer 1||users.read"
```

---

## 12. First HTML POST Form

POST forms require CSRF protection. The flow is two steps:

1. Your server renders an HTML form that contains two hidden fields: `_session_id` and `_csrf`.
2. When the form is submitted, `CsrfMiddleware` reads those fields and validates the token.

### Step 1 — Generate a CSRF token

Call `$csrfService->generateForSession($sessionId)` before rendering the form. The `$sessionId`
is any stable string that identifies the current user's session (e.g., `session_id()`).

```php
<?php
// form.php — renders the HTML create form
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Bamise\Infrastructure\Security\Csrf\SessionCsrfService;

/** Returns both $app and $csrfService */
['app' => $app, 'csrf' => $csrfService] = require __DIR__ . '/../src/Bootstrap/services.php';

session_start();
$sessionId = session_id();
$csrfToken = $csrfService->generateForSession($sessionId);
?>
<!DOCTYPE html>
<html lang="en">
<head><title>Create User</title></head>
<body>
  <form method="POST" action="/users">
    <input type="hidden" name="_session_id" value="<?= htmlspecialchars($sessionId) ?>">
    <input type="hidden" name="_csrf"       value="<?= htmlspecialchars($csrfToken) ?>">

    <label>Name:  <input type="text"  name="name"  required></label><br>
    <label>Email: <input type="email" name="email" required></label><br>

    <button type="submit">Create</button>
  </form>
</body>
</html>
```

To return both `$app` and `$csrfService` from bootstrap, update `container.php` to return an
array instead of just `$app`:

```php
// at the bottom of src/Bootstrap/container.php
return ['app' => $app, 'csrf' => $csrfService];
```

And update `public/index.php` accordingly:

```php
['app' => $app, 'csrf' => $csrfService] = require __DIR__ . '/../src/Bootstrap/container.php';
```

### Step 2 — Handle the POST

The `CsrfMiddleware` runs automatically. If the `_csrf` token is missing or invalid, the
middleware throws `CsrfException` and `ExceptionMapper` returns HTTP 403:

```json
{"success": false, "errors": {"message": "CSRF token validation failed.", "type": "..."}, "meta": []}
```

With `SessionAuthAdapter` (session-based auth instead of bearer tokens):

```php
// In your bootstrap, replace BearerTokenAuthAdapter with:
use Bamise\Infrastructure\Security\Auth\SessionAuthAdapter;
$authAdapter = new SessionAuthAdapter('_subject_id');
```

Then add `_subject_id` to your form:

```html
<input type="hidden" name="_subject_id" value="<?= (int) $_SESSION['user_id'] ?>">
```

`SessionAuthAdapter` reads the subject id from the `_subject_id` input field and returns an
`AuthSubjectDto` with no roles or permissions. You then need to load the real permissions from
your database and assign them — or use `BearerTokenAuthAdapter` for API clients.

### Testing the full POST flow

```bash
# Step 1: get a CSRF token (pretend we got it from the form)
# Step 2: submit the form
curl -s http://localhost:8080/users \
  -X POST \
  -d "_session_id=testsession&_csrf=REPLACE_WITH_REAL_TOKEN&name=Grace+Hopper&email=grace@example.com" \
  -H "Authorization: Bearer 1||users.create"
```

---

## 13. First Update & Delete Examples

### Update (PUT)

PUT requires an `id` (or the resource's `primaryKey`) in the request body:

```bash
curl -s http://localhost:8080/users \
  -X PUT \
  -d "id=1&name=Ada+L.&email=ada@example.com" \
  -H "Authorization: Bearer 1||users.update"
```

Response on success:

```json
{
  "success": true,
  "data": {"id": 1, "name": "Ada L.", "email": "ada@example.com"},
  "errors": [],
  "meta": {"operation": "update"}
}
```

If the record does not exist, `success` is `false` with HTTP 422.

### Delete (DELETE)

```bash
curl -s http://localhost:8080/users \
  -X DELETE \
  -d "id=1" \
  -H "Authorization: Bearer 1||users.delete"
```

Response on success:

```json
{
  "success": true,
  "data": {"id": 1},
  "errors": [],
  "meta": {"operation": "delete"}
}
```

### Bulk update (PATCH — disambiguated with client hint)

When a route should allow both `Update` and `BulkUpdate` via `PATCH`, send the
`_crud_operation` field to disambiguate:

```bash
curl -s http://localhost:8080/users \
  -X PATCH \
  -d "_crud_operation=bulk_update&name=Ada&email=updated@example.com" \
  -H "Authorization: Bearer 1||users.bulk_update"
```

---

## 14. First Query Builder Example

Bamise does not ship a fluent query builder. The SQL layer is `SqlCompiler`, which produces
parameterised `CompiledQuery` objects. For standard CRUD, you never touch it directly — the
`PdoRepository` uses it internally.

For **custom queries beyond standard CRUD**, use the `ConnectionInterface::pdo()` method to
access the raw `PDO` object:

```php
<?php
declare(strict_types=1);

use Bamise\Infrastructure\Persistence\PDO\PdoConnection;
use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;

$connection = PdoConnection::fromConfig(new ConnectionConfig(
    dsn:      'sqlite:' . __DIR__ . '/var/db.sqlite',
    user:     '',
    password: '',
    driver:   DatabaseDriver::Sqlite,
));

// Use the SqlCompiler directly for parameterised queries
use Bamise\Infrastructure\Persistence\Query\SqlCompiler;

$compiler   = new SqlCompiler($connection->dialect());
$pdo        = $connection->pdo();

// Compiled SELECT: SELECT * FROM "users" WHERE "name" = :__w_name LIMIT 100 OFFSET 0
$query = $compiler->compileSelectAll('users', ['name' => 'Ada'], limit: 10, offset: 0);
$stmt  = $pdo->prepare($query->sql);
$stmt->execute($query->bindings);
$rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);

var_dump($rows);

// Compiled INSERT: INSERT INTO "users" ("name", "email") VALUES (:name, :email)
$query = $compiler->compileInsert('users', 'id', ['name' => 'Lise Meitner', 'email' => 'lise@example.com']);
$stmt  = $pdo->prepare($query->sql);
$stmt->execute($query->bindings);

echo 'New ID: ' . $pdo->lastInsertId() . PHP_EOL;

// Wrap multiple writes in a transaction
$connection->transaction(function () use ($connection): void {
    $pdo = $connection->pdo();
    $pdo->prepare('INSERT INTO users (name, email) VALUES (:name, :email)')
        ->execute(['name' => 'Emmy Noether', 'email' => 'emmy@example.com']);
    $pdo->prepare('INSERT INTO users (name, email) VALUES (:name, :email)')
        ->execute(['name' => 'Marie Curie', 'email' => 'marie@example.com']);
    // Both inserts commit together; if either throws, both are rolled back.
});
```

For **building a custom repository** that plugs into Bamise's pipeline:

```php
<?php
declare(strict_types=1);

use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\ResourceId;

final class ReportRepository implements RepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function find(ResourceId $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reports WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id->value]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function insert(array $data): ResourceId
    {
        $stmt = $this->pdo->prepare('INSERT INTO reports (title) VALUES (:title)');
        $stmt->execute(['title' => $data['title']]);
        return new ResourceId((int) $this->pdo->lastInsertId());
    }

    public function update(ResourceId $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('UPDATE reports SET title = :title WHERE id = :id');
        $stmt->execute(['title' => $data['title'] ?? '', 'id' => $id->value]);
        return $stmt->rowCount() > 0;
    }

    public function delete(ResourceId $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM reports WHERE id = :id');
        $stmt->execute(['id' => $id->value]);
        return $stmt->rowCount() > 0;
    }

    public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM reports LIMIT $limit OFFSET $offset");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function updateBulk(array $criteria, array $data): int { return 0; }
    public function deleteBulk(array $criteria): int { return 0; }
}

// Register it alongside auto-generated repositories:
$repositoryResolver->register('reports', new ReportRepository($dbConnection->pdo()));
```

---

## 15. First Event Example

Bamise fires **lifecycle events** automatically around every mutating operation (Create, Update,
Delete). Events fire in this order: `BeforeCreate` → operation executes → `AfterCreate`.

Available events:

| Event class | Fired when |
|---|---|
| `Bamise\Contract\Event\BeforeCreate` | before a CREATE succeeds |
| `Bamise\Contract\Event\AfterCreate` | after a successful CREATE |
| `Bamise\Contract\Event\BeforeUpdate` | before an UPDATE / BulkUpdate |
| `Bamise\Contract\Event\AfterUpdate` | after a successful UPDATE / BulkUpdate |
| `Bamise\Contract\Event\BeforeDelete` | before a DELETE / BulkDelete |
| `Bamise\Contract\Event\AfterDelete` | after a successful DELETE / BulkDelete |

Every event has two public properties:
- `$context` — `CrudContext` with `operation`, `resourceName`, `inputData`, `subject`, `request`
- `$payload` — `array<string, mixed>|null` — the result data (null on Before events)

### Option A — Inline listener

Register a callable directly on the dispatcher before building the pipeline:

```php
use Bamise\Contract\Event\AfterCreate;

$eventDispatcher->subscribe(
    AfterCreate::class,
    function (AfterCreate $event): void {
        error_log(sprintf(
            'User created: id=%s by subject=%s',
            $event->payload['id'] ?? 'unknown',
            (string) ($event->context->subject?->id ?? 'anon'),
        ));
    },
);
```

### Option B — Subscriber class

A subscriber groups multiple event handlers in one class:

```php
<?php
// src/Listener/UserActivitySubscriber.php
declare(strict_types=1);

namespace App\Listener;

use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\Event\AfterDelete;
use Bamise\Contract\Event\DomainEventInterface;
use Bamise\Infrastructure\Event\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

final class UserActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function getSubscribedEvents(): array
    {
        return [
            AfterCreate::class => ['onCreated', 10],  // [method, priority]
            AfterDelete::class => 'onDeleted',         // string = method, priority defaults to 0
        ];
    }

    public function onCreated(AfterCreate $event): void
    {
        $this->logger->info('User created', [
            'id'      => $event->payload['id'] ?? null,
            'subject' => $event->context->subject?->id,
        ]);
    }

    public function onDeleted(AfterDelete $event): void
    {
        $this->logger->info('User deleted', [
            'id' => $event->context->inputData['id'] ?? null,
        ]);
    }
}
```

Register it in your bootstrap after the dispatcher is created:

```php
use Bamise\Infrastructure\Event\SubscriberLoader;
use Psr\Log\NullLogger;

$subscriberLoader = new SubscriberLoader();
$subscriberLoader->load($eventDispatcher, new UserActivitySubscriber(new NullLogger()));
```

### Returning `false` from a listener stops propagation

```php
$eventDispatcher->subscribe(
    \Bamise\Contract\Event\BeforeCreate::class,
    function (\Bamise\Contract\Event\BeforeCreate $event): bool {
        if ($event->context->resourceName === 'users') {
            // Returning false stops all subsequent listeners for this event.
            return false;
        }
        return true;
    },
);
```

---

## 16. First Middleware Example

### Custom middleware

Implement `MiddlewareInterface` and add it to the pipeline:

```php
<?php
// src/Middleware/LoggingMiddleware.php
declare(strict_types=1);

namespace App\Middleware;

use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        $start = microtime(true);

        $result = $next->handle($context);

        $elapsed = round((microtime(true) - $start) * 1000, 2);

        error_log(sprintf(
            '[Bamise] %s %s %s | %dms',
            $context->operation->value,
            $context->resourceName,
            $result->success ? 'OK' : 'FAIL',
            $elapsed,
        ));

        return $result;
    }
}
```

Register it in your pipeline builder:

```php
$pipeline = (new PipelineBuilder())
    ->add(new \App\Middleware\LoggingMiddleware(), 50) // runs before rate limiter
    ->add(new RateLimitMiddleware($rateLimiter), 100)
    // ... rest of middleware
    ->build($terminal);
```

### Middleware that modifies input data

Middleware can rewrite `inputData` via `CrudContextFactory::withInputData()`:

```php
<?php
declare(strict_types=1);

namespace App\Middleware;

use Bamise\Application\Context\CrudContextFactory;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class TimestampMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly CrudContextFactory $contextFactory) {}

    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        // Inject created_at / updated_at automatically
        $data = $context->inputData;

        if ($context->operation === \Bamise\Contract\Enum\OperationType::Create) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (in_array($context->operation, [
            \Bamise\Contract\Enum\OperationType::Create,
            \Bamise\Contract\Enum\OperationType::Update,
        ], true)) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        return $next->handle($this->contextFactory->withInputData($context, $data));
    }
}
```

### Middleware that short-circuits (returns early without calling `$next`)

```php
<?php
declare(strict_types=1);

namespace App\Middleware;

use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class ReadOnlyGuardMiddleware implements MiddlewareInterface
{
    /** List of resources that must never be written to via the CRUD API */
    private array $readOnlyResources = ['audit_logs', 'system_config'];

    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        $isMutating = in_array($context->operation, [
            OperationType::Create,
            OperationType::Update,
            OperationType::Delete,
            OperationType::BulkUpdate,
            OperationType::BulkDelete,
        ], true);

        if ($isMutating && in_array($context->resourceName, $this->readOnlyResources, true)) {
            // Return early — $next is never called.
            return new CrudResult(
                success: false,
                errors:  ['message' => 'This resource is read-only.'],
                meta:    ['operation' => $context->operation->value],
            );
        }

        return $next->handle($context);
    }
}
```

---

## 17. Security Examples

### 17.1 Authentication adapters

Bamise ships three authentication adapters. Pick one based on your application type.

#### BearerTokenAuthAdapter — API / JWT-like header auth

```php
use Bamise\Infrastructure\Security\Auth\BearerTokenAuthAdapter;

$authAdapter = new BearerTokenAuthAdapter();
```

The adapter reads `Authorization: Bearer {payload}` where `{payload}` is:

```
{subject_id}|{comma_separated_roles}|{comma_separated_permissions}
```

Examples:

```
Bearer 42                              → id=42, roles=[], permissions=[]
Bearer 42|admin,editor                 → id=42, roles=[admin,editor], permissions=[]
Bearer 42|admin|users.read,users.create → id=42, roles=[admin], permissions=[users.read, users.create]
Bearer alice-uuid||users.read          → id=alice-uuid, roles=[], permissions=[users.read]
```

#### SessionAuthAdapter — HTML form / session-based auth

```php
use Bamise\Infrastructure\Security\Auth\SessionAuthAdapter;

$authAdapter = new SessionAuthAdapter('_subject_id');
```

Include the subject id as a hidden field in every form:

```html
<input type="hidden" name="_subject_id" value="<?= (int) $_SESSION['user_id'] ?>">
```

The `SessionAuthAdapter` only resolves the subject's ID. Permissions must be encoded in the
request or loaded from the database in a custom middleware.

#### JwtAuthAdapter — verifies a real signed JWT

Requires the optional `firebase/php-jwt` package:

```bash
composer require firebase/php-jwt
```

```php
use Bamise\Infrastructure\Security\Auth\JwtAuthAdapter;

$authAdapter = new JwtAuthAdapter('your-256-bit-secret');
```

The adapter expects `Authorization: Bearer {jwt}`. The JWT payload must include:
- `sub` (string|int) — subject ID
- `roles` (array) — optional
- `permissions` (array) — optional

---

### 17.2 Authorization: permissions

`AuthorizeMiddleware` checks that the authenticated subject has a permission in the format
`{resource}.{operation}` before passing the request downstream.

For a subject to perform `GET /users`, their permissions must include `users.read`.
For `POST /users`, the subject needs `users.create`. For `PUT`, `users.update`. For `DELETE`,
`users.delete`.

If the permission is missing, `InsufficientPermissionException` is thrown → HTTP 403.

```php
use Bamise\Domain\Model\Subject;

// Create a Subject manually for testing
$subject = new Subject(
    id:          42,
    roles:       ['editor'],
    permissions: ['users.read', 'users.create', 'posts.read'],
);
```

---

### 17.3 Policies

Policies are a second authorization gate that runs after permission checks. They receive
`(OperationType $operation, ?object $subject, string $resource)` and return `bool`.

#### CallablePolicy — inline logic

```php
use Bamise\Infrastructure\Security\Policy\CallablePolicy;
use Bamise\Contract\Enum\OperationType;

// Allow only admin role to delete
$deletePolicy = new CallablePolicy(
    static function (OperationType $op, ?object $subject, string $resource): bool {
        if ($op !== OperationType::Delete) {
            return true; // non-delete operations not restricted by this policy
        }
        return $subject instanceof \Bamise\Domain\Model\Subject
            && in_array('admin', $subject->roles, true);
    }
);
```

#### PolicyChain — AND multiple policies together

```php
use Bamise\Infrastructure\Security\Policy\PolicyChain;

$policy = new PolicyChain($deletePolicy, $anotherPolicy);
// Both policies must return true for the operation to be allowed.
```

Pass the policy to `PolicyEvaluator`:

```php
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\OperationTypeMapper;

$policyEvaluator = new PolicyEvaluator($policy, new OperationTypeMapper());
```

---

### 17.4 CSRF protection

CSRF protection is built into `CsrfMiddleware`. It runs on every mutating operation
(Create, Update, Delete, BulkUpdate, BulkDelete). GET requests are skipped.

**Token lifecycle:**

1. Server generates a token: `$csrfService->generateForSession($sessionId)`
2. Token is stored in the cache with key `csrf:{sessionId}`, TTL 3600 s by default.
3. Form includes `_session_id` (the session identifier) and `_csrf` (the token).
4. On POST, `SessionCsrfService::validate()` verifies the token is in the cache and
   matches the one submitted. The token is deleted from cache after successful validation
   (single-use).

**Important:** `InMemoryCache` is **not** shared across PHP-FPM workers. If you run
multiple workers, CSRF tokens generated by worker A will be invisible to worker B.
For production, replace `InMemoryCache` with a Redis-backed implementation:

```php
use Bamise\Contract\CachePortInterface;

final class RedisCache implements CachePortInterface
{
    public function __construct(private readonly \Redis $redis) {}

    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);
        return $value === false ? null : unserialize($value);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if ($ttl !== null) {
            $this->redis->setex($key, $ttl, serialize($value));
        } else {
            $this->redis->set($key, serialize($value));
        }
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }
}
```

---

### 17.5 Rate limiting

`RateLimitMiddleware` uses `CacheRateLimiter` to limit requests per IP address (or per
resource + operation if no IP is available):

```php
use Bamise\Infrastructure\Security\RateLimit\CacheRateLimiter;
use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;

$rateLimiter = new CacheRateLimiter(
    $cache,
    new RateLimitConfig(
        maxAttempts:   60,  // allow 60 requests
        windowSeconds: 60,  // per 60-second window
    ),
);
```

If exceeded, `RateLimitException` is thrown → HTTP 429.

---

### 17.6 Input sanitization

`SanitizeMiddleware` + `HtmlSanitizer` strips HTML tags from all string input fields before
they reach validation or the database:

```php
use Bamise\Infrastructure\Security\Sanitizer\HtmlSanitizer;
use Bamise\Infrastructure\Security\Sanitizer\SanitizerConfig;

// Strip all tags and encode HTML entities
$sanitizer = new HtmlSanitizer(new SanitizerConfig(
    allowedTags:    [],    // no tags allowed
    encodeEntities: true,  // htmlspecialchars on the cleaned output
));

// Allow specific tags (e.g. a simple rich-text field)
$sanitizer = new HtmlSanitizer(new SanitizerConfig(
    allowedTags:    ['b', 'i', 'em', 'strong'],
    encodeEntities: false,
));
```

---

### 17.7 Request signing (HMAC)

`SigningMiddleware` verifies a SHA-256 HMAC signature on each request, ensuring the payload
was not tampered with in transit. Useful for webhook endpoints or server-to-server calls.

```php
use Bamise\Application\Middleware\SigningMiddleware;
use Bamise\Infrastructure\Security\Signing\HmacRequestSigner;
use Bamise\Infrastructure\Security\Signing\SigningConfig;

$signer = new HmacRequestSigner(
    $cache,
    new SigningConfig(secret: 'your-hmac-secret'),
);

$pipeline = (new PipelineBuilder())
    ->add(new SigningMiddleware($signer), 50) // before rate limiting
    // ...
    ->build($terminal);
```

---

### 17.8 Pinning or restricting operations per route

Use `RouteOperationConfig` to control which CRUD operations a route can perform,
regardless of HTTP method:

```php
use Bamise\Contract\ValueObject\RouteOperationConfig;
use Bamise\Contract\Enum\OperationType;

// This endpoint can ONLY create — HTTP verb is ignored
$envelope = $app->handle($request, 'users', routeConfig: RouteOperationConfig::pin(OperationType::Create));

// This endpoint allows read or bulk_delete only
$envelope = $app->handle($request, 'users', routeConfig: RouteOperationConfig::allow(
    OperationType::Read,
    OperationType::BulkDelete,
));

// Default: no restriction — HTTP verb determines the operation
$envelope = $app->handle($request, 'users', routeConfig: RouteOperationConfig::open());
```

---

## 18. Full Working Project

This section shows a complete, self-contained project that you can copy verbatim.
It uses SQLite in memory for zero-dependency setup, covers all five CRUD operations, and
includes CSRF, rate limiting, and event logging.

### File: `var/schema.sql`

```sql
CREATE TABLE IF NOT EXISTS users (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    name  TEXT    NOT NULL,
    email TEXT    NOT NULL UNIQUE
);
```

### File: `src/Resource/UserDefinition.php`

```php
<?php
declare(strict_types=1);

namespace App\Resource;

use Bamise\Contract\Crud\ResourceDefinitionInterface;
use Bamise\Contract\Enum\OperationType;

final class UserDefinition implements ResourceDefinitionInterface
{
    public function table(): string { return 'users'; }
    public function primaryKey(): string { return 'id'; }
    public function fillable(): array { return ['name', 'email']; }
    public function guarded(): array { return ['id']; }
    public function rules(OperationType $operation): array { return []; }
    public function policyClasses(): array { return []; }
}
```

### File: `src/Http/PhpRequest.php`

See [Section 8](#8-implementing-crudrequestinterface) — copy the `PhpRequest` class as-is.

### File: `src/Bootstrap/container.php`

```php
<?php
declare(strict_types=1);

use App\Resource\UserDefinition;
use Bamise\Application\Config\ApplicationConfig;
use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Context\SubjectFactory;
use Bamise\Application\CrudApplication;
use Bamise\Application\Handler\CrudOrchestrator;
use Bamise\Application\Handler\StrategyDispatchHandler;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Application\Middleware\CsrfMiddleware;
use Bamise\Application\Middleware\PipelineBuilder;
use Bamise\Application\Middleware\RateLimitMiddleware;
use Bamise\Application\Middleware\SanitizeMiddleware;
use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Response\ExceptionMapper;
use Bamise\Application\Response\ResponseMapper;
use Bamise\Application\Strategy\OperationStrategyFactory;
use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Domain\Event\LifecycleEventFactory;
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Domain\Service\OperationResolver;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Domain\Service\PermissionEvaluator;
use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;
use Bamise\Infrastructure\Persistence\Repository\PdoRepositoryFactory;
use Bamise\Infrastructure\Security\Auth\BearerTokenAuthAdapter;
use Bamise\Infrastructure\Security\Csrf\CsrfConfig;
use Bamise\Infrastructure\Security\Csrf\CsrfTokenGenerator;
use Bamise\Infrastructure\Security\Csrf\SessionCsrfService;
use Bamise\Infrastructure\Security\Policy\CallablePolicy;
use Bamise\Infrastructure\Security\RateLimit\CacheRateLimiter;
use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use Bamise\Infrastructure\Security\Sanitizer\HtmlSanitizer;
use Bamise\Infrastructure\Security\Sanitizer\SanitizerConfig;

$dbConnection = PdoConnection::fromConfig(new ConnectionConfig(
    dsn:      'sqlite::memory:',
    user:     '',
    password: '',
    driver:   DatabaseDriver::Sqlite,
));

// Bootstrap schema for in-memory SQLite
$dbConnection->pdo()->exec('
    CREATE TABLE IF NOT EXISTS users (
        id    INTEGER PRIMARY KEY AUTOINCREMENT,
        name  TEXT    NOT NULL,
        email TEXT    NOT NULL UNIQUE
    )
');

$userDefinition     = new UserDefinition();
$repoFactory        = new PdoRepositoryFactory($dbConnection);
$repositoryResolver = new RepositoryResolver(['users' => $repoFactory->for($userDefinition)]);
$resourceRegistry   = new ResourceRegistry(['users' => $userDefinition]);

$fillableGuard     = new FillableGuard();
$contextFactory    = new CrudContextFactory();
$operationMapper   = new OperationTypeMapper();
$operationResolver = new OperationResolver($operationMapper);

$listenerRegistry = new ListenerRegistry();
$eventDispatcher  = new SyncEventDispatcher($listenerRegistry);

// Log every create to PHP's error log
$eventDispatcher->subscribe(
    \Bamise\Contract\Event\AfterCreate::class,
    static function (\Bamise\Contract\Event\AfterCreate $event): void {
        error_log('User created: ' . json_encode($event->payload));
    },
);

$terminal = new CrudOrchestrator(
    $eventDispatcher,
    new LifecycleEventFactory(),
    new StrategyDispatchHandler(
        new OperationStrategyFactory($repositoryResolver, $resourceRegistry, $fillableGuard)
    ),
);

$cache = new InMemoryCache(); // dev only — see Section 17.4 for production

$csrfService = new SessionCsrfService(
    $cache,
    new CsrfTokenGenerator(),
    new CsrfConfig(),
);

$pipeline = (new PipelineBuilder())
    ->add(new RateLimitMiddleware(
        new CacheRateLimiter($cache, new RateLimitConfig(60, 60))
    ), 100)
    ->add(new AuthenticationMiddleware(
        new BearerTokenAuthAdapter(),
        new SubjectFactory(),
        $contextFactory,
    ), 200)
    ->add(new CsrfMiddleware($csrfService), 300)
    ->add(new SanitizeMiddleware(
        new HtmlSanitizer(new SanitizerConfig()),
        $contextFactory,
    ), 400)
    ->add(new AuthorizeMiddleware(
        new PermissionEvaluator(),
        new PolicyEvaluator(
            new CallablePolicy(static fn ($op, $subject, $res) => $subject !== null),
            $operationMapper,
        ),
    ), 600)
    ->build($terminal);

$app = new CrudApplication(
    $resourceRegistry,
    $contextFactory,
    $operationResolver,
    $pipeline,
    new ResponseMapper(),
    new ExceptionMapper(),
    new ApplicationConfig(),
);

return ['app' => $app, 'csrf' => $csrfService];
```

### File: `public/index.php`

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\PhpRequest;
use Bamise\Application\CrudApplication;
use Bamise\Application\DTO\ResponseEnvelope;
use Bamise\Infrastructure\Security\Csrf\SessionCsrfService;

/** @var array{app: CrudApplication, csrf: SessionCsrfService} $services */
$services    = require __DIR__ . '/../src/Bootstrap/container.php';
$app         = $services['app'];
$csrfService = $services['csrf'];

$request     = new PhpRequest();
$path        = $request->path();
$segment     = trim(explode('/', ltrim($path, '/'))[0]);

$knownResources = ['users', 'posts'];

if (!in_array($segment, $knownResources, true)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['message' => 'Not found']]);
    exit;
}

// Special route: GET /users/form — returns an HTML form for testing
if ($request->method() === 'GET' && str_ends_with($path, '/form')) {
    session_start();
    $sessionId = session_id();
    $token     = $csrfService->generateForSession($sessionId);
    header('Content-Type: text/html');
    echo <<<HTML
    <!DOCTYPE html>
    <html><head><title>Create User</title></head><body>
    <h1>Create User</h1>
    <form method="POST" action="/users">
      <input type="hidden" name="_session_id" value="{$sessionId}">
      <input type="hidden" name="_csrf"       value="{$token}">
      <input type="hidden" name="_subject_id" value="1">
      <!-- BearerTokenAuthAdapter is active; for forms use SessionAuthAdapter instead -->
      <label>Name:  <input type="text"  name="name"  required></label><br>
      <label>Email: <input type="email" name="email" required></label><br>
      <button type="submit">Create</button>
    </form>
    </body></html>
    HTML;
    exit;
}

$envelope = $app->handle($request, $segment);

http_response_code($envelope->httpStatus);
header('Content-Type: application/json');
echo json_encode([
    'success' => $envelope->success,
    'data'    => $envelope->data,
    'errors'  => $envelope->errors,
    'meta'    => $envelope->meta,
]);
```

### Run it

```bash
# Install dependencies
composer install

# Start the server
php -S localhost:8080 -t public

# Create a user
curl -s http://localhost:8080/users \
  -X POST \
  -d "name=Ada+Lovelace&email=ada@example.com" \
  -H "Authorization: Bearer 1||users.create"

# List all users
curl -s http://localhost:8080/users \
  -H "Authorization: Bearer 1||users.read"

# Find user by ID
curl -s "http://localhost:8080/users?id=1" \
  -H "Authorization: Bearer 1||users.read"

# Update user
curl -s http://localhost:8080/users \
  -X PUT \
  -d "id=1&name=Ada+L." \
  -H "Authorization: Bearer 1||users.update"

# Delete user
curl -s http://localhost:8080/users \
  -X DELETE \
  -d "id=1" \
  -H "Authorization: Bearer 1||users.delete"
```

---

## 19. Troubleshooting

### "Resource "users" is not registered."

You called `$app->handle($request, 'users')` but did not register `'users'` in `ResourceRegistry`.

```php
// Check your bootstrap — this must exist:
$resourceRegistry = new ResourceRegistry([
    'users' => $userDefinition,   // ← must match the string you pass to handle()
]);
```

### "No repository registered for resource "users"."

You registered the resource definition but not the repository.

```php
$repositoryResolver = new RepositoryResolver([
    'users' => $repoFactory->for($userDefinition),  // ← required
]);
```

### HTTP 403 — "Authentication required."

`AuthorizeMiddleware` found no `Subject` on the context. This means `AuthenticationMiddleware`
could not resolve a subject from the request.

- With `BearerTokenAuthAdapter`: add `Authorization: Bearer 1||users.read` to your request.
- With `SessionAuthAdapter`: add `_subject_id` to the POST body.
- If you don't need auth, remove `AuthenticationMiddleware` and `AuthorizeMiddleware` from the pipeline.

### HTTP 403 — "Permission "users.read" denied."

The subject was authenticated but lacks the specific permission. The permission string
must be `{resourceName}.{operationType}` where `operationType` is one of:
`read`, `create`, `update`, `delete`, `bulk_update`, `bulk_delete`.

With `BearerTokenAuthAdapter`, include the permission in the Bearer token:

```
Authorization: Bearer 1||users.read,users.create
```

### HTTP 403 — "CSRF token validation failed."

The `_csrf` token in the form body does not match what is stored in cache.

Common causes:
1. You are using `InMemoryCache` with multiple PHP-FPM workers (tokens are per-process).
   → Use a shared cache (Redis, Memcached, database).
2. The token was already consumed (it is single-use). Generate a new one per form render.
3. The `_session_id` field is missing or does not match the session used during `generateForSession()`.

### HTTP 422 — "Mass assignment not allowed for field "is_admin"."

A field in the request body is not in the `fillable()` list of the resource definition.
Either add the field to `fillable()`, or strip it from the request before calling `handle()`.

### "Unable to resolve CRUD operation for HTTP method "X"."

Bamise only maps GET, POST, PUT, PATCH, DELETE. If you send `OPTIONS` or `HEAD`, you will
get `OperationResolutionException` → HTTP 400. Handle those methods before calling `handle()`.

### "Insert requires at least one column."

All input fields were stripped by the fillable guard, leaving no data to insert. Check that
`fillable()` lists the columns the form is submitting.

### SQLite "database is locked"

SQLite allows only one writer at a time. This happens in load tests or concurrent requests.
For anything beyond a single-user tool, use MySQL or PostgreSQL.

### "SQLSTATE[HY000]: General error: 1 no such table: users"

You did not run the schema migration. For SQLite file databases:

```bash
sqlite3 var/db.sqlite < var/schema.sql
```

For in-memory SQLite (used in the full example), the schema is applied inline in the bootstrap.

---

## 20. FAQ

**Q: Does Bamise ship a router?**

No. Bamise does not care how you route requests. Use your framework's router (or plain
`$_SERVER['REQUEST_URI']`), extract the resource name from the URL, and pass it to
`$app->handle($request, $resourceName)`.

**Q: Can I use Bamise with Laravel / Symfony / Slim?**

Yes. Write an adapter that implements `CrudRequestInterface` (see [Section 8](#8-implementing-crudrequestinterface)).
The rest of Bamise is framework-agnostic.

**Q: What happens if I don't need auth or CSRF?**

Simply do not add `AuthenticationMiddleware`, `AuthorizeMiddleware`, or `CsrfMiddleware` to the
pipeline:

```php
$pipeline = (new PipelineBuilder())
    ->add(new RateLimitMiddleware($rateLimiter), 100)
    ->add(new SanitizeMiddleware($sanitizer, $contextFactory), 400)
    ->build($terminal);
```

The pipeline is fully composable — include only what you need.

**Q: Do I need to implement ValidatorPortInterface?**

Only if you want Bamise to enforce the rules returned by `ResourceDefinitionInterface::rules()`.
If you return `[]` from `rules()` and do not add `ValidateMiddleware` to the pipeline,
validation is skipped entirely.

To implement a minimal passthrough validator:

```php
use Bamise\Contract\ValidatorPortInterface;
use Bamise\Contract\ValueObject\ValidationResult;

final class AlwaysValidValidator implements ValidatorPortInterface
{
    public function validate(array $data, array $rules): ValidationResult
    {
        return new ValidationResult(valid: true);
    }
}
```

**Q: My `InMemoryCache` loses CSRF tokens between requests. Why?**

PHP reinitialises all objects on every request. `InMemoryCache` stores data in a plain PHP
array that lives only for the duration of one request. For CSRF tokens to survive across
requests, you must use a shared, persistent cache (Redis, APCu, database).

**Q: How do I get back the inserted record's ID?**

The `ResponseEnvelope::data` array includes the primary key after a successful create:

```json
{
  "success": true,
  "data": {"name": "Ada", "email": "ada@example.com", "id": 7},
  "meta": {"operation": "create"}
}
```

**Q: How do I register multiple resources?**

Add them to both registries:

```php
$resourceRegistry = new ResourceRegistry([
    'users' => $userDefinition,
    'posts' => $postDefinition,
    'comments' => $commentDefinition,
]);

$repositoryResolver = new RepositoryResolver([
    'users'    => $repoFactory->for($userDefinition),
    'posts'    => $repoFactory->for($postDefinition),
    'comments' => $repoFactory->for($commentDefinition),
]);
```

**Q: Can I use a different database per resource?**

Yes. Instantiate a separate `PdoConnection` and `PdoRepositoryFactory` per database, then
register each resource with its own factory:

```php
$usersRepo  = (new PdoRepositoryFactory($primaryConnection))->for($userDefinition);
$reportsRepo = (new PdoRepositoryFactory($analyticsConnection))->for($reportDefinition);

$repositoryResolver = new RepositoryResolver([
    'users'   => $usersRepo,
    'reports' => $reportsRepo,
]);
```

**Q: What is the difference between `allow()` and `pin()` in RouteOperationConfig?**

- `pin(OperationType::Create)` — the route is permanently Create, regardless of HTTP method.
- `allow(OperationType::Read, OperationType::BulkDelete)` — the server declares the set of
  valid operations; the HTTP method and an optional `_crud_operation` hint select within it.
- `open()` — the HTTP method alone resolves the operation (default behaviour).

---

## 21. Minimal Copy-Paste Example

This is the smallest working Bamise application. It uses SQLite in memory, no auth, no CSRF.
Copy these two files and run `php -S localhost:8080 -t .` from the same directory.

```php
<?php
// bootstrap.php — the entire wiring in one file
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Bamise\Application\Config\ApplicationConfig;
use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\CrudApplication;
use Bamise\Application\Handler\CrudOrchestrator;
use Bamise\Application\Handler\StrategyDispatchHandler;
use Bamise\Application\Middleware\PipelineBuilder;
use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Response\ExceptionMapper;
use Bamise\Application\Response\ResponseMapper;
use Bamise\Application\Strategy\OperationStrategyFactory;
use Bamise\Contract\Crud\ResourceDefinitionInterface;
use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Http\CrudRequestInterface;
use Bamise\Domain\Event\LifecycleEventFactory;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Domain\Service\OperationResolver;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;
use Bamise\Infrastructure\Persistence\Repository\PdoRepositoryFactory;

// ── Inline resource definition ────────────────────────────────────────────────
$userDefinition = new class implements ResourceDefinitionInterface {
    public function table(): string { return 'users'; }
    public function primaryKey(): string { return 'id'; }
    public function fillable(): array { return ['name', 'email']; }
    public function guarded(): array { return ['id']; }
    public function rules(OperationType $op): array { return []; }
    public function policyClasses(): array { return []; }
};

// ── Inline request adapter ────────────────────────────────────────────────────
$request = new class implements CrudRequestInterface {
    public function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }
    public function path(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $p = parse_url($uri, PHP_URL_PATH);
        return is_string($p) ? $p : '/';
    }
    public function input(): array {
        $m = $this->method();
        if ($m === 'POST') return $_POST;
        if (in_array($m, ['PUT', 'PATCH'], true)) {
            parse_str(file_get_contents('php://input') ?: '', $d);
            return $d;
        }
        return $_GET;
    }
    public function all(): array { return $this->input(); }
    public function headers(): array {
        $h = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $h[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
            }
        }
        return $h;
    }
    public function clientIp(): ?string { return $_SERVER['REMOTE_ADDR'] ?? null; }
};

// ── Database (SQLite in-memory) ───────────────────────────────────────────────
$conn = PdoConnection::fromConfig(new ConnectionConfig(
    dsn: 'sqlite::memory:', user: '', password: '', driver: DatabaseDriver::Sqlite,
));
$conn->pdo()->exec(
    'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL)'
);

// ── Bamise wiring ─────────────────────────────────────────────────────────────
$resources  = new ResourceRegistry(['users' => $userDefinition]);
$repos      = new RepositoryResolver(['users' => (new PdoRepositoryFactory($conn))->for($userDefinition)]);
$terminal   = new CrudOrchestrator(
    new SyncEventDispatcher(new ListenerRegistry()),
    new LifecycleEventFactory(),
    new StrategyDispatchHandler(new OperationStrategyFactory($repos, $resources, new FillableGuard())),
);
$pipeline   = (new PipelineBuilder())->build($terminal);
$ctxFactory = new CrudContextFactory();

$app = new CrudApplication(
    $resources,
    $ctxFactory,
    new OperationResolver(new OperationTypeMapper()),
    $pipeline,
    new ResponseMapper(),
    new ExceptionMapper(),
    new ApplicationConfig(),
);

// ── Dispatch ──────────────────────────────────────────────────────────────────
$path    = $request->path();
$segment = trim(explode('/', ltrim($path, '/'))[0]);

if ($segment !== 'users') {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

$envelope = $app->handle($request, 'users');

http_response_code($envelope->httpStatus);
header('Content-Type: application/json');
echo json_encode([
    'success' => $envelope->success,
    'data'    => $envelope->data,
    'errors'  => $envelope->errors,
    'meta'    => $envelope->meta,
]);
```

Start the server:

```bash
composer require bamise/framework
php -S localhost:8080 bootstrap.php
```

Test it:

```bash
# Create
curl -X POST http://localhost:8080/users -d "name=Ada&email=ada@example.com"
# List
curl http://localhost:8080/users
# Find by ID
curl "http://localhost:8080/users?id=1"
# Update
curl -X PUT http://localhost:8080/users -d "id=1&name=Ada+L."
# Delete
curl -X DELETE http://localhost:8080/users -d "id=1"
```

Because there is no `AuthenticationMiddleware` or `AuthorizeMiddleware` in this minimal
pipeline, no Authorization header is required. Add the security middleware from
[Section 9](#9-bootstrap-wiring-everything-together) when you are ready.
