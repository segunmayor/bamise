# Module 1 — Contracts

The contracts module defines the hexagonal boundary for Bamise: pure interfaces, backed enums, readonly value objects, domain event shapes, and exception types. No implementations live here.

## Layout

```
src/Contract/
├── Enum/                    # Backed enums
├── ValueObject/             # Immutable value types
├── Http/                    # Request abstraction (no framework types)
├── Crud/                    # Operation strategy & resource definition
├── Persistence/             # Repository, connection, dialect, query builder
├── Security/                # CSRF, policy, rate limit, signing, sanitization ports
├── Event/                   # Domain event marker & lifecycle events
├── Exception/               # Library exception hierarchy
└── *.php                    # Cross-cutting ports (auth, cache, queue, etc.)
```

Later application modules may expose the same contracts under `src/Port/`; those ports **alias** `src/Contract/` types rather than duplicating them.

## Enums

| Enum | Cases | Purpose |
|------|-------|---------|
| `OperationType` | Create, Read, Update, Delete, BulkDelete, BulkUpdate | CRUD operation discrimination |
| `ResponseMode` | Web, Api | Response formatting mode |
| `DatabaseDriver` | Mysql, Postgres, Mariadb, Sqlite | Driver identification for dialects |

## Value objects

| Class | Role |
|-------|------|
| `ResourceId` | Wraps `string\|int` primary keys |
| `CrudResult` | Operation outcome: success, data, errors, meta |
| `ValidationResult` | Validation outcome: valid flag, errors, sanitized data |
| `AuditRecord` | Immutable audit trail entry |
| `CrudContext` | Immutable operation bag: operation, resource, input, subject, request |

`CrudContext` depends on `CrudRequestInterface` only — never concrete HTTP implementations.

## Core operation flow

```
CrudRequestInterface
        ↓
   CrudContext ──→ MiddlewareInterface ──→ CrudHandlerInterface
        ↓                      ↓
OperationStrategyFactory   OperationStrategyInterface
        ↓                      ↓
OperationStrategyInterface   CrudResult
```

- `ResourceDefinitionInterface` — table metadata, fillable/guarded, rules per operation, policy classes.
- `OperationStrategyInterface::execute(CrudContext): CrudResult`
- `OperationStrategyFactoryInterface::for(OperationType): OperationStrategyInterface`

## Persistence ports

| Interface | Responsibility |
|-----------|----------------|
| `RepositoryInterface` | find, insert, update, delete |
| `ConnectionInterface` | PDO access, dialect, transactions |
| `DatabaseDialectInterface` | Identifier quoting, RETURNING support, driver enum |
| `QueryBuilderInterface` | Fluent SELECT builder |

## Security ports

| Interface | Responsibility |
|-----------|----------------|
| `CsrfPortInterface` | Token validation and generation |
| `PolicyPortInterface` | Authorization checks per operation |
| `RateLimiterPortInterface` | Request throttling |
| `RequestSignerPortInterface` | Signed request verification |
| `SanitizerPortInterface` | Input sanitization (XSS prevention) |

## Cross-cutting ports

| Interface | Responsibility |
|-----------|----------------|
| `AuthPortInterface` | Subject resolution and authentication |
| `ValidatorPortInterface` | Rule-based validation → `ValidationResult` |
| `CachePortInterface` | Key/value cache |
| `QueuePortInterface` | Async job dispatch |
| `AuditLoggerPortInterface` | Persist `AuditRecord` |
| `EventDispatcherPortInterface` | Dispatch and subscribe to events |
| `MiddlewareInterface` | Pipeline middleware |
| `CrudHandlerInterface` | Terminal pipeline handler |
| `PluginInterface` / `PluginRegistryInterface` | Extension registration |

## Domain events

- Marker: `DomainEventInterface`
- Lifecycle readonly classes: `BeforeCreate`, `AfterCreate`, `BeforeUpdate`, `AfterUpdate`, `BeforeDelete`, `AfterDelete` — each holds `CrudContext` and optional payload array.

## Exceptions

```
BamiseException
├── OperationResolutionException
├── AuthorizationException
├── ValidationException
├── CsrfException
└── RateLimitException
```

## Design constraints (module 1)

- PHP 8.4+, `declare(strict_types=1);` on every file
- PSR-12 coding style
- Constructor injection only in future modules — no static state, service locators, or facades on classes
- Contracts have zero infrastructure dependencies (PSR interfaces allowed at package level: `psr/container`, `psr/log` for future wiring)

## Next module

**Module 2 — Domain** will introduce domain services, entities, and business rules that depend on these contracts but remain free of infrastructure.
