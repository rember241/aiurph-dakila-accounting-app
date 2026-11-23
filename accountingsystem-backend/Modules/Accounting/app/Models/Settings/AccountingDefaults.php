<?php

namespace Modules\Accounting\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\ChartOfAccounts\Account;

/**
 * Singleton model — only one row ever exists.
 * Use AccountingDefaults::current() to retrieve or create it.
 */
class AccountingDefaults extends Model
{
    protected $table = 'accounting_defaults';

    protected $fillable = [
        'accounts_receivable_account_id',
        'sales_income_account_id',
        'inventory_asset_account_id',
        'cogs_account_id',
        'sales_tax_payable_account_id',
    ];

    /**
     * Retrieve the single defaults row, creating it if it does not yet exist.
     */
    public static function current(): self
    {
        return self::firstOrCreate([]);
    }

    public function accountsReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accounts_receivable_account_id');
    }

    public function salesIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sales_income_account_id');
    }

    public function inventoryAssetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'inventory_asset_account_id');
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cogs_account_id');
    }

    public function salesTaxPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sales_tax_payable_account_id');
    }
}
