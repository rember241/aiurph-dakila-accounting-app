<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Users\Models\Tenant;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_account(): void
    {
        [, $headers] = $this->actingAsAccountingOwner();
        [$accountTypeId, $detailTypeId] = $this->seedLookups('asset');

        $payload = [
            'name' => 'Cash on Hand',
            'type' => 'asset',
            'status' => 'active',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
            'is_posting' => false,
        ];

        $res = $this->withHeaders($headers)->postJson('/api/v1/accounts', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Cash on Hand')
            ->assertJsonPath('data.type', 'asset');

        $code = (string)$res->json('data.code');
        $this->assertSame('1000', $code);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);

        $this->assertDatabaseHas('accounts', [
            'name' => 'Cash on Hand',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
        ]);
    }

    public function test_generated_code_increments_within_core_type_range(): void
    {
        [, $headers] = $this->actingAsAccountingOwner();
        [$accountTypeId, $detailTypeId] = $this->seedLookups('asset');

        $payload = [
            'type' => 'asset',
            'status' => 'active',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
            'is_posting' => true,
        ];

        $first = $this->withHeaders($headers)->postJson('/api/v1/accounts', array_merge($payload, [
            'name' => 'Cash Bank Account',
        ]));

        $second = $this->withHeaders($headers)->postJson('/api/v1/accounts', array_merge($payload, [
            'name' => 'Cash Petty Account',
        ]));

        $first->assertStatus(201)
            ->assertJsonPath('data.code', '1000');

        $second->assertStatus(201)
            ->assertJsonPath('data.code', '1001');
    }

    public function test_code_is_prohibited_on_create(): void
    {
        [, $headers] = $this->actingAsAccountingOwner();
        [$accountTypeId, $detailTypeId] = $this->seedLookups('asset');

        $res = $this->withHeaders($headers)->postJson('/api/v1/accounts', [
            'code' => '1000',
            'name' => 'Invalid code payload',
            'type' => 'asset',
            'status' => 'active',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
            'is_posting' => true,
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_list_accounts_with_pagination(): void
    {
        [$tenant, $headers] = $this->actingAsAccountingOwner();

        Account::factory()->count(15)->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
        ]);

        $res = $this->withHeaders($headers)->getJson('/api/v1/accounts?page=1&pageSize=10');

        $res->assertOk()
            ->assertJsonStructure(['data', 'page', 'perPage', 'total']);
    }

    public function test_can_list_accounts_as_nested_tree(): void
    {
        [$tenant, $headers] = $this->actingAsAccountingOwner();

        $parent = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'type' => 'asset',
            'name' => 'Parent',
            'is_posting' => false,
            'parent_id' => null,
        ]);

        $child = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'type' => 'asset',
            'name' => 'Child',
            'is_posting' => true,
            'parent_id' => $parent->id,
        ]);

        $res = $this->withHeaders($headers)->getJson('/api/v1/accounts?nested=true');

        $res->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.id', $parent->id)
            ->assertJsonPath('data.0.children.0.id', $child->id);
    }

    public function test_create_assigns_parent_to_system_root_when_parent_id_missing(): void
    {
        [$tenant, $headers] = $this->actingAsAccountingOwner();
        [$accountTypeId, $detailTypeId] = $this->seedLookups('asset');

        $root = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'type' => 'asset',
            'is_system' => true,
            'is_posting' => false,
            'parent_id' => null,
        ]);

        $res = $this->withHeaders($headers)->postJson('/api/v1/accounts', [
            'name' => 'Auto Parent Account',
            'type' => 'asset',
            'status' => 'active',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
            'is_posting' => true,
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.parent_id', $root->id);

        $this->assertDatabaseHas('accounts', [
            'name' => 'Auto Parent Account',
            'parent_id' => $root->id,
        ]);
    }

    public function test_create_prefers_detail_type_system_parent_when_available(): void
    {
        [$tenant, $headers] = $this->actingAsAccountingOwner();
        [$accountTypeId, $detailTypeId] = $this->seedLookups('asset');

        $root = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'type' => 'asset',
            'is_system' => true,
            'is_posting' => false,
            'parent_id' => null,
            'account_type_id' => null,
            'detail_type_id' => null,
        ]);

        $accountTypeParent = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'type' => 'asset',
            'is_system' => true,
            'is_posting' => false,
            'parent_id' => $root->id,
            'account_type_id' => $accountTypeId,
            'detail_type_id' => null,
        ]);

        $detailTypeParent = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'type' => 'asset',
            'is_system' => true,
            'is_posting' => false,
            'parent_id' => $accountTypeParent->id,
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
        ]);

        $res = $this->withHeaders($headers)->postJson('/api/v1/accounts', [
            'name' => 'Auto Nested Under Detail Parent',
            'type' => 'asset',
            'status' => 'active',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
            'is_posting' => true,
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.parent_id', $detailTypeParent->id);
    }

    public function test_cannot_create_account_with_posting_parent(): void
    {
        [$tenant, $headers] = $this->actingAsAccountingOwner();
        [$accountTypeId, $detailTypeId] = $this->seedLookups('asset');
        $parent = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'type' => 'asset',
            'is_posting' => true,
        ]);

        $res = $this->withHeaders($headers)->postJson('/api/v1/accounts', [
            'name' => 'Nested Under Posting',
            'type' => 'asset',
            'status' => 'active',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
            'is_posting' => true,
            'parent_id' => $parent->id,
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_cannot_delete_parent_with_children(): void
    {
        [$tenant, $headers] = $this->actingAsAccountingOwner();

        $parent = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'is_posting' => false,
        ]);
        Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'parent_id' => $parent->id,
        ]);

        $res = $this->withHeaders($headers)->deleteJson("/api/v1/accounts/{$parent->id}");

        $res->assertStatus(409);
    }

    private function seedLookups(string $coreType): array
    {
        $now = now();

        $accountTypeId = DB::table('account_types')->insertGetId([
            'code' => strtoupper($coreType) . '-TYPE',
            'name' => ucfirst($coreType) . ' Type',
            'core_type' => $coreType,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $detailTypeId = DB::table('detail_types')->insertGetId([
            'account_type_id' => $accountTypeId,
            'code' => strtoupper($coreType) . '-DETAIL',
            'name' => ucfirst($coreType) . ' Detail',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$accountTypeId, $detailTypeId];
    }

    /**
     * @return array{0: Tenant, 1: array<string, string>}
     */
    private function actingAsAccountingOwner(): array
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $tenant = Tenant::create([
            'name' => 'Accounts Test '.$user->id,
            'slug' => 'accounts-test-'.$user->id,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->getKey(),
        ]);

        $user->forceFill(['company_id' => $tenant->getKey()])->save();

        return [
            $tenant,
            [
                'X-Tenant-ID' => (string) $tenant->getKey(),
            ],
        ];
    }
}
