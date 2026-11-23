<?php

namespace Modules\Sales\Enums;

/**
 * Business-facing sales invoice statuses derived from workflow, due dates,
 * and payment activity.
 */
final class SalesInvoiceDisplayStatus
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            'draft',
            'issued',
            'partially_paid',
            'paid',
            'overdue',
            'cancelled',
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'issued' => 'Issued',
            'partially_paid' => 'Partially Paid',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
