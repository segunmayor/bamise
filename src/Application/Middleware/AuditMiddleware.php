<?php

declare(strict_types=1);

namespace Bamise\Application\Middleware;

use Bamise\Contract\AuditLoggerPortInterface;
use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\AuditRecord;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;
use Bamise\Domain\Model\Subject;

final class AuditMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuditLoggerPortInterface $auditLogger,
    ) {
    }

    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        $result = $next->handle($context);

        if ($result->success && $this->isMutating($context->operation)) {
            $this->auditLogger->log($this->buildRecord($context, $result));
        }

        return $result;
    }

    private function isMutating(OperationType $operation): bool
    {
        return match ($operation) {
            OperationType::Create,
            OperationType::Update,
            OperationType::Delete,
            OperationType::BulkUpdate,
            OperationType::BulkDelete => true,
            default => false,
        };
    }

    private function buildRecord(CrudContext $context, CrudResult $result): AuditRecord
    {
        $subject = $context->subject;
        $actor = $subject instanceof Subject ? (string) $subject->id : null;

        return new AuditRecord(
            actor: $actor,
            action: $context->operation->value,
            resource: $context->resourceName,
            recordId: $result->data['id'] ?? $context->inputData['id'] ?? null,
            ip: $context->request->clientIp(),
            userAgent: $this->headerValue($context->request->headers(), 'user-agent'),
            before: null,
            after: $result->data !== [] ? $result->data : null,
        );
    }

    /**
     * @param array<string, list<string>|string> $headers
     */
    private function headerValue(array $headers, string $name): ?string
    {
        $normalized = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $normalized) {
                continue;
            }

            if (is_array($value)) {
                return $value[0] ?? null;
            }

            return (string) $value;
        }

        return null;
    }
}
