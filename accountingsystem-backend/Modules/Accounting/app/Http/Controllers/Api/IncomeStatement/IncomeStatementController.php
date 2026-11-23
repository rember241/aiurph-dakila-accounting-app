<?php

namespace Modules\Accounting\Http\Controllers\Api\IncomeStatement;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Modules\Accounting\Http\Requests\IncomeStatement\DownloadIncomeStatementRequest;
use Modules\Accounting\Http\Requests\IncomeStatement\GenerateIncomeStatementRequest;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Services\CurrentTenantContext;
use Modules\Accounting\Services\IncomeStatement\IncomeStatementQueryService;

class IncomeStatementController extends Controller
{
    public function __construct(
        private readonly IncomeStatementQueryService $incomeStatementService
    )
    {
    }

    /**
     * Generate an income statement report with the given account IDs and date ranges
     */
    public function generate(GenerateIncomeStatementRequest $request)
    {
        $params = $request->validated();
        $accounts = $this->incomeStatementService->generateReport($params);

        return response()->json($accounts);
    }

    /**
     * Download an income statement report with the given file type, account IDs, and date ranges
     */
    public function download(DownloadIncomeStatementRequest $request)
    {
        $params = $request->validated();
        return $this->incomeStatementService->downloadReport($params);
    }
}
