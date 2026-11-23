<?php

namespace Modules\Inventory\Enums;

/**
 * Supported product master item types.
 */
final class InventoryItemType
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            'inventory',
            'service',
            'non_inventory',
        ];
    }
}
