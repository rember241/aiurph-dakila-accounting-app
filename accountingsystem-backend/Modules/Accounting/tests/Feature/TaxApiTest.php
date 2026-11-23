<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\Taxes\Tax;
use Modules\Users\Models\Company;
use Tests\TestCase;

class TaxApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_owner_can_update_tax_inside_current_company_context(): void
    {
        [$company, $headers] = $this->actingAsCompanyOwner();

        $tax = Tax::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'VAT',
            'rate' => 12,
            'status' => 'active',
            'is_used' => false,
        ]);

        $this->withHeaders($headers)
            ->patchJson("/api/v1/taxes/{$tax->getKey()}", [
                'name' => 'Output VAT',
                'rate' => 12,
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('data.message', 'Tax "Output VAT" updated successfully.');

        $this->assertDatabaseHas('taxes', [
            'id' => (string) $tax->getKey(),
            'name' => 'Output VAT',
            'status' => 'inactive',
        ]);
    }

    public function test_company_owner_can_delete_tax_inside_current_company_context(): void
    {
        [$company, $headers] = $this->actingAsCompanyOwner();

        $tax = Tax::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Sales Tax',
            'rate' => 10,
            'status' => 'active',
            'is_used' => false,
        ]);

        $this->withHeaders($headers)
            ->deleteJson("/api/v1/taxes/{$tax->getKey()}")
            ->assertOk()
            ->assertJsonPath('message', 'Tax deleted successfully');

        $this->assertDatabaseMissing('taxes', [
            'id' => (string) $tax->getKey(),
        ]);
    }

    /**
     * @return array{0: Company, 1: array<string, string>}
     */
    private function actingAsCompanyOwner(): array
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $company = Company::create([
            'name' => 'Tax Test Company '.$user->id,
            'slug' => 'tax-test-company-'.$user->id,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->getKey(),
        ]);

        $user->forceFill(['company_id' => $company->getKey()])->save();

        return [
            $company,
            [
                'X-Company-ID' => (string) $company->getKey(),
            ],
        ];
    }
}
