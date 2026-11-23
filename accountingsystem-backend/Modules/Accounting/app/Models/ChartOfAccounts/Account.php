<?php

namespace Modules\Accounting\Models\ChartOfAccounts;

use Modules\Accounting\Database\Factories\AccountFactory;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Users\Models\Company;
use Modules\Users\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Account model
 *
 * @property string|null $company_id
 * @property string|null $tenant_id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property int|null $parent_id
 * @property bool $is_posting
 * @property string $status
 * @property string $normal_balance
 * @property bool $is_contra
 * @property string|null $contra_account_id
 * @property string|null $description
 */
class Account extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory()
    {
        return AccountFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_id',
        'company_id',
        'code',
        'name',
        'type',
        'account_type_id',
        'detail_type_id',
        'parent_id',
        'is_posting',
        'status',
        'is_contra',
        'contra_account_id',
        'description',
        'is_system',

    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string[]>
     */
    protected $casts = [
        'is_posting' => 'boolean',
        'is_contra' => 'boolean',
        'is_system' => 'boolean'
    ];

    protected static function booted(): void
    {
        static::saving(function (Account $account) {
            $companyId = is_string($account->company_id) ? trim($account->company_id) : '';
            $tenantId = is_string($account->tenant_id) ? trim($account->tenant_id) : '';

            if ($companyId !== '' && $tenantId !== '' && $companyId !== $tenantId) {
                throw new \LogicException('Account company scope must match the legacy tenant scope.');
            }

            if ($companyId === '' && $tenantId !== '') {
                $account->company_id = $tenantId;
            }

            if ($tenantId === '' && $companyId !== '') {
                $account->tenant_id = $companyId;
            }

            $normalBalanceMap = [
                'asset' => 'debit',
                'expense' => 'debit',
                'liability' => 'credit',
                'equity' => 'credit',
                'revenue' => 'credit',
            ];

            if (!empty($account->type) && isset($normalBalanceMap[$account->type])) {
                $account->normal_balance = $normalBalanceMap[$account->type];
            }

            $account->is_contra = !empty($account->contra_account_id) || (bool) $account->is_contra;
            // Flip normal balance if contra
            if ($account->is_contra) {
                $account->normal_balance = $account->normal_balance === 'debit'
                    ? 'credit'
                    : 'debit';
            }
        });
    }

    /**
     * Get the parent account.
     *
     * @return BelongsTo
     */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * Get the tenant that owns the account.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the company that owns the account.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get sub-accounts for the current account.
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /**
     * Get the contra account (if any).
     *
     * @return BelongsTo
     */
    public function contraAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'contra_account_id');
    }

    public function flags()
    {
        return $this->hasMany(AccountFlag::class, 'account_id');
    }

    /**
     * An account can have many journal items referencing it
     */
    public function journalItems(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'account_id');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Backward-compatible scope alias.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->forCompany($tenantId);
    }
}
