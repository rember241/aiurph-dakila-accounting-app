<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

abstract class CompanySetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $company = $this->input('company', []);
        $fiscalYear = $this->input('fiscal_year', []);
        $accountsSetup = $this->input('accounts_setup', []);
        $organization = $this->input('organization', []);
        $slug = data_get($company, 'slug');
        $mode = data_get($accountsSetup, 'mode');
        $createRootOrgUnit = data_get($organization, 'create_root_org_unit', true);
        $rootOrgUnitName = data_get($organization, 'root_org_unit_name', 'Head Office');

        $this->merge([
            'company' => [
                'name' => is_string(data_get($company, 'name')) ? trim((string) data_get($company, 'name')) : data_get($company, 'name'),
                'slug' => is_string($slug) && trim($slug) !== '' ? Str::slug($slug) : null,
                'timezone' => is_string(data_get($company, 'timezone'))
                    ? trim((string) data_get($company, 'timezone'))
                    : data_get($company, 'timezone'),
            ],
            'fiscal_year' => [
                'code' => is_string(data_get($fiscalYear, 'code')) ? trim((string) data_get($fiscalYear, 'code')) : data_get($fiscalYear, 'code'),
                'name' => is_string(data_get($fiscalYear, 'name')) ? trim((string) data_get($fiscalYear, 'name')) : data_get($fiscalYear, 'name'),
                'start_date' => data_get($fiscalYear, 'start_date'),
                'end_date' => data_get($fiscalYear, 'end_date'),
            ],
            'accounts_setup' => [
                'mode' => is_string($mode) && trim($mode) !== '' ? strtolower(trim($mode)) : 'empty',
            ],
            'organization' => [
                'create_root_org_unit' => filter_var($createRootOrgUnit, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
                'root_org_unit_name' => is_string($rootOrgUnitName) && trim($rootOrgUnitName) !== ''
                    ? trim($rootOrgUnitName)
                    : 'Head Office',
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'company' => ['required', 'array'],
            'company.name' => ['required', 'string', 'max:160'],
            'company.slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tenants', 'slug')],
            'company.timezone' => ['required', 'string', 'timezone:all'],

            'fiscal_year' => ['required', 'array'],
            'fiscal_year.code' => ['required', 'string', 'max:50'],
            'fiscal_year.name' => ['required', 'string', 'max:100'],
            'fiscal_year.start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:fiscal_year.end_date'],
            'fiscal_year.end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:fiscal_year.start_date'],

            'accounts_setup' => ['required', 'array'],
            'accounts_setup.mode' => ['required', 'string', Rule::in(['generated', 'empty'])],

            'organization' => ['nullable', 'array'],
            'organization.create_root_org_unit' => ['required', 'boolean'],
            'organization.root_org_unit_name' => ['nullable', 'string', 'max:160'],
        ];
    }
}
