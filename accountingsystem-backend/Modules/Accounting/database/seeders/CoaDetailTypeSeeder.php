<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Support\AccountCategoryDetailTypes;

class CoaDetailTypeSeeder extends Seeder
{
    public function run(): void
    {
        $detailsByType = [
            'Assets' => [
                'Cash on hand and in banks',
                ...AccountCategoryDetailTypes::ASSET_DETAILS,
                'Receivables',
                'Prepayments and Other Current Assets',
                'Advances',
                'Property, Plant, and Equipment',
                'Accumulated Depreciation',
            ],
            'Liabilities' => [
                'Payables',
                ...AccountCategoryDetailTypes::LIABILITY_DETAILS,
                'Deferred Income',
                'Deposit',
            ],
            'Equity' => [
                'Retained Earnings',
                'Reserves and Fund Balances',
            ],
            'Income/Revenue' => [
                'Income/Revenue',
            ],
            'Expenses' => [
                'Expenses',
            ],
        ];

        DB::transaction(function () use ($detailsByType): void {
            $typeIds = DB::table('account_types')
                ->where('is_active', true)
                ->whereIn('name', array_keys($detailsByType))
                ->pluck('id', 'name')
                ->all();

            $rows = [];
            $canonicalCodesByType = [];

            foreach ($detailsByType as $typeName => $details) {
                $typeId = $typeIds[$typeName] ?? null;

                if (! $typeId) {
                    continue;
                }

                foreach ($details as $detail) {
                    $code = Str::slug($detail);
                    $canonicalCodesByType[$typeId][] = $code;
                    $rows[] = [
                        'account_type_id' => $typeId,
                        'code' => $code,
                        'name' => $detail,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            DB::table('detail_types')->upsert(
                $rows,
                ['account_type_id', 'code'],
                ['name', 'is_active', 'updated_at']
            );

            foreach ($canonicalCodesByType as $typeId => $canonicalCodes) {
                DB::table('detail_types')
                    ->where('account_type_id', $typeId)
                    ->whereNotIn('code', $canonicalCodes)
                    ->update([
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);
            }
        });
    }
}
