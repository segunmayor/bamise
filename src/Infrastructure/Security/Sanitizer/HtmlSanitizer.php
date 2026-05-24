<?php

declare(strict_types=1);

namespace Bamise\Infrastructure\Security\Sanitizer;

use Bamise\Contract\Security\SanitizerPortInterface;

final class HtmlSanitizer implements SanitizerPortInterface
{
    public function __construct(
        private readonly SanitizerConfig $config,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    #[\Override]
    public function sanitize(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $result[$key] = $this->sanitize($value);

                continue;
            }

            if (! is_string($value)) {
                $result[$key] = $value;

                continue;
            }

            $result[$key] = $this->sanitizeString($value);
        }

        return $result;
    }

    private function sanitizeString(string $value): string
    {
        if ($this->config->allowedTags === []) {
            $clean = strip_tags($value);
        } else {
            $allowed = '<' . implode('><', $this->config->allowedTags) . '>';
            $clean = strip_tags($value, $allowed);
        }

        if ($this->config->encodeEntities) {
            return htmlspecialchars($clean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $clean;
    }
}
