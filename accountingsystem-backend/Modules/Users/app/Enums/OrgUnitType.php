<?php

namespace Modules\Users\Enums;

/**
 * Supported company organization unit types.
 */
enum OrgUnitType: string
{
    case HEAD_OFFICE = 'head_office';
    case BRANCH = 'branch';
    case DEPARTMENT = 'department';
    case TEAM = 'team';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn(self $case) => $case->value, self::cases());
    }
}
