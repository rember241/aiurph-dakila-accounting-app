<?php

namespace Modules\Accounting\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Accounting\Http\Requests\Settings\UpdateAccountingDefaultsRequest;
use Modules\Accounting\Http\Resources\Settings\AccountingDefaultsResource;
use Modules\Accounting\Models\Settings\AccountingDefaults;

class AccountingDefaultsController extends Controller
{
    /**
     * Return the current company accounting defaults.
     */
    public function index(): JsonResponse
    {
        $defaults = AccountingDefaults::current()->load([
            'accountsReceivableAccount',
            'salesIncomeAccount',
            'inventoryAssetAccount',
            'cogsAccount',
            'salesTaxPayableAccount',
        ]);

        return response()->json([
            'data' => new AccountingDefaultsResource($defaults),
        ]);
    }

    /**
     * Update the company accounting defaults (full or partial).
     */
    public function update(UpdateAccountingDefaultsRequest $request): JsonResponse
    {
        $defaults = AccountingDefaults::current();

        $defaults->update($request->validated());

        $defaults->load([
            'accountsReceivableAccount',
            'salesIncomeAccount',
            'inventoryAssetAccount',
            'cogsAccount',
            'salesTaxPayableAccount',
        ]);

        return response()->json([
            'data'    => new AccountingDefaultsResource($defaults),
            'message' => 'Accounting defaults updated successfully.',
        ]);
    }
}
