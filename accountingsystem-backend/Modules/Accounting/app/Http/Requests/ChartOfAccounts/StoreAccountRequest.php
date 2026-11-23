<?php

namespace Modules\Accounting\Http\Requests\ChartOfAccounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


/**
 * StoreAccountRequest
 *
 * Handles validation for creating a new Chart of Accounts (COA) record.
 *
 * This request is intentionally strict to protect financial data integrity.
 */
class StoreAccountRequest extends FormRequest
{
    /**
     * Resolve an account existence rule scoped to the current company runtime.
     *
     * Storage still keeps the legacy tenant column, so we fall back only when
     * a company_id has not been backfilled yet.
     */
    private function companyScopedAccountExistsRule(): \Illuminate\Validation\Rules\Exists
    {
        $companyId = (string) ($this->attributes->get('company_id') ?? $this->attributes->get('tenant_id', ''));

        return Rule::exists('accounts', 'id')->where(function ($query) use ($companyId): void {
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
        });
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'code' => ['prohibited'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'account_type_id' => ['required', 'integer', Rule::exists('account_types', 'id')],
            'detail_type_id' => ['required', 'integer', Rule::exists('detail_types', 'id')],
            'parent_id' => ['nullable', 'uuid', $this->companyScopedAccountExistsRule()],
            'status' => ['required', 'in:active,inactive'],
            'is_posting' => ['present', 'boolean'],
            'contra_account_id' => ['nullable', 'uuid', $this->companyScopedAccountExistsRule()],
            'is_system' => ['prohibited'],
        ];
    }
}
