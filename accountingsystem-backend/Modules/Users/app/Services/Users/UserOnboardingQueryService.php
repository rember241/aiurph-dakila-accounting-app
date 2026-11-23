<?php

namespace Modules\Users\Services\Users;

use App\Models\User;
use Modules\Users\Models\Company;

/**
 * Resolves onboarding state for the authenticated user.
 *
 * A user needs onboarding when they do not yet own or belong to a company.
 * The new domain rule is single-company-per-user.
 */
final class UserOnboardingQueryService
{
    private const ONBOARDING_REDIRECT = '/onboarding/company';
    private const DEFAULT_APP_REDIRECT = '/';

    /**
     * Build a frontend-friendly onboarding status payload.
     *
     * @return array{
     *     needs_onboarding: bool,
     *     has_company: bool,
     *     has_owned_company: bool,
     *     has_active_company_membership: bool,
     *     has_any_company_membership: bool,
     *     has_owned_tenant: bool,
     *     has_active_tenant_membership: bool,
     *     has_any_tenant_membership: bool,
     *     can_create_company: bool,
     *     redirect_to: non-empty-string
     * }
     */
    public function status(User $user): array
    {
        $hasOwnedCompany = $this->hasOwnedCompany($user);
        $hasAnyCompanyMembership = $this->hasAnyCompanyMembership($user);
        $hasActiveCompanyMembership = $this->hasActiveCompanyMembership($user);
        $hasCompany = $hasOwnedCompany || $hasAnyCompanyMembership;
        $needsOnboarding = !$hasCompany;
        $canCreateCompany = $needsOnboarding;

        return [
            'needs_onboarding' => $needsOnboarding,
            'has_company' => $hasCompany,
            'has_owned_company' => $hasOwnedCompany,
            'has_active_company_membership' => $hasActiveCompanyMembership,
            'has_any_company_membership' => $hasAnyCompanyMembership,
            'has_owned_tenant' => $hasOwnedCompany,
            'has_active_tenant_membership' => $hasActiveCompanyMembership,
            'has_any_tenant_membership' => $hasAnyCompanyMembership,
            'can_create_company' => $canCreateCompany,
            'redirect_to' => $this->resolveRedirectTarget($needsOnboarding),
        ];
    }

    /**
     * Build the normalized onboarding block used by the authenticated session response.
     *
     * @return array{
     *     required: bool,
     *     has_company: bool,
     *     can_create_company: bool,
     *     redirect_to: non-empty-string
     * }
     */
    public function authContext(User $user): array
    {
        $status = $this->status($user);

        return [
            'required' => $status['needs_onboarding'],
            'has_company' => $status['has_company'],
            'can_create_company' => $status['can_create_company'],
            'redirect_to' => $status['redirect_to'],
        ];
    }

    /**
     * Determine whether the user is still eligible for the initial onboarding flow.
     */
    public function needsOnboarding(User $user): bool
    {
        return $this->status($user)['needs_onboarding'];
    }

    public function canCreateAdditionalCompany(User $user): bool
    {
        return false;
    }

    /**
     * Check whether the user already owns a company.
     */
    private function hasOwnedCompany(User $user): bool
    {
        if (is_string($user->company_id) && trim($user->company_id) !== '') {
            return Company::query()
                ->whereKey($user->company_id)
                ->where('owner_user_id', $user->getKey())
                ->exists();
        }

        return Company::query()
            ->where('owner_user_id', $user->getKey())
            ->exists();
    }

    /**
     * Check whether the user already has an active company membership.
     */
    private function hasActiveCompanyMembership(User $user): bool
    {
        if (is_string($user->company_id) && trim($user->company_id) !== '') {
            return $user->companyMemberships()
                ->where('tenant_id', $user->company_id)
                ->where('status', 'active')
                ->exists();
        }

        return $user->companyMemberships()
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Check whether the user has any company membership, regardless of lifecycle state.
     */
    private function hasAnyCompanyMembership(User $user): bool
    {
        if (is_string($user->company_id) && trim($user->company_id) !== '') {
            return $user->companyMemberships()
                ->where('tenant_id', $user->company_id)
                ->exists();
        }

        return $user->companyMemberships()->exists();
    }

    /**
     * Resolve the frontend redirect target for the user's current onboarding state.
     */
    private function resolveRedirectTarget(bool $needsOnboarding): string
    {
        return $needsOnboarding
            ? self::ONBOARDING_REDIRECT
            : self::DEFAULT_APP_REDIRECT;
    }
}
