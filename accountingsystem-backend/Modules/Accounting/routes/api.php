<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\Api\BalanceSheet\BalanceSheetController;
use Modules\Accounting\Http\Controllers\Api\CashFlow\CashFlowController;
use Modules\Accounting\Http\Controllers\Api\ChartOfAccounts\ChartOfAccountsController;
use Modules\Accounting\Http\Controllers\Api\ChartOfAccounts\ChartOfAccountsLookupController;
use Modules\Accounting\Http\Controllers\Api\Cheques\ChequeBookController;
use Modules\Accounting\Http\Controllers\Api\Cheques\ChequeDisbursementController;
use Modules\Accounting\Http\Controllers\Api\Cheques\ChequeTemplateController;
use Modules\Accounting\Http\Controllers\Api\FundSources\FundSourceController;
use Modules\Accounting\Http\Controllers\Api\GeneralLedger\GeneralLedgerController;
use Modules\Accounting\Http\Controllers\Api\IncomeStatement\IncomeStatementController;
use Modules\Accounting\Http\Controllers\Api\JournalEntries\JournalEntryController;
use Modules\Accounting\Http\Controllers\Api\JournalEntries\JournalNumberController;
use Modules\Accounting\Http\Controllers\Api\PeriodManagement\AccountingPeriodController;
use Modules\Accounting\Http\Controllers\Api\PeriodManagement\FiscalYearController;
use Modules\Accounting\Http\Controllers\Api\Settings\AccountingDefaultsController;
use Modules\Accounting\Http\Controllers\Api\Taxes\TaxController;
use Modules\Accounting\Http\Controllers\Api\TrialBalance\TrialBalanceController;

/*
|--------------------------------------------------------------------------
| Primary v1 API (used by frontend proxies)
|--------------------------------------------------------------------------
*/
// Use `auth:sanctum` (not bare `auth`) so unauthenticated requests return a
// JSON 401 — bare `auth` redirects to /login, which an SPA can't follow. Do
// NOT add the `web` middleware group here: routes/api.php is already loaded
// under the api group with statefulApi() applied in bootstrap/app.php, which
// provides StartSession + EncryptCookies + VerifyCsrfToken once. Adding `web`
// causes StartSession to run a SECOND time, which rotates the browser's
// session cookie on every request and 419s the next state-changing POST.
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Shared lookup endpoints remain tenant-header free.
    Route::get('account-types', [ChartOfAccountsLookupController::class, 'accountTypes']);
    Route::get('account-types/{accountType}/detail-types', [ChartOfAccountsLookupController::class, 'detailTypes']);

    Route::middleware(['company.context', 'company.access'])->group(function () {
        Route::get('accounting/dashboard', \Modules\Accounting\Http\Controllers\Api\AccountingDashboardController::class)->middleware('company.ability:accounting.read');

        // Company-scoped accounting setup and ledgers. These endpoints assume
        // `company.context` already resolved the single company for the user.
        Route::get('accounting/settings/defaults', [AccountingDefaultsController::class, 'index'])->middleware('company.ability:accounting.read');
        Route::patch('accounting/settings/defaults', [AccountingDefaultsController::class, 'update'])->middleware('company.ability:accounting.write');

        Route::get('accounts/stats', [ChartOfAccountsController::class, 'stats'])->middleware('company.ability:accounting.read');
        Route::get('accounts', [ChartOfAccountsController::class, 'index'])->middleware('company.ability:accounting.read,journal.read,sales.read');
        Route::post('accounts', [ChartOfAccountsController::class, 'store'])->middleware('company.ability:accounting.create');
        Route::put('accounts/{account}', [ChartOfAccountsController::class, 'update'])->middleware('company.ability:accounting.write');
        Route::delete('accounts/{account}', [ChartOfAccountsController::class, 'destroy'])->middleware('company.ability:accounting.delete');

        Route::get('taxes', [TaxController::class, 'index'])->middleware('company.ability:accounting.read,journal.read,sales.read');
        Route::post('taxes', [TaxController::class, 'store'])->middleware('company.ability:accounting.create');
        Route::patch('taxes/{tax}', [TaxController::class, 'update'])->middleware('company.ability:accounting.write');
        Route::delete('taxes/{tax}', [TaxController::class, 'destroy'])->middleware('company.ability:accounting.delete');

        Route::get('fund-sources', [FundSourceController::class, 'index'])->middleware('company.ability:accounting.read,journal.read');
        Route::post('fund-sources', [FundSourceController::class, 'store'])->middleware('company.ability:accounting.create');
        Route::patch('fund-sources/{fundSource}', [FundSourceController::class, 'update'])->middleware('company.ability:accounting.write');
        Route::delete('fund-sources/{fundSource}', [FundSourceController::class, 'destroy'])->middleware('company.ability:accounting.delete');

        Route::get('journal-numbers', [JournalNumberController::class, 'index'])->middleware('company.ability:accounting.read,journal.read');
        Route::post('journal-numbers', [JournalNumberController::class, 'store'])->middleware('company.ability:accounting.create');
        Route::patch('journal-numbers/{journalNumber}', [JournalNumberController::class, 'update'])->middleware('company.ability:accounting.write');
        Route::delete('journal-numbers/{journalNumber}', [JournalNumberController::class, 'destroy'])->middleware('company.ability:accounting.delete');

        Route::get('journal-entries', [JournalEntryController::class, 'index'])->middleware('company.ability:journal.read');
        Route::get('journal-entries/{journalEntry}/edit', [JournalEntryController::class, 'fetchEntryForEdit'])->middleware('company.ability:journal.read');
        Route::post('journal-entries', [JournalEntryController::class, 'store'])->middleware('company.ability:journal.create');
        Route::patch('journal-entries/{journalEntry}', [JournalEntryController::class, 'update'])->middleware('company.ability:journal.write,accounting.write');
        Route::patch('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'postEntry'])->middleware('company.ability:journal.write,accounting.write');
        Route::patch('journal-entries/{journalEntry}/void', [JournalEntryController::class, 'voidEntry'])->middleware('company.ability:journal.write,accounting.write');

        if (config('module_availability.cheque_disbursements_enabled', false)) {
            Route::get('cheque-books', [ChequeBookController::class, 'index'])->middleware('company.ability:accounting.read,journal.read');
            Route::post('cheque-books', [ChequeBookController::class, 'store'])->middleware('company.ability:accounting.create');
            Route::get('cheque-templates', [ChequeTemplateController::class, 'index'])->middleware('company.ability:accounting.read,journal.read');
            Route::post('cheque-templates', [ChequeTemplateController::class, 'store'])->middleware('company.ability:accounting.create');

            Route::get('cheque-disbursements', [ChequeDisbursementController::class, 'index'])->middleware('company.ability:journal.read');
            Route::post('cheque-disbursements', [ChequeDisbursementController::class, 'store'])->middleware('company.ability:journal.create,accounting.write');
            Route::get('cheque-disbursements/{cheque}', [ChequeDisbursementController::class, 'show'])->middleware('company.ability:journal.read');
            Route::post('cheque-disbursements/{cheque}/approve', [ChequeDisbursementController::class, 'approve'])->middleware('company.ability:journal.write,accounting.write');
            Route::post('cheque-disbursements/{cheque}/print', [ChequeDisbursementController::class, 'print'])->middleware('company.ability:journal.write,accounting.write');
            Route::post('cheque-disbursements/{cheque}/post', [ChequeDisbursementController::class, 'post'])->middleware('company.ability:journal.write,accounting.write');
            Route::post('cheque-disbursements/{cheque}/spoil', [ChequeDisbursementController::class, 'spoil'])->middleware('company.ability:journal.write,accounting.write');
            Route::post('cheque-disbursements/{cheque}/void', [ChequeDisbursementController::class, 'void'])->middleware('company.ability:journal.write,accounting.write');
        }

        Route::get('general-ledger/accounts', [GeneralLedgerController::class, 'fetchAccounts'])->middleware('company.ability:accounting.read');
        Route::get('trial-balance', [TrialBalanceController::class, 'generate'])->middleware('company.ability:accounting.read');
        Route::get('trial-balance/download', [TrialBalanceController::class, 'download'])->middleware('company.ability:accounting.read');
        Route::get('income-statement', [IncomeStatementController::class, 'generate'])->middleware('company.ability:accounting.read');
        Route::get('income-statement/download', [IncomeStatementController::class, 'download'])->middleware('company.ability:accounting.read');
        Route::get('balance-sheet', [BalanceSheetController::class, 'generate'])->middleware('company.ability:accounting.read');
        Route::get('balance-sheet/download', [BalanceSheetController::class, 'download'])->middleware('company.ability:accounting.read');
        Route::get('cash-flow', [CashFlowController::class, 'generate'])->middleware('company.ability:accounting.read');

        Route::prefix('accounting/period-management')->group(function () {
            Route::get('fiscal-years', [FiscalYearController::class, 'index'])->middleware('company.ability:period.read');
            Route::get('fiscal-years/current', [FiscalYearController::class, 'getCurrent'])->middleware('company.ability:period.read');
            Route::get('kpi', [FiscalYearController::class, 'kpi'])->middleware('company.ability:period.read');
            Route::get('periods', [AccountingPeriodController::class, 'index'])->middleware('company.ability:period.read');

            Route::post('fiscal-years', [FiscalYearController::class, 'store'])->middleware('company.ability:period.create');
            Route::post('fiscal-years/{id}/set-current', [FiscalYearController::class, 'setCurrent'])->middleware('company.ability:period.write');
            Route::patch('fiscal-years/{id}', [FiscalYearController::class, 'update'])->middleware('company.ability:period.write');
            Route::delete('fiscal-years/{id}', [FiscalYearController::class, 'destroy'])->middleware('company.ability:period.delete');
            Route::patch('periods/{id}/status', [AccountingPeriodController::class, 'transition'])->middleware('company.ability:period.write');
        });
    });
});
