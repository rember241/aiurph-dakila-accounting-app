<?php

namespace Modules\Users\Services\Users;

use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Users\Models\Company;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Modules\Users\Services\Roles\RoleMembershipStatusService;
use RuntimeException;
use Throwable;

/**
 * UserCommandService
 *
 * Handles user commands: create, update, activate/deactivate and delete.
 */
class UserCommandService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly RoleMembershipStatusService $roleMembershipStatusService,
        private readonly UserCompanyAssignmentService $userCompanyAssignmentService
    )
    {
    }

    /**
     * Create a user (step-by-step):
     * 1) Split role_ids from payload.
     * 2) Create user inside a DB transaction.
     * 3) Sync role assignments.
     * 4) Record audit log (user.created).
     *
     * @throws Throwable
     */
    public function create(string $tenantId, array $payload): User
    {
        $roleIds = $payload['role_ids'] ?? [];
        $primaryOrgUnitId = is_string($payload['primary_org_unit_id'] ?? null)
            ? trim((string) $payload['primary_org_unit_id'])
            : null;
        $orgUnitIds = is_array($payload['org_unit_ids'] ?? null) ? $payload['org_unit_ids'] : [];
        unset($payload['role_ids']);
        unset($payload['primary_org_unit_id'], $payload['org_unit_ids']);

        $user = DB::transaction(function () use ($tenantId, $payload, $roleIds, $primaryOrgUnitId, $orgUnitIds) {
            $existingUser = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) ($payload['email'] ?? ''))])
                ->first();

            if ($existingUser instanceof User) {
                $membershipExists = TenantMembership::query()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $existingUser->getKey())
                    ->exists();
                $ownsCurrentCompany = Company::query()
                    ->whereKey($tenantId)
                    ->where('owner_user_id', $existingUser->getKey())
                    ->exists();

                if ($membershipExists || $ownsCurrentCompany) {
                    throw ValidationException::withMessages([
                        'email' => 'This user is already part of the current company.',
                    ]);
                }

                $this->ensureUserCanJoinCompany($tenantId, $existingUser);

                $user = $existingUser;
                $userPayload = array_filter([
                    'name' => $payload['name'] ?? null,
                    'avatar_url' => $payload['avatar_url'] ?? $existingUser->avatar_url,
                ], static fn($value) => $value !== null);

                if (array_key_exists('department', $payload)) {
                    $userPayload['department'] = $payload['department'];
                }

                $user->fill($userPayload);
                if ($user->isDirty()) {
                    $user->save();
                }
            } else {
                $password = (string) ($payload['password'] ?? '');
                if ($password === '') {
                    throw ValidationException::withMessages([
                        'password' => 'Password is required when creating a brand-new user.',
                    ]);
                }

                $user = User::create([
                    'name' => $payload['name'],
                    'email' => $payload['email'],
                    'password' => $password,
                    'status' => 'active',
                    'department' => $payload['department'] ?? null,
                    'avatar_url' => $payload['avatar_url'] ?? null,
                ]);
            }

            $this->userCompanyAssignmentService->assignUserToCompany($user, $tenantId);

            $membership = TenantMembership::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->getKey(),
                'status' => (string) ($payload['status'] ?? 'active'),
                'invited_by_user_id' => auth()->id(),
                'joined_at' => (($payload['status'] ?? 'active') === 'active') ? now() : null,
            ]);

            if (is_array($roleIds)) {
                $membership->roles()->sync($roleIds);
            }

            $this->userCompanyAssignmentService->syncOrgUnits(
                $user,
                $tenantId,
                $primaryOrgUnitId,
                $orgUnitIds
            );

            $this->roleMembershipStatusService->syncMembership($membership);

            return $this->findTenantUserOrFail($tenantId, $user);
        });

        $this->auditLogService->recordTenantScoped(
            'user.created',
            $user,
            null,
            array_merge(
                $user->only(['name', 'email', 'department', 'avatar_url', 'last_active_at']),
                [
                    'company_id' => $tenantId,
                    'tenant_id' => $tenantId,
                    'primary_org_unit_id' => $user->primary_org_unit_id ? (string) $user->primary_org_unit_id : null,
                    'org_unit_ids' => $user->relationLoaded('orgUnits')
                        ? $user->orgUnits->pluck('id')->map(fn($id) => (string) $id)->values()->all()
                        : [],
                    'status' => data_get($user, 'tenantMemberships.0.status', 'active'),
                    'role_ids' => data_get($user, 'tenantMemberships.0.roles', collect())->pluck('id')->map(fn($id) => (string)$id)->values()->all(),
                ]
            ),
            ['request_id' => request()->header('X-Request-ID'), 'tenant_id' => $tenantId]
        );

        return $user;
    }

    /**
     * Update a user.
     *
     * @throws Throwable
     */
    public function update(string $tenantId, User $user, array $payload): User
    {
        $this->guardOwnerDeactivation($tenantId, $user, $payload['status'] ?? null);
        $this->guardOwnerMutation($tenantId, $user, 'update');

        $roleIds = null;
        $shouldSyncOrgUnits = array_key_exists('primary_org_unit_id', $payload) || array_key_exists('org_unit_ids', $payload);
        $primaryOrgUnitId = array_key_exists('primary_org_unit_id', $payload)
            ? (is_string($payload['primary_org_unit_id']) ? trim((string) $payload['primary_org_unit_id']) : null)
            : null;
        $orgUnitIds = array_key_exists('org_unit_ids', $payload) && is_array($payload['org_unit_ids'])
            ? $payload['org_unit_ids']
            : [];
        if (array_key_exists('role_ids', $payload)) {
            $roleIds = $payload['role_ids'] ?? [];
            unset($payload['role_ids']);
        }
        unset($payload['primary_org_unit_id'], $payload['org_unit_ids']);

        if (array_key_exists('password', $payload) && ($payload['password'] === null || $payload['password'] === '')) {
            unset($payload['password']);
        }

        return DB::transaction(function () use ($tenantId, $user, $payload, $roleIds, $shouldSyncOrgUnits, $primaryOrgUnitId, $orgUnitIds) {
            $membership = $this->findMembershipOrFail($tenantId, $user);
            $membershipStatusBeforeUpdate = (string) $membership->status;
            $before = $this->snapshotUser($user, $membership);

            $userPayload = $payload;
            unset($userPayload['status']);
            $user->update($userPayload);

            if (array_key_exists('status', $payload)) {
                $membership->update([
                    'status' => (string) $payload['status'],
                    'joined_at' => (string) $payload['status'] === 'active'
                        ? ($membership->joined_at ?? now())
                        : $membership->joined_at,
                ]);
            }

            if (is_array($roleIds)) {
                $membership->roles()->sync($roleIds);
            }

            if ($shouldSyncOrgUnits) {
                $this->userCompanyAssignmentService->syncOrgUnits(
                    $user,
                    $tenantId,
                    $primaryOrgUnitId,
                    $orgUnitIds
                );
            }

            $this->roleMembershipStatusService->syncMembership($membership);
            $membership->refresh();
            $this->revokeTokensIfMembershipBecameInactive(
                $user,
                $membershipStatusBeforeUpdate,
                (string) $membership->status
            );

            $fresh = $this->findTenantUserOrFail($tenantId, $user);

            $after = $this->snapshotUser($fresh, data_get($fresh, 'tenantMemberships.0'));

            $this->auditLogService->recordTenantScoped(
                'user.updated',
                $fresh,
                $before,
                $after,
                ['request_id' => request()->header('X-Request-ID'), 'tenant_id' => $tenantId]
            );

            return $fresh;
        });
    }

    /**
     * Activate/deactivate a user.
     *
     * Step-by-step:
     * 1) Update status in a DB transaction.
     * 2) If deactivated, revoke all tokens (force logout across devices).
     * 3) Record audit log (user.activated/user.deactivated).
     *
     * @throws Throwable
     */
    public function transition(string $tenantId, User $user, string $status): User
    {
        $this->guardOwnerDeactivation($tenantId, $user, $status);
        $this->guardOwnerMutation($tenantId, $user, 'update');

        $membership = $this->findMembershipOrFail($tenantId, $user);
        $status = strtolower(trim($status));
        if ($status === '' || $status === (string)$membership->status) {
            return $this->findTenantUserOrFail($tenantId, $user);
        }

        return DB::transaction(function () use ($tenantId, $user, $status, $membership) {
            $before = ['status' => (string) $membership->status];

            $membership->update(['status' => $status]);

            $this->roleMembershipStatusService->syncMembership($membership);
            $membership->refresh();
            $this->revokeTokensIfMembershipBecameInactive(
                $user,
                $before['status'],
                (string) $membership->status
            );

            $fresh = $this->findTenantUserOrFail($tenantId, $user);
            $resolvedStatus = data_get($fresh, 'tenantMemberships.0.status', (string) $membership->status);

            if ($resolvedStatus !== $before['status']) {
                $event = $resolvedStatus === 'active' ? 'user.activated' : 'user.deactivated';
                $this->auditLogService->recordTenantScoped(
                    $event,
                    $fresh,
                    $before,
                    ['status' => $resolvedStatus],
                    ['request_id' => request()->header('X-Request-ID'), 'tenant_id' => $tenantId]
                );
            }

            return $fresh;
        });
    }

    /**
     * Delete a user.
     *
     * Prefer deactivation in most ERP scenarios; delete is provided for admin cleanup.
     *
     * @throws Throwable
     */
    public function delete(string $tenantId, User $user): void
    {
        $this->guardOwnerMutation($tenantId, $user, 'delete');

        $authId = auth()->id();
        if ($authId !== null && (string)$user->getKey() === (string)$authId) {
            throw new RuntimeException('You cannot remove your own access from this company.');
        }

        $membership = $this->findMembershipOrFail($tenantId, $user);

        $this->auditLogService->recordTenantScoped(
            'tenant_membership.deleted',
            $membership,
            array_merge(
                $user->only(['name', 'email', 'department', 'avatar_url', 'last_active_at']),
                [
                    'company_id' => $tenantId,
                    'tenant_id' => $tenantId,
                    'membership_id' => (string) $membership->getKey(),
                    'status' => (string) $membership->status,
                    'primary_org_unit_id' => $user->primary_org_unit_id ? (string) $user->primary_org_unit_id : null,
                    'org_unit_ids' => $user->orgUnits()->pluck('org_units.id')->map(fn($id) => (string) $id)->values()->all(),
                    'role_ids' => $membership->roles()->pluck('roles.id')->map(fn($id) => (string) $id)->values()->all(),
                ]
            ),
            [],
            ['request_id' => request()->header('X-Request-ID'), 'tenant_id' => $tenantId]
        );

        DB::transaction(function () use ($membership, $user): void {
            $this->userCompanyAssignmentService->clearUserCompanyContext($user);
            $membership->roles()->detach();
            $membership->delete();
        });
    }

    /**
     * Enforce the single-company-per-user rule for legacy user records.
     */
    private function ensureUserCanJoinCompany(string $companyId, User $user): void
    {
        $belongsToAnotherCompany = (
            is_string($user->company_id)
            && trim($user->company_id) !== ''
            && (string) $user->company_id !== $companyId
        ) || Company::query()
            ->where('owner_user_id', $user->getKey())
            ->whereKeyNot($companyId)
            ->exists()
            || TenantMembership::query()
                ->where('user_id', $user->getKey())
                ->where('tenant_id', '!=', $companyId)
                ->exists();

        if (!$belongsToAnotherCompany) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'This user is already assigned to another company.',
        ]);
    }

    private function findMembershipOrFail(string $tenantId, User $user): TenantMembership
    {
        return TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->getKey())
            ->with(['roles' => function ($query) use ($tenantId): void {
                $query
                    ->where('roles.status', 'active')
                    ->where(function ($roleQuery) use ($tenantId): void {
                        $roleQuery
                            ->where('roles.tenant_id', $tenantId)
                            ->orWhereNull('roles.tenant_id');
                    });
            }])
            ->firstOrFail();
    }

    private function findTenantUserOrFail(string $tenantId, User $user): User
    {
        return User::query()
            ->whereKey($user->getKey())
            ->with([
                'tenantMemberships' => function ($membershipQuery) use ($tenantId): void {
                    $membershipQuery
                        ->where('tenant_id', $tenantId)
                        ->with(['roles' => function ($query) use ($tenantId): void {
                            $query
                                ->where('roles.status', 'active')
                                ->where(function ($roleQuery) use ($tenantId): void {
                                    $roleQuery
                                        ->where('roles.tenant_id', $tenantId)
                                        ->orWhereNull('roles.tenant_id');
                                });
                        }]);
                },
                'company:id,name,slug',
                'primaryOrgUnit:id,company_id,parent_org_unit_id,type,name,status,is_root',
                'orgUnits' => function ($orgUnitQuery) use ($tenantId): void {
                    $orgUnitQuery
                        ->where('org_units.company_id', $tenantId)
                        ->select(['org_units.id', 'org_units.company_id', 'org_units.parent_org_unit_id', 'org_units.type', 'org_units.name', 'org_units.status', 'org_units.is_root']);
                },
            ])
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotUser(User $user, ?TenantMembership $membership): array
    {
        $loadedUser = $user->relationLoaded('orgUnits') && $user->relationLoaded('primaryOrgUnit')
            ? $user
            : $this->findTenantUserOrFail(
                (string) ($membership?->tenant_id ?? $user->company_id ?? ''),
                $user
            );

        return array_merge(
            $loadedUser->only(['name', 'email', 'department', 'avatar_url', 'last_active_at']),
            [
                'company_id' => $loadedUser->company_id ? (string) $loadedUser->company_id : null,
                'tenant_id' => $loadedUser->company_id ? (string) $loadedUser->company_id : null,
                'status' => (string) ($membership?->status ?? data_get($loadedUser, 'tenantMemberships.0.status', 'active')),
                'primary_org_unit_id' => $loadedUser->primary_org_unit_id ? (string) $loadedUser->primary_org_unit_id : null,
                'org_unit_ids' => $loadedUser->orgUnits->pluck('id')->map(fn($id) => (string) $id)->values()->all(),
                'role_ids' => ($membership?->relationLoaded('roles') ? $membership->roles : data_get($loadedUser, 'tenantMemberships.0.roles', collect()))
                    ->pluck('id')
                    ->map(fn($id) => (string) $id)
                    ->values()
                    ->all(),
            ]
        );
    }

    private function guardOwnerMutation(string $tenantId, User $user, string $action): void
    {
        $tenant = Tenant::query()
            ->select(['id', 'owner_user_id'])
            ->findOrFail($tenantId);

        $isOwner = (string) $tenant->owner_user_id === (string) $user->getKey();
        if (!$isOwner) {
            return;
        }

        $actorId = auth()->id();
        $isSelf = $actorId !== null && (string) $actorId === (string) $user->getKey();

        if ($action === 'delete') {
            throw new RuntimeException('The company owner cannot be removed from the company.');
        }

        if (!$isSelf) {
            throw new RuntimeException('Only the company owner can edit the owner account.');
        }
    }

    private function guardOwnerDeactivation(string $tenantId, User $user, mixed $nextStatus): void
    {
        $normalizedStatus = is_string($nextStatus) ? strtolower(trim($nextStatus)) : null;
        if ($normalizedStatus !== 'inactive') {
            return;
        }

        $tenant = Tenant::query()
            ->select(['id', 'owner_user_id'])
            ->findOrFail($tenantId);

        if ((string) $tenant->owner_user_id === (string) $user->getKey()) {
            throw new RuntimeException('The company owner cannot be deactivated.');
        }
    }

    private function userHasAnyActiveAccess(User $user): bool
    {
        if (Tenant::query()->where('owner_user_id', $user->getKey())->exists()) {
            return true;
        }

        return TenantMembership::query()
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->exists();
    }

    private function revokeTokensIfMembershipBecameInactive(User $user, string $previousStatus, string $currentStatus): void
    {
        if ($previousStatus === 'inactive' || $currentStatus !== 'inactive') {
            return;
        }

        if (!$this->userHasAnyActiveAccess($user)) {
            $user->tokens()->delete();
        }
    }
}
