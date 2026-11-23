<?php

namespace Modules\Accounting\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BalanceSheetExport implements FromView, ShouldAutoSize
{
    public function __construct(
        private readonly string $fileType,
        private readonly string $companyName,
        private readonly string $dateText,
        private readonly array $labels,
        private readonly mixed $currentAssets,
        private readonly mixed $nonCurrentAssets,
        private readonly mixed $currentLiabilities,
        private readonly mixed $nonCurrentLiabilities,
        private readonly mixed $equityAccounts,
        private readonly array $totalCurrentAssets,
        private readonly array $totalNonCurrentAssets,
        private readonly array $totalCurrentLiabilities,
        private readonly array $totalNonCurrentLiabilities,
        private readonly array $totalAssets,
        private readonly array $totalLiabilities,
        private readonly array $totalEquities,
        private readonly array $totalLiabilitiesEquities
    )
    {}

    public function view(): View
    {
        return view($this->fileType == 'xlsx' ? 'accounting::balance-sheet.excel' : 'accounting::balance-sheet.csv',
            [
                'companyName' => $this->companyName,
                'dateText' => $this->dateText,
                'labels' => $this->labels,
                'currentAssets' => $this->currentAssets,
                'nonCurrentAssets' => $this->nonCurrentAssets,
                'currentLiabilities' => $this->currentLiabilities,
                'nonCurrentLiabilities' => $this->nonCurrentLiabilities,
                'equityAccounts' => $this->equityAccounts,
                'totalCurrentAssets' => $this->totalCurrentAssets,
                'totalNonCurrentAssets' => $this->totalNonCurrentAssets,
                'totalCurrentLiabilities' => $this->totalCurrentLiabilities,
                'totalNonCurrentLiabilities' => $this->totalNonCurrentLiabilities,
                'totalAssets' => $this->totalAssets,
                'totalLiabilities' => $this->totalLiabilities,
                'totalEquities' => $this->totalEquities,
                'totalLiabilitiesEquities' => $this->totalLiabilitiesEquities
            ]
        );
    }
}
