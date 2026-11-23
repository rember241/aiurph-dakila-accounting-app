<?php

namespace Modules\Accounting\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class TrialBalanceExport implements FromView, ShouldAutoSize
{
    public function __construct(
        private readonly string $fileType,
        private readonly string $companyName,
        private readonly string $dateText,
        private readonly mixed $assetAccounts,
        private readonly mixed $liabilityAccounts,
        private readonly mixed $equityAccounts,
        private readonly mixed $revenueAccounts,
        private readonly mixed $expenseAccounts,
        private readonly string $totalDebit,
        private readonly string $totalCredit
    )
    {}

    public function view(): View
    {
        return view($this->fileType === 'xlsx' ? 'accounting::trial-balance.excel' : 'accounting::trial-balance.csv',
            [
                'companyName' => $this->companyName,
                'dateText' => $this->dateText,
                'assetAccounts' => $this->assetAccounts,
                'liabilityAccounts' => $this->liabilityAccounts,
                'equityAccounts' => $this->equityAccounts,
                'revenueAccounts' => $this->revenueAccounts,
                'expenseAccounts' => $this->expenseAccounts,
                'totalDebit' => $this->totalDebit,
                'totalCredit' => $this->totalCredit,
            ]
        );
    }
}
