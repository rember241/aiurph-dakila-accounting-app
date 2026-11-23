<?php

namespace Modules\Accounting\Http\Requests\PeriodManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rule;
use Modules\Accounting\Enums\PeriodManagement\FiscalYearStatus;

final class UpdateFiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fiscalYearId = (string)$this->route('id');
        $companyId = (string) ($this->attributes->get('company_id') ?? $this->attributes->get('tenant_id', ''));

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('fiscal_years', 'code')
                    ->ignore($fiscalYearId, 'id')
                    ->where(function ($query) use ($companyId) {
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
            'name' => ['required', 'string', 'max:50'],
            'status' => ['required', new Enum(FiscalYearStatus::class)],
        ];
    }
}
