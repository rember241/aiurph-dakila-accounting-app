<?php

namespace Modules\Users\Enums;

/**
 * Role lifecycle status.
 */
enum RoleStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn(self $case) => $case->value, self::cases());
    }
}

