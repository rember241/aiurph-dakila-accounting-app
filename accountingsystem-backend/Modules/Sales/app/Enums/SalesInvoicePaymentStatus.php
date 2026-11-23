<?php

namespace Modules\Sales\Enums;

/**
 * Payment-facing labels for sales invoices.
 */
final class SalesInvoicePaymentStatus
{
    public static function label(string $status): string
    {
        return match ($status) {
            'unpaid' => 'Unpaid',
            'partially_paid' => 'Partially Paid',
            'paid' => 'Paid',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
