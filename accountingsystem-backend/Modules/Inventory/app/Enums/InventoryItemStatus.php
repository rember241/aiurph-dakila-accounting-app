<?php

namespace Modules\Inventory\Enums;

/**
 * Supported inventory item lifecycle states.
 */
final class InventoryItemStatus
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
