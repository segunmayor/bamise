<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Context;

use Bamise\Application\Context\PipelineState;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Domain\Model\Resource;
use Bamise\Domain\Model\ResolvedOperation;
use Bamise\Domain\Model\Subject;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for PipelineState.
 *
 * Kills escaped mutants:
 * - Line 27: PublicVisibility for withContext()
 * - Line 37: PublicVisibility for withSubject()
 * - Line 50: PublicVisibility for withSanitizedData()
 */
final class PipelineStateMutationTest extends TestCase
{
    private PipelineState $state;
    private CrudContext $context;
    private ResolvedOperation $resolved;
    private FakeResourceDefinition $definition;

    protected function setUp(): void
    {
        $this->context = new CrudContext(
            OperationType::Read,
            'products',
            ['id' => 1],
            null,
            new FakeCrudRequest(),
        );
        $this->resolved = new ResolvedOperation(
            OperationType::Read,
            new Resource('products', 'products_table', 'id'),
        );
        $this->definition = new FakeResourceDefinition();
        $this->state = new PipelineState($this->context, $this->resolved, $this->definition);
    }

    // ── Line 27: withContext() is public ──────────────────────────────────────

    public function test_with_context_returns_new_state_with_replaced_context(): void
    {
        $newContext = new CrudContext(
            OperationType::Create,
            'orders',
            [],
            null,
            new FakeCrudRequest('POST'),
        );

        $newState = $this->state->withContext($newContext);

        self::assertNotSame($this->state, $newState);
        self::assertSame($newContext, $newState->context);
        self::assertSame($this->resolved, $newState->resolvedOperation);
        self::assertSame($this->definition, $newState->resourceDefinition);
    }

    public function test_with_context_preserves_subject(): void
    {
        $subject = new Subject('user-1', [], []);
        $stateWithSubject = $this->state->withSubject($subject);

        $newContext = new CrudContext(OperationType::Update, 'x', [], null, new FakeCrudRequest());
        $newState = $stateWithSubject->withContext($newContext);

        self::assertSame($subject, $newState->subject);
    }

    // ── Line 37: withSubject() is public ─────────────────────────────────────

    public function test_with_subject_returns_new_state_with_replaced_subject(): void
    {
        $subject = new Subject('user-42', ['admin'], []);

        $newState = $this->state->withSubject($subject);

        self::assertNotSame($this->state, $newState);
        self::assertSame($subject, $newState->subject);
        self::assertSame($this->context, $newState->context);
        self::assertSame($this->resolved, $newState->resolvedOperation);
    }

    public function test_with_subject_null_clears_subject(): void
    {
        $subject = new Subject('user-1', [], []);
        $withSubject = $this->state->withSubject($subject);

        $cleared = $withSubject->withSubject(null);

        self::assertNull($cleared->subject);
    }

    // ── Line 50: withSanitizedData() is public ───────────────────────────────

    public function test_with_sanitized_data_returns_new_state_with_new_context(): void
    {
        $sanitized = ['name' => 'clean', 'price' => 9.99];

        $newState = $this->state->withSanitizedData($sanitized);

        self::assertNotSame($this->state, $newState);
        self::assertSame($sanitized, $newState->context->inputData);
    }

    public function test_with_sanitized_data_preserves_operation(): void
    {
        $newState = $this->state->withSanitizedData(['x' => 1]);

        self::assertSame(OperationType::Read, $newState->context->operation);
    }

    public function test_with_sanitized_data_preserves_resource_name(): void
    {
        $newState = $this->state->withSanitizedData(['x' => 1]);

        self::assertSame('products', $newState->context->resourceName);
    }

    public function test_initial_state_has_no_subject(): void
    {
        self::assertNull($this->state->subject);
    }
}
