<?php

namespace Modules\Customers\Enums;

/**
 * Supported customer lifecycle states.
 */
final class CustomerStatus
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            'active',
            'inactive',
        ];
    }
}
