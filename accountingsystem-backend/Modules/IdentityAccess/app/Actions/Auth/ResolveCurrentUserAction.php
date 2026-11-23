<?php

declare(strict_types=1);

namespace Modules\IdentityAccess\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Users\Services\Companies\CompanyContextService;
use Modules\Users\Services\Users\UserOnboardingQueryService;

final class ResolveCurrentUserAction
{
    public function __construct(
        private readonly UserOnboardingQueryService $userOnboardingQueryService,
        private readonly CompanyContextService $companyContextService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(User $user): array
    {
        $company = $this->companyContextService->resolveCurrentCompany($user);

        return [
            'user' => $user,
            'company' => $company,
            'company_logo_url' => $this->versionedPublicUrl(
                $company?->logo_path,
                $company?->logo_updated_at?->getTimestamp(),
            ),
            'avatar_url' => $this->versionedPublicUrl(
                $user->avatar_path,
                $user->avatar_updated_at?->getTimestamp(),
            ) ?? $user->avatar_url,
            'onboarding' => $this->userOnboardingQueryService->authContext($user),
        ];
    }

    private function versionedPublicUrl(?string $path, ?int $version): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        return $version === null ? $url : "{$url}?v={$version}";
    }
}
