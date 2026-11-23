<?php

namespace Modules\Users\Enums;

/**
 * Organization unit lifecycle status.
 */
enum OrgUnitStatus: string
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
