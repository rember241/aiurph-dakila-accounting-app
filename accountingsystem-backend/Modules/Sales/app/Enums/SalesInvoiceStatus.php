<?php

namespace Modules\Sales\Enums;

/**
 * Supported sales invoice lifecycle states.
 */
final class SalesInvoiceStatus
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            'draft',
            'issued',
            'cancelled',
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'issued' => 'Issued',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
