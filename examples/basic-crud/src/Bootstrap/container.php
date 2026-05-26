<?php

declare(strict_types=1);

use App\Resource\UserDefinition;
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
use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Domain\Event\LifecycleEventFactory;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Domain\Service\OperationResolver;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;
use Bamise\Infrastructure\Persistence\Repository\PdoRepositoryFactory;

// ── 1. Database ───────────────────────────────────────────────────────────────

$dbPath = __DIR__ . '/../../var/db.sqlite';

$dbConnection = PdoConnection::fromConfig(new ConnectionConfig(
    dsn:      'sqlite:' . $dbPath,
    user:     '',
    password: '',
    driver:   DatabaseDriver::Sqlite,
));

// Apply schema on first run
if (! file_exists($dbPath)) {
    $schema = file_get_contents(__DIR__ . '/../../var/schema.sql');
    if ($schema !== false) {
        $dbConnection->pdo()->exec($schema);
    }
}

// ── 2. Resources and repositories ────────────────────────────────────────────

$userDefinition = new UserDefinition();
$repoFactory    = new PdoRepositoryFactory($dbConnection);

$resourceRegistry   = new ResourceRegistry(['users' => $userDefinition]);
$repositoryResolver = new RepositoryResolver([
    'users' => $repoFactory->for($userDefinition),
]);

// ── 3. Core services ──────────────────────────────────────────────────────────

$fillableGuard   = new FillableGuard();
$contextFactory  = new CrudContextFactory();
$operationMapper = new OperationTypeMapper();

// ── 4. Terminal handler ───────────────────────────────────────────────────────

$terminal = new CrudOrchestrator(
    new SyncEventDispatcher(new ListenerRegistry()),
    new LifecycleEventFactory(),
    new StrategyDispatchHandler(
        new OperationStrategyFactory($repositoryResolver, $resourceRegistry, $fillableGuard)
    ),
);

// ── 5. Pipeline (no middleware — open for local development only) ─────────────

$pipeline = (new PipelineBuilder())->build($terminal);

// ── 6. Application ────────────────────────────────────────────────────────────

$app = new CrudApplication(
    $resourceRegistry,
    $contextFactory,
    new OperationResolver($operationMapper),
    $pipeline,
    new ResponseMapper(),
    new ExceptionMapper(),
    new ApplicationConfig(),
);

return $app;
