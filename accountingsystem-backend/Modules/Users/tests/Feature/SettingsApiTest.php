<?php

namespace Modules\Users\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Users\Models\Company;
use Modules\Users\Models\Role;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_user_settings_can_be_read_and_updated(): void
    {
        $user = User::factory()->create([
            'name' => 'Alice Admin',
            'email' => 'alice@example.com',
            'phone' => '+63 917 123 4567',
            'currency_code' => 'PHP',
        ]);

        $company = Company::create([
            'name' => 'Aiur ERP',
            'slug' => 'aiur-erp',
            'status' => 'active',
            'timezone' => 'Asia/Manila',
            'owner_user_id' => $user->id,
        ]);

        $user->forceFill(['company_id' => $company->getKey()])->save();

        Sanctum::actingAs($user, [], 'sanctum');

        $this->getJson('/api/v1/settings/user')
            ->assertOk()
            ->assertJsonPath('data.name', 'Alice Admin')
            ->assertJsonPath('data.email', 'alice@example.com')
            ->assertJsonPath('data.phone', '+63 917 123 4567')
            ->assertJsonPath('data.currency_code', 'PHP')
            ->assertJsonPath('data.company.id', (string) $company->getKey());

        $this->putJson('/api/v1/settings/user', [
            'name' => 'Alice Updated',
            'email' => 'alice.updated@example.com',
            'phone' => '+63 917 765 4321',
            'currency_code' => 'USD',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Alice Updated')
            ->assertJsonPath('data.email', 'alice.updated@example.com')
            ->assertJsonPath('data.phone', '+63 917 765 4321')
            ->assertJsonPath('data.currency_code', 'USD');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Alice Updated',
            'email' => 'alice.updated@example.com',
            'phone' => '+63 917 765 4321',
            'currency_code' => 'USD',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'tenant_id' => (string) $company->getKey(),
            'action' => 'user_settings.updated',
        ]);
    }

    public function test_current_company_settings_can_be_read(): void
    {
        $owner = User::factory()->create();

        $company = Company::create([
            'name' => 'Northwind Logistics',
            'display_name' => 'Northwind',
            'slug' => 'northwind-logistics',
            'email' => 'hello@northwind.test',
            'phone' => '+1 555 1000',
            'address' => '12 Harbor Road, Seattle',
            'invoice_header_title' => 'Sales Invoice',
            'invoice_footer_note' => 'Thank you for your business.',
            'invoice_payment_note' => 'Please settle within 30 days.',
            'invoice_primary_color' => '#0f766e',
            'document_show_company_logo' => false,
            'document_logo_position' => 'center',
            'document_show_company_name' => true,
            'document_show_company_address' => false,
            'document_show_company_contact' => true,
            'document_show_document_title' => true,
            'document_accent_color' => '#64748b',
            'document_header_alignment' => 'center',
            'document_header_style' => 'formal',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $accountsReceivableAccount = $this->createCompanyAccount($company, '1100', 'Accounts Receivable', 'asset');
        $salesIncomeAccount = $this->createCompanyAccount($company, '4100', 'Sales Income', 'revenue');
        $inventoryAssetAccount = $this->createCompanyAccount($company, '1400', 'Inventory Asset', 'asset');
        $cogsAccount = $this->createCompanyAccount($company, '5100', 'Cost of Goods Sold', 'expense');
        $salesTaxPayableAccount = $this->createCompanyAccount($company, '2311', 'Sales Tax Payable', 'liability');
        $company->forceFill([
            'default_accounts_receivable_account_id' => $accountsReceivableAccount->getKey(),
            'default_sales_income_account_id' => $salesIncomeAccount->getKey(),
            'default_inventory_asset_account_id' => $inventoryAssetAccount->getKey(),
            'default_cogs_account_id' => $cogsAccount->getKey(),
            'default_sales_tax_payable_account_id' => $salesTaxPayableAccount->getKey(),
        ])->save();

        $owner->forceFill(['company_id' => $company->getKey()])->save();

        Sanctum::actingAs($owner, [], 'sanctum');

        $this->getJson('/api/v1/settings/company')
            ->assertOk()
            ->assertJsonPath('data.id', (string) $company->getKey())
            ->assertJsonPath('data.name', 'Northwind Logistics')
            ->assertJsonPath('data.display_name', 'Northwind')
            ->assertJsonPath('data.email', 'hello@northwind.test')
            ->assertJsonPath('data.phone', '+1 555 1000')
            ->assertJsonPath('data.address', '12 Harbor Road, Seattle')
            ->assertJsonPath('data.invoice_header_title', 'Sales Invoice')
            ->assertJsonPath('data.invoice_footer_note', 'Thank you for your business.')
            ->assertJsonPath('data.invoice_payment_note', 'Please settle within 30 days.')
            ->assertJsonPath('data.invoice_primary_color', '#0f766e')
            ->assertJsonPath('data.document_show_company_logo', false)
            ->assertJsonPath('data.document_logo_position', 'center')
            ->assertJsonPath('data.document_show_company_address', false)
            ->assertJsonPath('data.document_accent_color', '#64748b')
            ->assertJsonPath('data.document_header_alignment', 'center')
            ->assertJsonPath('data.document_header_style', 'formal')
            ->assertJsonPath('data.document_printing.logo_position', 'center')
            ->assertJsonPath('data.document_printing.primary_color', '#0f766e')
            ->assertJsonPath('data.document_printing.accent_color', '#64748b')
            ->assertJsonPath('data.default_accounts_receivable_account_id', (string) $accountsReceivableAccount->getKey())
            ->assertJsonPath('data.default_sales_income_account_id', (string) $salesIncomeAccount->getKey())
            ->assertJsonPath('data.default_inventory_asset_account_id', (string) $inventoryAssetAccount->getKey())
            ->assertJsonPath('data.default_cogs_account_id', (string) $cogsAccount->getKey())
            ->assertJsonPath('data.default_sales_tax_payable_account_id', (string) $salesTaxPayableAccount->getKey())
            ->assertJsonPath('data.default_accounts_receivable_account.code', '1100')
            ->assertJsonPath('data.default_sales_income_account.type', 'revenue')
            ->assertJsonPath('data.default_inventory_asset_account.name', 'Inventory Asset')
            ->assertJsonPath('data.default_cogs_account.type', 'expense')
            ->assertJsonPath('data.default_sales_tax_payable_account.code', '2311');
    }

    public function test_non_admin_member_cannot_update_company_settings(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $company = Company::create([
            'name' => 'Blue Orbit',
            'slug' => 'blue-orbit',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $member->forceFill(['company_id' => $company->getKey()])->save();

        $membership = TenantMembership::create([
            'tenant_id' => $company->getKey(),
            'user_id' => $member->getKey(),
            'status' => 'active',
            'invited_by_user_id' => $owner->getKey(),
            'joined_at' => now(),
        ]);

        $viewerRole = Role::create([
            'tenant_id' => $company->getKey(),
            'system_generated' => true,
            'key' => 'viewer',
            'name' => 'Viewer',
            'department' => 'Operations',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'global',
            'module_permissions' => [
                'users' => ['read'],
            ],
            'description' => 'Read-only access.',
        ]);
        $membership->roles()->attach($viewerRole->getKey());

        Sanctum::actingAs($member, [], 'sanctum');

        $this->putJson('/api/v1/settings/company', [
            'name' => 'Blue Orbit Updated',
            'display_name' => 'Blue Orbit HQ',
            'email' => 'ops@blueorbit.test',
            'phone' => '+1 555 3000',
            'address' => '42 Station Street',
            'timezone' => 'Asia/Manila',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');
    }

    public function test_member_with_settings_write_can_update_company_settings(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $company = Company::create([
            'name' => 'Delegated Settings Company',
            'slug' => 'delegated-settings-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $member->forceFill(['company_id' => $company->getKey()])->save();

        $membership = TenantMembership::create([
            'tenant_id' => $company->getKey(),
            'user_id' => $member->getKey(),
            'status' => 'active',
            'invited_by_user_id' => $owner->getKey(),
            'joined_at' => now(),
        ]);

        $settingsRole = Role::create([
            'tenant_id' => $company->getKey(),
            'system_generated' => false,
            'key' => 'settings-admin',
            'name' => 'Settings Admin',
            'department' => 'Administration',
            'status' => 'active',
            'access_level' => 'write',
            'permission_scope' => 'global',
            'module_permissions' => [
                'settings' => ['read', 'write'],
            ],
            'description' => 'Can manage company settings.',
        ]);
        $membership->roles()->attach($settingsRole->getKey());

        Sanctum::actingAs($member, [], 'sanctum');

        $this->putJson('/api/v1/settings/company', [
            'name' => 'Delegated Settings Company Updated',
            'display_name' => 'Delegated Settings',
            'email' => 'settings@example.test',
            'phone' => '+63 2 555 1200',
            'address' => 'Makati City',
            'timezone' => 'Asia/Manila',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Company settings updated')
            ->assertJsonPath('data.name', 'Delegated Settings Company Updated');

        $this->assertDatabaseHas('tenants', [
            'id' => $company->getKey(),
            'name' => 'Delegated Settings Company Updated',
            'timezone' => 'Asia/Manila',
        ]);
    }

    public function test_owner_can_update_company_settings_and_account_defaults_and_audit_is_company_scoped(): void
    {
        $owner = User::factory()->create();

        $company = Company::create([
            'name' => 'Atlas Trading',
            'slug' => 'atlas-trading',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->id,
        ]);

        $owner->forceFill(['company_id' => $company->getKey()])->save();

        $accountsReceivableAccount = $this->createCompanyAccount($company, '1310', 'Sales Receivable', 'asset');
        $salesIncomeAccount = $this->createCompanyAccount($company, '4110', 'Product Sales', 'revenue');
        $inventoryAssetAccount = $this->createCompanyAccount($company, '1410', 'Stock In Hand', 'asset');
        $cogsAccount = $this->createCompanyAccount($company, '5111', 'Cost of Goods Sold', 'expense');
        $salesTaxPayableAccount = $this->createCompanyAccount($company, '2311', 'Sales Tax Payable', 'liability');
        Sanctum::actingAs($owner, [], 'sanctum');

        $this->putJson('/api/v1/settings/company', [
            'name' => 'Atlas Trading Group',
            'display_name' => 'Atlas',
            'email' => 'finance@atlas.test',
            'phone' => '+1 555 2000',
            'address' => '88 Market Street',
            'invoice_header_title' => 'Tax Invoice',
            'invoice_footer_note' => 'Goods sold are not returnable once posted.',
            'invoice_payment_note' => 'Payment due on receipt unless otherwise agreed.',
            'invoice_primary_color' => '#1d4ed8',
            'document_show_company_logo' => true,
            'document_logo_position' => 'right',
            'document_show_company_name' => true,
            'document_show_company_address' => true,
            'document_show_company_contact' => false,
            'document_show_document_title' => true,
            'document_accent_color' => '#64748b',
            'document_header_alignment' => 'right',
            'document_header_style' => 'compact',
            'document_show_page_numbers' => false,
            'timezone' => 'Asia/Manila',
            'default_accounts_receivable_account_id' => (string) $accountsReceivableAccount->getKey(),
            'default_sales_income_account_id' => (string) $salesIncomeAccount->getKey(),
            'default_inventory_asset_account_id' => (string) $inventoryAssetAccount->getKey(),
            'default_cogs_account_id' => (string) $cogsAccount->getKey(),
            'default_sales_tax_payable_account_id' => (string) $salesTaxPayableAccount->getKey(),
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Atlas Trading Group')
            ->assertJsonPath('data.display_name', 'Atlas')
            ->assertJsonPath('data.email', 'finance@atlas.test')
            ->assertJsonPath('data.phone', '+1 555 2000')
            ->assertJsonPath('data.address', '88 Market Street')
            ->assertJsonPath('data.invoice_header_title', 'Tax Invoice')
            ->assertJsonPath('data.invoice_footer_note', 'Goods sold are not returnable once posted.')
            ->assertJsonPath('data.invoice_payment_note', 'Payment due on receipt unless otherwise agreed.')
            ->assertJsonPath('data.invoice_primary_color', '#1d4ed8')
            ->assertJsonPath('data.document_logo_position', 'right')
            ->assertJsonPath('data.document_show_company_contact', false)
            ->assertJsonPath('data.document_accent_color', '#64748b')
            ->assertJsonPath('data.document_header_alignment', 'right')
            ->assertJsonPath('data.document_header_style', 'compact')
            ->assertJsonPath('data.document_show_page_numbers', false)
            ->assertJsonPath('data.document_printing.show_page_numbers', false)
            ->assertJsonPath('data.timezone', 'Asia/Manila')
            ->assertJsonPath('data.default_accounts_receivable_account_id', (string) $accountsReceivableAccount->getKey())
            ->assertJsonPath('data.default_sales_income_account_id', (string) $salesIncomeAccount->getKey())
            ->assertJsonPath('data.default_inventory_asset_account_id', (string) $inventoryAssetAccount->getKey())
            ->assertJsonPath('data.default_cogs_account_id', (string) $cogsAccount->getKey())
            ->assertJsonPath('data.default_sales_tax_payable_account_id', (string) $salesTaxPayableAccount->getKey())
            ->assertJsonPath('data.default_sales_income_account.code', '4110')
            ->assertJsonPath('data.default_sales_tax_payable_account.code', '2311');

        $this->assertDatabaseHas('tenants', [
            'id' => (string) $company->getKey(),
            'name' => 'Atlas Trading Group',
            'display_name' => 'Atlas',
            'email' => 'finance@atlas.test',
            'phone' => '+1 555 2000',
            'address' => '88 Market Street',
            'invoice_header_title' => 'Tax Invoice',
            'invoice_footer_note' => 'Goods sold are not returnable once posted.',
            'invoice_payment_note' => 'Payment due on receipt unless otherwise agreed.',
            'invoice_primary_color' => '#1d4ed8',
            'document_show_company_logo' => true,
            'document_logo_position' => 'right',
            'document_show_company_name' => true,
            'document_show_company_address' => true,
            'document_show_company_contact' => false,
            'document_show_document_title' => true,
            'document_accent_color' => '#64748b',
            'document_header_alignment' => 'right',
            'document_header_style' => 'compact',
            'document_show_page_numbers' => false,
            'timezone' => 'Asia/Manila',
            'default_accounts_receivable_account_id' => (string) $accountsReceivableAccount->getKey(),
            'default_sales_income_account_id' => (string) $salesIncomeAccount->getKey(),
            'default_inventory_asset_account_id' => (string) $inventoryAssetAccount->getKey(),
            'default_cogs_account_id' => (string) $cogsAccount->getKey(),
            'default_sales_tax_payable_account_id' => (string) $salesTaxPayableAccount->getKey(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'tenant_id' => (string) $company->getKey(),
            'action' => 'company_settings.updated',
        ]);
    }

    public function test_company_settings_update_rejects_wrong_account_type(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner();
        $wrongTypeAccount = $this->createCompanyAccount($company, '4200', 'Other Income', 'revenue');

        $this->putJson('/api/v1/settings/company', [
            'name' => 'Type Check Company',
            'timezone' => 'UTC',
            'default_accounts_receivable_account_id' => (string) $wrongTypeAccount->getKey(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['default_accounts_receivable_account_id']);
    }

    public function test_company_settings_update_rejects_cross_company_account(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner();
        $otherOwner = User::factory()->create();
        $otherCompany = Company::create([
            'name' => 'Other Company',
            'slug' => 'other-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $otherOwner->getKey(),
        ]);
        $otherOwner->forceFill(['company_id' => $otherCompany->getKey()])->save();

        $crossCompanyAccount = $this->createCompanyAccount($otherCompany, '1500', 'Other Receivable', 'asset');

        $this->putJson('/api/v1/settings/company', [
            'name' => 'Cross Company Check',
            'timezone' => 'UTC',
            'default_accounts_receivable_account_id' => (string) $crossCompanyAccount->getKey(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['default_accounts_receivable_account_id']);
    }

    public function test_company_settings_update_rejects_inactive_account(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner();
        $inactiveAccount = $this->createCompanyAccount($company, '1600', 'Inactive Receivable', 'asset', [
            'status' => 'inactive',
        ]);

        $this->putJson('/api/v1/settings/company', [
            'name' => 'Inactive Check Company',
            'timezone' => 'UTC',
            'default_accounts_receivable_account_id' => (string) $inactiveAccount->getKey(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['default_accounts_receivable_account_id']);
    }

    public function test_company_settings_update_rejects_non_posting_account(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner();
        $nonPostingAccount = $this->createCompanyAccount($company, '1700', 'Parent Receivable', 'asset', [
            'is_posting' => false,
        ]);

        $this->putJson('/api/v1/settings/company', [
            'name' => 'Posting Check Company',
            'timezone' => 'UTC',
            'default_accounts_receivable_account_id' => (string) $nonPostingAccount->getKey(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['default_accounts_receivable_account_id']);
    }

    public function test_owner_can_upload_and_remove_company_logo(): void
    {
        Storage::fake('public');
        [$owner, $company] = $this->actingAsCompanyOwner();

        $this->postJson('/api/v1/settings/company/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 256, 128)->size(128),
        ])
            ->assertOk()
            ->assertJsonPath('data.logo_path', 'company-logos/' . (string) $company->getKey() . '/logo.png')
            ->assertJsonPath('data.logo_mime', 'image/png')
            ->assertJsonPath('data.logo_size', 128 * 1024)
            ->assertJsonPath('data.logo_width', 256)
            ->assertJsonPath('data.logo_height', 128);

        Storage::disk('public')->assertExists('company-logos/' . (string) $company->getKey() . '/logo.png');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'tenant_id' => (string) $company->getKey(),
            'action' => 'company_settings.logo_uploaded',
        ]);

        $this->deleteJson('/api/v1/settings/company/logo')
            ->assertOk()
            ->assertJsonPath('data.logo_path', null)
            ->assertJsonPath('data.logo_url', null);

        Storage::disk('public')->assertMissing('company-logos/' . (string) $company->getKey() . '/logo.png');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'tenant_id' => (string) $company->getKey(),
            'action' => 'company_settings.logo_deleted',
        ]);
    }

    public function test_current_user_can_upload_and_remove_profile_picture(): void
    {
        Storage::fake('public');
        [$owner, $company] = $this->actingAsCompanyOwner();

        $this->postJson('/api/v1/settings/user/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 128, 128)->size(96),
        ])
            ->assertOk()
            ->assertJsonPath('data.avatar_path', 'user-avatars/' . (string) $owner->getKey() . '/avatar.jpg')
            ->assertJsonPath('data.avatar_mime', 'image/jpeg')
            ->assertJsonPath('data.avatar_size', 96 * 1024)
            ->assertJsonPath('data.avatar_width', 128)
            ->assertJsonPath('data.avatar_height', 128);

        Storage::disk('public')->assertExists('user-avatars/' . (string) $owner->getKey() . '/avatar.jpg');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'tenant_id' => (string) $company->getKey(),
            'action' => 'user_settings.avatar_uploaded',
        ]);

        $this->deleteJson('/api/v1/settings/user/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatar_path', null)
            ->assertJsonPath('data.avatar_url', null);

        Storage::disk('public')->assertMissing('user-avatars/' . (string) $owner->getKey() . '/avatar.jpg');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'tenant_id' => (string) $company->getKey(),
            'action' => 'user_settings.avatar_deleted',
        ]);
    }

    private function actingAsCompanyOwner(): array
    {
        $owner = User::factory()->create();

        $company = Company::create([
            'name' => 'Settings Company',
            'slug' => 'settings-company-'.str()->lower((string) str()->uuid()),
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->getKey(),
        ]);

        $owner->forceFill(['company_id' => $company->getKey()])->save();

        Sanctum::actingAs($owner, [], 'sanctum');

        return [$owner, $company];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createCompanyAccount(Company $company, string $code, string $name, string $type, array $overrides = []): Account
    {
        return Account::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_posting' => true,
            'status' => 'active',
        ], $overrides));
    }
}
