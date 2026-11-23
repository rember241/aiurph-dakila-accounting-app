<?php

namespace Modules\Customers\Enums;

/**
 * Supported customer group classifications.
 */
final class CustomerGroup
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            'commercial',
            'government',
            'individual',
            'non_profit',
        ];
    }
}
