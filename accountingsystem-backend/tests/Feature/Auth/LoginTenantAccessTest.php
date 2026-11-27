<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class LoginTenantAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Login is a Sanctum browser-session flow. Include a first-party Referer
     * so Sanctum attaches the session middleware during feature tests.
     */
    private function postStatefulLogin(array $payload)
    {
        return $this
            ->withHeader('Referer', 'http://127.0.0.1:3001')
            ->postJson('/api/login', $payload);
    }

    public function test_new_user_without_any_company_access_can_still_log_in_for_onboarding(): void
    {
        $user = User::factory()->create();

        $response = $this->postStatefulLogin([
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login successful');
    }

    public function test_login_without_stateful_browser_session_does_not_report_success(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(419)
            ->assertJsonPath('message', 'Login session could not be started. Open the ERP through the configured app URL and make sure it is included in SANCTUM_STATEFUL_DOMAINS.');

        $this->assertGuest('web');
        $this->assertNull($user->fresh()->last_active_at);
    }

    public function test_user_with_an_active_membership_can_log_in(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Active Access Company',
            'slug' => 'active-access-company',
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

        $response = $this->postStatefulLogin([
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login successful');
    }

    public function test_user_with_only_inactive_memberships_cannot_log_in(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Inactive Access Company',
            'slug' => 'inactive-access-company',
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

        $response = $this->postStatefulLogin([
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Your company access is inactive. Please contact an administrator.');
    }

}
