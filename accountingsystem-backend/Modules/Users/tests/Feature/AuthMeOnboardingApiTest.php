<?php

namespace Modules\Users\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class AuthMeOnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_onboarding_redirect_for_user_who_still_needs_company_setup(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('id', (string)$user->id)
            ->assertJsonPath('onboarding.required', true)
            ->assertJsonPath('onboarding.has_company', false)
            ->assertJsonPath('onboarding.can_create_company', true)
            ->assertJsonPath('onboarding.redirect_to', '/onboarding/company');
    }

    public function test_me_returns_workspace_redirect_for_user_with_active_tenant_access(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Joined Company',
            'slug' => 'joined-company-auth-me',
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

        $response = $this->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('id', (string)$user->id)
            ->assertJsonPath('onboarding.required', false)
            ->assertJsonPath('onboarding.has_company', true)
            ->assertJsonPath('onboarding.can_create_company', false)
            ->assertJsonPath('onboarding.redirect_to', '/');
    }

    public function test_me_does_not_redirect_inactive_memberships_to_onboarding(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Inactive Member Company',
            'slug' => 'inactive-member-company-auth-me',
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

        $response = $this->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('id', (string)$user->id)
            ->assertJsonPath('onboarding.required', false)
            ->assertJsonPath('onboarding.has_company', true)
            ->assertJsonPath('onboarding.can_create_company', false)
            ->assertJsonPath('onboarding.redirect_to', '/');
    }
}
