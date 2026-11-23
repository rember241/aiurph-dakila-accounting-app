<?php

namespace Modules\Accounting\Services\ChartOfAccounts;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsLookupService
{
    public function accountTypes(?string $coreType = null): Collection
    {
        return DB::table('account_types')
            ->select(['id', 'code', 'name', 'core_type'])
            ->where('is_active', true)
            ->when($coreType, fn($q) => $q->where('core_type', $coreType))
            ->orderBy('name')
            ->get();
    }

    public function detailTypes(int $accountTypeId): Collection
    {
        return DB::table('detail_types')
            ->select(['id', 'code', 'name', 'account_type_id'])
            ->where('is_active', true)
            ->where('account_type_id', $accountTypeId)
            ->orderBy('name')
            ->get();
    }
}
