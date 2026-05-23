<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Audit;

use Bamise\Contract\AuditLoggerPortInterface;
use Bamise\Contract\ValueObject\AuditRecord;
use Psr\Log\LoggerInterface;

final class PsrAuditLogger implements AuditLoggerPortInterface
{
    /** @var list<string> */
    private readonly array $normalizedRedactFields;

    public function __construct(
        private readonly LoggerInterface $logger,
        AuditConfig $config,
    ) {
        $this->normalizedRedactFields = array_map(
            static fn (string $field): string => strtolower($field),
            $config->redactFields,
        );
    }

    public function log(AuditRecord $record): void
    {
        $payload = [
            'actor' => $record->actor,
            'action' => $record->action,
            'resource' => $record->resource,
            'record_id' => $record->recordId,
            'ip' => $record->ip,
            'user_agent' => $record->userAgent,
            'before' => $this->redact($record->before),
            'after' => $this->redact($record->after),
            'correlation_id' => $record->correlationId,
        ];

        $this->logger->info('audit', ['audit' => $payload]);
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array<string, mixed>|null
     */
    private function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $redacted = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $this->normalizedRedactFields, true)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $redacted[$key] = $this->redact($value);

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

}
