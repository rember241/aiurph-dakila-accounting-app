<?php

namespace Modules\Users\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Models\ChartOfAccounts\Account;

/**
 * Current company settings payload.
 *
 * @mixin \Modules\Users\Models\Company
 */
final class CompanySettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string)$this->getKey(),
            'name' => (string)$this->name,
            'display_name' => $this->display_name === null ? null : (string)$this->display_name,
            'slug' => (string)$this->slug,
            'email' => $this->email === null ? null : (string)$this->email,
            'phone' => $this->phone === null ? null : (string)$this->phone,
            'address' => $this->address === null ? null : (string)$this->address,
            'logo_path' => $this->logo_path === null ? null : (string) $this->logo_path,
            'logo_url' => $this->logoUrl(),
            'logo_mime' => $this->logo_mime === null ? null : (string) $this->logo_mime,
            'logo_size' => $this->logo_size,
            'logo_width' => $this->logo_width,
            'logo_height' => $this->logo_height,
            'logo_updated_at' => $this->logo_updated_at?->toISOString(),
            'invoice_header_title' => $this->invoice_header_title === null ? null : (string) $this->invoice_header_title,
            'invoice_footer_note' => $this->invoice_footer_note === null ? null : (string) $this->invoice_footer_note,
            'invoice_payment_note' => $this->invoice_payment_note === null ? null : (string) $this->invoice_payment_note,
            'invoice_primary_color' => $this->invoice_primary_color === null ? null : (string) $this->invoice_primary_color,
            'document_show_company_logo' => $this->document_show_company_logo ?? true,
            'document_logo_position' => $this->document_logo_position === null ? 'left' : (string) $this->document_logo_position,
            'document_show_company_name' => $this->document_show_company_name ?? true,
            'document_show_company_address' => $this->document_show_company_address ?? true,
            'document_show_company_contact' => $this->document_show_company_contact ?? true,
            'document_show_document_title' => $this->document_show_document_title ?? true,
            'document_accent_color' => $this->document_accent_color === null ? null : (string) $this->document_accent_color,
            'document_header_alignment' => $this->document_header_alignment === null ? 'left' : (string) $this->document_header_alignment,
            'document_header_style' => $this->document_header_style === null ? 'standard' : (string) $this->document_header_style,
            'document_footer_text' => $this->document_footer_text === null ? null : (string) $this->document_footer_text,
            'document_footer_alignment' => $this->document_footer_alignment === null ? 'center' : (string) $this->document_footer_alignment,
            'document_show_page_numbers' => $this->document_show_page_numbers ?? true,
            'document_printing' => [
                'show_company_logo' => $this->document_show_company_logo ?? true,
                'logo_position' => $this->document_logo_position === null ? 'left' : (string) $this->document_logo_position,
                'show_company_name' => $this->document_show_company_name ?? true,
                'show_company_address' => $this->document_show_company_address ?? true,
                'show_company_contact' => $this->document_show_company_contact ?? true,
                'show_document_title' => $this->document_show_document_title ?? true,
                'primary_color' => $this->invoice_primary_color === null ? null : (string) $this->invoice_primary_color,
                'accent_color' => $this->document_accent_color === null ? null : (string) $this->document_accent_color,
                'header_alignment' => $this->document_header_alignment === null ? 'left' : (string) $this->document_header_alignment,
                'header_style' => $this->document_header_style === null ? 'standard' : (string) $this->document_header_style,
                'footer_text' => $this->document_footer_text === null ? null : (string) $this->document_footer_text,
                'footer_alignment' => $this->document_footer_alignment === null ? 'center' : (string) $this->document_footer_alignment,
                'show_page_numbers' => $this->document_show_page_numbers ?? true,
                'footer_note' => $this->invoice_footer_note === null ? null : (string) $this->invoice_footer_note,
            ],
            'timezone' => (string)$this->timezone,
            'default_accounts_receivable_account_id' => $this->default_accounts_receivable_account_id,
            'default_sales_income_account_id' => $this->default_sales_income_account_id,
            'default_inventory_asset_account_id' => $this->default_inventory_asset_account_id,
            'default_cogs_account_id' => $this->default_cogs_account_id,
            'default_sales_tax_payable_account_id' => $this->default_sales_tax_payable_account_id,
            'default_check_receipt_account_id' => $this->default_check_receipt_account_id,
            'default_bank_receipt_account_id' => $this->default_bank_receipt_account_id,
            'default_online_receipt_account_id' => $this->default_online_receipt_account_id,
            'default_accounts_receivable_account' => $this->whenLoaded(
                'defaultAccountsReceivableAccount',
                fn (): ?array => $this->accountSummary($this->defaultAccountsReceivableAccount)
            ),
            'default_sales_income_account' => $this->whenLoaded(
                'defaultSalesIncomeAccount',
                fn (): ?array => $this->accountSummary($this->defaultSalesIncomeAccount)
            ),
            'default_inventory_asset_account' => $this->whenLoaded(
                'defaultInventoryAssetAccount',
                fn (): ?array => $this->accountSummary($this->defaultInventoryAssetAccount)
            ),
            'default_cogs_account' => $this->whenLoaded(
                'defaultCogsAccount',
                fn (): ?array => $this->accountSummary($this->defaultCogsAccount)
            ),
            'default_sales_tax_payable_account' => $this->whenLoaded(
                'defaultSalesTaxPayableAccount',
                fn (): ?array => $this->accountSummary($this->defaultSalesTaxPayableAccount)
            ),
            'default_check_receipt_account' => $this->whenLoaded(
                'defaultCheckReceiptAccount',
                fn (): ?array => $this->accountSummary($this->defaultCheckReceiptAccount)
            ),
            'default_bank_receipt_account' => $this->whenLoaded(
                'defaultBankReceiptAccount',
                fn (): ?array => $this->accountSummary($this->defaultBankReceiptAccount)
            ),
            'default_online_receipt_account' => $this->whenLoaded(
                'defaultOnlineReceiptAccount',
                fn (): ?array => $this->accountSummary($this->defaultOnlineReceiptAccount)
            ),
            'status' => (string)$this->status,
            'owner' => $this->whenLoaded('owner', function (): ?array {
                if ($this->owner === null) {
                    return null;
                }

                return [
                    'id' => (string)$this->owner->getKey(),
                    'name' => (string)$this->owner->name,
                    'email' => (string)$this->owner->email,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function accountSummary(?Account $account): ?array
    {
        if (!$account instanceof Account) {
            return null;
        }

        return [
            'id' => (string) $account->getKey(),
            'code' => (string) $account->code,
            'name' => (string) $account->name,
            'type' => (string) $account->type,
        ];
    }

    private function logoUrl(): ?string
    {
        if ($this->logo_path === null) {
            return null;
        }

        $url = Storage::disk('public')->url((string) $this->logo_path);
        $version = $this->logo_updated_at?->getTimestamp();

        return $version === null ? $url : $url . '?v=' . $version;
    }
}
