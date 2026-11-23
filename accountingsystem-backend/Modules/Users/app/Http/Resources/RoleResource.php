<?php

namespace Modules\Users\Http\Resources;

use App\Models\User as AuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Services\Companies\CompanyContextService;
use Modules\Users\Support\RolePermissionMatrix;

/**
 * Role API resource.
 *
 * Used for both list and detail responses.
 *
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assignedUsersCount = isset($this->users_count) ? (int) $this->users_count : 0;
        $assignedMembershipsCount = isset($this->memberships_count) ? (int) $this->memberships_count : 0;
        $tenant = $request->attributes->get('tenant');
        $actor = $request->user();
        $isAdminRole = strtolower(trim((string) $this->key)) === 'admin';
        $hasAssignedMembers = ($assignedUsersCount + $assignedMembershipsCount) > 0;
        $isTenantOwnerActor = $tenant instanceof Tenant
            && $actor instanceof AuthenticatedUser
            && (string) $tenant->owner_user_id === (string) $actor->getKey();
        $companyContextService = app(CompanyContextService::class);
        $actorCanEditRoles = $tenant instanceof Tenant
            && $actor instanceof AuthenticatedUser
            && $companyContextService->userHasAbility($actor, $tenant, 'roles.write');
        $actorCanDeleteRoles = $tenant instanceof Tenant
            && $actor instanceof AuthenticatedUser
            && $companyContextService->userHasAbility($actor, $tenant, 'roles.delete');

        return [
            'id' => (string) $this->id,
            'company_id' => $this->tenant_id ? (string) $this->tenant_id : null,
            'tenant_id' => $this->tenant_id ? (string) $this->tenant_id : null,
            'system_generated' => (bool) $this->system_generated,
            'role_type' => $this->system_generated ? 'system' : 'custom',
            'is_admin_role' => $isAdminRole,
            'can_edit' => $actorCanEditRoles && (! $isAdminRole || $isTenantOwnerActor),
            'can_delete' => $actorCanDeleteRoles && ! $isAdminRole && ! $hasAssignedMembers,
            'can_deactivate' => $actorCanEditRoles && ! $isAdminRole,
            'key' => (string) $this->key,
            'name' => (string) $this->name,
            'department' => $this->department,
            'org_unit_id' => $this->org_unit_id ? (string) $this->org_unit_id : null,
            'org_unit' => $this->whenLoaded('orgUnit', function (): ?array {
                if ($this->orgUnit === null) {
                    return null;
                }

                return [
                    'id' => (string) $this->orgUnit->getKey(),
                    'company_id' => (string) $this->orgUnit->company_id,
                    'parent_org_unit_id' => $this->orgUnit->parent_org_unit_id ? (string) $this->orgUnit->parent_org_unit_id : null,
                    'type' => (string) $this->orgUnit->type,
                    'name' => (string) $this->orgUnit->name,
                    'status' => (string) $this->orgUnit->status,
                    'is_root' => (bool) $this->orgUnit->is_root,
                ];
            }),
            'status' => (string) $this->status,
            'access_level' => (string) $this->access_level,
            'permission_scope' => (string) $this->permission_scope,
            'module_permissions' => RolePermissionMatrix::filterDisabledModules($this->module_permissions),
            'description' => $this->description,
            'members_count' => $this->when(
                isset($this->users_count) || isset($this->memberships_count),
                $assignedUsersCount + $assignedMembershipsCount
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
