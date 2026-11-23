<?php

namespace Modules\Accounting\Http\Controllers\Api\TrialBalance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Accounting\Http\Requests\TrialBalance\DownloadTrialBalanceRequest;
use Modules\Accounting\Http\Requests\TrialBalance\GenerateTrialBalanceRequest;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Services\CurrentTenantContext;
use Modules\Accounting\Services\TrialBalance\TrialBalanceQueryService;

class TrialBalanceController extends Controller
{
    public function __construct(
        private readonly TrialBalanceQueryService $trialBalanceService,
    )
    {
    }

    /**
     * Generate a Trial Balance report based on the date specified
     */
    public function generate(GenerateTrialBalanceRequest $request)
    {
        $params = $request->validated();
        $accounts = $this->trialBalanceService->generateReport($params);

        return response()->json($accounts);
    }

    public function download(DownloadTrialBalanceRequest $request)
    {
        $params = $request->validated();
        return $this->trialBalanceService->downloadReport($params);
    }
}
