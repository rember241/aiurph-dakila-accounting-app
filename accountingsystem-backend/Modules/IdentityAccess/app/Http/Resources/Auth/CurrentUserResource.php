<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];
        $company = $this->resource['company'];

        return [
            'id' => (string) $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $this->resource['avatar_url'],
            'company' => $company === null ? null : [
                'id' => (string) $company->getKey(),
                'name' => (string) $company->name,
                'slug' => (string) $company->slug,
                'timezone' => (string) $company->timezone,
                'logo_url' => $this->resource['company_logo_url'],
            ],
            'onboarding' => $this->resource['onboarding'],
        ];
    }
}
