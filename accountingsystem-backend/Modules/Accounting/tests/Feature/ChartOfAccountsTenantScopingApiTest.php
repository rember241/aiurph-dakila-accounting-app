<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Database\Seeders\CoaAccountTypeSeeder;
use Modules\Accounting\Database\Seeders\CoaDetailTypeSeeder;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class ChartOfAccountsTenantScopingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_codes_are_generated_per_tenant(): void
    {
        $userA = $this->actingAsUserWithAbilities(['accounting.write']);
        $userB = User::factory()->create();
        [$accountTypeId, $detailTypeId] = $this->seedAccountLookups();
        $tenantA = $this->createTenant($userA, 'accounts-tenant-a');
        $tenantB = $this->createTenant($userB, 'accounts-tenant-b');

        $payload = [
            'name' => 'Cash',
            'type' => 'asset',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
            'status' => 'active',
            'is_posting' => true,
        ];

        $this->withHeaders($this->tenantHeaders($tenantA))
            ->postJson('/api/v1/accounts', $payload)
            ->assertCreated()
            ->assertJsonPath('data.company_id', (string) $tenantA->getKey())
            ->assertJsonPath('data.code', '1000');

        Sanctum::actingAs($userB, ['accounting.write'], 'sanctum');

        $this->withHeaders($this->tenantHeaders($tenantB))
            ->postJson('/api/v1/accounts', $payload)
            ->assertCreated()
            ->assertJsonPath('data.code', '1000');

        $this->assertDatabaseHas('accounts', [
            'company_id' => $tenantA->getKey(),
            'tenant_id' => $tenantA->getKey(),
            'code' => '1000',
            'name' => 'Cash',
        ]);
        $this->assertDatabaseHas('accounts', [
            'company_id' => $tenantB->getKey(),
            'tenant_id' => $tenantB->getKey(),
            'code' => '1000',
            'name' => 'Cash',
        ]);
    }

    public function test_account_reads_and_stats_are_scoped_to_the_active_tenant_context(): void
    {
        $user = $this->actingAsUserWithAbilities(['accounting.read']);
        $tenantA = $this->createTenant($user, 'accounts-list-tenant-a');
        $tenantB = $this->createTenant($user, 'accounts-list-tenant-b');

        $accountA = Account::factory()->create([
            'tenant_id' => $tenantA->getKey(),
            'code' => '1100',
            'name' => 'Tenant A Cash',
            'type' => 'asset',
            'status' => 'active',
            'is_posting' => true,
        ]);

        Account::factory()->create([
            'tenant_id' => $tenantB->getKey(),
            'code' => '1100',
            'name' => 'Tenant B Cash',
            'type' => 'asset',
            'status' => 'active',
            'is_posting' => true,
        ]);

        $this->withHeaders($this->tenantHeaders($tenantA))
            ->getJson('/api/v1/accounts')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', (string)$accountA->getKey())
            ->assertJsonPath('data.0.name', 'Tenant A Cash');

        $this->withHeaders($this->tenantHeaders($tenantA))
            ->getJson('/api/v1/accounts/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.active', 1);
    }


    public function test_account_category_filters_work_in_table_and_tree_views(): void
    {
        $user = $this->actingAsUserWithAbilities(['accounting.read']);
        $tenant = $this->createTenant($user, 'account-category');
        $otherTenant = $this->createTenant(User::factory()->create(), 'other-account-category');

        $this->seed([CoaAccountTypeSeeder::class, CoaDetailTypeSeeder::class]);
        $detailIds = DB::table('detail_types')->pluck('id', 'code');

        $parent = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'name' => 'Current Assets',
            'type' => 'asset',
            'is_posting' => false,
        ]);

        $expected = [];
        foreach ([
            ['Operating Account', 'asset', 'bank-accounts', 'bank'],
            ['CIB-Reserve Account', 'asset', 'cash-on-hand-and-in-banks', 'bank'],
            ['Corporate Visa', 'liability', 'credit-card', 'credit_card'],
            ['Receipts Awaiting Deposit', 'asset', 'undeposited-cash', 'undeposited_cash'],
            ['Undeposited Funds', 'asset', null, 'undeposited_cash'],
            ['Petty Cash', 'asset', 'cash-on-hand-and-in-banks', null],
            ['Credit Card', 'expense', null, null],
        ] as $index => [$name, $type, $detailCode, $category]) {
            $account = Account::factory()->create([
                'company_id' => $tenant->getKey(),
                'tenant_id' => $tenant->getKey(),
                'code' => (string) (9000 + $index),
                'name' => $name,
                'type' => $type,
                'parent_id' => $type === 'asset' ? $parent->getKey() : null,
                'detail_type_id' => $detailCode ? $detailIds[$detailCode] : null,
                'status' => 'active',
            ]);

            if ($category) {
                $expected[$category][] = (string) $account->getKey();
            }
        }

        Account::factory()->create([
            'company_id' => $otherTenant->getKey(),
            'tenant_id' => $otherTenant->getKey(),
            'name' => 'Undeposited Funds',
            'type' => 'asset',
        ]);

        foreach ($expected as $category => $ids) {
            foreach (['', '&nested=true'] as $view) {
                $response = $this->getJson("/api/v1/accounts?category={$category}&status=active{$view}")
                    ->assertOk()
                    ->assertJsonPath('total', count($ids));

                $this->assertEqualsCanonicalizing($ids, array_column($response->json('data'), 'id'));
            }
        }

        $this->getJson('/api/v1/accounts?category=unknown')->assertJsonValidationErrors('category');
    }

    public function test_account_type_sort_uses_canonical_accounting_order(): void
    {
        $user = $this->actingAsUserWithAbilities(['accounting.read']);
        $tenant = $this->createTenant($user, 'accounts-type-sort');

        foreach ([
            ['code' => '4100', 'name' => 'Service Revenue', 'type' => 'revenue'],
            ['code' => '5100', 'name' => 'Office Expense', 'type' => 'expense'],
            ['code' => '3100', 'name' => 'Owner Equity', 'type' => 'equity'],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '1100', 'name' => 'Cash on Hand', 'type' => 'asset'],
        ] as $account) {
            Account::factory()->create([
                'company_id' => $tenant->getKey(),
                'tenant_id' => $tenant->getKey(),
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'status' => 'active',
                'is_posting' => true,
            ]);
        }

        $this->withHeaders($this->tenantHeaders($tenant))
            ->getJson('/api/v1/accounts?sortField=type&sortDir=asc&perPage=10')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'asset')
            ->assertJsonPath('data.1.type', 'liability')
            ->assertJsonPath('data.2.type', 'equity')
            ->assertJsonPath('data.3.type', 'revenue')
            ->assertJsonPath('data.4.type', 'expense');

        $this->withHeaders($this->tenantHeaders($tenant))
            ->getJson('/api/v1/accounts?nested=true&status=active')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'asset')
            ->assertJsonPath('data.1.type', 'liability')
            ->assertJsonPath('data.2.type', 'equity')
            ->assertJsonPath('data.3.type', 'revenue')
            ->assertJsonPath('data.4.type', 'expense');
    }

    public function test_balance_sheet_classifies_flat_chart_accounts_from_lookup_metadata(): void
    {
        $user = $this->actingAsUserWithAbilities(['accounting.read']);
        $tenant = $this->createTenant($user, 'flat-chart-balance-sheet');

        $assetTypeId = DB::table('account_types')->insertGetId([
            'code' => 'assets',
            'name' => 'Assets',
            'core_type' => 'asset',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $liabilityTypeId = DB::table('account_types')->insertGetId([
            'code' => 'liabilities',
            'name' => 'Liabilities',
            'core_type' => 'liability',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $equityTypeId = DB::table('account_types')->insertGetId([
            'code' => 'equity',
            'name' => 'Equity',
            'core_type' => 'equity',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cashDetailTypeId = DB::table('detail_types')->insertGetId([
            'account_type_id' => $assetTypeId,
            'code' => 'cash-on-hand-and-in-banks',
            'name' => 'Cash on hand and in banks',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ppeDetailTypeId = DB::table('detail_types')->insertGetId([
            'account_type_id' => $assetTypeId,
            'code' => 'property-plant-and-equipment',
            'name' => 'Property, Plant, and Equipment',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accounts = collect([
            ['code' => '1100', 'name' => 'Operating Cash', 'type' => 'asset', 'account_type_id' => $assetTypeId, 'detail_type_id' => $cashDetailTypeId],
            ['code' => '1500', 'name' => 'Office Equipment', 'type' => 'asset', 'account_type_id' => $assetTypeId, 'detail_type_id' => $ppeDetailTypeId],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'account_type_id' => $liabilityTypeId, 'detail_type_id' => null],
            ['code' => '3100', 'name' => 'Owner Equity', 'type' => 'equity', 'account_type_id' => $equityTypeId, 'detail_type_id' => null],
        ])->map(fn (array $attributes) => Account::factory()->create([
            ...$attributes,
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'status' => 'active',
            'is_posting' => true,
        ]));

        $query = http_build_query([
            'date' => '2026-01-31',
            'accountIds' => $accounts->pluck('id')->all(),
        ]);
        $response = $this->withHeaders($this->tenantHeaders($tenant))
            ->getJson("/api/v1/balance-sheet?{$query}")
            ->assertOk();

        $accountsByCode = collect($response->json('accounts'))->keyBy('code');

        $this->assertSame('currentAsset', $accountsByCode->get('1100')['accountType']);
        $this->assertSame('nonCurrentAsset', $accountsByCode->get('1500')['accountType']);
        $this->assertSame('currentLiability', $accountsByCode->get('2100')['accountType']);
        $this->assertSame('equity', $accountsByCode->get('3100')['accountType']);
    }

    public function test_account_update_and_delete_cannot_target_another_account_in_a_different_tenant_context(): void
    {
        $user = $this->actingAsUserWithAbilities(['accounting.write']);
        [$accountTypeId, $detailTypeId] = $this->seedAccountLookups();
        $tenantA = $this->createTenant($user, 'accounts-update-tenant-a');
        $tenantB = $this->createTenant($user, 'accounts-update-tenant-b');

        $tenantBAccount = Account::factory()->create([
            'tenant_id' => $tenantB->getKey(),
            'code' => '2100',
            'name' => 'Tenant B Liability',
            'type' => 'liability',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
            'status' => 'active',
            'is_posting' => true,
            'is_system' => false,
        ]);

        $updatePayload = [
            'name' => 'Updated Name',
            'type' => 'liability',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
            'status' => 'active',
            'is_posting' => true,
        ];

        $this->withHeaders($this->tenantHeaders($tenantA))
            ->putJson("/api/v1/accounts/{$tenantBAccount->getKey()}", $updatePayload)
            ->assertNotFound();

        $this->withHeaders($this->tenantHeaders($tenantA))
            ->deleteJson("/api/v1/accounts/{$tenantBAccount->getKey()}")
            ->assertNotFound();

        $this->assertDatabaseHas('accounts', [
            'id' => $tenantBAccount->getKey(),
            'tenant_id' => $tenantB->getKey(),
            'name' => 'Tenant B Liability',
        ]);
    }

    public function test_cross_tenant_parent_selection_is_rejected(): void
    {
        $user = $this->actingAsUserWithAbilities(['accounting.write']);
        [$accountTypeId, $detailTypeId] = $this->seedAccountLookups();
        $tenantA = $this->createTenant($user, 'accounts-parent-tenant-a');
        $tenantB = $this->createTenant($user, 'accounts-parent-tenant-b');

        $tenantBParent = Account::factory()->create([
            'tenant_id' => $tenantB->getKey(),
            'code' => '1200',
            'name' => 'Tenant B Parent',
            'type' => 'asset',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => null,
            'status' => 'active',
            'is_posting' => false,
            'is_system' => false,
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenantA))
            ->postJson('/api/v1/accounts', [
                'name' => 'Tenant A Child',
                'type' => 'asset',
                'account_type_id' => $accountTypeId,
                'detail_type_id' => $detailTypeId,
                'parent_id' => (string)$tenantBParent->getKey(),
                'status' => 'active',
                'is_posting' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_unused_custom_account_can_be_deleted(): void
    {
        $user = $this->actingAsUserWithAbilities(['accounting.delete']);
        $tenant = $this->createTenant($user, 'accounts-delete-eligible');

        $account = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'code' => '1180',
            'name' => 'Temporary Asset',
            'type' => 'asset',
            'status' => 'active',
            'is_posting' => true,
            'is_system' => false,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant))
            ->deleteJson("/api/v1/accounts/{$account->getKey()}")
            ->assertOk()
            ->assertJsonPath('message', 'Account has been deleted');

        $this->assertDatabaseMissing('accounts', [
            'id' => $account->getKey(),
        ]);
    }

    public function test_account_with_journal_activity_returns_conflict_on_delete(): void
    {
        $user = $this->actingAsUserWithAbilities(['accounting.delete']);
        $tenant = $this->createTenant($user, 'accounts-delete-journaled');

        $account = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'code' => '1190',
            'name' => 'Journaled Asset',
            'type' => 'asset',
            'status' => 'active',
            'is_posting' => true,
            'is_system' => false,
        ]);

        $fundSource = FundSource::query()->create([
            'name' => 'Main Cash',
            'status' => 'active',
            'is_used' => false,
        ]);

        $journalEntry = JournalEntry::query()->create([
            'company_id' => $tenant->getKey(),
            'org_unit_id' => null,
            'prefix' => 'JE',
            'entry_number' => 1,
            'entry_date' => '2026-01-01',
            'type' => 'general',
            'fund_source_id' => $fundSource->getKey(),
            'reference_number' => 'REF-001',
            'notes' => 'Seed activity',
            'amount' => 100,
            'status' => 'posted',
        ]);

        JournalItem::query()->create([
            'journal_entry_id' => $journalEntry->getKey(),
            'account_id' => $account->getKey(),
            'tax_id' => null,
            'description' => 'Seed journal line',
            'type' => 'debit',
            'amount' => 100,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant))
            ->deleteJson("/api/v1/accounts/{$account->getKey()}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Account already has journal activity and cannot be deleted.');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->getKey(),
        ]);
    }

    public function test_company_default_account_returns_conflict_on_delete(): void
    {
        $user = $this->actingAsUserWithAbilities(['accounting.delete']);
        $tenant = $this->createTenant($user, 'accounts-delete-default');

        $account = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'code' => '1210',
            'name' => 'Default Receivable',
            'type' => 'asset',
            'status' => 'active',
            'is_posting' => true,
            'is_system' => false,
        ]);

        $tenant->forceFill([
            'default_accounts_receivable_account_id' => $account->getKey(),
        ])->save();

        $this->withHeaders($this->tenantHeaders($tenant))
            ->deleteJson("/api/v1/accounts/{$account->getKey()}")
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'Account is assigned in company default posting settings. Change the default account first before deleting it.'
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $account->getKey(),
        ]);
    }

    public function test_sales_and_journal_read_roles_can_use_account_lookup_without_accounting_management_access(): void
    {
        [$accountTypeId, $detailTypeId] = $this->seedAccountLookups();
        $owner = User::factory()->create();
        $journalUser = User::factory()->create();
        $salesUser = User::factory()->create();
        $tenant = Tenant::create([
            'name' => 'Account Lookup Tenant',
            'slug' => 'account-lookup-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->getKey(),
        ]);

        $account = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'code' => '1110',
            'name' => 'Cash Lookup',
            'type' => 'asset',
            'account_type_id' => $accountTypeId,
            'detail_type_id' => $detailTypeId,
            'status' => 'active',
            'is_posting' => true,
        ]);

        $journalMembership = TenantMembership::create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $journalUser->getKey(),
            'status' => 'active',
            'invited_by_user_id' => $owner->getKey(),
            'joined_at' => now(),
        ]);
        $salesMembership = TenantMembership::create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $salesUser->getKey(),
            'status' => 'active',
            'invited_by_user_id' => $owner->getKey(),
            'joined_at' => now(),
        ]);

        $journalRole = Role::create([
            'tenant_id' => $tenant->getKey(),
            'key' => 'journal-reader',
            'name' => 'Journal Reader',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'global',
            'module_permissions' => [
                'journal' => ['read'],
            ],
        ]);
        $salesRole = Role::create([
            'tenant_id' => $tenant->getKey(),
            'key' => 'sales-reader',
            'name' => 'Sales Reader',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'global',
            'module_permissions' => [
                'sales' => ['read'],
            ],
        ]);

        $journalMembership->roles()->syncWithoutDetaching([$journalRole->getKey()]);
        $salesMembership->roles()->syncWithoutDetaching([$salesRole->getKey()]);
        $journalUser->forceFill(['company_id' => $tenant->getKey()])->save();
        $salesUser->forceFill(['company_id' => $tenant->getKey()])->save();

        Sanctum::actingAs($journalUser, [], 'sanctum');

        $this->withHeaders($this->tenantHeaders($tenant))
            ->getJson('/api/v1/accounts?nested=true')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', (string) $account->getKey());

        $this->withHeaders($this->tenantHeaders($tenant))
            ->getJson('/api/v1/accounts/stats')
            ->assertForbidden()
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $this->withHeaders($this->tenantHeaders($tenant))
            ->postJson('/api/v1/accounts', [
                'name' => 'Should Not Create',
                'type' => 'asset',
                'account_type_id' => $accountTypeId,
                'detail_type_id' => $detailTypeId,
                'status' => 'active',
                'is_posting' => true,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Insufficient company permissions.');

        Sanctum::actingAs($salesUser, [], 'sanctum');

        $this->withHeaders($this->tenantHeaders($tenant))
            ->getJson('/api/v1/accounts?type=asset&status=active&is_posting=true')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', (string) $account->getKey());
    }

    public function test_tenant_scoped_membership_permissions_do_not_leak_between_tenants(): void
    {
        $viewerUser = User::factory()->create();
        $writerUser = User::factory()->create();
        $owner = User::factory()->create();
        [$accountTypeId, $detailTypeId] = $this->seedAccountLookups();

        $tenantA = Tenant::create([
            'name' => 'Viewer Tenant',
            'slug' => 'viewer-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->getKey(),
        ]);

        $tenantB = Tenant::create([
            'name' => 'Writer Tenant',
            'slug' => 'writer-tenant',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->getKey(),
        ]);

        $viewerMembership = TenantMembership::create([
            'tenant_id' => $tenantA->getKey(),
            'user_id' => $viewerUser->getKey(),
            'status' => 'active',
            'invited_by_user_id' => $owner->getKey(),
            'joined_at' => now(),
        ]);

        $writerMembership = TenantMembership::create([
            'tenant_id' => $tenantB->getKey(),
            'user_id' => $writerUser->getKey(),
            'status' => 'active',
            'invited_by_user_id' => $owner->getKey(),
            'joined_at' => now(),
        ]);

        $viewerRole = Role::create([
            'tenant_id' => $tenantA->getKey(),
            'key' => 'viewer',
            'name' => 'Viewer',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'global',
            'module_permissions' => [
                'accounting' => ['read'],
            ],
        ]);

        $writerRole = Role::create([
            'tenant_id' => $tenantB->getKey(),
            'key' => 'accountant',
            'name' => 'Accountant',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'global',
            'module_permissions' => [
                'accounting' => ['read', 'create', 'write'],
            ],
        ]);

        $viewerMembership->roles()->syncWithoutDetaching([$viewerRole->getKey()]);
        $writerMembership->roles()->syncWithoutDetaching([$writerRole->getKey()]);

        $viewerUser->forceFill(['company_id' => $tenantA->getKey()])->save();
        $writerUser->forceFill(['company_id' => $tenantB->getKey()])->save();

        Sanctum::actingAs($viewerUser, [], 'sanctum');

        $this->withHeaders($this->tenantHeaders($tenantA))
            ->getJson('/api/v1/accounts')
            ->assertOk();

        $this->withHeaders($this->tenantHeaders($tenantA))
            ->postJson('/api/v1/accounts', [
                'name' => 'Should Not Create',
                'type' => 'asset',
                'account_type_id' => $accountTypeId,
                'detail_type_id' => $detailTypeId,
                'status' => 'active',
                'is_posting' => true,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Insufficient company permissions.');

        Sanctum::actingAs($writerUser, [], 'sanctum');

        $this->withHeaders($this->tenantHeaders($tenantB))
            ->postJson('/api/v1/accounts', [
                'name' => 'Can Create',
                'type' => 'asset',
                'account_type_id' => $accountTypeId,
                'detail_type_id' => $detailTypeId,
                'status' => 'active',
                'is_posting' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Can Create');
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function seedAccountLookups(): array
    {
        $accountTypeId = DB::table('account_types')->insertGetId([
            'code' => 'current-assets',
            'name' => 'Current Assets',
            'core_type' => 'asset',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $detailTypeId = DB::table('detail_types')->insertGetId([
            'account_type_id' => $accountTypeId,
            'code' => 'cash-in-hand',
            'name' => 'Cash In Hand',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$accountTypeId, $detailTypeId];
    }

    private function actingAsUserWithAbilities(array $abilities): User
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, $abilities, 'sanctum');

        return $user;
    }

    private function createTenant(User $user, string $slug): Tenant
    {
        return Tenant::create([
            'name' => 'Chart Scope '.strtoupper($slug),
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->getKey(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function tenantHeaders(Tenant $tenant): array
    {
        return [
            'X-Tenant-ID' => (string)$tenant->getKey(),
        ];
    }
}
