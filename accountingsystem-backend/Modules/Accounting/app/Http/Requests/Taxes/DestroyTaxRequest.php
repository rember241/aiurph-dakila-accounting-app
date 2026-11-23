<?php

namespace Modules\Accounting\Http\Requests\Taxes;

use Illuminate\Foundation\Http\FormRequest;

class DestroyTaxRequest extends FormRequest
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
        // Authorize if the user's company ID matches this tax's company ID
        $userCompanyId = (string) ($this->attributes->get('company_id') ?? $this->attributes->get('tenant_id'));
        $taxCompanyId = $this->route('tax')->company_id;

        return $userCompanyId === $taxCompanyId;
    }
}
