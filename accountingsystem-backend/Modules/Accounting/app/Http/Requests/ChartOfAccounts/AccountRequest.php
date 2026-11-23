<?php

namespace Modules\Accounting\Http\Requests\ChartOfAccounts;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth later
    }

    /**
     * Returns an array of validation rules based on the HTTP method.
     *
     * If the request is a POST, the rules for creating a new account are returned.
     * If the request is a PATCH, the rules for updating an existing account are returned.
     *
     * @return array
     */
    public function rules(): array
    {
        // Decide rules based on HTTP method
        return $this->isMethod('post')
            ? $this->rulesForStore()
            : $this->rulesForUpdate();
    }


    /**
     * Trims whitespace from the code and name fields before validation.
     *
     * This is useful because Laravel's validation will consider whitespace
     * as part of the field's value, and will prevent the validation from
     * passing if the field contains only whitespace.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->code) ? trim($this->code) : $this->code,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);
    }

    /**
     * Returns an array of basic validation rules for accounts.
     *
     * These rules are used to validate both new and existing accounts.
     *
     * @return array
     */
    private function baseRules(): array
    {
        return [
            'code' => ['string', 'max:32'],
            'name' => ['string', 'max:120'],
            'type' => [Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'account_type_id' => ['nullable', 'integer', Rule::exists('account_types', 'id')],
            'detail_type_id' => ['nullable', 'integer', Rule::exists('detail_types', 'id')],
            'parent_id' => ['nullable', 'uuid', Rule::exists('accounts', 'id')],
            'is_posting' => ['boolean'],
            'status' => [Rule::in(['active', 'inactive'])],
            'contra_account_id' => ['nullable', 'uuid', Rule::exists('accounts', 'id')],
            'description' => ['nullable', 'string', 'max:500'],
            'is_system' => ['boolean']
        ];
    }

    /**
     * Returns an array of validation rules for creating a new account.
     *
     * Required fields are code, name, type, and normal_balance.
     * Optional fields are parent_id, is_posting, status, is_contra, and description.
     *
     * @return array
     */
    private function rulesForStore(): array
    {
        return [
            // required on create
            'code' => ['required', ...$this->baseRules()['code'], Rule::unique('accounts', 'code')],
            'name' => ['required', ...$this->baseRules()['name']],
            'type' => ['required', ...$this->baseRules()['type']],
            'is_system' => ['required', ...$this->baseRules()['is_system']],

            // optional on create
            'account_type_id' => ['sometimes', ...$this->baseRules()['account_type_id']],
            'detail_type_id' => ['sometimes', ...$this->baseRules()['detail_type_id']],
            'parent_id' => $this->baseRules()['parent_id'],
            'is_posting' => ['sometimes', ...$this->baseRules()['is_posting']],
            'status' => ['sometimes', ...$this->baseRules()['status']],
            'contra_account_id' => ['sometimes', ...$this->baseRules()['contra_account_id']],
            'description' => $this->baseRules()['description'],
        ];
    }

    /**
     * Returns an array of validation rules for updating an existing account.
     *
     * Optional fields are code, name, type, normal_balance, parent_id, is_posting, status, is_contra, and description.
     *
     * @return array
     */
    private function rulesForUpdate(): array
    {
        $accountId = $this->route('account')?->id;

        return [
            // optional on update
            'code' => ['sometimes', ...$this->baseRules()['code'], Rule::unique('accounts', 'code')->ignore($accountId)],
            'name' => ['sometimes', ...$this->baseRules()['name']],
            'type' => ['sometimes', ...$this->baseRules()['type']],
            'is_system' => ['sometimes', ...$this->baseRules()['is_system']],
            'account_type_id' => ['sometimes', ...$this->baseRules()['account_type_id']],
            'detail_type_id' => ['sometimes', ...$this->baseRules()['detail_type_id']],
            'parent_id' => ['sometimes', ...$this->baseRules()['parent_id']],
            'is_posting' => ['sometimes', ...$this->baseRules()['is_posting']],
            'status' => ['sometimes', ...$this->baseRules()['status']],
            'contra_account_id' => ['sometimes', ...$this->baseRules()['contra_account_id']],
            'description' => ['sometimes', ...$this->baseRules()['description']],
        ];
    }

    /**
     * After validation, this function checks if the detail type belongs to the account type.
     *
     * @param Validator $validator The validator instance.
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $coreType = $this->input('type');
            $typeId = $this->input('account_type_id');
            $detailId = $this->input('detail_type_id');

            if ($typeId && $coreType) {
                $accountType = DB::table('account_types')
                    ->select('core_type')
                    ->where('id', $typeId)
                    ->first();

                if (!$accountType || ($accountType->core_type !== null && $accountType->core_type !== $coreType)) {
                    $validator->errors()->add(
                        'account_type_id',
                        'Account type does not belong to selected core account type.'
                    );
                }
            }

            if (!$detailId || !$typeId) {
                return;
            }

            $ok = DB::table('detail_types')
                ->where('id', $detailId)
                ->where('account_type_id', $typeId)
                ->exists();

            if (!$ok) {
                $validator->errors()->add('detail_type_id', 'Detail type does not belong to selected account type.');
            }
        });
    }


    public function messages(): array
    {
        return [
            'code.unique' => 'The account code is already in use.',
            'parent_id.exists' => 'The selected parent account does not exist.',
        ];
    }
}
