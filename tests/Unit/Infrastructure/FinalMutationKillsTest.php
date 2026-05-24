<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Infrastructure;

use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Strategy\DeleteStrategy;
use Bamise\Application\Strategy\OperationStrategyFactory;
use Bamise\Application\Strategy\UpdateStrategy;
use Bamise\Contract\Crud\OperationStrategyInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Exception\AuthorizationException;
use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Contract\ValueObject\ResourceId;
use Bamise\Domain\Model\Permission;
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Domain\Service\PermissionEvaluator;
use Bamise\Infrastructure\Security\Audit\AuditConfig;
use Bamise\Infrastructure\Security\Audit\PsrAuditLogger;
use Bamise\Infrastructure\Security\Csrf\CsrfTokenGenerator;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use Bamise\Tests\Fixtures\FakeResourceDefinition;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Final targeted mutation-killing tests.
 *
 * Kills escaped mutants:
 * - AuthorizeMiddleware line 31: InstanceOf_ (null subject throws AuthorizationException)
 * - UpdateStrategy line 30: Coalesce (both pk and id in data, pk wins)
 * - DeleteStrategy line 28: Coalesce (same)
 * - AuditConfig line 13: ArrayItemRemoval ('password' in default redact list)
 * - PsrAuditLogger line 67: Continue_ (array field followed by non-array still output)
 * - CsrfTokenGenerator lines 9/11: DecrementInteger/IncrementInteger on defaults
 */
final class FinalMutationKillsTest extends TestCase
{
    // ── AuthorizeMiddleware line 31: InstanceOf_ ─────────────────────────────

    public function test_authorize_middleware_throws_when_subject_is_null(): void
    {
        $middleware = new AuthorizeMiddleware(
            new PermissionEvaluator(),
            new PolicyEvaluator(
                new class implements \Bamise\Contract\Security\PolicyPortInterface {
                    public function allows(\Bamise\Contract\Enum\OperationType $op, ?object $sub, string $res): bool
                    {
                        return true;
                    }
                },
                new \Bamise\Domain\Service\OperationTypeMapper(),
            ),
        );
        $terminal = new class implements \Bamise\Contract\CrudHandlerInterface {
            public function handle(CrudContext $c): CrudResult
            {
                return new CrudResult(success: true);
            }
        };

        // context with no Subject (null subject)
        $ctx = new CrudContext(OperationType::Read, 'res', [], null, new FakeCrudRequest());

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Authentication required.');

        $middleware->process($ctx, $terminal);
    }

    // ── UpdateStrategy line 30: Coalesce — primaryKey wins over 'id' ─────────

    public function test_update_uses_primaryKey_not_id_when_both_present(): void
    {
        $resources = new ResourceRegistry();
        $repos = new RepositoryResolver();
        $definition = new FakeResourceDefinition(primaryKey: 'product_id', fillable: ['name', 'id'], guarded: ['product_id']);
        $resources->register('products', $definition);

        $capturedId = null;
        $repos->register('products', new class ($capturedId) implements RepositoryInterface {
            public function __construct(private mixed &$id)
            {
            }
            public function find(ResourceId $id): ?array
            {
                return null;
            }
            public function insert(array $data): ResourceId
            {
                return new ResourceId(1);
            }
            public function update(ResourceId $id, array $data): bool
            {
                $this->id = $id->value;
                return true;
            }
            public function delete(ResourceId $id): bool
            {
                return true;
            }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
            {
                return [];
            }
            public function updateBulk(array $criteria, array $data): int
            {
                return 0;
            }
            public function deleteBulk(array $criteria): int
            {
                return 0;
            }
        });

        $strategy = new UpdateStrategy($repos, $resources, new FillableGuard());
        $ctx = new CrudContext(
            OperationType::Update,
            'products',
            ['product_id' => 5, 'id' => 99, 'name' => 'Widget'],
            null,
            new FakeCrudRequest(),
        );
        $strategy->execute($ctx);

        // Original: uses product_id=5. Coalesce mutant: uses id=99.
        self::assertSame(5, $capturedId, 'Update must use primary key field (product_id=5), not id fallback (id=99)');
    }

    // ── DeleteStrategy line 28: Coalesce — primaryKey wins over 'id' ─────────

    public function test_delete_uses_primaryKey_not_id_when_both_present(): void
    {
        $resources = new ResourceRegistry();
        $repos = new RepositoryResolver();
        $definition = new FakeResourceDefinition(primaryKey: 'item_id', fillable: [], guarded: ['item_id']);
        $resources->register('products', $definition);

        $capturedId = null;
        $repos->register('products', new class ($capturedId) implements RepositoryInterface {
            public function __construct(private mixed &$id)
            {
            }
            public function find(ResourceId $id): ?array
            {
                return null;
            }
            public function insert(array $data): ResourceId
            {
                return new ResourceId(1);
            }
            public function update(ResourceId $id, array $data): bool
            {
                return true;
            }
            public function delete(ResourceId $id): bool
            {
                $this->id = $id->value;
                return true;
            }
            public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
            {
                return [];
            }
            public function updateBulk(array $criteria, array $data): int
            {
                return 0;
            }
            public function deleteBulk(array $criteria): int
            {
                return 0;
            }
        });

        $strategy = new DeleteStrategy($repos, $resources);
        $ctx = new CrudContext(
            OperationType::Delete,
            'products',
            ['item_id' => 7, 'id' => 42],
            null,
            new FakeCrudRequest(),
        );
        $strategy->execute($ctx);

        // Original: uses item_id=7. Coalesce mutant: uses id=42.
        self::assertSame(7, $capturedId, 'Delete must use primary key field (item_id=7), not id fallback (id=42)');
    }

    // ── AuditConfig line 13: ArrayItemRemoval — 'password' in defaults ───────

    public function test_audit_config_default_redact_fields_include_password(): void
    {
        $config = new AuditConfig();

        self::assertContains('password', $config->redactFields, "'password' must be in default redact fields");
    }

    public function test_audit_config_default_redact_fields_has_four_entries(): void
    {
        $config = new AuditConfig();

        self::assertCount(4, $config->redactFields, 'Default redact fields must have 4 entries');
    }

    // ── PsrAuditLogger line 67: Continue_→break — array field doesn't stop loop

    public function test_psr_audit_logger_continues_after_array_field(): void
    {
        $logger = new PsrAuditLogger(new NullLogger(), new AuditConfig(redactFields: []));
        $logged = null;

        // Use a capturing logger
        $capturingLogger = new class ($logged) implements \Psr\Log\LoggerInterface {
            public function __construct(private mixed &$captured)
            {
            }
            public function info(string|\Stringable $message, array $context = []): void
            {
                $this->captured = $context;
            }
            public function emergency(string|\Stringable $message, array $context = []): void
            {
            }
            public function alert(string|\Stringable $message, array $context = []): void
            {
            }
            public function critical(string|\Stringable $message, array $context = []): void
            {
            }
            public function error(string|\Stringable $message, array $context = []): void
            {
            }
            public function warning(string|\Stringable $message, array $context = []): void
            {
            }
            public function notice(string|\Stringable $message, array $context = []): void
            {
            }
            public function debug(string|\Stringable $message, array $context = []): void
            {
            }
            public function log($level, string|\Stringable $message, array $context = []): void
            {
            }
        };

        $auditLogger = new PsrAuditLogger($capturingLogger, new AuditConfig(redactFields: []));
        $record = new \Bamise\Contract\ValueObject\AuditRecord(
            actor: 'user1',
            action: 'create',
            resource: 'items',
            recordId: null,
            ip: null,
            userAgent: null,
            before: [
                'nested_arr' => ['x' => 1],  // array field → recursed, then continue
                'plain_field' => 'hello',     // NON-array field AFTER the array — must still appear
            ],
        );

        $auditLogger->log($record);

        // Continue_ mutation → break after nested_arr → plain_field missing from output
        self::assertNotNull($logged ?? null);
        $before = ($logged ?? [])['audit']['before'] ?? null;

        // Since we captured via capturing logger, get from it
        $auditLogger2 = new PsrAuditLogger($capturingLogger, new AuditConfig(redactFields: []));
        $auditLogger2->log($record);

        // Check that before has both fields
        self::assertArrayHasKey('nested_arr', $before ?? [], 'nested_arr must be in before');
        self::assertArrayHasKey('plain_field', $before ?? [], 'plain_field after nested_arr must not be dropped by break');
    }

    // ── CsrfTokenGenerator line 9/11: default byteLength and max() ───────────

    public function test_csrf_generator_default_produces_64_hex_chars(): void
    {
        $generator = new CsrfTokenGenerator();
        $token = $generator->generate();

        // 32 bytes → 64 hex chars. If default=31 → 62 chars, if default=33 → 66 chars
        self::assertSame(64, strlen($token), 'Default generate() must produce 64 hex chars (32 bytes)');
    }

    public function test_csrf_generator_with_1_byte_produces_2_hex_chars(): void
    {
        $generator = new CsrfTokenGenerator();
        $token = $generator->generate(1);

        // max(1, 1)=1 → 2 hex. Mutant max(2, 1)=2 → 4 hex.
        self::assertSame(2, strlen($token), 'generate(1) must produce 2 hex chars (max(1,1)=1 byte)');
    }

    public function test_csrf_generator_explicit_32_same_as_default(): void
    {
        $generator = new CsrfTokenGenerator();

        self::assertSame(64, strlen($generator->generate(32)), '32 bytes = 64 hex chars');
    }
}
