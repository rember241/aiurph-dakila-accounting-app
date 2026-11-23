<?php

namespace Modules\Accounting\Http\Requests\JournalEntries;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\Taxes\Tax;

class UpdateJournalEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorize if the user's company ID matches this entry's company ID
        $userCompanyId = $this->attributes->get('company_id') ?? $this->attributes->get('tenant_id');
        $entryCompanyId = $this->route('journalEntry')->company_id;

        return $userCompanyId === $entryCompanyId;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // For journal_entries table
            'date' =>            ['required', 'date_format:Y-m-d'],
            'type' =>            ['required', 'string', 'in:general,adjusting,closing'],
            'fundSourceId' =>    ['required', 'uuid', $this->ensureCompanyScope('fund_sources')],
            'referenceNumber' => ['nullable', 'string'],
            'note' =>            ['nullable', 'string'],
            'status' =>          ['required', 'string', 'in:posted,draft'],

            // For the journal items array nesting the data
            'journalItems' =>    ['required', 'array', 'min:2'],

            // For journal_items table
            'journalItems.*.accountId' =>   ['required', 'uuid', $this->ensureCompanyScope('accounts')],
            'journalItems.*.taxId' =>       ['nullable', 'uuid', $this->ensureCompanyScope('taxes')],
            'journalItems.*.description' => ['nullable', 'string'],
            'journalItems.*.debit' =>       ['nullable', 'numeric', 'decimal:0,2', 'max:999999999999'],
            'journalItems.*.credit' =>      ['nullable', 'numeric', 'decimal:0,2', 'max:999999999999'],
        ];
    }

    /**
     * Runs after validation rules are met
     *
     * @return void
     */
    protected function passedValidation(): void
    {
        $this->checkPostingPeriod();
        $this->checkBalanceTotals();
    }

    /**
     * Validates that a record's ID is within the scope of the user's company
     *
     * @param string $table The record's table
     * @return Exists
     */
    private function ensureCompanyScope(string $table): Exists
    {
        return Rule::exists($table, 'id')->where(function ($query) {
            $query->where('company_id', $this->attributes->get('company_id'));
        });
    }

    /**
     * Ensure the transaction date falls within an open accounting period
     *
     * @return void
     */
    private function checkPostingPeriod()
    {
        $entryDate = $this->date;
        $period = AccountingPeriod::query()
            ->forCompany($this->attributes->get('company_id'))
            ->with('fiscalYear')
            ->whereDate('start_date', '<=', $entryDate)
            ->whereDate('end_date', '>=', $entryDate)
            ->first();

        if (!$period || !$period->canAcceptPosting('general')) {
            throw ValidationException::withMessages([
                'date' => 'Posting is not allowed in this period.',
            ]);
        }
    }

    /**
     * Verifies that the journal items balance (Debits = Credits) after tax adjustments.
     *
     * @return void
     */
    private function checkBalanceTotals()
    {
        // Extract the received journalItems array then
        // collect the tax IDs used in this entry
        $journalItems = collect($this->journalItems);
        $taxIds = $journalItems->pluck('taxId')->filter()->unique()->values();

        // Determine which computation function to use if taxes are used or not
        [$totalDebit, $totalCredit] = $taxIds->isEmpty()
            ? $this->computeTaxExclusive($journalItems)
            : $this->computeTaxInclusive($journalItems, $taxIds);

        if ($totalDebit !== $totalCredit || $totalDebit + $totalCredit <= 0) {
            throw ValidationException::withMessages([
                'journalItems' => 'The total debits must equal total credits and be greater than zero.',
            ]);
        }
    }

    /**
     * Computes the total of both the debit and credit side without taxes
     *
     * @param Collection $journalItems
     * @return array
     */
    private function computeTaxExclusive(Collection $journalItems): array
    {
        $totalDebit = round($journalItems->sum('debit'), 2);
        $totalCredit = round($journalItems->sum('credit'), 2);

        return [$totalDebit, $totalCredit];
    }

    /**
     * Computes the total of both the debit and credit side with taxes
     *
     * @param Collection $items
     * @param Collection $taxIds
     * @return array
     */
    private function computeTaxInclusive(Collection $items, Collection $taxIds): array
    {
        $totalDebit = 0;
        $totalCredit = 0;

        // Fetch the tax rates for all taxes used in this journal
        // entry then compute the total debit and credit
        $taxes = Tax::whereIn('id', $taxIds)->pluck('rate', 'id');

        foreach ($items as $item) {
            $debit = (float) ($item['debit'] ?? 0);
            $credit = (float) ($item['credit'] ?? 0);
            $taxRate = isset($item['taxId']) ? (float) ($taxes->get($item['taxId']) ?? 0) : 0;

            $totalDebit += round($debit + ($debit * $taxRate * 0.01), 2);
            $totalCredit += round($credit + ($credit * $taxRate * 0.01), 2);
        }

        return [round($totalDebit, 2), round($totalCredit, 2)];
    }
}
