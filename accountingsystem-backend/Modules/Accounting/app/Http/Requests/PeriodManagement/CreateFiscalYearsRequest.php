<?php

namespace Modules\Accounting\Http\Requests\PeriodManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


/**
 * A request for creating a fiscal year.
 *
 */
final class CreateFiscalYearsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * This method should return true if the user is authorized to make the request,
     * false otherwise.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (string) ($this->attributes->get('company_id') ?? $this->attributes->get('tenant_id', ''));

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('fiscal_years', 'code')->where(function ($query) use ($companyId) {
                    if ($companyId !== '') {
                        $query->where(function ($scopedQuery) use ($companyId): void {
                            $scopedQuery->where('company_id', $companyId)
                                ->orWhere(function ($legacyQuery) use ($companyId): void {
                                    $legacyQuery->whereNull('company_id')
                                        ->where('tenant_id', $companyId);
                                });
                        });
                    } else {
                        $query->whereNull('company_id')->whereNull('tenant_id');
                    }
                }),
            ],
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_current' => ['boolean'],
        ];
    }
}
