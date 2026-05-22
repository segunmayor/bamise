<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Context;

use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Context\PipelineState;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Domain\Model\FieldBag;
use Bamise\Domain\Model\Resource;
use Bamise\Domain\Model\ResolvedOperation;
use Bamise\Domain\Model\Subject;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use PHPUnit\Framework\TestCase;

final class CrudContextFactoryTest extends TestCase
{
    private CrudContextFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new CrudContextFactory();
    }

    public function test_create_builds_context_from_resolved_operation(): void
    {
        $request = new FakeCrudRequest('POST', '/users', ['name' => 'Ada']);
        $resolved = new ResolvedOperation(
            OperationType::Create,
            new Resource('users', 'users', 'id'),
        );

        $context = $this->factory->create($resolved, $request);

        self::assertSame(OperationType::Create, $context->operation);
        self::assertSame('users', $context->resourceName);
        self::assertSame(['name' => 'Ada'], $context->inputData);
        self::assertNull($context->subject);
        self::assertSame($request, $context->request);
    }

    public function test_create_uses_field_bag_data_when_provided(): void
    {
        $request = new FakeCrudRequest('POST', '/users', ['name' => 'Ada']);
        $resolved = new ResolvedOperation(
            OperationType::Create,
            new Resource('users', 'users', 'id'),
        );
        $bag = new FieldBag(['name' => 'Bob']);

        $context = $this->factory->create($resolved, $request, data: $bag);

        self::assertSame(['name' => 'Bob'], $context->inputData);
    }

    public function test_with_subject_replaces_subject_only(): void
    {
        $original = $this->makeContext();
        $subject = new Subject(42, ['admin']);

        $updated = $this->factory->withSubject($original, $subject);

        self::assertSame($subject, $updated->subject);
        self::assertSame($original->operation, $updated->operation);
        self::assertSame($original->resourceName, $updated->resourceName);
        self::assertSame($original->inputData, $updated->inputData);
        self::assertSame($original->request, $updated->request);
    }

    public function test_with_input_data_replaces_input_only(): void
    {
        $original = $this->makeContext(['name' => 'Ada']);
        $newInput = ['name' => 'Bob', 'email' => 'bob@example.com'];

        $updated = $this->factory->withInputData($original, $newInput);

        self::assertSame($newInput, $updated->inputData);
        self::assertSame($original->operation, $updated->operation);
        self::assertSame($original->resourceName, $updated->resourceName);
        self::assertSame($original->subject, $updated->subject);
    }

    public function test_from_state_carries_subject_from_pipeline_state(): void
    {
        $subject = new Subject(7, ['editor']);
        $context = $this->makeContext();
        $state = new PipelineState(
            $context,
            new ResolvedOperation(OperationType::Create, new Resource('users', 'users', 'id')),
            new FakeResourceDefinition(),
            $subject,
        );

        $result = $this->factory->fromState($state);

        self::assertSame($subject, $result->subject);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function makeContext(array $input = []): CrudContext
    {
        return new CrudContext(
            OperationType::Create,
            'users',
            $input,
            null,
            new FakeCrudRequest('POST', '/users', $input),
        );
    }
}
