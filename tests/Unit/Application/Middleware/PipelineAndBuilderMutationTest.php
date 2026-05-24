<?php

declare(strict_types=1);

namespace Bamise\Tests\Unit\Application\Middleware;

use Bamise\Application\Middleware\MiddlewarePipeline;
use Bamise\Application\Middleware\PipelineBuilder;
use Bamise\Application\Middleware\PrioritizedMiddleware;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Contract\Enum\OperationType;
use Bamise\Tests\Fixtures\FakeCrudRequest;
use PHPUnit\Framework\TestCase;

/**
 * Targeted mutation-killing tests for MiddlewarePipeline and PipelineBuilder.
 *
 * Kills escaped mutants:
 * - MiddlewarePipeline line 58: DecrementInteger (0 → -1) on non-int key path
 * - MiddlewarePipeline line 58: IncrementInteger (0 → +1) on non-int key path
 * - MiddlewarePipeline line 58: Ternary swap (int-indexed gets 0, string-indexed gets index*100)
 * - PipelineBuilder line 15: DecrementInteger/IncrementInteger on default priority=0
 */
final class PipelineAndBuilderMutationTest extends TestCase
{
    private CrudContext $ctx;

    protected function setUp(): void
    {
        $this->ctx = new CrudContext(OperationType::Read, 'x', [], null, new FakeCrudRequest());
    }

    private function terminal(): CrudHandlerInterface
    {
        return new class implements CrudHandlerInterface {
            public function handle(CrudContext $c): CrudResult { return new CrudResult(success: true); }
        };
    }

    private function tracker(array &$order, string $label): MiddlewareInterface
    {
        return new class ($order, $label) implements MiddlewareInterface {
            public function __construct(private array &$o, private string $l) {}

            public function process(CrudContext $c, CrudHandlerInterface $next): CrudResult
            {
                $this->o[] = $this->l;

                return $next->handle($c);
            }
        };
    }

    // ── MiddlewarePipeline: string-keyed gets priority 0 (not -1 or +1) ────────

    public function test_string_keyed_middleware_runs_after_pm_with_lower_priority(): void
    {
        // mwString should get priority 0 (original). pm has explicit priority -1.
        // With priority 0: pm(-1) < mwString(0), so pm runs first.
        // Mutant 0 → -1: mwString(-1) = pm(-1), stable sort keeps pm first (inserted first).
        //   pm runs first → same → test doesn't detect.
        // Mutant 0 → +1: mwString(+1) > pm(-1), so pm runs first → same → not detected.
        //
        // Use a different reference: pm at priority 0 inserted FIRST, then mwString at string key.
        // Original: both 0, stable → pm first in normalized array → sorted [pm(0), ms(0)] → reverse [ms, pm] → execute pm first.
        // Mutant(-1): ms(-1) < pm(0). Sort: [ms(-1), pm(0)] → reverse [pm, ms] → execute ms first.
        $order = [];
        $mwString = $this->tracker($order, 'string_keyed');
        $pm = new PrioritizedMiddleware($this->tracker($order, 'explicit_pm'), 0);

        // pm inserted first in the iterable (before string key)
        $pipeline = new MiddlewarePipeline([$pm, 'any_key' => $mwString], $this->terminal());
        $pipeline->handle($this->ctx);

        // Original: pm first, then string_keyed. (pm has same priority 0 but inserted first)
        self::assertSame('explicit_pm', $order[0], 'String-keyed middleware should not run before same-priority explicit PM');
    }

    public function test_string_keyed_middleware_runs_before_pm_with_priority_1(): void
    {
        // mwString gets priority 0 (original), pm at explicit priority 1.
        // Original: mwString(0) < pm(1) → mwString runs first.
        // Mutant(+1): mwString(+1) = pm(1). Stable sort: pm inserted first → pm first.
        //   → pm runs first → test detects Mutant(+1).
        $order = [];
        $pm = new PrioritizedMiddleware($this->tracker($order, 'explicit_pm'), 1);
        $mwString = $this->tracker($order, 'string_keyed');

        // pm inserted first (PrioritizedMiddleware), then string-keyed
        $pipeline = new MiddlewarePipeline([$pm, 'any_key' => $mwString], $this->terminal());
        $pipeline->handle($this->ctx);

        // Original: string_keyed(0) runs first (lower priority), then pm(1)
        self::assertSame('string_keyed', $order[0], 'String-keyed middleware (priority 0) should run before pm(priority 1)');
    }

    public function test_int_indexed_middleware_priority_determines_order(): void
    {
        // Verify basic: int-indexed at 0 runs before int-indexed at 1 (priority 0 < 100)
        // Also kills Ternary mutation: if Ternary swaps, int-indexed gets 0,
        // and a PM with explicit priority 50 inserted BEFORE it would change order.
        $order = [];
        $pm50 = new PrioritizedMiddleware($this->tracker($order, 'explicit_50'), 50);
        $mwInt1 = $this->tracker($order, 'int_index_1'); // will be at int index 1 → priority 100

        // Construct generator with specific key assignments
        $middlewares = (static function () use ($pm50, $mwInt1): \Generator {
            yield $pm50;        // PrioritizedMiddleware, keeps priority 50
            yield 1 => $mwInt1; // int index 1 → priority 1*100=100 (original) vs 0 (Ternary mutant)
        })();

        $pipeline = new MiddlewarePipeline($middlewares, $this->terminal());
        $pipeline->handle($this->ctx);

        // Original: pm50(50) runs before mwInt1(100). Execute: pm50 first, mwInt1 second.
        // Ternary mutant: mwInt1 gets 0. Sort: [mwInt1(0), pm50(50)]. Execute: mwInt1 first, pm50 second.
        self::assertSame('explicit_50', $order[0], 'Middleware at int index 1 should have priority 100, running after priority 50 PM');
    }

    // ── PipelineBuilder: default priority=0 ──────────────────────────────────

    public function test_builder_default_priority_middleware_runs_before_explicit_priority_1(): void
    {
        // add($mw) with no priority → default 0. add($mw2, 1) → explicit 1.
        // Original: mw(0) before mw2(1). Mutant(+1): mw(+1) = mw2(1), stable → mw inserted first → runs before.
        // Wait, need to use stable sort insight. Let me use explicit pm at priority 1 added SECOND.
        // add($mwDefault) → priority 0; then add($mwExplicit, 1) → priority 1.
        // Original: mwDefault(0) runs first. Mutant(+1): mwDefault(+1) = mwExplicit(1), stable, mwDefault first → SAME!
        //
        // Better: add explicit first with priority 1, then default. Stable sort keeps explicit before default.
        // Original: explicit(1) > default(0) → default runs first. ✓
        // Mutant(+1): default(+1) = explicit(1). Stable: explicit inserted first → explicit first → runs first. Test fails. ✓
        $order = [];
        $mwDefault = $this->tracker($order, 'default_priority');
        $mwExplicit = $this->tracker($order, 'explicit_priority_1');

        $builder = new PipelineBuilder();
        $builder->add($mwExplicit, 1);    // explicit priority 1, added first
        $builder->add($mwDefault);         // no priority → default 0
        $pipeline = $builder->build($this->terminal());
        $pipeline->handle($this->ctx);

        // default_priority (0) should run before explicit_priority_1 (1)
        self::assertSame('default_priority', $order[0]);
    }

    public function test_builder_default_priority_middleware_runs_after_explicit_priority_negative_1(): void
    {
        // add($mwDefault) → priority 0; add($mwNeg, -1) → explicit -1.
        // Original: mwNeg(-1) runs first. ✓
        // Mutant(-1): default(-1) = explicit(-1). Stable: mwDefault inserted first → both -1. Sort stable keeps mwDefault first. Execute: mwDefault first. Test fails. ✓
        $order = [];
        $mwDefault = $this->tracker($order, 'default_priority');
        $mwNeg = $this->tracker($order, 'explicit_negative_1');

        $builder = new PipelineBuilder();
        $builder->add($mwDefault);        // no priority → default 0, added first
        $builder->add($mwNeg, -1);        // explicit -1
        $pipeline = $builder->build($this->terminal());
        $pipeline->handle($this->ctx);

        // explicit_negative_1 (-1) should run before default_priority (0)
        self::assertSame('explicit_negative_1', $order[0]);
    }
}
