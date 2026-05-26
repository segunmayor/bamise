# Bamise

<p align="center">
    <img src=".github/assets/bamise_logo.png" width="450">
</p>

<h1 align="center">
Bamise
</h1>

<p align="center">
Enterprise Secure CRUD Automation Framework
</p>

[![CI](https://github.com/bamise/bamise/actions/workflows/ci.yml/badge.svg)](https://github.com/bamise/bamise/actions/workflows/ci.yml)
[![Mutation Testing](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fbamise%2Fbamise%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/bamise/bamise/main)
[![PHP 8.4+](https://img.shields.io/badge/php-8.4%2B-blue)](https://www.php.net/)
[![License: GPL v3](https://shields.io)](https://www.gnu.org/licenses/gpl-3.0)(LICENSE)

Secure enterprise CRUD library for PHP 8.4+ built on hexagonal (ports-and-adapters) architecture.
Domain-driven, PSR-compliant, zero hardcoded dependencies.

---

## Features

- **Hexagonal architecture** — contracts, domain, application, infrastructure layers fully separated
- **Repository pattern** — PDO-backed with MySQL, MariaDB, PostgreSQL, and SQLite dialects
- **Security-first** — CSRF protection, HTML sanitisation, cache-backed rate limiting, HMAC request signing, bearer/session/JWT auth stubs, PSR audit logging
- **Event system** — synchronous dispatcher, async queue bridge, before/after CRUD lifecycle hooks, subscriber autoloading
- **Policy engine** — callable and class-based access policies with a chainable evaluator
- **Middleware pipeline** — pluggable before/after handlers with a `PipelineState` value object
- **PHPUnit 11** — full unit test suite; PHPStan level 6; PHP CS Fixer; Infection mutation testing

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.4+ |
| psr/container | ^2.0 |
| psr/log | ^3.0 |

Optional: `firebase/php-jwt ^6.10` for `JwtAuthAdapter` (HS256 bearer validation).

---

## Installation

```bash
composer require bamise/bamise
```

---

## Quick Start

### 1. Define a resource

```php
use Bamise\Contract\Domain\ResourceDefinitionInterface;
use Bamise\Contract\Enum\OperationType;

final class PostDefinition implements ResourceDefinitionInterface
{
    public function resourceName(): string  { return 'posts'; }
    public function primaryKey(): string    { return 'id'; }
    public function fillable(): array       { return ['title', 'body', 'author_id']; }
    public function guarded(): array        { return ['id', 'created_at']; }

    public function allowedOperations(): array
    {
        return [
            OperationType::Create,
            OperationType::Read,
            OperationType::Update,
            OperationType::Delete,
        ];
    }
}
```

### 2. Wire up the repository

```php
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;
use Bamise\Infrastructure\Persistence\PDO\PdoRepository;
use Bamise\Contract\Enum\DatabaseDriver;

$config = new ConnectionConfig(
    driver: DatabaseDriver::Mysql,
    dsn: 'mysql:host=127.0.0.1;dbname=myapp',
    user: 'root',
    password: 'secret',
);

$connection  = PdoConnection::fromConfig($config);
$definition  = new PostDefinition();
$repository  = new PdoRepository($connection, $definition);
```

### 3. Register resources and build the application

```php
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\CrudApplication;

$resources  = new ResourceRegistry();
$resolver   = new RepositoryResolver();

$resources->register($definition);
$resolver->register('posts', $repository);

$app = new CrudApplication($resources, $resolver, /* ...middlewares, strategies... */);
```

### 4. Dispatch an operation

```php
use Bamise\Contract\Http\CrudRequestInterface;

// $request is any object implementing CrudRequestInterface
$response = $app->handle($request);

echo $response->status();  // 200, 422, 403 …
echo json_encode($response->data());
```

---

## Security

### CSRF Protection

```php
use Bamise\Infrastructure\Security\Csrf\CsrfGuard;
use Bamise\Infrastructure\Cache\InMemoryCache;

$cache = new InMemoryCache();
$guard = new CsrfGuard($cache, ttl: 3600);

$token = $guard->generate('session-id');
$guard->validate('session-id', $token); // throws on mismatch
```

### Rate Limiting

```php
use Bamise\Infrastructure\Security\RateLimit\CacheRateLimiter;

$limiter = new CacheRateLimiter($cache, maxAttempts: 60, windowSeconds: 60);
// Used automatically by RateLimitMiddleware when registered in the pipeline
```

### Request Signing (HMAC)

```php
use Bamise\Infrastructure\Security\Signing\HmacRequestSigner;

$signer    = new HmacRequestSigner(secret: getenv('HMAC_SECRET'));
$signature = $signer->sign(['resource' => 'posts', 'action' => 'create']);
// Attach as X-Signature header; verify incoming requests with $signer->verify($request)
```

### Access Policies

```php
use Bamise\Infrastructure\Security\Policy\CallablePolicy;
use Bamise\Domain\Policy\PolicyEvaluator;

$policy = new CallablePolicy(
    static fn (OperationType $op, ?object $subject, string $resource): bool =>
        $subject !== null && in_array('admin', $subject->roles ?? [], true),
);

$evaluator = new PolicyEvaluator([$policy]);
$allowed   = $evaluator->evaluate(OperationType::Delete, $subject, 'posts');
```

---

## Event System

### Listening to CRUD lifecycle events

```php
use Bamise\Contract\Event\DomainEventInterface;
use Bamise\Infrastructure\Event\EventDispatcher;
use Bamise\Infrastructure\Event\ListenerRegistry;

$registry   = new ListenerRegistry();
$dispatcher = new EventDispatcher($registry);

// Register a listener for all BeforeCrud events
$registry->register(
    eventClass: \Bamise\Domain\Event\BeforeCrudEvent::class,
    listener:   static function (DomainEventInterface $event): void {
        // inspect $event->context(), $event->payload() …
    },
);
```

### Subscriber classes

```php
use Bamise\Contract\Event\EventSubscriberInterface;

final class AuditSubscriber implements EventSubscriberInterface
{
    public static function subscribedEvents(): array
    {
        return [
            \Bamise\Domain\Event\AfterCrudEvent::class => [['onAfterCrud', 10]],
        ];
    }

    public function onAfterCrud(DomainEventInterface $event): void
    {
        // log to your audit trail
    }
}

// Register via SubscriberLoader
$loader = new \Bamise\Infrastructure\Event\SubscriberLoader($registry);
$loader->load(new AuditSubscriber());
```

### Async (queued) listeners

```php
// Implement QueuePortInterface and pass to AsyncEventDispatcher
$asyncDispatcher = new \Bamise\Infrastructure\Event\AsyncEventDispatcher($queue, $encoder);
```

---

## Architecture

Bamise follows hexagonal (ports-and-adapters) architecture. Full module documentation lives under [`docs/architecture/`](docs/architecture/).

| Module | Path | Description |
|---|---|---|
| 1 — Contracts | `src/Contract/` | Pure interfaces and value objects only |
| 2 — Domain | `src/Domain/` | Models, services, policies, lifecycle events |
| 3 — Application | `src/Application/` | Orchestrator, middleware pipeline, strategies, response mapping |
| 4 — Infrastructure | `src/Infrastructure/Persistence/` | PDO connection, dialects, SQL compiler, repositories |
| 8 — Security | `src/Infrastructure/Security/` | CSRF, XSS, rate limiting, HMAC signing, auth adapters, audit |
| 9 — Events | `src/Infrastructure/Event/` | Sync dispatcher, async queue bridge, subscriber loader, plugin hooks |
| 11 — CI/CD | `.github/workflows/` | PHPUnit, PHPStan, PHP CS Fixer, Infection |
| 12 — Composer | `composer.json` | Packagist metadata, scripts, semantic versioning |

---

## Development

### Available commands

```bash
composer test            # PHPUnit (no coverage)
composer test-coverage   # PHPUnit + Clover report
composer analyse         # PHPStan level 6
composer cs-check        # PHP CS Fixer dry-run
composer cs-fix          # Auto-fix style violations
composer mutation        # Infection mutation testing
composer ci              # test + analyse + cs-check
```

### Running tests

```bash
# All tests
composer test

# Single suite
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration

# With coverage (requires pcov or xdebug)
composer test-coverage
```

### Static analysis

```bash
composer analyse
# Config: phpstan.neon (level 6, src/ only)
```

### Code style

```bash
composer cs-check   # Check without modifying
composer cs-fix     # Fix in place
# Config: .php-cs-fixer.dist.php (@PER-CS2.0 + @PHP84Migration)
```

### Mutation testing

```bash
composer mutation
# Config: infection.json5 (minMsi=70%, minCoveredMsi=80%)
```

---

## Contributing

See [CONTRIBUTING.md](.github/CONTRIBUTING.md).

---

## Security Policy

See [SECURITY.md](.github/SECURITY.md).

---

## License & Copyright

Formerly: Sitcalm
Current: Bamise Framework
Support development via donations

Copyright (c) 2026 Segun Mayor

This project is licensed under the **GNU General Public License v3.0**. See the [LICENSE](LICENSE) file for the full text.
