<?php

namespace Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Organization unit API resource.
 *
 * @mixin \Modules\Users\Models\OrgUnit
 */
class OrgUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $code = is_string($this->getAttribute('code')) && trim((string) $this->getAttribute('code')) !== ''
            ? trim((string) $this->getAttribute('code'))
            : $this->deriveCode();

        return [
            'id' => (string) $this->id,
            'company_id' => (string) $this->company_id,
            'tenant_id' => (string) $this->company_id,
            'parent_org_unit_id' => $this->parent_org_unit_id ? (string) $this->parent_org_unit_id : null,
            'parent' => $this->whenLoaded('parent', function (): ?array {
                if ($this->parent === null) {
                    return null;
                }

                return [
                    'id' => (string) $this->parent->getKey(),
                    'name' => (string) $this->parent->name,
                    'type' => (string) $this->parent->type,
                    'status' => (string) $this->parent->status,
                ];
            }),
            'type' => (string) $this->type,
            'name' => (string) $this->name,
            'code' => $code,
            'location' => $this->getAttribute('location'),
            'description' => $this->getAttribute('description'),
            'status' => (string) $this->status,
            'is_root' => (bool) $this->is_root,
            'children_count' => $this->when(isset($this->children_count), (int) $this->children_count),
            'members_count' => $this->when(
                isset($this->assigned_users_count) || isset($this->primary_users_count),
                max((int) ($this->assigned_users_count ?? 0), (int) ($this->primary_users_count ?? 0))
            ),
            'linked_roles_count' => $this->when(isset($this->linked_roles_count), (int) $this->linked_roles_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function deriveCode(): string
    {
        $fallback = Str::of((string) $this->name)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->limit(24, '');

        return $fallback->value() !== '' ? $fallback->value() : 'ORG-UNIT';
    }
}
