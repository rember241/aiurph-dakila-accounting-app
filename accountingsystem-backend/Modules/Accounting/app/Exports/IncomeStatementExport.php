<?php

namespace Modules\Accounting\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class IncomeStatementExport implements FromView, ShouldAutoSize
{
    public function __construct(
        private readonly string $fileType,
        private readonly string $companyName,
        private readonly array $labels,
        private readonly array $revenueAccounts,
        private readonly array $expenseAccounts,
        private readonly array $totalRevenue,
        private readonly array $totalExpense,
        private readonly array $netTotal,
    ) {}

    public function view(): View
    {
        return view($this->fileType == 'xlsx' ? 'accounting::income-statement.excel' : 'accounting::income-statement.csv',
            [
                'companyName'     => $this->companyName,
                'labels'          => $this->labels,
                'revenueAccounts' => $this->revenueAccounts,
                'expenseAccounts' => $this->expenseAccounts,
                'totalRevenue'    => $this->totalRevenue,
                'totalExpense'    => $this->totalExpense,
                'netTotal'        => $this->netTotal,
            ]
        );
    }
}
