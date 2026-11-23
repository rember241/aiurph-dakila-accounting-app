<?php

namespace Modules\Accounting\Http\Requests\BalanceSheet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Models\ChartOfAccounts\Account;

class DownloadBalanceSheetRequest extends FormRequest
{
    private function companyId(): string
    {
        return (string) ($this->attributes->get('company_id') ?? $this->attributes->get('tenant_id', ''));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $companyId = $this->companyId();

        return [
            'fileType' => ['required', 'in:csv,pdf,xlsx'],
            'date' =>  ['required', 'date_format:Y-m-d'],
            'compareWith' => ['nullable', 'in:period,fiscalYear,specific'],
            'numberOfPeriods' => ['nullable', 'integer', 'min:1', 'max:36'],
            'compareDate' => ['nullable', 'date_format:Y-m-d', 'before:date'],
            'accountIds' => ['required', 'array', 'min:3'],

            'accountIds.*' => [
                'required',
                'uuid',
                Rule::exists('accounts', 'id')->where(function ($query) use ($companyId) {
                    $query->where('is_posting', true)
                        ->where('company_id', $companyId)
                        ->whereIn('type', ['asset', 'liability', 'equity']);
                }),
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Checks if at least 1 asset, 1 liability, and 1 equity account have been requested
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $accountIds = $this->input('accountIds');

            if (!is_array($accountIds) || $accountIds === []) {
                return;
            }

            $types = Account::query()
                ->where('company_id', $this->companyId())
                ->whereIn('id', $accountIds)
                ->pluck('type')
                ->unique();

            $hasAsset = $types->contains('asset');
            $hasLiability = $types->contains('liability');
            $hasEquity = $types->contains('equity');

            if (!($hasAsset && $hasLiability && $hasEquity)) {
                $validator->errors()->add(
                    'accountIds',
                    'Select at least 1 asset, 1 liability, and 1 equity account.'
                );
            }
        });
    }
}
