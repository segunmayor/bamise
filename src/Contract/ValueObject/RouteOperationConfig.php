<?php

declare(strict_types=1);

namespace Bamise\Contract\ValueObject;

use Bamise\Contract\Enum\OperationType;
use InvalidArgumentException;

/**
 * Server-side declaration of which operation(s) a route is authorised to perform.
 *
 * Resolution priority:
 *   1. pin()   — single operation, authoritative; client input is never consulted.
 *   2. allow() — server-declared set; HTTP method and (as a last resort) client
 *                hints select within it, but cannot introduce operations outside it.
 *   3. open()  — no server-side restriction; HTTP method resolves the operation and
 *                client hints are still subject to HTTP-method compatibility.
 *
 * Client values (request body fields, headers) may never override the server's
 * declared allowed set.  They can only disambiguate between operations the server
 * has explicitly permitted.
 */
readonly class RouteOperationConfig
{
    /**
     * @param list<OperationType>|null $allowed null means no restriction (open mode)
     */
    private function __construct(
        public ?OperationType $pinned,
        public ?array $allowed,
    ) {
    }

    /**
     * Exactly one operation — the server's authoritative answer.
     * HTTP method, route pattern, and all client input are ignored.
     */
    public static function pin(OperationType $operation): self
    {
        return new self($operation, null);
    }

    /**
     * Server-declared set of permitted operations.
     * HTTP method resolves the base; client hints may only select within this set
     * AND must be compatible with the HTTP verb.
     *
     * @param OperationType $first  At least one operation is required.
     * @param OperationType ...$rest
     */
    public static function allow(OperationType $first, OperationType ...$rest): self
    {
        return new self(null, array_values([$first, ...$rest]));
    }

    /**
     * No server-side restriction on the allowed operation set.
     * Client hints are still limited to HTTP-method-compatible operations.
     */
    public static function open(): self
    {
        return new self(null, null);
    }

    public function isPinned(): bool
    {
        return $this->pinned !== null;
    }

    /** True when an explicit allowed set constrains what is permitted. */
    public function isRestricted(): bool
    {
        return $this->allowed !== null;
    }

    /**
     * Whether $operation is within the declared allowed set.
     * Always returns true when the config is open (unrestricted).
     */
    public function permits(OperationType $operation): bool
    {
        if ($this->allowed === null) {
            return true;
        }

        return in_array($operation, $this->allowed, true);
    }
}
