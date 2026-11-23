<?php

namespace Modules\Accounting\Services\ChartOfAccounts;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\Settings\AccountingDefaults;
use Modules\Accounting\Services\CurrentTenantContext;
use Modules\Users\Models\Tenant;
use RuntimeException;
use Throwable;


/**
 * Class ChartOfAccountsCommandService
 *
 * Service for handling account commands: create, update and delete.
 *
 * IMPROVEMENTS
 * - Add https request header codes
 *
 * @package Modules\Accounting\Services\Accounts
 */
class ChartOfAccountsCommandService
{
    private const TYPE_BASE_CODE = [
        'asset' => 1000,
        'liability' => 2000,
        'equity' => 3000,
        'revenue' => 4000,
        'expense' => 5000,
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentTenantContext $currentTenantContext
    )
    {
    }

    private function ensureNotSystem(Account $account): void
    {
        if ($account->is_system) {
            throw new RuntimeException('System account is locked and cannot be updated or deleted.');
        }
    }

    /**
     * Chooses a sensible system parent when the user creates a posting account.
     *
     * The chart template owns the non-posting hierarchy. Attaching new accounts
     * under the matching detail/type node keeps reports grouped correctly.
     */
    private function assignDefaultParentIfMissing(array &$payload, string $tenantId): void
    {
        $parentId = $payload['parent_id'] ?? null;
        if (!empty($parentId)) {
            return;
        }

        $type = (string)($payload['type'] ?? '');
        if ($type === '') {
            return;
        }

        $detailTypeId = (int)($payload['detail_type_id'] ?? 0);
        if ($detailTypeId > 0) {
            $detailNode = Account::query()
                ->forTenant($tenantId)
                ->select(['id'])
                ->where('type', $type)
                ->where('is_system', true)
                ->where('is_posting', false)
                ->where('detail_type_id', $detailTypeId)
                ->orderBy('created_at')
                ->first();

            if ($detailNode) {
                $payload['parent_id'] = $detailNode->id;
                return;
            }
        }

        $accountTypeId = (int)($payload['account_type_id'] ?? 0);
        if ($accountTypeId > 0) {
            $accountTypeNode = Account::query()
                ->forTenant($tenantId)
                ->select(['id'])
                ->where('type', $type)
                ->where('is_system', true)
                ->where('is_posting', false)
                ->where('account_type_id', $accountTypeId)
                ->whereNull('detail_type_id')
                ->orderBy('created_at')
                ->first();

            if ($accountTypeNode) {
                $payload['parent_id'] = $accountTypeNode->id;
                return;
            }
        }

        $root = Account::query()
            ->forTenant($tenantId)
            ->select(['id'])
            ->where('type', $type)
            ->where('is_system', true)
            ->where('is_posting', false)
            ->whereNull('parent_id')
            ->orderBy('created_at')
            ->first();

        if ($root) {
            $payload['parent_id'] = $root->id;
        }
    }

    /**
     * Protects the chart hierarchy from cross-type parents and cycles.
     *
     * Posting accounts are leaves in this ERP, so they cannot be selected as
     * parents for other accounts.
     */
    private function validateParentPlacement(array $payload, string $tenantId, ?Account $account = null): void
    {
        $parentId = $payload['parent_id'] ?? null;
        if (empty($parentId)) {
            return;
        }

        $parent = Account::query()
            ->forTenant($tenantId)
            ->select(['id', 'type', 'is_posting', 'parent_id'])
            ->find($parentId);

        if (!$parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent account does not exist.',
            ]);
        }

        if ($account && $parent->id === $account->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Account cannot be its own parent.',
            ]);
        }

        $targetType = (string)($payload['type'] ?? $account?->type ?? '');
        if ($targetType !== '' && $parent->type !== $targetType) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent account must match the selected account type.',
            ]);
        }

        if ((bool)$parent->is_posting) {
            throw ValidationException::withMessages([
                'parent_id' => 'Posting accounts cannot be used as parent accounts.',
            ]);
        }

        if (!$account) {
            return;
        }

        $cursor = $parent;
        while ($cursor->parent_id) {
            if ($cursor->parent_id === $account->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Invalid hierarchy. This parent selection creates a cycle.',
                ]);
            }

            $next = Account::query()
                ->forTenant($tenantId)
                ->select(['id', 'parent_id'])
                ->find($cursor->parent_id);

            if (!$next) {
                break;
            }

            if ($next->id === $account->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Invalid hierarchy. This parent selection creates a cycle.',
                ]);
            }

            $cursor = $next;
        }
    }


    /**
     * @throws Throwable
     */
    public function create(array $payload): Account
    {
        $tenantId = $this->currentTenantContext->id();

        unset($payload['is_system']);
        $payload['is_system'] = false;
        $payload['company_id'] = $tenantId;
        $payload['tenant_id'] = $tenantId;
        $this->assignDefaultParentIfMissing($payload, $tenantId);
        $this->validateParentPlacement($payload, $tenantId);

        $account = DB::transaction(function () use ($payload, $tenantId) {
            $payload['code'] = $this->generateCode(
                $tenantId,
                (string)($payload['type'] ?? ''),
                (int)($payload['account_type_id'] ?? 0),
                (int)($payload['detail_type_id'] ?? 0),
            );

            return Account::create($payload);
        });

        $this->auditLogService->recordTenantScoped(
            'account.created',
            $account,
            null,
            $account->only(['company_id', 'tenant_id', 'code', 'name', 'type', 'status', 'normal_balance', 'parent_id', 'is_posting', 'is_contra', 'contra_account_id', 'description', 'account_type_id', 'detail_type_id']),
            ['request_id' => request()->header('X-Request-ID')]
        );

        return $account;
    }

    /**
     * Generates a company-scoped code in the classic accounting ranges.
     *
     * The row lock prevents two users from receiving the same next code while
     * creating accounts in the same company and account type.
     */
    private function generateCode(string $tenantId, string $type, int $accountTypeId, int $detailTypeId): string
    {
        $baseCode = self::TYPE_BASE_CODE[$type] ?? null;
        if ($baseCode === null) {
            throw ValidationException::withMessages([
                'type' => 'Unsupported account type for code generation.',
            ]);
        }

        $accountType = DB::table('account_types')
            ->select(['id', 'code', 'core_type'])
            ->where('id', $accountTypeId)
            ->first();

        if (!$accountType || $accountType->core_type !== $type) {
            throw ValidationException::withMessages([
                'account_type_id' => 'Account type does not belong to selected core type.',
            ]);
        }

        $detailType = DB::table('detail_types')
            ->select(['id', 'code', 'account_type_id'])
            ->where('id', $detailTypeId)
            ->first();

        if (!$detailType || (int)$detailType->account_type_id !== $accountTypeId) {
            throw ValidationException::withMessages([
                'detail_type_id' => 'Detail type does not belong to selected account type.',
            ]);
        }

        $existingCodes = Account::query()
            ->forTenant($tenantId)
            ->where('type', $type)
            ->lockForUpdate()
            ->pluck('code');

        $maxCode = $baseCode - 1;
        foreach ($existingCodes as $existingCode) {
            $candidate = (string)$existingCode;

            if (preg_match('/^\d{4}$/', $candidate) !== 1) {
                continue;
            }

            $numericCode = (int)$candidate;
            if ($numericCode < $baseCode || $numericCode > $baseCode + 999) {
                continue;
            }

            $maxCode = max($maxCode, $numericCode);
        }

        $nextCode = $maxCode + 1;
        if ($nextCode > $baseCode + 999) {
            throw ValidationException::withMessages([
                'type' => "No more available {$type} account codes in {$baseCode}-" . ($baseCode + 999) . '.',
            ]);
        }

        return (string)$nextCode;
    }

    /**
     * @throws Throwable
     */
    public function update(Account $account, array $payload): Account
    {
        $tenantId = $this->currentTenantContext->id();

        $this->ensureNotSystem($account);
        unset($payload['is_system']);
        unset($payload['code']);
        $this->validateParentPlacement($payload, $tenantId, $account);


        return DB::transaction(function () use ($account, $payload) {
            $before = $account->only(['company_id', 'tenant_id', 'code', 'name', 'type', 'status', 'normal_balance', 'parent_id', 'is_posting', 'is_contra', 'contra_account_id', 'description', 'account_type_id', 'detail_type_id']);
            $account->update($payload);
            $fresh = $account->fresh();

            $this->auditLogService->recordTenantScoped(
                'account.updated',
                $fresh,
                $before,
                $fresh->only(['company_id', 'tenant_id', 'code', 'name', 'type', 'status', 'normal_balance', 'parent_id', 'is_posting', 'is_contra', 'contra_account_id', 'description', 'account_type_id', 'detail_type_id']),
                ['request_id' => request()->header('X-Request-ID')]
            );

            return $fresh;
        });
    }


    /**
     * Delete account with business rule checks.
     *
     * @throws Throwable
     */
    public function delete(Account $account): void
    {
        $this->ensureNotSystem($account);

        $tenantId = (string) ($account->company_id ?: $account->tenant_id);

        $this->ensureAccountCanBeDeleted($account, $tenantId);

        $this->auditLogService->recordTenantScoped(
            'account.delete',
            $account,
            $account->only(['company_id', 'tenant_id', 'code', 'name', 'type', 'status', 'normal_balance', 'parent_id', 'is_posting', 'is_contra', 'contra_account_id', 'description', 'account_type_id', 'detail_type_id']),
            [],
            ['request_id' => request()->header('X-Request-ID')]
        );

        DB::transaction(static fn() => $account->delete());
    }

    private function ensureAccountCanBeDeleted(Account $account, string $tenantId): void
    {
        $hasChildren = Account::query()
            ->forTenant($tenantId)
            ->where('parent_id', $account->id)
            ->exists();
        if ($hasChildren) {
            throw new RuntimeException('Account has sub-accounts and cannot be deleted.');
        }

        $isContraBase = Account::query()
            ->forTenant($tenantId)
            ->where('contra_account_id', $account->id)
            ->exists();
        if ($isContraBase) {
            throw new RuntimeException('Account is used as a contra-account by another account. Remove that relationship before deleting it.');
        }

        if ($account->journalItems()->exists()) {
            throw new RuntimeException('Account already has journal activity and cannot be deleted.');
        }

        if ($this->isCompanyDefaultAccount($account, $tenantId)) {
            throw new RuntimeException('Account is assigned in company default posting settings. Change the default account first before deleting it.');
        }
    }

    private function isCompanyDefaultAccount(Account $account, string $tenantId): bool
    {
        $accountId = (string) $account->getKey();
        $companyDefaultColumns = [
            'default_accounts_receivable_account_id',
            'default_sales_income_account_id',
            'default_inventory_asset_account_id',
            'default_cogs_account_id',
            'default_sales_tax_payable_account_id',
            'default_cash_receipt_account_id',
            'default_check_receipt_account_id',
            'default_bank_receipt_account_id',
            'default_online_receipt_account_id',
        ];

        $isTenantDefault = Tenant::query()
            ->whereKey($tenantId)
            ->where(function ($query) use ($companyDefaultColumns, $accountId): void {
                foreach ($companyDefaultColumns as $index => $column) {
                    if ($index === 0) {
                        $query->where($column, $accountId);
                        continue;
                    }

                    $query->orWhere($column, $accountId);
                }
            })
            ->exists();

        if ($isTenantDefault) {
            return true;
        }

        return AccountingDefaults::query()
            ->where(function ($query) use ($accountId): void {
                $query->where('accounts_receivable_account_id', $accountId)
                    ->orWhere('sales_income_account_id', $accountId)
                    ->orWhere('inventory_asset_account_id', $accountId)
                    ->orWhere('cogs_account_id', $accountId)
                    ->orWhere('sales_tax_payable_account_id', $accountId);
            })
            ->exists();
    }


}
