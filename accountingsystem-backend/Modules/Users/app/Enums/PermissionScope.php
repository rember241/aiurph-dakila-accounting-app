<?php

namespace Modules\Users\Enums;

/**
 * Describes how far a role's permissions apply across the organization.
 *
 * - global: access across all records/modules permitted
 * - department: access constrained to a department boundary (future)
 * - own: access constrained to "created by/assigned to user" boundary (future)
 */
enum PermissionScope: string
{
    case GLOBAL = 'global';
    case DEPARTMENT = 'department';
    case OWN = 'own';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn(self $case) => $case->value, self::cases());
    }
}

