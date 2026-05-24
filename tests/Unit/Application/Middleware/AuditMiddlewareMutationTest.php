<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Middleware;

use Bamise\Application\Middleware\AuditMiddleware;
use Bamise\Contract\AuditLoggerPortInterface;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\ValueObject\AuditRecord;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Model\Subject;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for AuditMiddleware.
 *
 * Kills escaped mutants:
 * - Line 37: MatchArmRemoval for all 5 mutating operations
 * - Line 60: NotIdentical/Ternary on actor (Subject vs non-Subject)
 * - Line 70: Coalesce on data['id'] vs inputData['id']
 * - Line 72: LogicalOr on is_int || is_string
 * - Lines 84,87,88,92,95: headerValue internals (strtolower, CastString, Continue_, IncrementInteger)
 */
final class AuditMiddlewareMutationTest extends TestCase
{
    /** @var list<AuditRecord> */
    private array $logged = [];

    private AuditMiddleware $middleware;

    protected function setUp(): void
    {
        $logger = new class ($this->logged) implements AuditLoggerPortInterface {
            /** @param list<AuditRecord> $log */
            public function __construct(private array &$log) {}

            public function log(AuditRecord $record): void
            {
                $this->log[] = $record;
            }
        };

        $this->middleware = new AuditMiddleware($logger);
    }

    private function context(
        OperationType $operation,
        array $inputData = [],
        ?object $subject = null,
        array $headers = [],
    ): CrudContext {
        return new CrudContext(
            $operation,
            'products',
            $inputData,
            $subject,
            new FakeCrudRequest(headers: $headers),
        );
    }

    private function nextWith(CrudResult $result): CrudHandlerInterface
    {
        return new class ($result) implements CrudHandlerInterface {
            public function __construct(private CrudResult $r) {}

            public function handle(CrudContext $c): CrudResult
            {
                unset($c);

                return $this->r;
            }
        };
    }

    // ── Line 37: MatchArmRemoval — each mutating operation must trigger audit ─

    public function test_create_operation_triggers_audit(): void
    {
        $ctx = $this->context(OperationType::Create);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertCount(1, $this->logged);
    }

    public function test_update_operation_triggers_audit(): void
    {
        $ctx = $this->context(OperationType::Update);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertCount(1, $this->logged);
    }

    public function test_delete_operation_triggers_audit(): void
    {
        $ctx = $this->context(OperationType::Delete);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertCount(1, $this->logged);
    }

    public function test_bulk_update_operation_triggers_audit(): void
    {
        $ctx = $this->context(OperationType::BulkUpdate);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertCount(1, $this->logged);
    }

    public function test_bulk_delete_operation_triggers_audit(): void
    {
        $ctx = $this->context(OperationType::BulkDelete);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertCount(1, $this->logged);
    }

    public function test_read_operation_does_not_trigger_audit(): void
    {
        $ctx = $this->context(OperationType::Read);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertCount(0, $this->logged);
    }

    public function test_failed_result_does_not_trigger_audit(): void
    {
        $ctx = $this->context(OperationType::Create);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: false)));

        self::assertCount(0, $this->logged);
    }

    // ── Line 60: actor from Subject vs other subject type ─────────────────────

    public function test_subject_instance_produces_actor_string(): void
    {
        $subject = new Subject('user-99', [], []);
        $ctx = $this->context(OperationType::Create, [], $subject);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertSame('user-99', $this->logged[0]->actor);
    }

    public function test_non_subject_instance_produces_null_actor(): void
    {
        $ctx = $this->context(OperationType::Create, [], new \stdClass());
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertNull($this->logged[0]->actor);
    }

    public function test_null_subject_produces_null_actor(): void
    {
        $ctx = $this->context(OperationType::Create, [], null);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertNull($this->logged[0]->actor);
    }

    // ── Line 70: Coalesce — data['id'] takes precedence over inputData['id'] ──

    public function test_record_id_from_result_data_takes_priority(): void
    {
        $ctx = $this->context(OperationType::Create, ['id' => 999]);
        $result = new CrudResult(success: true, data: ['id' => 42]);
        $this->middleware->process($ctx, $this->nextWith($result));

        self::assertSame(42, $this->logged[0]->recordId);
    }

    public function test_record_id_falls_back_to_input_data(): void
    {
        $ctx = $this->context(OperationType::Update, ['id' => 77]);
        $result = new CrudResult(success: true, data: ['name' => 'Bob']);
        $this->middleware->process($ctx, $this->nextWith($result));

        self::assertSame(77, $this->logged[0]->recordId);
    }

    public function test_record_id_is_null_when_not_in_data_or_input(): void
    {
        $ctx = $this->context(OperationType::Delete, []);
        $result = new CrudResult(success: true, data: ['name' => 'Bob']);
        $this->middleware->process($ctx, $this->nextWith($result));

        self::assertNull($this->logged[0]->recordId);
    }

    // ── Line 72: LogicalOr — both int and string record ids are kept ──────────

    public function test_int_record_id_is_preserved(): void
    {
        $ctx = $this->context(OperationType::Delete);
        $result = new CrudResult(success: true, data: ['id' => 123]);
        $this->middleware->process($ctx, $this->nextWith($result));

        self::assertSame(123, $this->logged[0]->recordId);
        self::assertIsInt($this->logged[0]->recordId);
    }

    public function test_string_record_id_is_preserved(): void
    {
        $ctx = $this->context(OperationType::Delete);
        $result = new CrudResult(success: true, data: ['id' => 'order-456']);
        $this->middleware->process($ctx, $this->nextWith($result));

        self::assertSame('order-456', $this->logged[0]->recordId);
    }

    public function test_array_record_id_yields_null(): void
    {
        $ctx = $this->context(OperationType::Delete);
        $result = new CrudResult(success: true, data: ['id' => ['nested']]);
        $this->middleware->process($ctx, $this->nextWith($result));

        self::assertNull($this->logged[0]->recordId);
    }

    // ── Lines 84,87,88: strtolower/CastString/Continue_ in headerValue ────────

    public function test_user_agent_extracted_from_lowercase_header(): void
    {
        $ctx = $this->context(OperationType::Create, [], null, ['user-agent' => 'TestBrowser/1.0']);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertSame('TestBrowser/1.0', $this->logged[0]->userAgent);
    }

    public function test_user_agent_extracted_from_uppercase_header(): void
    {
        $ctx = $this->context(OperationType::Create, [], null, ['User-Agent' => 'MyBot/2.0']);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertSame('MyBot/2.0', $this->logged[0]->userAgent);
    }

    public function test_unrelated_headers_skipped_and_user_agent_found(): void
    {
        $ctx = $this->context(OperationType::Create, [], null, [
            'Content-Type' => 'application/json',
            'User-Agent' => 'TargetAgent/3.0',
            'Accept' => '*/*',
        ]);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertSame('TargetAgent/3.0', $this->logged[0]->userAgent);
    }

    // ── Line 92: IncrementInteger on $value[0] for array header ──────────────

    public function test_user_agent_as_array_uses_first_element(): void
    {
        $ctx = $this->context(OperationType::Create, [], null, [
            'User-Agent' => ['PrimaryAgent', 'SecondaryAgent'],
        ]);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertSame('PrimaryAgent', $this->logged[0]->userAgent);
    }

    // ── Line 95: CastString on non-array header ───────────────────────────────

    public function test_user_agent_missing_header_is_null(): void
    {
        $ctx = $this->context(OperationType::Create, [], null, ['Accept' => '*/*']);
        $this->middleware->process($ctx, $this->nextWith(new CrudResult(success: true)));

        self::assertNull($this->logged[0]->userAgent);
    }

    // ── After data is non-empty ───────────────────────────────────────────────

    public function test_non_empty_result_data_is_stored_as_after(): void
    {
        $ctx = $this->context(OperationType::Create);
        $result = new CrudResult(success: true, data: ['id' => 1, 'name' => 'Widget']);
        $this->middleware->process($ctx, $this->nextWith($result));

        self::assertSame(['id' => 1, 'name' => 'Widget'], $this->logged[0]->after);
    }

    public function test_empty_result_data_stores_null_as_after(): void
    {
        $ctx = $this->context(OperationType::Create);
        $result = new CrudResult(success: true, data: []);
        $this->middleware->process($ctx, $this->nextWith($result));

        self::assertNull($this->logged[0]->after);
    }
}
