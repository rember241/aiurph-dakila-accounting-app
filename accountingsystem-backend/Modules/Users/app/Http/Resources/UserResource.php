<?php

namespace Modules\Users\Http\Resources;

use App\Models\User;
use App\Models\User as AuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Modules\Users\Services\Companies\CompanyContextService;
use Modules\Users\Support\RolePermissionMatrix;

/**
 * User API resource.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TenantMembership|null $membership */
        $membership = $this->relationLoaded('tenantMemberships') ? $this->tenantMemberships->first() : null;
        $roles = $membership?->relationLoaded('roles') ? $membership->roles : collect();
        $hasWriteRole = $roles->contains(fn ($role) => (string) $role->access_level === 'write');
        $tenant = $request->attributes->get('tenant');
        $actor = $request->user();
        $companyId = is_string($this->company_id) && trim($this->company_id) !== ''
            ? (string) $this->company_id
            : ($membership?->tenant_id ? (string) $membership->tenant_id : null);

        $isOwner = $tenant instanceof Tenant && (string) $tenant->owner_user_id === (string) $this->getKey();
        $isCurrentUser = $actor instanceof AuthenticatedUser && (string) $actor->getKey() === (string) $this->getKey();
        $companyContextService = app(CompanyContextService::class);
        $actorCanEditUsers = $tenant instanceof Tenant
            && $actor instanceof AuthenticatedUser
            && $companyContextService->userHasAbility($actor, $tenant, 'users.write');
        $actorCanDeleteUsers = $tenant instanceof Tenant
            && $actor instanceof AuthenticatedUser
            && $companyContextService->userHasAbility($actor, $tenant, 'users.delete');
        $canEdit = $actorCanEditUsers && (! $isOwner || $isCurrentUser);
        $canDelete = $actorCanDeleteUsers && ! $isOwner && ! $isCurrentUser;

        $primaryOrgUnit = $this->relationLoaded('primaryOrgUnit') ? $this->primaryOrgUnit : null;
        $orgUnits = $this->relationLoaded('orgUnits') ? $this->orgUnits : collect();

        return [
            'id' => (string) $this->id,
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'company_id' => $companyId,
            'tenant_id' => $companyId,
            'is_owner' => $isOwner,
            'is_current_user' => $isCurrentUser,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'status' => (string) ($membership?->status ?? $this->status ?? 'active'),
            'department' => $this->department,
            'access_level' => $hasWriteRole ? 'write' : 'read',
            'avatar_url' => $this->avatarUrl(),
            'last_active_at' => $this->last_active_at,
            'primary_org_unit' => $primaryOrgUnit instanceof OrgUnit ? $this->serializeOrgUnit($primaryOrgUnit) : null,
            'org_units' => $orgUnits
                ->map(fn (OrgUnit $orgUnit) => $this->serializeOrgUnit($orgUnit))
                ->values(),
            'tenant_membership' => $membership ? [
                'id' => (string) $membership->getKey(),
                'company_id' => (string) $membership->tenant_id,
                'tenant_id' => (string) $membership->tenant_id,
                'status' => (string) $membership->status,
                'joined_at' => $membership->joined_at?->toISOString(),
            ] : null,
            'roles' => $roles->map(fn ($role) => [
                'id' => (string) $role->id,
                'key' => (string) $role->key,
                'name' => (string) $role->name,
                'status' => (string) $role->status,
                'access_level' => (string) ($role->access_level ?? 'read'),
                'module_permissions' => RolePermissionMatrix::filterDisabledModules($role->module_permissions),
            ])->values(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function avatarUrl(): ?string
    {
        if ($this->avatar_path !== null) {
            $url = Storage::disk('public')->url((string) $this->avatar_path);
            $version = $this->avatar_updated_at?->getTimestamp();

            return $version === null ? $url : $url.'?v='.$version;
        }

        return $this->avatar_url === null ? null : (string) $this->avatar_url;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrgUnit(OrgUnit $orgUnit): array
    {
        $code = is_string($orgUnit->getAttribute('code')) && trim((string) $orgUnit->getAttribute('code')) !== ''
            ? trim((string) $orgUnit->getAttribute('code'))
            : $this->deriveOrgUnitCode($orgUnit);

        return [
            'id' => (string) $orgUnit->getKey(),
            'company_id' => (string) $orgUnit->company_id,
            'tenant_id' => (string) $orgUnit->company_id,
            'parent_org_unit_id' => $orgUnit->parent_org_unit_id ? (string) $orgUnit->parent_org_unit_id : null,
            'type' => (string) $orgUnit->type,
            'name' => (string) $orgUnit->name,
            'code' => $code,
            'status' => (string) $orgUnit->status,
            'is_root' => (bool) $orgUnit->is_root,
        ];
    }

    private function deriveOrgUnitCode(OrgUnit $orgUnit): string
    {
        $fallback = Str::of((string) $orgUnit->name)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->limit(24, '');

        return $fallback->value() !== '' ? $fallback->value() : 'ORG-UNIT';
    }
}
