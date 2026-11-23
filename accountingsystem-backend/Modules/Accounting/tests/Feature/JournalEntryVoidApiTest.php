<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Customers\Models\Customer;
use Modules\Sales\Models\SalesInvoice;
use Modules\Users\Models\Tenant;
use Tests\TestCase;

class JournalEntryVoidApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_scoped_journal_entry_can_be_voided(): void
    {
        [$company, $headers] = $this->actingAsJournalWriter('journal-void-a');

        $fundSource = FundSource::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Operating Cash',
            'status' => 'active',
        ]);

        $entry = JournalEntry::query()->create([
            'company_id' => $company->getKey(),
            'org_unit_id' => null,
            'prefix' => 'JE',
            'entry_number' => 1,
            'entry_date' => '2046-02-10',
            'fund_source_id' => $fundSource->getKey(),
            'reference_number' => 'REF-VOID-1',
            'type' => 'general',
            'status' => 'posted',
            'notes' => 'Void me',
            'amount' => 250,
        ]);

        $response = $this->withHeaders($headers)
            ->patchJson("/api/v1/journal-entries/{$entry->getKey()}/void");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $entry->getKey())
            ->assertJsonPath('message', 'Journal Entry #JE-1 voided');

        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->getKey(),
            'company_id' => $company->getKey(),
            'status' => 'void',
        ]);
    }

    public function test_sales_invoice_generated_journal_entry_cannot_be_manually_voided(): void
    {
        [$company, $headers] = $this->actingAsJournalWriter('journal-void-sales-source');

        $fundSource = FundSource::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Operating Cash',
            'status' => 'active',
        ]);

        $entry = JournalEntry::query()->create([
            'company_id' => $company->getKey(),
            'org_unit_id' => null,
            'prefix' => 'JE',
            'entry_number' => 2,
            'entry_date' => '2046-02-10',
            'fund_source_id' => $fundSource->getKey(),
            'reference_number' => 'SI-VOID-BLOCK',
            'type' => 'general',
            'status' => 'posted',
            'notes' => 'Generated from invoice',
            'amount' => 500,
        ]);
        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'CUST-VOID-BLOCK',
            'name' => 'Void Block Customer',
            'status' => 'active',
        ]);

        SalesInvoice::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'invoice_number' => 'SI-VOID-BLOCK',
            'status' => 'issued',
            'invoice_date' => '2046-02-10',
            'journal_entry_id' => $entry->getKey(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'total_amount' => 500,
        ]);

        $response = $this->withHeaders($headers)
            ->patchJson("/api/v1/journal-entries/{$entry->getKey()}/void");

        $response->assertStatus(409)
            ->assertJsonPath('message', 'This journal entry was generated from a sales invoice. Void the sales invoice instead.');

        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->getKey(),
            'status' => 'posted',
        ]);
    }

    /**
     * @return array{0: Tenant, 1: array<string, string>}
     */
    private function actingAsJournalWriter(string $slug): array
    {
        $user = User::factory()->create([
            'company_id' => null,
        ]);

        $company = Tenant::create([
            'name' => 'Journal Void '.strtoupper($slug),
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->getKey(),
        ]);

        $user->forceFill(['company_id' => $company->getKey()])->save();

        Sanctum::actingAs($user, ['journal.write'], 'sanctum');

        return [$company, [
            'X-Company-ID' => (string) $company->getKey(),
        ]];
    }
}
