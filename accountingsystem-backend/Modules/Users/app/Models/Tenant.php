<?php

namespace Modules\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Accounting\Models\ChartOfAccounts\Account;

/**
 * Tenant model.
 *
 * Represents a single company/workspace in the ERP.
 * A tenant has one dedicated database target and many user memberships.
 */
class Tenant extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tenants';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'slug',
        'status',
        'timezone',
        'email',
        'phone',
        'address',
        'logo_path',
        'logo_mime',
        'logo_size',
        'logo_width',
        'logo_height',
        'logo_updated_at',
        'invoice_header_title',
        'invoice_footer_note',
        'invoice_payment_note',
        'invoice_primary_color',
        'document_show_company_logo',
        'document_logo_position',
        'document_show_company_name',
        'document_show_company_address',
        'document_show_company_contact',
        'document_show_document_title',
        'document_accent_color',
        'document_header_alignment',
        'document_header_style',
        'document_footer_text',
        'document_footer_alignment',
        'document_show_page_numbers',
        'default_accounts_receivable_account_id',
        'default_sales_income_account_id',
        'default_inventory_asset_account_id',
        'default_cogs_account_id',
        'default_sales_tax_payable_account_id',
        'default_cash_receipt_account_id',
        'default_check_receipt_account_id',
        'default_bank_receipt_account_id',
        'default_online_receipt_account_id',
        'owner_user_id',
    ];

    protected $casts = [
        'logo_size' => 'integer',
        'logo_width' => 'integer',
        'logo_height' => 'integer',
        'logo_updated_at' => 'datetime',
        'document_show_company_logo' => 'boolean',
        'document_show_company_name' => 'boolean',
        'document_show_company_address' => 'boolean',
        'document_show_company_contact' => 'boolean',
        'document_show_document_title' => 'boolean',
        'document_show_page_numbers' => 'boolean',
    ];

    /**
     * Tenant owner (control-plane user).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Default receivables account used when sales invoices are posted.
     */
    public function defaultAccountsReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_accounts_receivable_account_id');
    }

    /**
     * Default revenue account used for sales invoice line income.
     */
    public function defaultSalesIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_sales_income_account_id');
    }

    /**
     * Default inventory asset account relieved when stock items are issued.
     */
    public function defaultInventoryAssetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_inventory_asset_account_id');
    }

    /**
     * Default expense account used for cost of goods sold on inventory lines.
     */
    public function defaultCogsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_cogs_account_id');
    }

    public function defaultSalesTaxPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_sales_tax_payable_account_id');
    }

    public function defaultCashReceiptAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_cash_receipt_account_id');
    }

    public function defaultCheckReceiptAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_check_receipt_account_id');
    }

    public function defaultBankReceiptAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_bank_receipt_account_id');
    }

    public function defaultOnlineReceiptAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_online_receipt_account_id');
    }

    /**
     * Dedicated database connection metadata for this tenant.
     */
    public function database(): HasOne
    {
        return $this->hasOne(TenantDatabase::class, 'tenant_id');
    }

    /**
     * Membership rows linking users to this tenant.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'tenant_id');
    }

    /**
     * Convenience relation to users via tenant memberships.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_memberships', 'tenant_id', 'user_id')
            ->withPivot(['id', 'status', 'invited_by_user_id', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Tenant-scoped roles.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'tenant_id');
    }

    /**
     * Invite records for this tenant.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'tenant_id');
    }

    /**
     * Company organization units.
     */
    public function orgUnits(): HasMany
    {
        return $this->hasMany(OrgUnit::class, 'company_id');
    }
}
