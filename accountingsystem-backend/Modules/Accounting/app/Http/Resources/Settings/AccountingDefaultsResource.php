<?php

namespace Modules\Accounting\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountingDefaultsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'accounts_receivable_account_id' => $this->accounts_receivable_account_id,
            'accounts_receivable_account'     => $this->whenLoaded('accountsReceivableAccount', fn () => $this->accountsReceivableAccount ? [
                'id'   => $this->accountsReceivableAccount->id,
                'code' => $this->accountsReceivableAccount->code,
                'name' => $this->accountsReceivableAccount->name,
            ] : null),

            'sales_income_account_id' => $this->sales_income_account_id,
            'sales_income_account'    => $this->whenLoaded('salesIncomeAccount', fn () => $this->salesIncomeAccount ? [
                'id'   => $this->salesIncomeAccount->id,
                'code' => $this->salesIncomeAccount->code,
                'name' => $this->salesIncomeAccount->name,
            ] : null),

            'inventory_asset_account_id' => $this->inventory_asset_account_id,
            'inventory_asset_account'    => $this->whenLoaded('inventoryAssetAccount', fn () => $this->inventoryAssetAccount ? [
                'id'   => $this->inventoryAssetAccount->id,
                'code' => $this->inventoryAssetAccount->code,
                'name' => $this->inventoryAssetAccount->name,
            ] : null),

            'cogs_account_id' => $this->cogs_account_id,
            'cogs_account'    => $this->whenLoaded('cogsAccount', fn () => $this->cogsAccount ? [
                'id'   => $this->cogsAccount->id,
                'code' => $this->cogsAccount->code,
                'name' => $this->cogsAccount->name,
            ] : null),

            'sales_tax_payable_account_id' => $this->sales_tax_payable_account_id,
            'sales_tax_payable_account'    => $this->whenLoaded('salesTaxPayableAccount', fn () => $this->salesTaxPayableAccount ? [
                'id'   => $this->salesTaxPayableAccount->id,
                'code' => $this->salesTaxPayableAccount->code,
                'name' => $this->salesTaxPayableAccount->name,
            ] : null),

            'updated_at' => $this->updated_at,
        ];
    }
}
