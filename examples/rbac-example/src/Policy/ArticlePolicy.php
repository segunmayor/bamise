<?php

declare(strict_types=1);

namespace App\Policy;

use Bamise\Infrastructure\Security\Policy\PolicyInterface;

/**
 * Authors can only update and delete their own articles.
 * Admins can do anything.
 * Viewers can only read.
 */
final class ArticlePolicy implements PolicyInterface
{
    public function allows(object $subject, string $action, string $resource, mixed $target = null): bool
    {
        $roles = (array) ($subject->roles ?? []);

        // Admins are always allowed
        if (in_array('admin', $roles, true)) {
            return true;
        }

        // Viewers: read only
        if (in_array('viewer', $roles, true)) {
            return $action === 'read';
        }

        // Authors: read + create always; update/delete only on own articles
        if (in_array('author', $roles, true)) {
            if (in_array($action, ['read', 'create'], true)) {
                return true;
            }

            if (in_array($action, ['update', 'delete'], true) && $target !== null) {
                $authorId  = $target['author_id'] ?? null;
                $subjectId = $subject->id ?? null;
                return $authorId !== null && (string) $authorId === (string) $subjectId;
            }

            return false;
        }

        return false;
    }
}
