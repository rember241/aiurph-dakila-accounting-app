<?php

namespace Modules\Users\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class CompanyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_company_endpoint_returns_the_authenticated_users_company(): void
    {
        $owner = User::factory()->create();

        $company = Tenant::create([
            'name' => 'Current Company',
            'slug' => 'current-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        Sanctum::actingAs($owner, [], 'sanctum');

        $response = $this->getJson('/api/v1/company');

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $company->id)
            ->assertJsonPath('data.company_id', (string) $company->id)
            ->assertJsonPath('data.name', 'Current Company');
    }

    public function test_companies_index_collapses_legacy_memberships_to_the_current_company(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $currentCompany = Tenant::create([
            'name' => 'First Company',
            'slug' => 'first-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $legacyExtraCompany = Tenant::create([
            'name' => 'Second Company',
            'slug' => 'second-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        TenantMembership::create([
            'tenant_id' => $currentCompany->id,
            'user_id' => $user->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        TenantMembership::create([
            'tenant_id' => $legacyExtraCompany->id,
            'user_id' => $user->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now()->addMinute(),
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson('/api/v1/companies');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $currentCompany->id)
            ->assertJsonPath('data.0.name', 'First Company');
    }
}
