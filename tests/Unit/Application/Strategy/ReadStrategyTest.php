<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Strategy;

use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Strategy\ReadStrategy;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use PHPUnit\Framework\TestCase;

final class ReadStrategyTest extends TestCase
{
    public function test_returns_row_when_found(): void
    {
        $row = ['id' => 3, 'name' => 'Ada'];
        $repo = new class ($row) implements RepositoryInterface {
            public function __construct(private array $row) {}
            public function find(ResourceId $id): ?array { return $this->row; }
            public function insert(array $data): ResourceId { return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { return true; }
            public function delete(ResourceId $id): bool { return true; }
        };

        $result = $this->makeStrategy($repo)->execute($this->context(['id' => 3]));

        self::assertTrue($result->success);
        self::assertSame($row, $result->data);
    }

    public function test_returns_not_found_when_row_is_null(): void
    {
        $repo = new class implements RepositoryInterface {
            public function find(ResourceId $id): ?array { return null; }
            public function insert(array $data): ResourceId { return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { return true; }
            public function delete(ResourceId $id): bool { return true; }
        };

        $result = $this->makeStrategy($repo)->execute($this->context(['id' => 99]));

        self::assertFalse($result->success);
        self::assertArrayHasKey('message', $result->errors);
    }

    public function test_returns_not_found_when_id_absent_from_input(): void
    {
        $repo = new class implements RepositoryInterface {
            public function find(ResourceId $id): ?array { return ['id' => 1]; }
            public function insert(array $data): ResourceId { return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { return true; }
            public function delete(ResourceId $id): bool { return true; }
        };

        $result = $this->makeStrategy($repo)->execute($this->context([]));

        self::assertFalse($result->success);
    }

    public function test_uses_primary_key_from_definition(): void
    {
        $captured = null;
        $repo = new class ($captured) implements RepositoryInterface {
            public function __construct(private mixed &$captured) {}
            public function find(ResourceId $id): ?array
            {
                $this->captured = $id->value;
                return ['uuid' => 'abc'];
            }
            public function insert(array $data): ResourceId { return new ResourceId(1); }
            public function update(ResourceId $id, array $data): bool { return true; }
            public function delete(ResourceId $id): bool { return true; }
        };

        $def = new FakeResourceDefinition(primaryKey: 'uuid');
        $resources = new ResourceRegistry(['users' => $def]);
        $resolver = new RepositoryResolver(['users' => $repo]);
        $strategy = new ReadStrategy($resolver, $resources);

        $strategy->execute($this->context(['uuid' => 'abc']));

        self::assertSame('abc', $captured);
    }

    private function makeStrategy(RepositoryInterface $repo): ReadStrategy
    {
        $resources = new ResourceRegistry(['users' => new FakeResourceDefinition()]);
        $resolver = new RepositoryResolver(['users' => $repo]);

        return new ReadStrategy($resolver, $resources);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function context(array $input): CrudContext
    {
        return new CrudContext(
            OperationType::Read,
            'users',
            $input,
            null,
            new FakeCrudRequest('GET', '/users', $input),
        );
    }
}
