<?php

declare(strict_types=1);

namespace Bamise\Application\Context;

use Bamise\Domain\Model\Subject;
use InvalidArgumentException;

final class SubjectFactory
{
    public function fromAuthSubject(?object $authSubject): ?Subject
    {
        if ($authSubject === null) {
            return null;
        }

        if ($authSubject instanceof Subject) {
            return $authSubject;
        }

        if ($authSubject instanceof AuthSubjectDto) {
            return new Subject(
                $authSubject->id,
                $authSubject->roles,
                $authSubject->permissions,
            );
        }

        if (method_exists($authSubject, 'id')) {
            $id = $authSubject->id();
            if (! is_string($id) && ! is_int($id)) {
                throw new InvalidArgumentException('Auth subject id must be string or int.');
            }

            $roles = method_exists($authSubject, 'roles') ? (array) $authSubject->roles() : [];
            $permissions = method_exists($authSubject, 'permissions')
                ? (array) $authSubject->permissions()
                : [];

            return new Subject($id, $roles, $permissions);
        }

        throw new InvalidArgumentException(
            sprintf('Cannot map auth subject of type "%s" to domain Subject.', $authSubject::class),
        );
    }
}
