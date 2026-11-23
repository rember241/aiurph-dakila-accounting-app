<?php

namespace Modules\Users\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class TenantRequestContractApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'company.context'])->get('/api/v1/test/tenant-context', function (Request $request) {
            /** @var Tenant|null $tenant */
            $tenant = $request->attributes->get('tenant');

            return response()->json([
                'company_id' => $request->attributes->get('company_id'),
                'tenant_id' => $request->attributes->get('tenant_id'),
                'tenant' => $tenant instanceof Tenant
                    ? [
                        'id' => (string)$tenant->getKey(),
                        'name' => (string)$tenant->name,
                        'slug' => (string)$tenant->slug,
                    ]
                    : null,
            ]);
        });

        Route::middleware(['auth:sanctum', 'company.context', 'company.access'])->get('/api/v1/test/tenant-business-context', function (Request $request) {
            /** @var Tenant|null $tenant */
            $tenant = $request->attributes->get('tenant');

            return response()->json([
                'company_id' => $request->attributes->get('company_id'),
                'tenant_id' => $request->attributes->get('tenant_id'),
                'tenant' => $tenant instanceof Tenant
                    ? [
                        'id' => (string)$tenant->getKey(),
                        'name' => (string)$tenant->name,
                        'slug' => (string)$tenant->slug,
                    ]
                    : null,
            ]);
        });
    }

    public function test_tenant_scoped_route_resolves_the_authenticated_users_company_without_a_header(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'name' => 'Resolved Company',
            'slug' => 'resolved-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson('/api/v1/test/tenant-context');

        $response->assertOk()
            ->assertJsonPath('company_id', (string) $tenant->id)
            ->assertJsonPath('tenant_id', (string) $tenant->id)
            ->assertJsonPath('tenant.slug', 'resolved-company');
    }

    public function test_tenant_scoped_route_rejects_malformed_company_context_header(): void
    {
        $user = User::factory()->create();
        Tenant::create([
            'name' => 'Resolved Company',
            'slug' => 'resolved-company-header',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => 'not-a-tenant-id',
        ])->getJson('/api/v1/test/tenant-context');

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Company context header must be a valid UUID.');
    }

    public function test_tenant_scoped_route_rejects_company_switching_attempts(): void
    {
        $user = User::factory()->create();
        Tenant::create([
            'name' => 'Resolved Company',
            'slug' => 'resolved-company-switch',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string)fake()->uuid(),
        ])->getJson('/api/v1/test/tenant-context');

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Company switching is no longer supported for this account.');
    }

    public function test_tenant_scoped_route_attaches_resolved_tenant_context_to_request_attributes(): void
    {
        $user = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Resolved Tenant',
            'slug' => 'resolved-tenant-context',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson('/api/v1/test/tenant-context');

        $response->assertOk()
            ->assertJsonPath('company_id', (string)$tenant->getKey())
            ->assertJsonPath('tenant_id', (string)$tenant->getKey())
            ->assertJsonPath('tenant.id', (string)$tenant->getKey())
            ->assertJsonPath('tenant.name', 'Resolved Tenant')
            ->assertJsonPath('tenant.slug', 'resolved-tenant-context');
    }

    public function test_tenant_scoped_route_accepts_a_matching_x_company_id_compatibility_header(): void
    {
        $user = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Resolved Company Header',
            'slug' => 'resolved-company-header-match',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Company-ID' => (string) $tenant->getKey(),
        ])->getJson('/api/v1/test/tenant-context');

        $response->assertOk()
            ->assertJsonPath('company_id', (string) $tenant->getKey())
            ->assertJsonPath('tenant_id', (string) $tenant->getKey())
            ->assertJsonPath('tenant.slug', 'resolved-company-header-match');
    }

    public function test_tenant_header_compatibility_is_marked_as_deprecated(): void
    {
        $user = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Deprecated Header Company',
            'slug' => 'deprecated-header-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->getKey(),
        ])->getJson('/api/v1/test/tenant-context');

        $response->assertOk()
            ->assertHeader('X-Deprecated-Header', 'X-Tenant-ID')
            ->assertHeader('X-Canonical-Company-Header', 'X-Company-ID');
    }

    public function test_tenant_business_route_returns_not_found_for_inaccessible_tenant_context(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Hidden Tenant',
            'slug' => 'hidden-tenant-access',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $otherOwner->id,
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string)$tenant->getKey(),
        ])->getJson('/api/v1/test/tenant-business-context');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Company context not found.');
    }

    public function test_tenant_business_route_allows_active_member_access(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Member Tenant',
            'slug' => 'member-tenant-access',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'active',
            'invited_by_user_id' => $owner->getKey(),
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson('/api/v1/test/tenant-business-context');

        $response->assertOk()
            ->assertJsonPath('company_id', (string)$tenant->getKey())
            ->assertJsonPath('tenant_id', (string)$tenant->getKey())
            ->assertJsonPath('tenant.slug', 'member-tenant-access');
    }

    public function test_shared_onboarding_endpoint_does_not_require_x_tenant_id_header(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->getJson('/api/v1/me/onboarding-status');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'needs_onboarding',
                    'has_owned_tenant',
                    'has_active_tenant_membership',
                    'redirect_to',
                ],
            ]);
    }
}
