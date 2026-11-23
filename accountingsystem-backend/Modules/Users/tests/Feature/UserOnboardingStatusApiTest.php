<?php

namespace Modules\Users\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class UserOnboardingStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_onboarding_status(): void
    {
        $response = $this->getJson('/api/v1/me/onboarding-status');

        $response->assertUnauthorized();
    }

    public function test_user_needs_onboarding_when_they_have_no_owned_tenant_and_no_active_membership(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson('/api/v1/me/onboarding-status');

        $response->assertOk()
            ->assertJsonPath('data.needs_onboarding', true)
            ->assertJsonPath('data.has_company', false)
            ->assertJsonPath('data.has_owned_company', false)
            ->assertJsonPath('data.has_active_company_membership', false)
            ->assertJsonPath('data.has_any_company_membership', false)
            ->assertJsonPath('data.can_create_company', true)
            ->assertJsonPath('data.has_owned_tenant', false)
            ->assertJsonPath('data.has_active_tenant_membership', false)
            ->assertJsonPath('data.has_any_tenant_membership', false)
            ->assertJsonPath('data.redirect_to', '/onboarding/company');
    }

    public function test_user_does_not_need_onboarding_when_they_own_a_tenant(): void
    {
        $user = User::factory()->create();

        Tenant::create([
            'name' => 'Owner Tenant',
            'slug' => 'owner-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson('/api/v1/me/onboarding-status');

        $response->assertOk()
            ->assertJsonPath('data.needs_onboarding', false)
            ->assertJsonPath('data.has_company', true)
            ->assertJsonPath('data.has_owned_company', true)
            ->assertJsonPath('data.has_active_company_membership', false)
            ->assertJsonPath('data.has_any_company_membership', false)
            ->assertJsonPath('data.can_create_company', false)
            ->assertJsonPath('data.has_owned_tenant', true)
            ->assertJsonPath('data.has_active_tenant_membership', false)
            ->assertJsonPath('data.has_any_tenant_membership', false)
            ->assertJsonPath('data.redirect_to', '/');
    }

    public function test_user_does_not_need_onboarding_when_they_have_an_active_membership(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Member Tenant',
            'slug' => 'member-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson('/api/v1/me/onboarding-status');

        $response->assertOk()
            ->assertJsonPath('data.needs_onboarding', false)
            ->assertJsonPath('data.has_company', true)
            ->assertJsonPath('data.has_owned_company', false)
            ->assertJsonPath('data.has_active_company_membership', true)
            ->assertJsonPath('data.has_any_company_membership', true)
            ->assertJsonPath('data.can_create_company', false)
            ->assertJsonPath('data.has_owned_tenant', false)
            ->assertJsonPath('data.has_active_tenant_membership', true)
            ->assertJsonPath('data.has_any_tenant_membership', true)
            ->assertJsonPath('data.redirect_to', '/');
    }

    public function test_inactive_membership_does_not_send_the_user_back_to_onboarding(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Inactive Membership Tenant',
            'slug' => 'inactive-membership-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'inactive',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson('/api/v1/me/onboarding-status');

        $response->assertOk()
            ->assertJsonPath('data.needs_onboarding', false)
            ->assertJsonPath('data.has_company', true)
            ->assertJsonPath('data.has_owned_company', false)
            ->assertJsonPath('data.has_active_company_membership', false)
            ->assertJsonPath('data.has_any_company_membership', true)
            ->assertJsonPath('data.can_create_company', false)
            ->assertJsonPath('data.has_owned_tenant', false)
            ->assertJsonPath('data.has_active_tenant_membership', false)
            ->assertJsonPath('data.has_any_tenant_membership', true)
            ->assertJsonPath('data.redirect_to', '/');
    }
}
