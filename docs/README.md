# Bamise Documentation

# Bamise

![Bamise Logo](../.github/assets/bamise_logo.png)

Primary Blue: #2563EB
Accent Orange: #F97316
Dark: #111827
Light: #FFFFFF

Bamise is a PHP 8.4+ library for building secure, structured CRUD operations using
hexagonal architecture. It integrates with any HTTP framework — you bring the request
object; Bamise handles routing, middleware, auth, events, and the database layer.

---

## Start Here

If you are completely new to Bamise, follow this path in order:

| Step | Document | Time |
|------|----------|------|
| 1 | [Installation](installation.md) | 2 min |
| 2 | [Quick Start](quick-start.md) | 5 min |
| 3 | [Your First Project](first-project.md) | 20 min |
| 4 | [CRUD Operations](crud.md) | 10 min |
| 5 | [Security](security.md) | 15 min |

---

## Reference

| Document | What it covers |
|----------|----------------|
| [Installation](installation.md) | Requirements, Composer setup, extensions |
| [Quick Start](quick-start.md) | Smallest working example |
| [First Project](first-project.md) | Full project with database, routing, HTML form |
| [Routing](routing.md) | URL routing, resource dispatch, operation pinning |
| [CRUD Operations](crud.md) | Create, read, update, delete, bulk operations |
| [Query Builder](query-builder.md) | Filtering, pagination, custom queries |
| [Middleware](middleware.md) | Built-in and custom middleware |
| [Security](security.md) | CSRF, authentication, authorization, rate limiting |
| [Events](events.md) | Lifecycle events, listeners, subscribers |
| [FAQ](faq.md) | Frequently asked questions |
| [Troubleshooting](troubleshooting.md) | Common errors and how to fix them |

---

## Architecture Reference

For contributors and advanced users, the architecture docs explain every internal module:

| Document | Covers |
|----------|--------|
| [01-contracts.md](architecture/01-contracts.md) | Ports, interfaces, value objects, enums |
| [02-domain.md](architecture/02-domain.md) | Domain services, models, operation resolution |
| [03-application.md](architecture/03-application.md) | CrudApplication, pipeline, strategies |
| [04-infrastructure.md](architecture/04-infrastructure.md) | PDO, SQL compiler, repositories |
| [05-middleware.md](architecture/05-middleware.md) | Pipeline internals, priority ordering |
| [06-strategies.md](architecture/06-strategies.md) | Strategy pattern, bulk operations |
| [08-security.md](architecture/08-security.md) | Security adapters, threat model |
| [09-events.md](architecture/09-events.md) | Event system, async dispatch, subscribers |
| [11-cicd.md](architecture/11-cicd.md) | CI/CD, static analysis, mutation testing |
| [12-composer.md](architecture/12-composer.md) | Package publishing and versioning |

---

## Examples

Working mini-projects ready to run:

| Example | Demonstrates |
|---------|-------------|
| [basic-crud/](../examples/basic-crud/) | SQLite CRUD with no auth — zero config to start |
| [api-example/](../examples/api-example/) | JSON API with Bearer token authentication |
| [rbac-example/](../examples/rbac-example/) | Role-based access control with custom policies |
| [file-upload/](../examples/file-upload/) | File metadata stored via Bamise + custom strategy |

---

## Core concepts in one paragraph

Every HTTP request arrives as a `CrudRequestInterface`. Bamise maps the HTTP verb to a
`OperationType` (POST→Create, GET→Read, PUT/PATCH→Update, DELETE→Delete), then runs the
request through a `MiddlewarePipeline` (rate limit → auth → CSRF → sanitize → validate →
authorize → audit) before delegating to one of the CRUD strategies, which execute safe
parameterised SQL through a `PdoRepository`. The result comes back as a `ResponseEnvelope`
with `success`, `data`, `errors`, `meta`, and `httpStatus` fields.

---

## Version

Bamise requires **PHP 8.4+**. This documentation describes the current `dev-master` / `0.1.x-dev`
release.

## License & Copyright

Formerly: Sitcalm
Current: Bamise Framework
Support development via donations

Copyright (c) 2026 Segun Mayor

This project is licensed under the **GNU General Public License v3.0**. See the [LICENSE](LICENSE) file for the full text.