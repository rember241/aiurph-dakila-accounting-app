<?php

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Services\CurrentTenantContext;
use Modules\Accounting\Services\IncomeStatement\IncomeStatementQueryService;
use Modules\Accounting\Services\BalanceSheet\BalanceSheetQueryService;

final class AccountingDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CurrentTenantContext $context,
        IncomeStatementQueryService $income,
        BalanceSheetQueryService $balance,
    ): JsonResponse {
        $dates = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'startDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:date'],
        ]);
        // Resolve every posting account on the server, without pagination or
        // client-supplied company IDs. Reuse the financial reports' calculations.
        $accounts = Account::query()->where('company_id', $context->companyId())
            ->where('is_posting', true)->get(['id', 'type']);
        $incomeReport = $income->generateReport([
            'startDate' => $dates['startDate'], 'endDate' => $dates['date'],
            'accountIds' => $accounts->whereIn('type', ['revenue', 'expense'])->pluck('id')->all(),
        ]);
        $balanceReport = $balance->generateReport([
            'date' => $dates['date'],
            'accountIds' => $accounts->whereIn('type', ['asset', 'liability', 'equity'])->pluck('id')->all(),
        ]);

        return response()->json(['income' => $incomeReport, 'balance' => $balanceReport]);
    }
}
