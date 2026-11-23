<?php

namespace Modules\Users\Services\Tenants;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Users\Services\Users\UserOnboardingQueryService;

/**
 * Applies the phase-one tenant bootstrap access rule.
 *
 * For the initial rollout, tenant creation is limited to users who still need
 * onboarding. This keeps the authorization model simple until explicit tenant
 * selection and switching flows are introduced.
 */
final readonly class InitialTenantEligibilityService
{
    public function __construct(
        private UserOnboardingQueryService $userOnboardingQueryService
    )
    {
    }

    /**
     * Ensure the user is still eligible to create their initial tenant.
     *
     * @throws ValidationException
     */
    public function ensureCanCreateInitialTenant(User $user): void
    {
        if (!$this->userOnboardingQueryService->needsOnboarding($user)) {
            throw ValidationException::withMessages([
                'user' => 'Initial company setup has already been completed for this account.',
            ]);
        }
    }
}
