<?php

namespace Modules\Accounting\Http\Controllers\Api\BalanceSheet;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Modules\Accounting\Http\Requests\BalanceSheet\DownloadBalanceSheetRequest;
use Modules\Accounting\Http\Requests\BalanceSheet\GenerateBalanceSheetRequest;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Services\BalanceSheet\BalanceSheetQueryService;
use Modules\Accounting\Services\CurrentTenantContext;

class BalanceSheetController extends Controller
{
    public function __construct(
        private readonly BalanceSheetQueryService $balanceSheetService,
    )
    {
    }

    /**
     * Generate a Balance Sheet report with the given account IDs and date
     */
    public function generate(GenerateBalanceSheetRequest $request)
    {
        $params = $request->validated();
        $accounts = $this->balanceSheetService->generateReport($params);

        return response()->json($accounts);
    }

    public function download(DownloadBalanceSheetRequest $request)
    {
        $params = $request->validated();
        return $this->balanceSheetService->downloadReport($params);
    }
}
