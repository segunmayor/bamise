# Contributing to Bamise

Thank you for considering a contribution to Bamise. Please read this guide before opening a pull request.

---

## Development Setup

```bash
git clone https://github.com/bamise/bamise.git
cd bamise
composer install
```

---

## Running the Full Suite Locally

```bash
composer ci          # tests + static analysis + code style
composer test        # PHPUnit only (fast feedback loop)
composer analyse     # PHPStan level 6
composer cs-fix      # auto-fix style before committing
composer mutation    # Infection (slow — run before PR, not on every save)
```

---

## Coding Standards

- **PHP 8.4+**, `declare(strict_types=1)` in every file
- **PSR-12** — enforced by PHP CS Fixer (`@PER-CS2.0` + `@PHP84Migration`)
- **One class per file** — PSR-4 autoloading depends on it
- **Constructor injection only** — no service locator, static state, or facades
- **`final` by default** on concrete classes unless the design requires inheritance
- **No comments** unless the *why* is non-obvious (invariants, workarounds, hidden constraints)
- No inline `declare_strict_types` banner comments; `declare(strict_types=1);` is sufficient

Run `composer cs-fix` before every commit. CI will reject PRs that fail CS check.

---

## Architecture Principles

Bamise follows hexagonal (ports-and-adapters) architecture. New code must respect layer boundaries:

| Layer | Package | Rule |
|---|---|---|
| Contracts | `src/Contract/` | Interfaces and value objects only — no implementations |
| Domain | `src/Domain/` | No infrastructure imports |
| Application | `src/Application/` | Depends on Contracts and Domain only |
| Infrastructure | `src/Infrastructure/` | Implements Contract ports; may use external libraries |

Introducing an `Infrastructure` import into `Domain` or `Application` is a hard rejection.

---

## Tests

- Every public method needs at least one test
- Tests live under `tests/Unit/` (mirroring `src/` structure) or `tests/Integration/`
- Use `#[\PHPUnit\Framework\Attributes\DataProvider]` — not `@dataProvider` doc-comments
- Do not use mocks for internal collaborators when a simple anonymous class or fake suffices
- Do not use `final` on test fake/stub classes referenced by multiple tests

CI enforces:
- PHPUnit: 0 failures, 0 errors
- PHPStan: level 6 clean
- PHP CS Fixer: dry-run passes
- Infection (on `main` merges): minMsi ≥ 70%, minCoveredMsi ≥ 80%

---

## Pull Request Process

1. Fork the repository and create a feature branch from `main`
2. Run `composer ci` locally — all checks must pass
3. Run `composer mutation` and ensure the mutation score does not regress
4. Open a PR against `main` with a clear title and description
5. Reference any related issues

PRs that lower the mutation score or introduce PHPStan errors will not be merged.

---

## Commit Messages

Use the imperative mood in the subject line (e.g. `Add SessionAuthAdapter` not `Added …`).
Keep the subject under 72 characters. Reference issue numbers where applicable.

---

## Reporting Bugs

Open an issue at <https://github.com/bamise/bamise/issues> with:
- PHP version
- Bamise version
- Minimal reproduction case
- Expected vs actual behaviour

---

## Security Vulnerabilities

See [SECURITY.md](SECURITY.md).
