<?php

declare(strict_types=1);

namespace Bamise\Tests\Integration\Infrastructure;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Strategy\CreateStrategy;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Infrastructure\Persistence\Repository\PdoRepositoryFactory;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\SqliteTestConnection;
use Bamise\Tests\Fixtures\TestUserResourceDefinition;
use PHPUnit\Framework\TestCase;

final class CreateStrategyIntegrationTest extends TestCase
{
    private CreateStrategy $strategy;

    protected function setUp(): void
    {
        if (! SqliteTestConnection::isAvailable()) {
            self::markTestSkipped('pdo_sqlite extension is not available.');
        }

        $connection = SqliteTestConnection::create();
        $connection->pdo()->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL
            )',
        );

        $definition = new TestUserResourceDefinition();
        $factory = new PdoRepositoryFactory($connection);
        $repository = $factory->for($definition);

        $resolver = new RepositoryResolver(['users' => $repository]);
        $resources = new ResourceRegistry(['users' => $definition]);

        $this->strategy = new CreateStrategy($resolver, $resources, new FillableGuard());
    }

    public function test_create_persists_row_and_returns_id(): void
    {
        $context = new CrudContext(
            operation: OperationType::Create,
            resourceName: 'users',
            inputData: [
                'name' => 'Alan Turing',
                'email' => 'alan@example.com',
            ],
            subject: null,
            request: new FakeCrudRequest('POST', '/users'),
        );

        $result = $this->strategy->execute($context);

        self::assertTrue($result->success);
        self::assertArrayHasKey('id', $result->data);
        self::assertSame('Alan Turing', $result->data['name']);
        self::assertSame('alan@example.com', $result->data['email']);
    }
}
