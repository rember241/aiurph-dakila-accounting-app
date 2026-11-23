<?php

namespace Modules\Customers\Services;

use Illuminate\Support\Facades\DB;
use Modules\Customers\Models\Customer;

/**
 * Allocates sequential customer codes per company.
 *
 * Format: CUST-0001, CUST-0002, ...
 */
final class CustomerCodeNumberingService
{
    private const PREFIX = 'CUST';
    private const PADDING = 4;

    public function allocate(string $companyId): string
    {
        $this->lockCompany($companyId);

        $row = DB::table('customer_sequences')
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            $nextSequence = $this->inferNextSequence($companyId);

            DB::table('customer_sequences')->insert([
                'company_id' => $companyId,
                'last_sequence' => $nextSequence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->formatNumber($nextSequence);
        }

        $nextSequence = (int) $row->last_sequence + 1;

        DB::table('customer_sequences')
            ->where('company_id', $companyId)
            ->update([
                'last_sequence' => $nextSequence,
                'updated_at' => now(),
            ]);

        return $this->formatNumber($nextSequence);
    }

    public function syncProvidedCode(string $companyId, string $code): void
    {
        if (!preg_match('/^CUST-(\d+)$/i', trim($code), $matches)) {
            return;
        }

        $providedSequence = (int) $matches[1];

        if ($providedSequence < 1) {
            return;
        }

        $this->lockCompany($companyId);

        $row = DB::table('customer_sequences')
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            DB::table('customer_sequences')->insert([
                'company_id' => $companyId,
                'last_sequence' => $providedSequence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if ((int) $row->last_sequence >= $providedSequence) {
            return;
        }

        DB::table('customer_sequences')
            ->where('company_id', $companyId)
            ->update([
                'last_sequence' => $providedSequence,
                'updated_at' => now(),
            ]);
    }

    private function inferNextSequence(string $companyId): int
    {
        $maxSequence = Customer::query()
            ->forCompany($companyId)
            ->pluck('code')
            ->reduce(function (int $max, mixed $code): int {
                if (!is_string($code)) {
                    return $max;
                }

                if (!preg_match('/^CUST-(\d+)$/i', trim($code), $matches)) {
                    return $max;
                }

                return max($max, (int) $matches[1]);
            }, 0);

        return max(1, $maxSequence + 1);
    }

    private function formatNumber(int $sequence): string
    {
        return sprintf('%s-%s', self::PREFIX, str_pad((string) $sequence, self::PADDING, '0', STR_PAD_LEFT));
    }

    private function lockCompany(string $companyId): void
    {
        DB::table('tenants')
            ->where('id', $companyId)
            ->lockForUpdate()
            ->first();
    }
}
