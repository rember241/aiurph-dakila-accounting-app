<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Models\JournalEntries\JournalNumber;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Models\Taxes\Tax;
use Modules\Users\Models\Tenant;
use Tests\TestCase;

class JournalEntriesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_journal_entry_with_balanced_items(): void
    {
        [$tenant, $headers] = $this->actingAsJournalOwner();
        $this->createOpenPeriod($tenant, '2026-02-26');

        $debitAccount = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'normal_balance' => 'debit',
            'type' => 'asset',
            'is_posting' => true,
            'status' => 'active',
        ]);

        $creditAccount = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'normal_balance' => 'credit',
            'type' => 'liability',
            'is_posting' => true,
            'status' => 'active',
        ]);

        $payload = [
            'journalNumberId' => JournalNumber::query()->create([
                'company_id' => $tenant->getKey(),
                'name' => 'General Journal',
                'prefix' => 'JE',
                'starting_number' => 1,
                'status' => 'active',
                'reset_interval' => 'monthly',
            ])->getKey(),
            'date' => '2026-02-26',
            'type' => 'general',
            'fundSourceId' => FundSource::query()->create([
                'company_id' => $tenant->getKey(),
                'name' => 'General Fund',
                'status' => 'active',
            ])->getKey(),
            'referenceNumber' => 'REF-1001',
            'note' => 'Created from API test',
            'status' => 'posted',
            'journalItems' => [
                [
                    'accountId' => $debitAccount->id,
                    'description' => 'Debit row',
                    'debit' => 1000,
                    'credit' => 0,
                ],
                [
                    'accountId' => $creditAccount->id,
                    'description' => 'Credit row',
                    'debit' => 0,
                    'credit' => 1000,
                ],
            ],
        ];

        $response = $this->withHeaders($headers)->postJson('/api/v1/journal-entries', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Journal Entry #JE-1 created');

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $tenant->getKey(),
            'entry_number' => 1,
            'entry_date' => '2026-02-26',
            'type' => 'general',
            'status' => 'posted',
            'notes' => 'Created from API test',
        ]);

        $this->assertDatabaseCount('journal_items', 2);
    }

    public function test_taxed_journal_entry_posts_input_vat_to_general_ledger(): void
    {
        [$tenant, $headers] = $this->actingAsJournalOwner();
        $this->createOpenPeriod($tenant, '2026-02-26');

        $journalNumber = JournalNumber::query()->create([
            'company_id' => $tenant->getKey(),
            'name' => 'General Journal',
            'prefix' => 'JE',
            'starting_number' => 1,
            'status' => 'active',
            'reset_interval' => 'monthly',
        ]);

        $fundSource = FundSource::query()->create([
            'company_id' => $tenant->getKey(),
            'name' => 'General Fund',
            'status' => 'active',
        ]);

        $expenseAccount = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'code' => '5209',
            'name' => 'Office Rent',
            'normal_balance' => 'debit',
            'type' => 'expense',
            'is_posting' => true,
            'status' => 'active',
        ]);

        $inputVatAccount = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'code' => '3004',
            'name' => 'Input Vat',
            'normal_balance' => 'debit',
            'type' => 'asset',
            'is_posting' => true,
            'status' => 'active',
        ]);

        $cashAccount = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'code' => '1110',
            'name' => 'Cash',
            'normal_balance' => 'debit',
            'type' => 'asset',
            'is_posting' => true,
            'status' => 'active',
        ]);

        $vat = Tax::query()->create([
            'company_id' => $tenant->getKey(),
            'name' => 'VAT',
            'rate' => 12,
            'status' => 'active',
        ]);

        $payload = [
            'journalNumberId' => $journalNumber->getKey(),
            'date' => '2026-02-26',
            'type' => 'general',
            'fundSourceId' => $fundSource->getKey(),
            'referenceNumber' => 'VAT-INPUT-001',
            'note' => 'Purchase with input VAT',
            'status' => 'posted',
            'journalItems' => [
                [
                    'accountId' => $expenseAccount->getKey(),
                    'taxId' => $vat->getKey(),
                    'description' => 'Rent exclusive of VAT',
                    'debit' => 100,
                    'credit' => 0,
                ],
                [
                    'accountId' => $cashAccount->getKey(),
                    'description' => 'Cash paid',
                    'debit' => 0,
                    'credit' => 112,
                ],
            ],
        ];

        $response = $this->withHeaders($headers)->postJson('/api/v1/journal-entries', $payload);

        $response->assertCreated();

        $entryId = $response->json('data.id');

        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $entryId,
            'account_id' => $expenseAccount->getKey(),
            'type' => 'debit',
            'amount' => 100,
        ]);
        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $entryId,
            'account_id' => $inputVatAccount->getKey(),
            'description' => 'Input VAT - VAT',
            'type' => 'debit',
            'amount' => 12,
        ]);
        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $entryId,
            'account_id' => $cashAccount->getKey(),
            'type' => 'credit',
            'amount' => 112,
        ]);

        $this->withHeaders($headers)
            ->getJson(sprintf(
                '/api/v1/general-ledger/accounts?startDate=2026-02-01&endDate=2026-02-28&accountIds[]=%s',
                $inputVatAccount->getKey()
            ))
            ->assertOk()
            ->assertJsonPath('0.id', (string) $inputVatAccount->getKey())
            ->assertJsonPath('0.debit', 12)
            ->assertJsonPath('0.balance', 12)
            ->assertJsonPath('0.transactions.0.description', 'Input VAT - VAT');
    }

    public function test_can_list_journal_entries_with_search(): void
    {
        [$tenant, $headers] = $this->actingAsJournalOwner();

        $debitAccount = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'normal_balance' => 'debit',
            'type' => 'asset',
            'is_posting' => true,
            'status' => 'active',
        ]);

        $creditAccount = Account::factory()->create([
            'company_id' => $tenant->getKey(),
            'tenant_id' => $tenant->getKey(),
            'normal_balance' => 'credit',
            'type' => 'liability',
            'is_posting' => true,
            'status' => 'active',
        ]);

        $fundSource = FundSource::query()->create([
            'company_id' => $tenant->getKey(),
            'name' => 'General Fund',
            'status' => 'active',
        ]);

        $entry = JournalEntry::query()->create([
            'company_id' => $tenant->getKey(),
            'prefix' => 'JE',
            'entry_date' => '2026-02-15',
            'entry_number' => 1,
            'fund_source_id' => $fundSource->getKey(),
            'reference_number' => 'REF-SEARCH',
            'type' => 'general',
            'status' => 'draft',
            'notes' => 'Searchable note',
            'amount' => 500.00,
        ]);

        JournalItem::query()->create([
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $entry->getKey(),
            'account_id' => $debitAccount->getKey(),
            'description' => 'Debit line',
            'type' => 'debit',
            'amount' => 500,
        ]);
        JournalItem::query()->create([
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $entry->getKey(),
            'account_id' => $creditAccount->getKey(),
            'description' => 'Credit line',
            'type' => 'credit',
            'amount' => 500,
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/v1/journal-entries?search=SEARCH&per_page=5');

        $response->assertOk()
            ->assertJsonPath('data.0.prefix', 'JE')
            ->assertJsonPath('data.0.journalNumber', 1)
            ->assertJsonPath('data.0.referenceNumber', 'REF-SEARCH')
            ->assertJsonPath('data.0.note', 'Searchable note')
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * @return array{0: Tenant, 1: array<string, string>}
     */
    private function actingAsJournalOwner(): array
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $tenant = Tenant::create([
            'name' => 'Journal Test '.$user->id,
            'slug' => 'journal-test-'.$user->id,
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

    private function createOpenPeriod(Tenant $tenant, string $entryDate): void
    {
        $fiscalYear = FiscalYear::query()->create([
            'tenant_id' => $tenant->getKey(),
            'company_id' => $tenant->getKey(),
            'code' => 'FY-'.str_replace('-', '', $entryDate),
            'name' => 'Journal Test Fiscal Year',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);

        AccountingPeriod::query()->create([
            'tenant_id' => $tenant->getKey(),
            'company_id' => $tenant->getKey(),
            'fiscal_year_id' => $fiscalYear->getKey(),
            'period_number' => 2,
            'name' => 'Feb 2026',
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);
    }
}
