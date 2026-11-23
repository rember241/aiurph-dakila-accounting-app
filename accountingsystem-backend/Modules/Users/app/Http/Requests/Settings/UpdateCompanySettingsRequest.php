<?php

namespace Modules\Users\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCompanySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'display_name' => $this->normalizeOptionalString($this->input('display_name')),
            'email' => $this->normalizeOptionalEmail($this->input('email')),
            'phone' => $this->normalizeOptionalString($this->input('phone')),
            'address' => $this->normalizeOptionalString($this->input('address')),
            'invoice_header_title' => $this->normalizeOptionalString($this->input('invoice_header_title')),
            'invoice_footer_note' => $this->normalizeOptionalString($this->input('invoice_footer_note')),
            'invoice_payment_note' => $this->normalizeOptionalString($this->input('invoice_payment_note')),
            'invoice_primary_color' => $this->normalizeOptionalHexColor($this->input('invoice_primary_color')),
            'document_logo_position' => $this->normalizeOptionalString($this->input('document_logo_position')),
            'document_accent_color' => $this->normalizeOptionalHexColor($this->input('document_accent_color')),
            'document_header_alignment' => $this->normalizeOptionalString($this->input('document_header_alignment')),
            'document_header_style' => $this->normalizeOptionalString($this->input('document_header_style')),
            'document_footer_text' => $this->normalizeOptionalString($this->input('document_footer_text')),
            'document_footer_alignment' => $this->normalizeOptionalString($this->input('document_footer_alignment')),
            'timezone' => is_string($this->input('timezone')) ? trim($this->input('timezone')) : $this->input('timezone'),
            'default_accounts_receivable_account_id' => $this->normalizeOptionalUuid($this->input('default_accounts_receivable_account_id')),
            'default_sales_income_account_id' => $this->normalizeOptionalUuid($this->input('default_sales_income_account_id')),
            'default_inventory_asset_account_id' => $this->normalizeOptionalUuid($this->input('default_inventory_asset_account_id')),
            'default_cogs_account_id' => $this->normalizeOptionalUuid($this->input('default_cogs_account_id')),
            'default_sales_tax_payable_account_id' => $this->normalizeOptionalUuid($this->input('default_sales_tax_payable_account_id')),
            'default_check_receipt_account_id' => $this->normalizeOptionalUuid($this->input('default_check_receipt_account_id')),
            'default_bank_receipt_account_id' => $this->normalizeOptionalUuid($this->input('default_bank_receipt_account_id')),
            'default_online_receipt_account_id' => $this->normalizeOptionalUuid($this->input('default_online_receipt_account_id')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'display_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\.\s]+$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'invoice_header_title' => ['nullable', 'string', 'max:160'],
            'invoice_footer_note' => ['nullable', 'string', 'max:2000'],
            'invoice_payment_note' => ['nullable', 'string', 'max:2000'],
            'invoice_primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'document_show_company_logo' => ['sometimes', 'boolean'],
            'document_logo_position' => ['nullable', 'string', 'in:left,center,right'],
            'document_show_company_name' => ['sometimes', 'boolean'],
            'document_show_company_address' => ['sometimes', 'boolean'],
            'document_show_company_contact' => ['sometimes', 'boolean'],
            'document_show_document_title' => ['sometimes', 'boolean'],
            'document_accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'document_header_alignment' => ['nullable', 'string', 'in:left,center,right'],
            'document_header_style' => ['nullable', 'string', 'in:compact,standard,formal'],
            'document_footer_text' => ['nullable', 'string', 'max:500'],
            'document_footer_alignment' => ['nullable', 'string', 'in:left,center,right'],
            'document_show_page_numbers' => ['sometimes', 'boolean'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'default_accounts_receivable_account_id' => ['nullable', 'uuid'],
            'default_sales_income_account_id' => ['nullable', 'uuid'],
            'default_inventory_asset_account_id' => ['nullable', 'uuid'],
            'default_cogs_account_id' => ['nullable', 'uuid'],
            'default_sales_tax_payable_account_id' => ['nullable', 'uuid'],
            'default_check_receipt_account_id' => ['nullable', 'uuid'],
            'default_bank_receipt_account_id' => ['nullable', 'uuid'],
            'default_online_receipt_account_id' => ['nullable', 'uuid'],
        ];
    }

    private function normalizeOptionalString(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeOptionalEmail(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : mb_strtolower($normalized);
    }

    private function normalizeOptionalUuid(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeOptionalHexColor(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : mb_strtolower($normalized);
    }
}
