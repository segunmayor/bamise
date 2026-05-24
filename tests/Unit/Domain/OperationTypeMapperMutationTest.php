<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Domain;

use Bamise\Contract\Enum\OperationType;
use Bamise\Domain\Service\OperationTypeMapper;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for OperationTypeMapper.
 *
 * Kills escaped mutants:
 * - Line 41: MatchArmRemoval (each HTTP method match arm)
 * - Line 41: UnwrapStrToUpper (method normalization)
 * - Lines 42,43: ArrayItemRemoval (Delete/BulkDelete and Update/BulkUpdate in compatible list)
 */
final class OperationTypeMapperMutationTest extends TestCase
{
    private OperationTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new OperationTypeMapper();
    }

    // ── Line 41: MatchArmRemoval — each method's compatible list ─────────────

    public function test_get_compatible_includes_only_read(): void
    {
        $ops = $this->mapper->compatibleOperations('GET');

        self::assertContains(OperationType::Read, $ops);
        self::assertNotContains(OperationType::Create, $ops);
        self::assertNotContains(OperationType::Delete, $ops);
    }

    public function test_post_compatible_includes_only_create(): void
    {
        $ops = $this->mapper->compatibleOperations('POST');

        self::assertContains(OperationType::Create, $ops);
        self::assertNotContains(OperationType::Read, $ops);
        self::assertNotContains(OperationType::Update, $ops);
    }

    public function test_put_compatible_includes_update_and_bulk_update(): void
    {
        $ops = $this->mapper->compatibleOperations('PUT');

        self::assertContains(OperationType::Update, $ops);
        self::assertContains(OperationType::BulkUpdate, $ops);
        self::assertNotContains(OperationType::Delete, $ops);
    }

    public function test_patch_compatible_same_as_put(): void
    {
        $ops = $this->mapper->compatibleOperations('PATCH');

        self::assertContains(OperationType::Update, $ops);
        self::assertContains(OperationType::BulkUpdate, $ops);
    }

    public function test_delete_compatible_includes_delete_and_bulk_delete(): void
    {
        $ops = $this->mapper->compatibleOperations('DELETE');

        self::assertContains(OperationType::Delete, $ops);
        self::assertContains(OperationType::BulkDelete, $ops);
        self::assertNotContains(OperationType::Create, $ops);
    }

    // ── Line 42: ArrayItemRemoval on Delete group ─────────────────────────────

    public function test_delete_compatible_has_exactly_two_operations(): void
    {
        $ops = $this->mapper->compatibleOperations('DELETE');

        self::assertCount(2, $ops);
    }

    public function test_delete_and_bulk_delete_are_distinct_in_compatible_list(): void
    {
        $ops = $this->mapper->compatibleOperations('DELETE');
        $values = array_map(fn (OperationType $o): string => $o->value, $ops);

        self::assertContains('delete', $values);
        self::assertContains('bulk_delete', $values);
    }

    // ── Line 43: ArrayItemRemoval on Update group ─────────────────────────────

    public function test_patch_compatible_has_exactly_two_operations(): void
    {
        $ops = $this->mapper->compatibleOperations('PATCH');

        self::assertCount(2, $ops);
    }

    public function test_update_and_bulk_update_are_distinct_in_compatible_list(): void
    {
        $ops = $this->mapper->compatibleOperations('PATCH');
        $values = array_map(fn (OperationType $o): string => $o->value, $ops);

        self::assertContains('update', $values);
        self::assertContains('bulk_update', $values);
    }

    // ── Line 41: UnwrapStrToUpper — method is normalized ─────────────────────

    public function test_lowercase_delete_has_same_compatible_as_uppercase(): void
    {
        $lower = $this->mapper->compatibleOperations('delete');
        $upper = $this->mapper->compatibleOperations('DELETE');

        self::assertSame(
            array_map(fn ($o) => $o->value, $upper),
            array_map(fn ($o) => $o->value, $lower),
        );
    }

    public function test_lowercase_get_resolves_correctly(): void
    {
        $ops = $this->mapper->compatibleOperations('get');

        self::assertContains(OperationType::Read, $ops);
    }

    public function test_unknown_method_returns_empty_list(): void
    {
        $ops = $this->mapper->compatibleOperations('OPTIONS');

        self::assertSame([], $ops);
    }

    // ── fromHttpMethod: lowercase input is normalized ─────────────────────────

    public function test_from_http_method_lowercase_get_maps_to_read(): void
    {
        self::assertSame(OperationType::Read, $this->mapper->fromHttpMethod('get'));
    }

    public function test_from_http_method_delete_maps_to_delete(): void
    {
        self::assertSame(OperationType::Delete, $this->mapper->fromHttpMethod('DELETE'));
    }

    // ── fromString ─────────────────────────────────────────────────────────────

    public function test_from_string_read(): void
    {
        self::assertSame(OperationType::Read, $this->mapper->fromString('read'));
    }

    public function test_from_string_bulk_delete(): void
    {
        self::assertSame(OperationType::BulkDelete, $this->mapper->fromString('bulk_delete'));
    }

    public function test_from_string_unknown_returns_null(): void
    {
        self::assertNull($this->mapper->fromString('nonexistent'));
    }
}
