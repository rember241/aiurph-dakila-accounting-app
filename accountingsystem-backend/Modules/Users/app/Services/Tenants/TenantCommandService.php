<?php

namespace Modules\Users\Services\Tenants;

use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Users\DTO\Tenants\CreateTenantData;
use Modules\Users\DTO\Tenants\CreateTenantOptions;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantDatabase;
use Modules\Users\Models\TenantMembership;
use Modules\Users\Services\Roles\RoleCommandService;
use Throwable;

/**
 * Handles tenant creation and owner bootstrap operations.
 */
class TenantCommandService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly RoleCommandService $roleCommandService,
        private readonly TenantDatabaseProvisioningService $tenantDatabaseProvisioningService,
        private readonly \Modules\Users\Services\Users\UserCompanyAssignmentService $userCompanyAssignmentService
    )
    {
    }

    /**
     * Create a tenant, owner membership and baseline tenant-scoped roles.
     *
     * This onboarding bootstrap is limited to shared-database control-plane
     * writes. It does not provision a physical tenant database.
     *
     * @throws Throwable
     */
    public function create(CreateTenantData $data, ?CreateTenantOptions $options = null): Tenant
    {
        $options ??= CreateTenantOptions::defaults();

        try {
            return DB::transaction(function () use ($data, $options) {
                $owner = User::query()->findOrFail($data->ownerUserId);

                $tenant = $this->createTenantRecord($data, $owner);
                $this->userCompanyAssignmentService->assignUserToCompany($owner, (string) $tenant->getKey());
                $tenantDatabase = $this->createProvisioningRecordIfRequested($tenant, $options);
                $ownerMembership = $this->createOwnerMembership($tenant, $owner);
                $defaultRoles = $this->bootstrapDefaultTenantRoles($tenant);

                /** @var Role $adminRole */
                $adminRole = $defaultRoles->firstWhere('key', 'admin');
                $ownerMembership->roles()->syncWithoutDetaching([$adminRole->getKey()]);

                $fresh = $this->loadTenantForResponse($tenant, $owner);

                $this->recordTenantCreatedAudit($tenant, $tenantDatabase, $ownerMembership, $defaultRoles);
                $this->recordMembershipCreatedAudit($tenant, $ownerMembership, $adminRole);
                $this->recordOwnerRoleAssignmentAudit($tenant, $ownerMembership, $adminRole);

                return $fresh;
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateSlugException($e)) {
                throw ValidationException::withMessages([
                    'slug' => 'The slug has already been taken.',
                ]);
            }

            throw $e;
        }
    }

    /**
     * Create the persisted tenant record.
     */
    private function createTenantRecord(CreateTenantData $data, User $owner): Tenant
    {
        return Tenant::create([
            'name' => $data->name,
            'slug' => $this->resolveSlug($data),
            'status' => 'active',
            'timezone' => $data->timezone,
            'owner_user_id' => $owner->getKey(),
        ]);
    }

    /**
     * Create the active owner membership row for the new tenant.
     */
    private function createOwnerMembership(Tenant $tenant, User $owner): TenantMembership
    {
        return TenantMembership::create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $owner->getKey(),
            'status' => 'active',
            'invited_by_user_id' => $owner->getKey(),
            'joined_at' => now(),
        ]);
    }

    /**
     * Bootstrap the default tenant-scoped roles.
     *
     * The global role templates are preferred when present so tenant roles stay
     * aligned with the system defaults already defined elsewhere.
     */
    private function bootstrapDefaultTenantRoles(Tenant $tenant): Collection
    {
        $templates = Role::query()
            ->whereNull('tenant_id')
            ->whereIn('key', ['admin', 'accountant', 'viewer'])
            ->get()
            ->keyBy('key');

        $roles = [];
        foreach ($this->defaultRoleBlueprints() as $key => $defaults) {
            $template = $templates->get($key);
            $roles[] = $this->roleCommandService->create([
                'tenant_id' => (string)$tenant->getKey(),
                'system_generated' => true,
                'key' => $key,
                'name' => (string)($template?->name ?? $defaults['name']),
                'department' => $template?->department ?? $defaults['department'],
                'status' => (string)($template?->status ?? $defaults['status']),
                'access_level' => (string)($template?->access_level ?? $defaults['access_level']),
                'permission_scope' => (string)($template?->permission_scope ?? $defaults['permission_scope']),
                'module_permissions' => is_array($template?->module_permissions)
                    ? $template->module_permissions
                    : $defaults['module_permissions'],
                'description' => $template?->description ?? $defaults['description'],
            ]);
        }

        return new Collection($roles);
    }

    /**
     * Resolve a persisted tenant slug from the payload.
     */
    private function resolveSlug(CreateTenantData $data): string
    {
        if ($data->slug !== null && $data->slug !== '') {
            return $data->slug;
        }

        $base = Str::slug($data->name);
        if ($base === '') {
            $base = 'tenant';
        }

        $slug = $base;
        $suffix = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Load the tenant aggregate used by API responses.
     */
    private function loadTenantForResponse(Tenant $tenant, User $owner): Tenant
    {
        return Tenant::query()
            ->with([
                'owner:id,name,email',
                'memberships' => fn($query) => $query
                    ->where('user_id', $owner->getKey())
                    ->with(['roles:id,tenant_id,key,name,status']),
            ])
            ->findOrFail($tenant->getKey());
    }

    /**
     * Create the async-ready tenant database provisioning record when requested.
     */
    private function createProvisioningRecordIfRequested(Tenant $tenant, CreateTenantOptions $options): ?TenantDatabase
    {
        if (!$options->createProvisioningRecord) {
            return null;
        }

        return $this->tenantDatabaseProvisioningService->createProvisioningRecord($tenant);
    }

    /**
     * Record the tenant creation audit trail.
     */
    private function recordTenantCreatedAudit(
        Tenant $tenant,
        ?TenantDatabase $tenantDatabase,
        TenantMembership $membership,
        Collection $roles
    ): void
    {
        /** @var Role|null $ownerRole */
        $ownerRole = $roles->firstWhere('key', 'admin');

        $this->auditLogService->recordTenantScoped(
            'tenant.created',
            $tenant,
            null,
            [
                'tenant_id' => (string)$tenant->getKey(),
                'name' => (string)$tenant->name,
                'slug' => (string)$tenant->slug,
                'status' => (string)$tenant->status,
                'timezone' => (string)$tenant->timezone,
                'owner_user_id' => (string)$tenant->owner_user_id,
                'tenant_database_provisioning_requested' => $tenantDatabase instanceof TenantDatabase,
                'tenant_database_id' => $tenantDatabase instanceof TenantDatabase ? (string)$tenantDatabase->getKey() : null,
                'tenant_database_status' => $tenantDatabase instanceof TenantDatabase ? (string)$tenantDatabase->status : null,
                'owner_membership_id' => (string)$membership->getKey(),
                'owner_role_id' => $ownerRole instanceof Role ? (string)$ownerRole->getKey() : null,
                'owner_role_key' => 'admin',
                'default_role_ids' => $roles->pluck('id')->map(static fn($id) => (string)$id)->values()->all(),
                'default_role_keys' => $roles->pluck('key')->map(static fn($key) => (string)$key)->values()->all(),
            ],
            [
                'request_id' => request()->header('X-Request-ID'),
                'tenant_id' => $tenant->getKey(),
            ]
        );
    }

    /**
     * Record the owner membership bootstrap audit trail.
     */
    private function recordMembershipCreatedAudit(Tenant $tenant, TenantMembership $membership, Role $adminRole): void
    {
        $this->auditLogService->recordTenantScoped(
            'tenant_membership.created',
            $membership,
            null,
            [
                'tenant_id' => (string)$membership->tenant_id,
                'user_id' => (string)$membership->user_id,
                'status' => (string)$membership->status,
                'invited_by_user_id' => $membership->invited_by_user_id ? (string)$membership->invited_by_user_id : null,
                'joined_at' => $membership->joined_at?->toISOString(),
                'role_ids' => [(string)$adminRole->getKey()],
                'role_keys' => [(string)$adminRole->key],
            ],
            [
                'request_id' => request()->header('X-Request-ID'),
                'tenant_id' => $tenant->getKey(),
            ]
        );
    }

    /**
     * Record the initial owner-role assignment in the tenant membership context.
     */
    private function recordOwnerRoleAssignmentAudit(Tenant $tenant, TenantMembership $membership, Role $role): void
    {
        $this->auditLogService->recordTenantScoped(
            'tenant_membership.role_assigned',
            $membership,
            null,
            [
                'tenant_id' => (string)$tenant->getKey(),
                'membership_id' => (string)$membership->getKey(),
                'user_id' => (string)$membership->user_id,
                'role_id' => (string)$role->getKey(),
                'role_key' => (string)$role->key,
                'role_name' => (string)$role->name,
                'assignment_source' => 'tenant_owner_bootstrap',
            ],
            [
                'request_id' => request()->header('X-Request-ID'),
                'tenant_id' => $tenant->getKey(),
            ]
        );
    }

    /**
     * Default role definitions used when global templates are absent.
     *
     * @return array<string, array<string, mixed>>
     */
    private function defaultRoleBlueprints(): array
    {
        return [
            'admin' => [
                'name' => 'Admin',
                'department' => 'Administration',
                'status' => 'active',
                'access_level' => 'write',
                'permission_scope' => 'global',
                'module_permissions' => [
                    'accounting' => ['read', 'create', 'write', 'delete'],
                    'customers' => ['read', 'create', 'write', 'delete'],
                    'inventory' => ['read', 'create', 'write', 'delete'],
                    'sales' => ['read', 'create', 'write', 'delete'],
                    'period' => ['read', 'create', 'write', 'delete'],
                    'journal' => ['read', 'create', 'write', 'delete'],
                    'users' => ['read', 'create', 'write', 'delete'],
                    'roles' => ['read', 'create', 'write', 'delete'],
                    'org_units' => ['read', 'create', 'write', 'delete'],
                ],
                'description' => 'Full access to the tenant workspace.',
            ],
            'accountant' => [
                'name' => 'Accountant',
                'department' => 'Accounting',
                'status' => 'active',
                'access_level' => 'write',
                'permission_scope' => 'global',
                'module_permissions' => [
                    'accounting' => ['read', 'write'],
                    'customers' => ['read', 'write'],
                    'inventory' => ['read'],
                    'sales' => ['read'],
                    'period' => ['read'],
                    'journal' => ['read', 'write'],
                    'org_units' => ['read'],
                ],
                'description' => 'Can manage accounting records and post journal entries.',
            ],
            'viewer' => [
                'name' => 'Viewer',
                'department' => null,
                'status' => 'active',
                'access_level' => 'read',
                'permission_scope' => 'global',
                'module_permissions' => [
                    'accounting' => ['read'],
                    'customers' => ['read'],
                    'inventory' => ['read'],
                    'sales' => ['read'],
                    'period' => ['read'],
                    'journal' => ['read'],
                    'org_units' => ['read'],
                ],
                'description' => 'Read-only access for reporting and review.',
            ],
        ];
    }

    private function isDuplicateSlugException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'tenants_slug_unique')
            || str_contains($message, 'tenants.slug')
            || str_contains($message, 'unique constraint failed: tenants.slug');
    }
}
