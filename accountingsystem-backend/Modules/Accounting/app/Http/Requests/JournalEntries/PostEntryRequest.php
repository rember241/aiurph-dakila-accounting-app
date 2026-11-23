<?php

namespace Modules\Accounting\Http\Requests\JournalEntries;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;

class PostEntryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }

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
     * Checks if the entry date of the entry trying to be
     * posted falls within a period open for postings
     *
     * @return void
     */
    protected function passedValidation(): void
    {
        $entryDate = $this->route('journalEntry')->entry_date;
        $period = AccountingPeriod::query()
            ->forCompany($this->attributes->get('company_id'))
            ->with('fiscalYear')
            ->whereDate('start_date', '<=', $entryDate)
            ->whereDate('end_date', '>=', $entryDate)
            ->first();

        if (!$period || !$period->canAcceptPosting('general')) {
            throw ValidationException::withMessages([
                'date' => "Cannot post entry since its date falls within a closed period",
            ]);
        }
    }
}
