<?php

namespace Modules\Accounting\Http\Requests\JournalEntries;

use Illuminate\Foundation\Http\FormRequest;

class DestroyJournalNumberRequest extends FormRequest
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
        // Authorize if the user's company ID matches this journal number's company ID
        $userCompanyId = $this->attributes->get('company_id') ?? $this->attributes->get('tenant_id');
        $numberCompanyId = $this->route('journalNumber')->company_id;

        return $userCompanyId === $numberCompanyId;
    }
}
