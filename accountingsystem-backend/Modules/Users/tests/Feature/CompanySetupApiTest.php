<?php

namespace Modules\Users\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Database\Seeders\CoaAccountTypeSeeder;
use Modules\Accounting\Database\Seeders\CoaDetailTypeSeeder;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Users\Models\OrgUnit;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class CompanySetupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_complete_company_setup(): void
    {
        $response = $this->postJson('/api/v1/me/onboarding/company-setup', []);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_complete_company_setup_from_the_renamed_route(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->postJson('/api/v1/me/onboarding/company-setup', [
            'company' => [
                'name' => 'Acme Setup Co',
                'timezone' => 'Asia/Manila',
            ],
            'fiscal_year' => [
                'code' => 'FY2026',
                'name' => 'Fiscal Year 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Company setup completed')
            ->assertJsonPath('data.company.name', 'Acme Setup Co')
            ->assertJsonPath('data.tenant.name', 'Acme Setup Co')
            ->assertJsonPath('data.organization.root_org_unit.name', 'Head Office')
            ->assertJsonPath('data.organization.root_org_unit.type', 'head_office')
            ->assertJsonPath('data.organization.root_org_unit.is_root', true)
            ->assertJsonPath('data.tenant.slug', 'acme-setup-co')
            ->assertJsonPath('data.tenant.owner.id', (string) $user->id)
            ->assertJsonPath('data.fiscal_year.code', 'FY2026')
            ->assertJsonPath('data.fiscal_year.is_current', true)
            ->assertJsonPath('data.accounts_setup.mode', 'empty')
            ->assertJsonPath('data.accounts_setup.created_count', 0);

        $tenant = Tenant::query()->where('slug', 'acme-setup-co')->firstOrFail();
        $response->assertJsonPath('data.fiscal_year.tenant_id', (string) $tenant->id);

        $this->assertSame($user->id, $tenant->owner_user_id);
        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('fiscal_years', [
            'tenant_id' => $tenant->id,
            'code' => 'FY2026',
            'name' => 'Fiscal Year 2026',
            'is_current' => true,
        ]);
        $fiscalYear = FiscalYear::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'FY2026')
            ->firstOrFail();
        $rootOrgUnit = OrgUnit::query()
            ->where('company_id', $tenant->id)
            ->where('is_root', true)
            ->firstOrFail();
        $user->refresh();

        $this->assertSame(
            12,
            AccountingPeriod::query()
                ->where('tenant_id', $tenant->id)
                ->where('fiscal_year_id', $fiscalYear->getKey())
                ->count()
        );
        $this->assertSame('Head Office', $rootOrgUnit->name);
        $this->assertSame('head_office', $rootOrgUnit->type);
        $this->assertSame((string) $tenant->id, (string) $user->company_id);
        $this->assertSame((string) $rootOrgUnit->getKey(), (string) $user->primary_org_unit_id);
        $this->assertDatabaseHas('user_org_unit_assignments', [
            'user_id' => $user->id,
            'company_id' => $tenant->id,
            'org_unit_id' => $rootOrgUnit->getKey(),
        ]);
    }

    public function test_company_setup_records_tenant_scoped_audit_trail_without_requiring_a_tenant_database_record(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->postJson('/api/v1/me/onboarding/company-setup', [
            'company' => [
                'name' => 'Audit Ready Co',
                'timezone' => 'Asia/Manila',
            ],
            'fiscal_year' => [
                'code' => 'FY2026',
                'name' => 'Fiscal Year 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
        ]);

        $response->assertCreated();

        $tenant = Tenant::query()->where('slug', 'audit-ready-co')->firstOrFail();
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail();
        $fiscalYear = FiscalYear::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('code', 'FY2026')
            ->firstOrFail();

        $this->assertDatabaseMissing('tenant_databases', [
            'tenant_id' => $tenant->getKey(),
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'tenant_database.provisioning_requested',
            'tenant_id' => $tenant->getKey(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant.created',
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant_membership.created',
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant_membership.role_assigned',
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'auditable_id' => $membership->getKey(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'fiscal_year.created',
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'auditable_id' => $fiscalYear->getKey(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org_unit.created',
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->assertSame(
            3,
            AuditLog::query()
                ->where('action', 'role.created')
                ->where('tenant_id', $tenant->getKey())
                ->count()
        );

        $tenantCreatedAudit = AuditLog::query()
            ->where('action', 'tenant.created')
            ->where('tenant_id', $tenant->getKey())
            ->firstOrFail();
        $membershipCreatedAudit = AuditLog::query()
            ->where('action', 'tenant_membership.created')
            ->where('tenant_id', $tenant->getKey())
            ->firstOrFail();
        $ownerRoleAssignedAudit = AuditLog::query()
            ->where('action', 'tenant_membership.role_assigned')
            ->where('tenant_id', $tenant->getKey())
            ->firstOrFail();
        $fiscalYearCreatedAudit = AuditLog::query()
            ->where('action', 'fiscal_year.created')
            ->where('tenant_id', $tenant->getKey())
            ->firstOrFail();

        $this->assertSame((string) $tenant->getKey(), $tenantCreatedAudit->after['tenant_id'] ?? null);
        $this->assertFalse($tenantCreatedAudit->after['tenant_database_provisioning_requested'] ?? true);
        $this->assertSame((string) $membership->getKey(), $tenantCreatedAudit->after['owner_membership_id'] ?? null);

        $this->assertSame((string) $tenant->getKey(), $membershipCreatedAudit->after['tenant_id'] ?? null);
        $this->assertSame((string) $user->getKey(), $membershipCreatedAudit->after['user_id'] ?? null);

        $this->assertSame((string) $tenant->getKey(), $ownerRoleAssignedAudit->after['tenant_id'] ?? null);
        $this->assertSame('tenant_owner_bootstrap', $ownerRoleAssignedAudit->after['assignment_source'] ?? null);

        $this->assertSame((string) $tenant->getKey(), $fiscalYearCreatedAudit->after['tenant_id'] ?? null);
        $this->assertSame(12, $fiscalYearCreatedAudit->after['period_count'] ?? null);

        $this->assertEqualsCanonicalizing(
            ['admin', 'accountant', 'viewer'],
            Role::query()
                ->where('tenant_id', $tenant->getKey())
                ->pluck('key')
                ->values()
                ->all()
        );
    }

    public function test_company_setup_can_skip_default_root_org_unit_creation(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->postJson('/api/v1/me/onboarding/company-setup', [
            'company' => [
                'name' => 'No Org Unit Co',
                'timezone' => 'Asia/Manila',
            ],
            'organization' => [
                'create_root_org_unit' => false,
            ],
            'fiscal_year' => [
                'code' => 'FY2026',
                'name' => 'Fiscal Year 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.organization.root_org_unit', null);

        $tenant = Tenant::query()->where('slug', 'no-org-unit-co')->firstOrFail();

        $this->assertDatabaseMissing('org_units', [
            'company_id' => $tenant->getKey(),
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'org_unit.created',
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function test_company_setup_allows_immediate_access_to_tenant_scoped_accounting_routes_without_relogin(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->postJson('/api/v1/me/onboarding/company-setup', [
            'company' => [
                'name' => 'Access Ready Co',
                'timezone' => 'Asia/Manila',
            ],
            'fiscal_year' => [
                'code' => 'FY2026',
                'name' => 'Fiscal Year 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
        ]);

        $response->assertCreated();

        $tenant = Tenant::query()->where('slug', 'access-ready-co')->firstOrFail();

        $this->getJson('/api/v1/accounting/period-management/fiscal-years/current')
            ->assertOk()
            ->assertJsonPath('code', 'FY2026');

        $this->withHeaders([
            'X-Company-ID' => (string) $tenant->getKey(),
        ])->getJson('/api/v1/accounts')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_company_setup_can_generate_starter_accounts(): void
    {
        $this->seed(CoaAccountTypeSeeder::class);
        $this->seed(CoaDetailTypeSeeder::class);

        $existingOwner = User::factory()->create();
        $existingCompany = Tenant::create([
            'name' => 'Existing Company',
            'slug' => 'existing-company',
            'status' => 'active',
            'timezone' => 'Asia/Manila',
            'owner_user_id' => $existingOwner->id,
        ]);
        $existingAccount = Account::create([
            'tenant_id' => $existingCompany->getKey(),
            'company_id' => $existingCompany->getKey(),
            'code' => '1110',
            'name' => 'Existing custom cash account',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_posting' => true,
            'is_system' => false,
            'status' => 'active',
        ]);
        $originalAccount = $existingAccount->fresh()->getAttributes();
        $originalCompany = $existingCompany->fresh()->getAttributes();

        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->postJson('/api/v1/me/onboarding/company-setup', [
            'company' => [
                'name' => 'Starter Accounts Co',
                'timezone' => 'Asia/Manila',
            ],
            'fiscal_year' => [
                'code' => 'FY2026',
                'name' => 'Fiscal Year 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
            'accounts_setup' => [
                'mode' => 'generated',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.accounts_setup.mode', 'generated')
            ->assertJsonPath('data.accounts_setup.created_count', 101);

        $this->assertSame($originalAccount, $existingAccount->fresh()->getAttributes());
        $this->assertSame($originalCompany, $existingCompany->fresh()->getAttributes());

        $tenant = Tenant::query()->where('slug', 'starter-accounts-co')->firstOrFail();
        $salesTaxPayableAccount = Account::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('code', '2311')
            ->firstOrFail();

        $this->assertSame(
            101,
            Account::query()
                ->where('tenant_id', $tenant->getKey())
                ->count()
        );

        $this->assertDatabaseHas('accounts', [
            'tenant_id' => $tenant->getKey(),
            'name' => 'Application of Funds (Assets)',
            'type' => 'asset',
            'status' => 'active',
            'is_posting' => false,
        ]);
        $this->assertDatabaseHas('accounts', [
            'tenant_id' => $tenant->getKey(),
            'name' => 'Administrative Expenses',
            'type' => 'expense',
            'status' => 'active',
            'is_posting' => true,
        ]);
        $this->assertDatabaseHas('accounts', [
            'tenant_id' => $tenant->getKey(),
            'code' => '1510',
            'name' => 'Input VAT',
            'type' => 'asset',
            'status' => 'active',
            'is_posting' => true,
        ]);
        $this->assertDatabaseHas('accounts', [
            'tenant_id' => $tenant->getKey(),
            'code' => '2311',
            'name' => 'Sales Tax Payable',
            'type' => 'liability',
            'status' => 'active',
            'is_posting' => true,
        ]);
        foreach ([
            'default_accounts_receivable_account_id' => '1310',
            'default_sales_income_account_id' => '4110',
            'default_inventory_asset_account_id' => '1410',
            'default_cogs_account_id' => '5111',
            'default_cash_receipt_account_id' => '1110',
            'default_check_receipt_account_id' => '1210',
            'default_bank_receipt_account_id' => '1210',
            'default_online_receipt_account_id' => '1230',
        ] as $column => $code) {
            $account = Account::query()->where('tenant_id', $tenant->getKey())->where('code', $code)->firstOrFail();
            $this->assertTrue((bool) $account->is_posting);
            $this->assertSame((string) $account->getKey(), (string) $tenant->{$column});
        }
        $this->assertSame((string) $salesTaxPayableAccount->getKey(), (string) $tenant->default_sales_tax_payable_account_id);
    }

    public function test_user_with_an_active_membership_cannot_complete_company_setup_again(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Joined Company',
            'slug' => 'joined-company',
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

        $response = $this->postJson('/api/v1/me/onboarding/company-setup', [
            'company' => [
                'name' => 'Second Setup Company',
                'timezone' => 'Asia/Manila',
            ],
            'fiscal_year' => [
                'code' => 'FY2027',
                'name' => 'Fiscal Year 2027',
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);

        $this->assertSame(1, Tenant::query()->count());
        $this->assertSame(0, FiscalYear::query()->count());
    }

    public function test_onboarded_user_cannot_create_an_additional_company(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $initialResponse = $this->postJson('/api/v1/me/onboarding/company-setup', [
            'company' => [
                'name' => 'Primary Workspace Co',
                'timezone' => 'Asia/Manila',
            ],
            'fiscal_year' => [
                'code' => 'FY2026',
                'name' => 'Fiscal Year 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
            'accounts_setup' => [
                'mode' => 'empty',
            ],
        ]);

        $initialResponse->assertCreated();

        $response = $this->postJson('/api/v1/tenants/company-setup', [
            'company' => [
                'name' => 'Expansion Workspace Co',
                'timezone' => 'Asia/Manila',
            ],
            'fiscal_year' => [
                'code' => 'FY2027',
                'name' => 'Fiscal Year 2027',
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
            ],
            'accounts_setup' => [
                'mode' => 'generated',
            ],
        ]);

        $response->assertStatus(422)
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('X-Replacement-Path', '/api/v1/me/onboarding/company-setup')
            ->assertJsonValidationErrors(['user']);

        $this->assertSame(1, Tenant::query()->count());
    }

    public function test_member_only_user_cannot_create_an_additional_company(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant = Tenant::create([
            'name' => 'Assigned Workspace',
            'slug' => 'assigned-workspace',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->postJson('/api/v1/tenants/company-setup', [
            'company' => [
                'name' => 'Unauthorized Expansion Co',
                'timezone' => 'Asia/Manila',
            ],
            'fiscal_year' => [
                'code' => 'FY2027',
                'name' => 'Fiscal Year 2027',
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
            ],
            'accounts_setup' => [
                'mode' => 'empty',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user']);

        $this->assertSame(1, Tenant::query()->count());
    }
}
