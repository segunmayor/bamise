<?php

declare(strict_types=1);

use App\Resource\ArticleDefinition;
use Bamise\Application\Config\ApplicationConfig;
use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Context\SubjectFactory;
use Bamise\Application\CrudApplication;
use Bamise\Application\Handler\CrudOrchestrator;
use Bamise\Application\Handler\StrategyDispatchHandler;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Application\Middleware\PipelineBuilder;
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
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;
use Bamise\Infrastructure\Persistence\Repository\PdoRepositoryFactory;
use Bamise\Infrastructure\Security\Auth\BearerTokenAuthAdapter;
use Bamise\Infrastructure\Security\Policy\ClassPolicyAdapter;

// ── 1. Database ───────────────────────────────────────────────────────────────

$dbPath = __DIR__ . '/../../var/db.sqlite';

$dbConnection = PdoConnection::fromConfig(new ConnectionConfig(
    dsn:      'sqlite:' . $dbPath,
    user:     '',
    password: '',
    driver:   DatabaseDriver::Sqlite,
));

if (! file_exists($dbPath)) {
    $schema = file_get_contents(__DIR__ . '/../../var/schema.sql');
    if ($schema !== false) {
        $dbConnection->pdo()->exec($schema);
    }
}

// ── 2. Resources ──────────────────────────────────────────────────────────────

$articleDefinition = new ArticleDefinition();
$repoFactory       = new PdoRepositoryFactory($dbConnection);

$resourceRegistry   = new ResourceRegistry(['articles' => $articleDefinition]);
$repositoryResolver = new RepositoryResolver([
    'articles' => $repoFactory->for($articleDefinition),
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

// ── 5. Pipeline ───────────────────────────────────────────────────────────────
//
// Policy evaluation:
// - PermissionEvaluator checks {resource}.{operation} permission strings on the subject
// - ClassPolicyAdapter instantiates ArticlePolicy and calls allows() with the subject,
//   action string, and resource name.  See ArticlePolicy for the role rules.

$pipeline = (new PipelineBuilder())
    ->add(
        new AuthenticationMiddleware(
            new BearerTokenAuthAdapter(),
            new SubjectFactory(),
            $contextFactory,
        ),
        200,
    )
    ->add(
        new AuthorizeMiddleware(
            new PermissionEvaluator(),
            new PolicyEvaluator(
                new ClassPolicyAdapter($articleDefinition->policyClasses()),
                $operationMapper,
            ),
        ),
        600,
    )
    ->build($terminal);

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
