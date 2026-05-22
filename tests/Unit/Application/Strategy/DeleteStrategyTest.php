<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Strategy\DeleteStrategy;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use PHPUnit\Framework\TestCase;

final class DeleteStrategyTest extends TestCase
{
    public function test_returns_success_with_deleted_id(): void
    {
        $result = $this->makeStrategy(deleted: true)->execute($this->context(['id' => 3]));

        self::assertTrue($result->success);
        self::assertSame(3, $result->data['id']);
    }

    public function test_returns_failure_when_no_id_in_input(): void
    {
        $result = $this->makeStrategy()->execute($this->context([]));

        self::assertFalse($result->success);
        self::assertArrayHasKey('message', $result->errors);
    }

    public function test_returns_failure_when_repository_delete_returns_false(): void
    {
        $result = $this->makeStrategy(deleted: false)->execute($this->context(['id' => 99]));

        self::assertFalse($result->success);
    }

    public function test_meta_contains_operation(): void
    {
        $result = $this->makeStrategy(deleted: true)->execute($this->context(['id' => 1]));

        self::assertSame('delete', $result->meta['operation']);
    }

    private function makeStrategy(bool $deleted = true): DeleteStrategy
    {
        $repo = new class ($deleted) implements RepositoryInterface {
            public function __construct(private bool $deleted) {}
            public function find(ResourceId $id): ?array { return null; }
            public function insert(array $data): ResourceId { return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { return true; }
            public function delete(ResourceId $id): bool { return $this->deleted; }
        };

        $def = new FakeResourceDefinition();

        return new DeleteStrategy(
            new RepositoryResolver(['users' => $repo]),
            new ResourceRegistry(['users' => $def]),
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function context(array $input): CrudContext
    {
        return new CrudContext(
            OperationType::Delete,
            'users',
            $input,
            null,
            new FakeCrudRequest('DELETE', '/users', $input),
        );
    }
}
