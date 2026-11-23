<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;

/**
 * Allocates a unique, sequential invoice number per company per year.
 *
 * Format: SI-{YYYY}-{NNN} (e.g. SI-2026-001, SI-2026-042, SI-2026-1000)
 * Thread-safe via row-level locking within the caller's transaction.
 */
final class SalesInvoiceNumberingService
{
    /**
     * Allocate the next invoice number for the given company in the current year.
     *
     * Must be called inside an outer DB::transaction() so the lock is held
     * until the invoice row is also committed.
     */
    public function allocate(string $companyId): string
    {
        $year = (int) now()->format('Y');

        $sequence = DB::transaction(function () use ($companyId, $year): int {
            $row = DB::table('sales_invoice_sequences')
                ->where('company_id', $companyId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('sales_invoice_sequences')->insert([
                    'company_id' => $companyId,
                    'year' => $year,
                    'last_sequence' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = (int) $row->last_sequence + 1;

            DB::table('sales_invoice_sequences')
                ->where('company_id', $companyId)
                ->where('year', $year)
                ->update([
                    'last_sequence' => $next,
                    'updated_at' => now(),
                ]);

            return $next;
        });

        return sprintf('SI-%d-%s', $year, str_pad((string) $sequence, 3, '0', STR_PAD_LEFT));
    }
}
