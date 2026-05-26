# Your First Project

Build a complete, production-structured project from scratch. By the end you will have:

- A SQLite database with a `users` table
- A PHP front controller (`public/index.php`)
- A bootstrap file that wires all Bamise services
- An HTML form for creating users with CSRF protection
- Bearer-token authentication for API clients
- A working development server

---

## Project layout

```
my-project/
├── composer.json
├── public/
│   └── index.php           ← entry point for all HTTP requests
├── src/
│   ├── Bootstrap/
│   │   └── container.php   ← wires all Bamise services; returns [$app, $csrf]
│   ├── Http/
│   │   └── PhpRequest.php  ← CrudRequestInterface for plain PHP
│   └── Resource/
│       └── UserDefinition.php
└── var/
    ├── schema.sql
    └── db.sqlite            ← created automatically on first run
```

---

## Step 1 — Create the project

```bash
mkdir my-project && cd my-project
composer require bamise/framework
mkdir -p public src/Bootstrap src/Http src/Resource var
```

---

## Step 2 — Database schema

```sql
-- var/schema.sql
CREATE TABLE IF NOT EXISTS users (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    name  TEXT    NOT NULL,
    email TEXT    NOT NULL UNIQUE
);
```

Apply the schema once:

```bash
sqlite3 var/db.sqlite < var/schema.sql
```

---

## Step 3 — Resource definition

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
     * Only these columns may be written via CRUD operations.
     * Any other column in the request body throws MassAssignmentException (HTTP 422).
     */
    public function fillable(): array
    {
        return ['name', 'email'];
    }

    /**
     * These columns are silently stripped from input (never written by clients).
     */
    public function guarded(): array
    {
        return ['id'];
    }

    /**
     * Return validation rules per operation.
     * Return [] to skip validation (no ValidateMiddleware needed).
     */
    public function rules(OperationType $operation): array
    {
        return [];
    }

    /**
     * Policy class names to apply for this resource.
     * See security.md for usage.
     */
    public function policyClasses(): array
    {
        return [];
    }
}
```

---

## Step 4 — Request adapter

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

        // PUT and PATCH: PHP does not auto-populate $_POST; read php://input instead.
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

    public function method(): string { return $this->method; }

    public function path(): string
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) ? $path : '/';
    }

    public function input(): array   { return $this->input; }
    public function all(): array     { return $this->input; }
    public function headers(): array { return $this->headers; }

    public function clientIp(): ?string { return $_SERVER['REMOTE_ADDR'] ?? null; }

    /** @return array<string, list<string>|string> */
    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name           = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = is_string($value) ? $value : (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }
}
```

---

## Step 5 — Bootstrap (container.php)

This file wires every Bamise service together. It returns an array containing
`$app` and `$csrfService` so the front controller can generate CSRF tokens for forms.

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

// ── 1. Database ───────────────────────────────────────────────────────────────

$dbConnection = PdoConnection::fromConfig(new ConnectionConfig(
    dsn:      'sqlite:' . __DIR__ . '/../../var/db.sqlite',
    user:     '',
    password: '',
    driver:   DatabaseDriver::Sqlite,
));

// ── 2. Resources and repositories ────────────────────────────────────────────

$userDefinition     = new UserDefinition();
$repoFactory        = new PdoRepositoryFactory($dbConnection);

$resourceRegistry   = new ResourceRegistry(['users' => $userDefinition]);
$repositoryResolver = new RepositoryResolver([
    'users' => $repoFactory->for($userDefinition),
]);

// ── 3. Core services ──────────────────────────────────────────────────────────

$fillableGuard     = new FillableGuard();
$contextFactory    = new CrudContextFactory();
$operationMapper   = new OperationTypeMapper();
$operationResolver = new OperationResolver($operationMapper);

// ── 4. Event dispatcher ───────────────────────────────────────────────────────

$eventDispatcher = new SyncEventDispatcher(new ListenerRegistry());

// ── 5. Terminal handler ───────────────────────────────────────────────────────

$terminal = new CrudOrchestrator(
    $eventDispatcher,
    new LifecycleEventFactory(),
    new StrategyDispatchHandler(
        new OperationStrategyFactory($repositoryResolver, $resourceRegistry, $fillableGuard)
    ),
);

// ── 6. Cache ──────────────────────────────────────────────────────────────────
// InMemoryCache is per-process: fine for a single-worker dev server.
// For production with multiple FPM workers, use a shared cache (Redis).

$cache = new InMemoryCache();

// ── 7. CSRF service ───────────────────────────────────────────────────────────

$csrfService = new SessionCsrfService(
    $cache,
    new CsrfTokenGenerator(),
    new CsrfConfig(),        // defaults: fieldName='_csrf', sessionField='_session_id', ttl=3600
);

// ── 8. Middleware pipeline ────────────────────────────────────────────────────

$pipeline = (new PipelineBuilder())
    ->add(new RateLimitMiddleware(
        new CacheRateLimiter($cache, new RateLimitConfig(maxAttempts: 60, windowSeconds: 60))
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
            new CallablePolicy(
                static fn ($op, $subject, $res): bool => $subject !== null
            ),
            $operationMapper,
        ),
    ), 600)
    ->build($terminal);

// ── 9. Application ────────────────────────────────────────────────────────────

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

---

## Step 6 — Front controller (public/index.php)

```php
<?php
// public/index.php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\PhpRequest;
use Bamise\Application\CrudApplication;
use Bamise\Application\DTO\ResponseEnvelope;
use Bamise\Infrastructure\Security\Csrf\SessionCsrfService;

/**
 * @var array{app: CrudApplication, csrf: SessionCsrfService} $services
 */
$services    = require __DIR__ . '/../src/Bootstrap/container.php';
$app         = $services['app'];
$csrfService = $services['csrf'];

$request = new PhpRequest();
$method  = $request->method();
$path    = $request->path();

// Extract the first path segment: /users → "users", /users/form → "users"
$segment = trim(explode('/', ltrim($path, '/'))[0]);

// ── HTML form route (GET /users/form) ─────────────────────────────────────────
if ($method === 'GET' && str_ends_with($path, '/form')) {
    session_start();
    $sessionId  = session_id();
    $csrfToken  = $csrfService->generateForSession($sessionId);
    $sessionEnc = htmlspecialchars($sessionId, ENT_QUOTES, 'UTF-8');
    $tokenEnc   = htmlspecialchars($csrfToken,  ENT_QUOTES, 'UTF-8');

    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Create User</title>
    </head>
    <body>
        <h1>Create User</h1>
        <form method="POST" action="/users">
            <input type="hidden" name="_session_id" value="{$sessionEnc}">
            <input type="hidden" name="_csrf"       value="{$tokenEnc}">
            <label>
                Name: <input type="text" name="name" required>
            </label><br>
            <label>
                Email: <input type="email" name="email" required>
            </label><br>
            <button type="submit">Create</button>
        </form>
        <p>
            <a href="/users">View all users</a>
        </p>
        <p>
            <em>Note: POST requests require an Authorization header when using BearerTokenAuthAdapter.
            See the API example for curl commands.</em>
        </p>
    </body>
    </html>
    HTML;
    exit;
}

// ── API / CRUD routes ─────────────────────────────────────────────────────────
$knownResources = ['users'];

if (!in_array($segment, $knownResources, true)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['message' => 'Resource not found']]);
    exit;
}

$envelope = $app->handle($request, $segment);
sendJson($envelope);

function sendJson(ResponseEnvelope $envelope): void
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

---

## Step 7 — Run

```bash
php -S localhost:8080 -t public
```

---

## Step 8 — Test

```bash
# Create a user (API — Bearer token carries id|roles|permissions)
curl -s -X POST http://localhost:8080/users \
  -d "name=Ada+Lovelace&email=ada@example.com" \
  -H "Authorization: Bearer 1||users.create"

# List users
curl -s http://localhost:8080/users \
  -H "Authorization: Bearer 1||users.read"

# Find by ID
curl -s "http://localhost:8080/users?id=1" \
  -H "Authorization: Bearer 1||users.read"

# Update
curl -s -X PUT http://localhost:8080/users \
  -d "id=1&name=Ada+L." \
  -H "Authorization: Bearer 1||users.update"

# Delete
curl -s -X DELETE http://localhost:8080/users \
  -d "id=1" \
  -H "Authorization: Bearer 1||users.delete"

# HTML form (browser)
open http://localhost:8080/users/form
```

---

## Understanding the permission format

`BearerTokenAuthAdapter` parses `Authorization: Bearer {payload}` where payload is:

```
{subject_id}|{comma_roles}|{comma_permissions}
```

Permission strings must be `{resourceName}.{operationType}`:

| Operation | Permission string |
|-----------|------------------|
| GET / list | `users.read` |
| POST / create | `users.create` |
| PUT/PATCH / update | `users.update` |
| DELETE | `users.delete` |
| Bulk update | `users.bulk_update` |
| Bulk delete | `users.bulk_delete` |

`AuthorizeMiddleware` checks that the authenticated subject has the exact permission string
before allowing the operation. For an "admin" user with all permissions:

```
Authorization: Bearer 1|admin|users.read,users.create,users.update,users.delete
```

---

## Next steps

- Add more resources → [CRUD Operations](crud.md)
- Add policies and roles → [Security](security.md)
- Listen to create/update/delete events → [Events](events.md)
- Custom middleware (logging, timestamps) → [Middleware](middleware.md)
