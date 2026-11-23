<?php

namespace Modules\Accounting\Enums;

/**
 * Supported transaction-series document types.
 */
enum TransactionSeriesDocumentType: string
{
    case JOURNAL_ENTRY = 'journal_entry';
    case SALES_INVOICE = 'sales_invoice';

    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }
}
