<?php

namespace Modules\Users\Enums;

/**
 * User lifecycle status.
 *
 * Inactive users should be blocked from logging in and should have tokens revoked.
 */
enum UserStatus: string
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

