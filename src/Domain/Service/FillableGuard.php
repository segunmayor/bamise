<?php

declare(strict_types=1);

namespace Bamise\Domain\Service;

use Bamise\Domain\Exception\MassAssignmentException;

final class FillableGuard
{
    /**
     * @param array<string, mixed> $data
     * @param list<string>         $fillable
     * @param list<string>         $guarded
     *
     * @return array<string, mixed>
     */
    public function filter(array $data, array $fillable, array $guarded): array
    {
        $stripped = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $guarded, true)) {
                $stripped[$key] = $value;
                continue;
            }

            if ($fillable !== [] && ! in_array($key, $fillable, true)) {
                throw new MassAssignmentException(
                    sprintf('Mass assignment not allowed for field "%s".', $key),
                );
            }

            $stripped[$key] = $value;
        }

        if ($fillable === []) {
            return $stripped;
        }

        return array_intersect_key(
            $stripped,
            array_flip($fillable),
        );
    }
}
