<?php

namespace Modules\Accounting\Http\Requests\FundSources;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFundSourceRequest extends FormRequest
{
    /**
     * Grab the company ID of the user hitting this form request
     */
    private function companyId(): string
    {
        return (string) ($this->attributes->get('company_id') ?? $this->attributes->get('tenant_id', ''));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if the fund source's company ID is the same with the user's company ID
        // Returns a 403 if they don't match
        $fundSource = $this->route('fundSource');
        return $fundSource && $fundSource->company_id === $this->companyId();
    }
}
