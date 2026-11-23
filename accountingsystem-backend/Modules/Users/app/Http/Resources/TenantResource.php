<?php

namespace Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tenant API resource.
 *
 * @mixin \Modules\Users\Models\Tenant
 */
class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ownerMembership = null;

        if ($this->relationLoaded('memberships')) {
            $membership = $this->memberships->firstWhere('user_id', $this->owner_user_id);

            if ($membership !== null) {
                $ownerMembership = [
                    'id' => (string)$membership->id,
                    'user_id' => (string)$membership->user_id,
                    'status' => (string)$membership->status,
                    'joined_at' => $membership->joined_at,
                    'roles' => $membership->relationLoaded('roles')
                        ? $membership->roles->map(static fn($role) => [
                            'id' => (string)$role->id,
                            'company_id' => $role->tenant_id ? (string)$role->tenant_id : null,
                            'tenant_id' => $role->tenant_id ? (string)$role->tenant_id : null,
                            'key' => (string)$role->key,
                            'name' => (string)$role->name,
                            'status' => (string)$role->status,
                        ])->values()
                        : [],
                ];
            }
        }

        return [
            'id' => (string)$this->id,
            'company_id' => (string)$this->id,
            'name' => (string)$this->name,
            'slug' => (string)$this->slug,
            'status' => (string)$this->status,
            'timezone' => (string)$this->timezone,
            'owner_user_id' => $this->owner_user_id ? (string)$this->owner_user_id : null,
            'owner' => $this->whenLoaded('owner', function () {
                return $this->owner === null ? null : [
                    'id' => (string)$this->owner->id,
                    'name' => (string)$this->owner->name,
                    'email' => (string)$this->owner->email,
                ];
            }),
            'owner_membership' => $ownerMembership,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
