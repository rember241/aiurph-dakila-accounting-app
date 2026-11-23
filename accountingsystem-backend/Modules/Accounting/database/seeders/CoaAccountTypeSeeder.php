<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CoaAccountTypeSeeder extends Seeder
{
    /**
     * @param  array<int, int>  $remapMap  old account_type_id => canonical account_type_id
     */
    private function remapDuplicateAccountTypes(array $remapMap): void
    {
        if (empty($remapMap)) {
            return;
        }

        foreach ($remapMap as $oldId => $newId) {
            DB::table('accounts')
                ->where('account_type_id', $oldId)
                ->update(['account_type_id' => $newId]);

            $detailRows = DB::table('detail_types')
                ->where('account_type_id', $oldId)
                ->select(['id', 'code'])
                ->get();

            foreach ($detailRows as $detailRow) {
                $existingDetailId = DB::table('detail_types')
                    ->where('account_type_id', $newId)
                    ->where('code', $detailRow->code)
                    ->value('id');

                if ($existingDetailId) {
                    DB::table('accounts')
                        ->where('detail_type_id', $detailRow->id)
                        ->update(['detail_type_id' => $existingDetailId]);

                    DB::table('detail_types')
                        ->where('id', $detailRow->id)
                        ->delete();

                    continue;
                }

                DB::table('detail_types')
                    ->where('id', $detailRow->id)
                    ->update(['account_type_id' => $newId]);
            }

            DB::table('account_types')
                ->where('id', $oldId)
                ->delete();
        }
    }

    public function run(): void
    {
        $types = [
            ['name' => 'Assets', 'core_type' => 'asset'],
            ['name' => 'Liabilities', 'core_type' => 'liability'],
            ['name' => 'Equity', 'core_type' => 'equity'],
            ['name' => 'Income/Revenue', 'core_type' => 'revenue'],
            ['name' => 'Expenses', 'core_type' => 'expense'],
        ];

        $rows = array_map(static function (array $type): array {
            return [
                'code' => Str::slug($type['name']),
                'name' => $type['name'],
                'core_type' => $type['core_type'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $types);

        DB::transaction(function () use ($rows): void {
            DB::table('account_types')->upsert(
                $rows,
                ['code'],
                ['name', 'core_type', 'is_active', 'updated_at']
            );

            $canonicalCodes = array_column($rows, 'code');

            $canonicalRows = DB::table('account_types')
                ->whereIn('code', $canonicalCodes)
                ->select(['id', 'core_type', 'name'])
                ->get();

            $canonicalByKey = [];
            foreach ($canonicalRows as $row) {
                $key = strtolower(trim((string) $row->core_type).'|'.trim((string) $row->name));
                $canonicalByKey[$key] = (int) $row->id;
            }

            $existingRows = DB::table('account_types')
                ->select(['id', 'core_type', 'name', 'code'])
                ->orderBy('id')
                ->get();

            $remapMap = [];
            foreach ($existingRows as $row) {
                $key = strtolower(trim((string) $row->core_type).'|'.trim((string) $row->name));
                $canonicalId = $canonicalByKey[$key] ?? null;

                if ($canonicalId && $canonicalId !== (int) $row->id) {
                    $remapMap[(int) $row->id] = $canonicalId;
                }
            }

            $this->remapDuplicateAccountTypes($remapMap);

            DB::table('account_types')
                ->whereNotIn('code', $canonicalCodes)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        });
    }
}
