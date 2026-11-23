<?php

namespace Modules\Users\Services\Settings;

use App\Models\User;
use Modules\Users\Models\Company;
use Modules\Users\Services\Companies\CompanyContextService;

/**
 * Resolves current-company settings reads.
 */
final class CompanySettingsQueryService
{
    public function __construct(
        private readonly CompanyContextService $companyContextService
    ) {
    }

    public function current(User $user): ?Company
    {
        $company = $this->companyContextService->resolveCurrentCompany($user);
        if (!$company instanceof Company) {
            return null;
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
            ->find($company->getKey());
    }
}
