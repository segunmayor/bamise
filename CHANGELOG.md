# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [0.1.0] — 2026-05-22

### Added

**Module 1 — Contracts**
- `CrudRequestInterface`, `CrudResponseInterface`, `FieldBagInterface`
- `ResourceDefinitionInterface`, `OperationResolverInterface`
- `ConnectionInterface`, `DatabaseDialectInterface`, `RepositoryInterface`
- `AuthPortInterface`, `RateLimiterPortInterface`, `SanitizerPortInterface`, `ValidatorPortInterface`
- `AuditLoggerPortInterface`, `RequestSignerPortInterface`, `CsrfGuardPortInterface`
- `EventDispatcherInterface`, `DomainEventInterface`, `EventSubscriberInterface`, `QueuePortInterface`
- `OperationType`, `DatabaseDriver`, `ResponseMode` enums

**Module 2 — Domain**
- `CrudContext`, `Subject`, `ResolvedOperation`, `FieldBag`, `ResourceOperation` value objects
- `OperationTypeMapper` service (HTTP method → `OperationType`)
- `PolicyEvaluator` with `PolicyInterface` chain
- `LifecycleEventFactory` — `before*` / `after*` helpers for all CRUD operations
- `RepositoryRegistry` with `RepositoryRegistryException`

**Module 3 — Application**
- `ResourceRegistry` and `RepositoryResolver`
- `CrudContextFactory`, `SubjectFactory`, `AuthSubjectDto`
- `PipelineState` value object
- `AuditMiddleware`, `AuthenticationMiddleware`, `RateLimitMiddleware`, `SanitizeMiddleware`, `ValidateMiddleware`
- `OperationStrategyFactory` with `CreateStrategy`, `ReadStrategy`, `UpdateStrategy`, `DeleteStrategy`
- `StrategyDispatchHandler`
- `ResponseMapper` with `CrudResponse`
- `CrudApplication` entry point

**Module 4 — Infrastructure / Persistence**
- `PdoConnection` with nested transaction guard
- `ConnectionConfig` value object
- `MysqlDialect`, `MariadbDialect`, `PostgresDialect`, `SqliteDialect`
- `DialectFactory` — driver-to-dialect mapping
- `SqlCompiler` — parameterised SELECT / INSERT / UPDATE / DELETE builder
- `PdoRepository` — full `RepositoryInterface` over PDO

**Module 8 — Security**
- `CsrfGuard` — token generation and validation backed by `CacheInterface`
- `HtmlSanitizer` — tag-stripping XSS sanitiser
- `CacheRateLimiter` — sliding-window rate limiter backed by `CacheInterface`
- `HmacRequestSigner` — HMAC-SHA256 request signing and verification
- `PolicyChain` and `PolicyAdapter` composites
- `BearerTokenAuthAdapter`, `SessionAuthAdapter`, `JwtAuthAdapter` stubs
- `PsrAuditLogger` — PSR-3 structured audit log with field redaction
- `SecurityFactory` — wires all security components from config
- `InMemoryCache` — in-process `CacheInterface` implementation with TTL

**Module 9 — Events**
- `EventDispatcher` — synchronous dispatcher with stoppable propagation
- `ListenerRegistry` — priority-ordered, interface-expanded listener registry
- `AsyncEventDispatcher` — queue-backed async dispatch via `QueuePortInterface`
- `EventPayloadEncoder` — serialises `DomainEventInterface` to queue payload
- `SubscriberLoader` — registers `EventSubscriberInterface` instances
- `PluginHookDispatcher` — named hook dispatch for plugin extensibility

**Module 10 — Tests**
- 232 PHPUnit tests across Unit suite (all application, domain, and infrastructure classes covered)

**Module 11 — CI/CD**
- GitHub Actions: `ci.yml` (tests + static analysis + code style), `mutation.yml` (Infection)
- `phpstan.neon` — PHPStan level 6 configuration
- `.php-cs-fixer.dist.php` — `@PER-CS2.0` + `@PHP84Migration` rule sets
- `infection.json5` — minMsi 70%, minCoveredMsi 80%

**Module 12 — Composer / Packagist**
- Full Packagist metadata: authors, keywords, homepage, support links
- Semantic versioning with `branch-alias`
- `CHANGELOG.md`, `.github/CONTRIBUTING.md`, `.github/SECURITY.md`
- Architecture documentation for all modules

[Unreleased]: https://github.com/bamise/bamise/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/bamise/bamise/releases/tag/v0.1.0
