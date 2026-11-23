<?php

namespace Modules\Accounting\Http\Controllers\Api\ChartOfAccounts;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Accounting\Http\Requests\ChartOfAccounts\IndexAccountTypeRequest;
use Modules\Accounting\Http\Requests\ChartOfAccounts\IndexDetailTypeRequest;
use Modules\Accounting\Services\ChartOfAccounts\ChartOfAccountsLookupService;

class ChartOfAccountsLookupController extends Controller
{
    public function __construct(
        private readonly ChartOfAccountsLookupService $lookupService
    )
    {
    }

    public function accountTypes(IndexAccountTypeRequest $request): JsonResponse
    {

//        $this->authorize('viewAny', Account::class);

        $rows = $this->lookupService->accountTypes($request->validated('core_type'));

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function detailTypes(IndexDetailTypeRequest $request): JsonResponse
    {
//        $this->authorize('viewAny', Account::class);


        $accountTypeId = (int)$request->validated('accountType');
        $rows = $this->lookupService->detailTypes($accountTypeId);

        return response()->json([
            'data' => $rows,
        ]);
    }
}
