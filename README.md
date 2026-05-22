# Bamise

Secure enterprise CRUD library for PHP 8.4+.

## Architecture

Bamise follows hexagonal (ports and adapters) architecture. Module documentation lives under [`docs/architecture/`](docs/architecture/).

| Module | Path | Notes |
|--------|------|-------|
| 1 — Contracts | `src/Contract/` | Pure interfaces and value contracts |
| 2 — Domain | `src/Domain/` | *(planned)* |
| 3+ | `src/Infrastructure/`, etc. | *(planned)* |

**Naming:** Application-layer ports may live under `src/Port/` in later modules; they alias the same contracts defined in `src/Contract/`. See [01-contracts](docs/architecture/01-contracts.md).

## Requirements

- PHP 8.4+
- PSR-12 coding style
- Constructor injection only (no service locator, facades, or static class state)

## Installation

```bash
composer require bamise/bamise
```

## License

MIT
