<?php

namespace Modules\Accounting\Http\Requests\JournalEntries;

use Illuminate\Foundation\Http\FormRequest;

class FetchEntryForEditRequest extends FormRequest
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
        // Compare the user's company ID with the journal entry's company
        // ID to check if this user is authorized to edit this journal entry
        $companyId = $this->attributes->get('company_id') ?? $this->attributes->get('tenant_id');
        $entry = $this->route('journalEntry');

        return $companyId === $entry->company_id;
    }
}
