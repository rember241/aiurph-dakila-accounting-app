<?php

namespace Modules\Users\Services\Roles;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Support\RolePermissionMatrix;
use RuntimeException;
use Throwable;

/**
 * RoleCommandService
 *
 * Handles role commands: create, update, activate/deactivate and delete.
 */
class RoleCommandService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly RoleMembershipStatusService $roleMembershipStatusService
    )
    {
    }

    /**
     * Normalize a role's module_permissions array.
     *
     * - Forces lowercase keys/verbs
     * - Removes empty verbs
     * - Removes duplicates
     *
     * @param array|null $modulePermissions
     * @return array<string, array<int, string>>
     */
    private function normalizeModulePermissions(?array $modulePermissions): array
    {
        if (empty($modulePermissions)) {
            return [];
        }

        $normalized = [];
        foreach ($modulePermissions as $module => $verbs) {
            $moduleKey = strtolower(trim((string)$module));
            if ($moduleKey === '' || !is_array($verbs)) {
                continue;
            }

            $cleanVerbs = [];
            foreach ($verbs as $verb) {
                $v = strtolower(trim((string)$verb));
                if ($v === '') {
                    continue;
                }
                $cleanVerbs[] = $v;
            }

            $cleanVerbs = array_values(array_unique($cleanVerbs));
            if (!empty($cleanVerbs)) {
                $normalized[$moduleKey] = $cleanVerbs;
            }
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * Create a role (step-by-step):
     * 1) Normalize permissions payload.
     * 2) Create role inside a DB transaction.
     * 3) Record audit log (role.created).
     */
    public function create(array $payload): Role
    {
        $payload['module_permissions'] = RolePermissionMatrix::sanitizeModulePermissionsForAccessLevel(
            $this->normalizeModulePermissions($payload['module_permissions'] ?? null),
            (string) ($payload['access_level'] ?? 'read')
        );
        $payload = $this->applyOrgUnitContext($payload);
        $payload = $this->applyCompanyScopeStorageCompatibility($payload);

        $role = DB::transaction(function () use ($payload) {
            return Role::create($payload);
        });

        $this->auditLogService->record(
            'role.created',
            $role,
            null,
            $role->only(['tenant_id', 'system_generated', 'key', 'name', 'department', 'org_unit_id', 'status', 'access_level', 'permission_scope', 'module_permissions', 'description']),
            [
                'request_id' => request()->header('X-Request-ID'),
                'company_id' => $role->tenant_id,
            ]
        );

        return $role;
    }

    /**
     * @throws Throwable
     */
    public function update(Role $role, array $payload): Role
    {
        $this->guardAdminRoleMutation($role, $payload['status'] ?? null, $payload['key'] ?? null);

        if (array_key_exists('module_permissions', $payload) || array_key_exists('access_level', $payload)) {
            $effectiveAccessLevel = (string) ($payload['access_level'] ?? $role->access_level ?? 'read');
            $baseModulePermissions = array_key_exists('module_permissions', $payload)
                ? $payload['module_permissions']
                : $role->module_permissions;

            $payload['module_permissions'] = RolePermissionMatrix::sanitizeModulePermissionsForAccessLevel(
                $this->normalizeModulePermissions(is_array($baseModulePermissions) ? $baseModulePermissions : null),
                $effectiveAccessLevel
            );
        }

        $payload = $this->applyOrgUnitContext($payload);
        $payload = $this->applyCompanyScopeStorageCompatibility($payload);

        return DB::transaction(function () use ($role, $payload) {
            $before = $role->only(['tenant_id', 'system_generated', 'key', 'name', 'department', 'org_unit_id', 'status', 'access_level', 'permission_scope', 'module_permissions', 'description']);
            $statusChanged = array_key_exists('status', $payload) && (string) $payload['status'] !== (string) $role->status;
            $role->update($payload);
            $fresh = $role->fresh();

            if ($statusChanged) {
                $this->roleMembershipStatusService->syncMembersForRole($fresh);
            }

            $this->auditLogService->record(
                'role.updated',
                $fresh,
                $before,
                $fresh->only(['tenant_id', 'system_generated', 'key', 'name', 'department', 'org_unit_id', 'status', 'access_level', 'permission_scope', 'module_permissions', 'description']),
                [
                    'request_id' => request()->header('X-Request-ID'),
                    'company_id' => $fresh->tenant_id,
                ]
            );

            return $fresh;
        });
    }

    /**
     * Activate/deactivate a role.
     *
     * @throws Throwable
     */
    public function transition(Role $role, string $status): Role
    {
        $this->guardAdminRoleMutation($role, $status);

        $status = strtolower(trim($status));
        if ($status === '' || $status === (string)$role->status) {
            return $role;
        }

        return DB::transaction(function () use ($role, $status) {
            $before = $role->only(['status']);
            $role->update(['status' => $status]);
            $fresh = $role->fresh();

            $this->roleMembershipStatusService->syncMembersForRole($fresh);

            $event = $status === 'active' ? 'role.activated' : 'role.deactivated';
            $this->auditLogService->record(
                $event,
                $fresh,
                $before,
                $fresh->only(['status']),
                [
                    'request_id' => request()->header('X-Request-ID'),
                    'company_id' => $fresh->tenant_id,
                ]
            );

            return $fresh;
        });
    }

    /**
     * Delete a role if it has no members.
     *
     * @throws Throwable
     */
    public function delete(Role $role): void
    {
        if (strtolower(trim((string) $role->key)) === 'admin') {
            throw new RuntimeException('Admin role cannot be deleted.');
        }

        $hasMembers = $role->users()->exists() || $role->memberships()->exists();
        if ($hasMembers) {
            throw new RuntimeException('Role has assigned users and cannot be deleted.');
        }

        $this->auditLogService->record(
            'role.deleted',
            $role,
            $role->only(['tenant_id', 'system_generated', 'key', 'name', 'department', 'org_unit_id', 'status', 'access_level', 'permission_scope', 'module_permissions', 'description']),
            [],
            [
                'request_id' => request()->header('X-Request-ID'),
                'company_id' => $role->tenant_id,
            ]
        );

        DB::transaction(static fn() => $role->delete());
    }

    private function guardAdminRoleMutation(Role $role, mixed $nextStatus = null, mixed $nextKey = null): void
    {
        if (strtolower(trim((string) $role->key)) !== 'admin') {
            return;
        }

        $tenantId = $role->tenant_id ? (string) $role->tenant_id : '';
        if ($tenantId === '') {
            return;
        }

        $tenant = Tenant::query()
            ->select(['id', 'owner_user_id'])
            ->findOrFail($tenantId);

        $actorId = auth()->id();
        $isTenantOwnerActor = $actorId !== null && (string) $tenant->owner_user_id === (string) $actorId;
        if (!$isTenantOwnerActor) {
            throw new RuntimeException('Only the company owner can edit the admin role.');
        }

        $normalizedStatus = is_string($nextStatus) ? strtolower(trim($nextStatus)) : null;
        if ($normalizedStatus === 'inactive') {
            throw new RuntimeException('Admin role cannot be deactivated.');
        }

        $normalizedKey = is_string($nextKey) ? strtolower(trim($nextKey)) : null;
        if ($normalizedKey !== null && $normalizedKey !== '' && $normalizedKey !== 'admin') {
            throw new RuntimeException('Admin role key cannot be changed.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applyOrgUnitContext(array $payload): array
    {
        if (!array_key_exists('org_unit_id', $payload)) {
            return $payload;
        }

        $orgUnitId = is_string($payload['org_unit_id'] ?? null)
            ? trim((string) $payload['org_unit_id'])
            : '';

        if ($orgUnitId === '') {
            $payload['org_unit_id'] = null;
            $payload['department'] = null;

            return $payload;
        }

        $orgUnit = OrgUnit::query()
            ->select(['id', 'company_id', 'name'])
            ->findOrFail($orgUnitId);

        $companyId = is_string($payload['company_id'] ?? null)
            ? trim((string) $payload['company_id'])
            : (is_string($payload['tenant_id'] ?? null) ? trim((string) $payload['tenant_id']) : '');
        if ($companyId !== '' && (string) $orgUnit->company_id !== $companyId) {
            throw new RuntimeException('The selected org unit does not belong to the current company.');
        }

        $payload['org_unit_id'] = (string) $orgUnit->getKey();
        $payload['department'] = (string) $orgUnit->name;

        return $payload;
    }

    /**
     * Persist role scope through the legacy tenant storage column while keeping
     * company_id as the canonical in-memory contract for new callers.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applyCompanyScopeStorageCompatibility(array $payload): array
    {
        $companyId = is_string($payload['company_id'] ?? null)
            ? trim((string) $payload['company_id'])
            : '';
        $tenantId = is_string($payload['tenant_id'] ?? null)
            ? trim((string) $payload['tenant_id'])
            : '';
        $resolvedCompanyId = $companyId !== '' ? $companyId : $tenantId;

        if ($resolvedCompanyId !== '') {
            $payload['tenant_id'] = $resolvedCompanyId;
        }

        unset($payload['company_id']);

        return $payload;
    }
}
