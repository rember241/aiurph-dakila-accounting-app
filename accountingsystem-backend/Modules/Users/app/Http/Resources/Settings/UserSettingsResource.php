<?php

namespace Modules\Users\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Modules\Users\Models\Company;
use Modules\Users\Models\OrgUnit;

/**
 * Current user settings payload.
 *
 * @mixin \App\Models\User
 */
final class UserSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $company = $this->relationLoaded('company') ? $this->company : null;
        $primaryOrgUnit = $this->relationLoaded('primaryOrgUnit') ? $this->primaryOrgUnit : null;

        return [
            'id' => (string) $this->getKey(),
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'phone' => $this->phone === null ? null : (string) $this->phone,
            'currency_code' => (string) ($this->currency_code ?: 'PHP'),
            'avatar_path' => $this->avatar_path === null ? null : (string) $this->avatar_path,
            'avatar_url' => $this->avatarUrl(),
            'avatar_mime' => $this->avatar_mime === null ? null : (string) $this->avatar_mime,
            'avatar_size' => $this->avatar_size,
            'avatar_width' => $this->avatar_width,
            'avatar_height' => $this->avatar_height,
            'avatar_updated_at' => $this->avatar_updated_at?->toISOString(),
            'company' => $company instanceof Company ? [
                'id' => (string) $company->getKey(),
                'name' => (string) $company->name,
                'display_name' => $company->display_name === null ? null : (string) $company->display_name,
                'slug' => (string) $company->slug,
                'timezone' => (string) $company->timezone,
            ] : null,
            'primary_org_unit' => $primaryOrgUnit instanceof OrgUnit ? [
                'id' => (string) $primaryOrgUnit->getKey(),
                'name' => (string) $primaryOrgUnit->name,
                'type' => (string) $primaryOrgUnit->type,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function avatarUrl(): ?string
    {
        if ($this->avatar_path !== null) {
            $url = Storage::disk('public')->url((string) $this->avatar_path);
            $version = $this->avatar_updated_at?->getTimestamp();

            return $version === null ? $url : $url . '?v=' . $version;
        }

        return $this->avatar_url === null ? null : (string) $this->avatar_url;
    }
}
