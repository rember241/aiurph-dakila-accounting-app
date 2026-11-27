<?php

declare(strict_types=1);

namespace Modules\Accounting\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AccountCategoryDetailTypes
{
    public const ASSET_DETAILS = ['Bank Accounts', 'Undeposited Cash'];
    public const LIABILITY_DETAILS = ['Credit Card'];

    public static function install(): void
    {
        $byAccountType = [
            'assets' => self::ASSET_DETAILS,
            'current-assets' => self::ASSET_DETAILS,
            'liabilities' => self::LIABILITY_DETAILS,
            'current-liabilities' => self::LIABILITY_DETAILS,
        ];

        $accountTypeIds = DB::table('account_types')
            ->whereIn('code', array_keys($byAccountType))
            ->pluck('id', 'code');
        $timestamp = now();

        foreach ($accountTypeIds as $code => $id) {
            $rows = array_map(static fn (string $name) => [
                'account_type_id' => $id,
                'code' => Str::slug($name),
                'name' => $name,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $byAccountType[$code]);

            DB::table('detail_types')->upsert($rows, ['account_type_id', 'code'], ['is_active', 'updated_at']);
        }
    }
}
