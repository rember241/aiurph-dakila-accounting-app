<?php

namespace Modules\Users\Services\Settings;

use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Users\Models\Company;
use Modules\Users\Services\Companies\CompanyContextService;
use Throwable;

/**
 * Handles current-company settings updates and profile uploads.
 *
 * Company Settings intentionally covers profile/default-account fields only.
 * Organization hierarchy lives in Org Units, and transaction behavior belongs
 * in the operational modules that consume these defaults.
 */
final class CompanySettingsCommandService
{
    public function __construct(
        private readonly AuditLogService       $auditLogService,
        private readonly CompanyContextService $companyContextService
    )
    {
    }

    /**
     * Updates profile fields and accounting defaults for the current company.
     *
     * Account defaults are validated here instead of trusting the request layer
     * because the rule depends on company scope, posting status, activity, and
     * the expected core account type.
     *
     * @param array{
     *   name:string,
     *   display_name:?string,
     *   email:?string,
     *   phone:?string,
     *   address:?string,
     *   invoice_header_title?:?string,
     *   invoice_footer_note?:?string,
     *   invoice_payment_note?:?string,
     *   invoice_primary_color?:?string,
     *   document_show_company_logo?:?bool,
     *   document_logo_position?:?string,
     *   document_show_company_name?:?bool,
     *   document_show_company_address?:?bool,
     *   document_show_company_contact?:?bool,
     *   document_show_document_title?:?bool,
     *   document_accent_color?:?string,
     *   document_header_alignment?:?string,
     *   document_header_style?:?string,
     *   document_footer_text?:?string,
     *   document_footer_alignment?:?string,
     *   timezone:string,
     *   default_accounts_receivable_account_id?:?string,
     *   default_sales_income_account_id?:?string,
     *   default_inventory_asset_account_id?:?string,
     *   default_cogs_account_id?:?string,
     *   default_sales_tax_payable_account_id?:?string,
     *   default_check_receipt_account_id?:?string,
     *   default_bank_receipt_account_id?:?string,
     *   default_online_receipt_account_id?:?string
     * } $payload
     *
     * @throws Throwable
     */
    public function update(User $actor, Company $company, array $payload): Company
    {
        $this->authorizeCompanySettingsWrite($actor, $company);

        $companyId = (string) $company->getKey();
        $defaultAccountsReceivableAccount = $this->resolveAccountOrFail(
            $companyId,
            $payload['default_accounts_receivable_account_id'] ?? null,
            'asset',
            'default_accounts_receivable_account_id'
        );
        $defaultSalesIncomeAccount = $this->resolveAccountOrFail(
            $companyId,
            $payload['default_sales_income_account_id'] ?? null,
            'revenue',
            'default_sales_income_account_id'
        );
        $defaultInventoryAssetAccount = $this->resolveAccountOrFail(
            $companyId,
            $payload['default_inventory_asset_account_id'] ?? null,
            'asset',
            'default_inventory_asset_account_id'
        );
        $defaultCogsAccount = $this->resolveAccountOrFail(
            $companyId,
            $payload['default_cogs_account_id'] ?? null,
            'expense',
            'default_cogs_account_id'
        );
        $defaultSalesTaxPayableAccount = $this->resolveAccountOrFail(
            $companyId,
            $payload['default_sales_tax_payable_account_id'] ?? null,
            'liability',
            'default_sales_tax_payable_account_id'
        );
        $defaultCheckReceiptAccount = $this->resolveAccountOrFail(
            $companyId,
            $payload['default_check_receipt_account_id'] ?? null,
            'asset',
            'default_check_receipt_account_id'
        );
        $defaultBankReceiptAccount = $this->resolveAccountOrFail(
            $companyId,
            $payload['default_bank_receipt_account_id'] ?? null,
            'asset',
            'default_bank_receipt_account_id'
        );
        $defaultOnlineReceiptAccount = $this->resolveAccountOrFail(
            $companyId,
            $payload['default_online_receipt_account_id'] ?? null,
            'asset',
            'default_online_receipt_account_id'
        );

        $before = $this->snapshot($company);

        $updatedCompany = DB::transaction(function () use (
            $company,
            $payload,
            $defaultAccountsReceivableAccount,
            $defaultSalesIncomeAccount,
            $defaultInventoryAssetAccount,
            $defaultCogsAccount,
            $defaultSalesTaxPayableAccount,
            $defaultCheckReceiptAccount,
            $defaultBankReceiptAccount,
            $defaultOnlineReceiptAccount
        ): Company {
            $company->fill([
                'name' => $payload['name'],
                'display_name' => $payload['display_name'] ?? null,
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'address' => $payload['address'] ?? null,
                'invoice_header_title' => $payload['invoice_header_title'] ?? null,
                'invoice_footer_note' => $payload['invoice_footer_note'] ?? null,
                'invoice_payment_note' => $payload['invoice_payment_note'] ?? null,
                'invoice_primary_color' => $payload['invoice_primary_color'] ?? null,
                'document_show_company_logo' => $payload['document_show_company_logo'] ?? true,
                'document_logo_position' => $payload['document_logo_position'] ?? 'left',
                'document_show_company_name' => $payload['document_show_company_name'] ?? true,
                'document_show_company_address' => $payload['document_show_company_address'] ?? true,
                'document_show_company_contact' => $payload['document_show_company_contact'] ?? true,
                'document_show_document_title' => $payload['document_show_document_title'] ?? true,
                'document_accent_color' => $payload['document_accent_color'] ?? null,
                'document_header_alignment' => $payload['document_header_alignment'] ?? 'left',
                'document_header_style' => $payload['document_header_style'] ?? 'standard',
                'document_footer_text' => $payload['document_footer_text'] ?? null,
                'document_footer_alignment' => $payload['document_footer_alignment'] ?? 'center',
                'document_show_page_numbers' => $payload['document_show_page_numbers'] ?? true,
                'timezone' => $payload['timezone'],
                'default_accounts_receivable_account_id' => $defaultAccountsReceivableAccount?->getKey(),
                'default_sales_income_account_id' => $defaultSalesIncomeAccount?->getKey(),
                'default_inventory_asset_account_id' => $defaultInventoryAssetAccount?->getKey(),
                'default_cogs_account_id' => $defaultCogsAccount?->getKey(),
                'default_sales_tax_payable_account_id' => $defaultSalesTaxPayableAccount?->getKey(),
                'default_check_receipt_account_id' => $defaultCheckReceiptAccount?->getKey(),
                'default_bank_receipt_account_id' => $defaultBankReceiptAccount?->getKey(),
                'default_online_receipt_account_id' => $defaultOnlineReceiptAccount?->getKey(),
            ]);

            if ($company->isDirty()) {
                $company->save();
            }

            return Company::query()
                ->with([
                    'owner:id,name,email',
                    'defaultAccountsReceivableAccount:id,company_id,code,name,type,status,is_posting',
                    'defaultSalesIncomeAccount:id,company_id,code,name,type,status,is_posting',
                    'defaultInventoryAssetAccount:id,company_id,code,name,type,status,is_posting',
                    'defaultCogsAccount:id,company_id,code,name,type,status,is_posting',
                    'defaultSalesTaxPayableAccount:id,company_id,code,name,type,status,is_posting',
                    'defaultCheckReceiptAccount:id,company_id,code,name,type,status,is_posting',
                    'defaultBankReceiptAccount:id,company_id,code,name,type,status,is_posting',
                    'defaultOnlineReceiptAccount:id,company_id,code,name,type,status,is_posting',
                ])
                ->findOrFail($company->getKey());
        });

        $this->auditLogService->recordCompanyScoped(
            'company_settings.updated',
            $updatedCompany,
            $before,
            $this->snapshot($updatedCompany),
            [
                'company_id' => (string)$updatedCompany->getKey(),
                'request_id' => request()->header('X-Request-ID'),
            ]
        );

        return $updatedCompany;
    }

    /**
     * @throws Throwable
     */
    /**
     * Stores the company logo and replaces the previous file metadata.
     *
     * Only the path and image metadata are stored in the database; the image
     * itself remains on the configured public disk.
     */
    public function uploadLogo(User $actor, Company $company, UploadedFile $file): Company
    {
        $this->authorizeCompanySettingsWrite($actor, $company);

        $dimensions = @getimagesize($file->getRealPath());
        if ($dimensions === false) {
            throw ValidationException::withMessages([
                'logo' => ['The company logo dimensions could not be read.'],
            ]);
        }

        $before = $this->snapshot($company);
        $oldPath = $company->logo_path === null ? null : (string) $company->logo_path;
        $extension = strtolower($file->extension() ?: 'png');
        $path = $file->storeAs(
            'company-logos/' . (string) $company->getKey(),
            'logo.' . $extension,
            'public'
        );

        $updatedCompany = DB::transaction(function () use ($company, $file, $dimensions, $path): Company {
            $company->fill([
                'logo_path' => $path,
                'logo_mime' => $file->getMimeType(),
                'logo_size' => $file->getSize(),
                'logo_width' => (int) $dimensions[0],
                'logo_height' => (int) $dimensions[1],
                'logo_updated_at' => now(),
            ]);
            $company->save();

            return $this->reloadCompanySettings($company);
        });

        if ($oldPath !== null && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->auditLogService->recordCompanyScoped(
            'company_settings.logo_uploaded',
            $updatedCompany,
            $before,
            $this->snapshot($updatedCompany),
            [
                'company_id' => (string) $updatedCompany->getKey(),
                'request_id' => request()->header('X-Request-ID'),
            ]
        );

        return $updatedCompany;
    }

    /**
     * @throws Throwable
     */
    public function deleteLogo(User $actor, Company $company): Company
    {
        $this->authorizeCompanySettingsWrite($actor, $company);

        $before = $this->snapshot($company);
        $oldPath = $company->logo_path === null ? null : (string) $company->logo_path;

        $updatedCompany = DB::transaction(function () use ($company): Company {
            $company->fill([
                'logo_path' => null,
                'logo_mime' => null,
                'logo_size' => null,
                'logo_width' => null,
                'logo_height' => null,
                'logo_updated_at' => null,
            ]);
            $company->save();

            return $this->reloadCompanySettings($company);
        });

        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->auditLogService->recordCompanyScoped(
            'company_settings.logo_deleted',
            $updatedCompany,
            $before,
            $this->snapshot($updatedCompany),
            [
                'company_id' => (string) $updatedCompany->getKey(),
                'request_id' => request()->header('X-Request-ID'),
            ]
        );

        return $updatedCompany;
    }


    /**
     * Resolves the account for the given company ID, account ID and expected type.
     *
     * @param string $companyId The ID of the company.
     * @param ?string $accountId The ID of the account.
     * @param string $expectedType The expected type of the account.
     * @param string $field The field to use for validation error messages.
     * @return ?Account The resolved account or null if no account was found.
     * @throws ValidationException If the account was not found or has an invalid type.
     */
    private function resolveAccountOrFail(string $companyId, ?string $accountId, string $expectedType, string $field): ?Account
    {
        if ($accountId === null) {
            return null;
        }

        $account = Account::query()
            ->whereKey($accountId)
            ->first();

        if ($account === null) {
            throw ValidationException::withMessages([
                $field => ['Selected account does not exist.'],
            ]);
        }

        if (!Account::query()->forCompany($companyId)->whereKey($accountId)->exists()) {
            throw ValidationException::withMessages([
                $field => ['Selected account must belong to the current company.'],
            ]);
        }

        if ($account->status !== 'active') {
            throw ValidationException::withMessages([
                $field => ['Selected account must be active.'],
            ]);
        }

        if (!(bool) $account->is_posting) {
            throw ValidationException::withMessages([
                $field => ['Selected account must be a posting account.'],
            ]);
        }

        if ($account->type !== $expectedType) {
            throw ValidationException::withMessages([
                $field => ["Selected account must be a valid {$expectedType} account."],
            ]);
        }

        return $account;
    }


    /**
     * @return array<string, mixed>
     */
    private function snapshot(Company $company): array
    {
        return [
            'id' => (string)$company->getKey(),
            'name' => (string)$company->name,
            'display_name' => $company->display_name === null ? null : (string)$company->display_name,
            'slug' => (string)$company->slug,
            'email' => $company->email === null ? null : (string)$company->email,
            'phone' => $company->phone === null ? null : (string)$company->phone,
            'address' => $company->address === null ? null : (string)$company->address,
            'logo_path' => $company->logo_path === null ? null : (string) $company->logo_path,
            'logo_mime' => $company->logo_mime === null ? null : (string) $company->logo_mime,
            'logo_size' => $company->logo_size,
            'logo_width' => $company->logo_width,
            'logo_height' => $company->logo_height,
            'logo_updated_at' => $company->logo_updated_at?->toISOString(),
            'invoice_header_title' => $company->invoice_header_title === null ? null : (string) $company->invoice_header_title,
            'invoice_footer_note' => $company->invoice_footer_note === null ? null : (string) $company->invoice_footer_note,
            'invoice_payment_note' => $company->invoice_payment_note === null ? null : (string) $company->invoice_payment_note,
            'invoice_primary_color' => $company->invoice_primary_color === null ? null : (string) $company->invoice_primary_color,
            'document_show_company_logo' => $company->document_show_company_logo ?? true,
            'document_logo_position' => $company->document_logo_position === null ? 'left' : (string) $company->document_logo_position,
            'document_show_company_name' => $company->document_show_company_name ?? true,
            'document_show_company_address' => $company->document_show_company_address ?? true,
            'document_show_company_contact' => $company->document_show_company_contact ?? true,
            'document_show_document_title' => $company->document_show_document_title ?? true,
            'document_accent_color' => $company->document_accent_color === null ? null : (string) $company->document_accent_color,
            'document_header_alignment' => $company->document_header_alignment === null ? 'left' : (string) $company->document_header_alignment,
            'document_header_style' => $company->document_header_style === null ? 'standard' : (string) $company->document_header_style,
            'document_footer_text' => $company->document_footer_text === null ? null : (string) $company->document_footer_text,
            'document_footer_alignment' => $company->document_footer_alignment === null ? 'center' : (string) $company->document_footer_alignment,
            'document_show_page_numbers' => $company->document_show_page_numbers ?? true,
            'timezone' => (string)$company->timezone,
            'default_accounts_receivable_account_id' => $company->default_accounts_receivable_account_id,
            'default_sales_income_account_id' => $company->default_sales_income_account_id,
            'default_inventory_asset_account_id' => $company->default_inventory_asset_account_id,
            'default_cogs_account_id' => $company->default_cogs_account_id,
            'default_sales_tax_payable_account_id' => $company->default_sales_tax_payable_account_id,
            'default_check_receipt_account_id' => $company->default_check_receipt_account_id,
            'default_bank_receipt_account_id' => $company->default_bank_receipt_account_id,
            'default_online_receipt_account_id' => $company->default_online_receipt_account_id,
            'status' => (string)$company->status,
        ];
    }

    private function reloadCompanySettings(Company $company): Company
    {
        return Company::query()
            ->with([
                'owner:id,name,email',
                'defaultAccountsReceivableAccount:id,company_id,code,name,type,status,is_posting',
                'defaultSalesIncomeAccount:id,company_id,code,name,type,status,is_posting',
                'defaultInventoryAssetAccount:id,company_id,code,name,type,status,is_posting',
                'defaultCogsAccount:id,company_id,code,name,type,status,is_posting',
                'defaultSalesTaxPayableAccount:id,company_id,code,name,type,status,is_posting',
                'defaultCheckReceiptAccount:id,company_id,code,name,type,status,is_posting',
                'defaultBankReceiptAccount:id,company_id,code,name,type,status,is_posting',
                'defaultOnlineReceiptAccount:id,company_id,code,name,type,status,is_posting',
            ])
            ->findOrFail($company->getKey());
    }

    /**
     * Company owners keep full access, while delegated administrators need the
     * explicit Settings > Edit permission from the role matrix.
     */
    private function authorizeCompanySettingsWrite(User $actor, Company $company): void
    {
        if (
            !$this->companyContextService->userHasFullAccess($actor, $company)
            && !$this->companyContextService->userHasAbility($actor, $company, 'settings.write')
        ) {
            throw new AuthorizationException('You do not have permission to update company settings.');
        }
    }
}
