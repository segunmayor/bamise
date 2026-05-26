<?php

declare(strict_types=1);

use App\Resource\PostDefinition;
use App\Resource\UserDefinition;
use Bamise\Application\Config\ApplicationConfig;
use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Context\SubjectFactory;
use Bamise\Application\CrudApplication;
use Bamise\Application\Handler\CrudOrchestrator;
use Bamise\Application\Handler\StrategyDispatchHandler;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Application\Middleware\AuthorizeMiddleware;
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
use Bamise\Infrastructure\Security\Policy\CallablePolicy;
use Bamise\Infrastructure\Security\RateLimit\CacheRateLimiter;
use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use Bamise\Infrastructure\Security\Sanitizer\HtmlSanitizer;
use Bamise\Infrastructure\Security\Sanitizer\SanitizerConfig;

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
$postDefinition = new PostDefinition();
$repoFactory    = new PdoRepositoryFactory($dbConnection);

$resourceRegistry   = new ResourceRegistry([
    'users' => $userDefinition,
    'posts' => $postDefinition,
]);
$repositoryResolver = new RepositoryResolver([
    'users' => $repoFactory->for($userDefinition),
    'posts' => $repoFactory->for($postDefinition),
]);

// ── 3. Core services ──────────────────────────────────────────────────────────

$fillableGuard   = new FillableGuard();
$contextFactory  = new CrudContextFactory();
$operationMapper = new OperationTypeMapper();
$cache           = new InMemoryCache();

// ── 4. Terminal handler ───────────────────────────────────────────────────────

$terminal = new CrudOrchestrator(
    new SyncEventDispatcher(new ListenerRegistry()),
    new LifecycleEventFactory(),
    new StrategyDispatchHandler(
        new OperationStrategyFactory($repositoryResolver, $resourceRegistry, $fillableGuard)
    ),
);

// ── 5. Middleware pipeline ────────────────────────────────────────────────────

$pipeline = (new PipelineBuilder())
    ->add(
        new RateLimitMiddleware(
            new CacheRateLimiter($cache, new RateLimitConfig(maxAttempts: 60, windowSeconds: 60))
        ),
        100,
    )
    ->add(
        new AuthenticationMiddleware(
            new BearerTokenAuthAdapter(),
            new SubjectFactory(),
            $contextFactory,
        ),
        200,
    )
    ->add(
        new SanitizeMiddleware(
            new HtmlSanitizer(new SanitizerConfig()),
            $contextFactory,
        ),
        400,
    )
    ->add(
        new AuthorizeMiddleware(
            new PermissionEvaluator(),
            new PolicyEvaluator(
                new CallablePolicy(
                    static fn ($op, $subject, $res): bool => $subject !== null
                ),
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
