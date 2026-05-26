# Installation

## Requirements

| Requirement | Minimum | Notes |
|-------------|---------|-------|
| PHP | **8.4** | Requires enums, readonly classes, constructor promotion |
| Composer | 2.x | PHP package manager |
| PDO | bundled with PHP | Core extension — always available |
| pdo_sqlite | bundled | For SQLite databases (local development) |
| pdo_mysql | optional | For MySQL 8+ and MariaDB 10.6+ production use |
| pdo_pgsql | optional | For PostgreSQL 13+ production use |

Check your environment:

```bash
# Verify PHP version (must be ≥ 8.4)
php -v

# Verify Composer
composer --version

# Verify PDO drivers
php -m | grep -i pdo
# Expected output includes: PDO, pdo_sqlite (minimum)
# For MySQL add: pdo_mysql
# For PostgreSQL add: pdo_pgsql
```

If `pdo_mysql` is missing on Debian/Ubuntu:

```bash
sudo apt-get install php8.4-mysql
```

On Alpine (Docker):

```bash
apk add php84-pdo php84-pdo_mysql php84-pdo_sqlite
```

---

## Install via Composer

```bash
composer require bamise/framework
```

This installs Bamise and its two production dependencies (`psr/container ^2.0`,
`psr/log ^3.0`) into your `vendor/` directory.

Verify the install:

```bash
php -r "require 'vendor/autoload.php'; echo Bamise\Application\CrudApplication::class . PHP_EOL;"
# Should print: Bamise\Application\CrudApplication
```

---

## Optional: JWT support

Bamise ships `JwtAuthAdapter` for verifying signed Bearer tokens. It depends on
`firebase/php-jwt`, which is not installed by default:

```bash
composer require firebase/php-jwt:^6.10
```

You only need this if you use `SecurityFactory::jwtAuth($secret)`.

---

## Development dependencies

For contributing to Bamise or running its test suite:

```bash
composer install --dev
```

This adds PHPUnit, PHPStan, PHP CS Fixer, Psalm, PHP_CodeSniffer, and Infection.

---

## Directory structure for a new project

```
my-app/
├── composer.json           ← "require": {"bamise/framework": "..."}
├── public/
│   └── index.php           ← front controller
├── src/
│   ├── Bootstrap/
│   │   └── container.php   ← wires all Bamise objects
│   ├── Http/
│   │   └── PhpRequest.php  ← implements CrudRequestInterface
│   └── Resource/
│       └── UserDefinition.php  ← describes one database table
└── var/
    └── db.sqlite           ← SQLite database (development)
```

---

## Environment variables

Bamise does not read environment variables automatically. Your bootstrap file should
read them and pass the values to the relevant config objects:

```php
// src/Bootstrap/container.php
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;
use Bamise\Contract\Enum\DatabaseDriver;

$config = new ConnectionConfig(
    dsn:      getenv('DB_DSN')      ?: 'sqlite:' . __DIR__ . '/../../var/db.sqlite',
    user:     getenv('DB_USER')     ?: '',
    password: getenv('DB_PASSWORD') ?: '',
    driver:   DatabaseDriver::from(getenv('DB_DRIVER') ?: 'sqlite'),
);
```

A minimal `.env` for local development (requires your own `dotenv` loader, such as
`vlucas/phpdotenv` — Bamise does not ship one):

```ini
DB_DSN=mysql:host=127.0.0.1;dbname=myapp;charset=utf8mb4
DB_USER=myapp
DB_PASSWORD=secret
DB_DRIVER=mysql
BAMISE_SIGNING_SECRET=change-me-in-production
```

---

## Verifying installation with a smoke test

Create `smoke.php` in your project root:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;

$conn = PdoConnection::fromConfig(new ConnectionConfig(
    dsn:      'sqlite::memory:',
    user:     '',
    password: '',
    driver:   DatabaseDriver::Sqlite,
));

$conn->pdo()->exec('CREATE TABLE ping (id INTEGER PRIMARY KEY)');
$conn->pdo()->exec('INSERT INTO ping VALUES (1)');

$row = $conn->pdo()->query('SELECT id FROM ping')->fetch(\PDO::FETCH_ASSOC);

echo $row['id'] === 1 ? "Installation OK\n" : "Something went wrong\n";
```

Run it:

```bash
php smoke.php
# Installation OK
```

---

## Next step

→ [Quick Start](quick-start.md) — get a running CRUD endpoint in 5 minutes.
