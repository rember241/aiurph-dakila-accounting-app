<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Users\Models\Company;
use Tests\TestCase;

class GeneralLedgerOpeningBalanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_ledger_includes_inventory_opening_balance_for_default_stock_account(): void
    {
        [$company, $headers] = $this->actingAsCompanyOwner();

        $inventoryAssetAccount = Account::factory()->create([
            'company_id' => $company->getKey(),
            'tenant_id' => $company->getKey(),
            'code' => '1410',
            'name' => 'Stock In Hand',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_posting' => true,
            'status' => 'active',
            'is_contra' => false,
        ]);

        $offsetAccount = Account::factory()->create([
            'company_id' => $company->getKey(),
            'tenant_id' => $company->getKey(),
            'code' => '3100',
            'name' => 'Opening Equity',
            'type' => 'equity',
            'normal_balance' => 'credit',
            'is_posting' => true,
            'status' => 'active',
            'is_contra' => false,
        ]);

        $company->forceFill([
            'default_inventory_asset_account_id' => $inventoryAssetAccount->getKey(),
        ])->save();

        $fundSource = FundSource::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'General Fund',
            'status' => 'active',
        ]);

        $openingEntry = JournalEntry::query()->create([
            'company_id' => $company->getKey(),
            'prefix' => 'JE',
            'entry_number' => 1,
            'entry_date' => '2026-01-10',
            'type' => 'general',
            'fund_source_id' => $fundSource->getKey(),
            'reference_number' => 'OPEN-001',
            'notes' => 'Opening stock capitalization',
            'amount' => 100.00,
            'status' => 'posted',
        ]);

        JournalItem::query()->create([
            'journal_entry_id' => $openingEntry->getKey(),
            'account_id' => $inventoryAssetAccount->getKey(),
            'description' => 'Opening stock',
            'type' => 'debit',
            'amount' => 100.00,
        ]);

        JournalItem::query()->create([
            'journal_entry_id' => $openingEntry->getKey(),
            'account_id' => $offsetAccount->getKey(),
            'description' => 'Opening equity',
            'type' => 'credit',
            'amount' => 100.00,
        ]);

        $periodEntry = JournalEntry::query()->create([
            'company_id' => $company->getKey(),
            'prefix' => 'JE',
            'entry_number' => 2,
            'entry_date' => '2026-02-06',
            'type' => 'general',
            'fund_source_id' => $fundSource->getKey(),
            'reference_number' => 'ADJ-001',
            'notes' => 'Inventory adjustment',
            'amount' => 20.00,
            'status' => 'posted',
        ]);

        JournalItem::query()->create([
            'journal_entry_id' => $periodEntry->getKey(),
            'account_id' => $inventoryAssetAccount->getKey(),
            'description' => 'Inventory adjustment',
            'type' => 'debit',
            'amount' => 20.00,
        ]);

        JournalItem::query()->create([
            'journal_entry_id' => $periodEntry->getKey(),
            'account_id' => $offsetAccount->getKey(),
            'description' => 'Inventory adjustment offset',
            'type' => 'credit',
            'amount' => 20.00,
        ]);

        $openingStockItem = InventoryItem::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'INV-OPEN-001',
            'name' => 'Widget A',
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'item_type' => 'inventory',
            'quantity_on_hand' => 5,
            'cost_price' => 10,
            'stock_as_of' => '2026-01-15',
            'created_at' => '2026-01-15 08:00:00',
            'updated_at' => '2026-02-10 10:00:00',
        ]);

        InventoryMovement::query()->create([
            'company_id' => $company->getKey(),
            'inventory_item_id' => $openingStockItem->getKey(),
            'movement_type' => 'sales_invoice_issue',
            'direction' => 'out',
            'quantity' => 2,
            'quantity_before' => 7,
            'quantity_after' => 5,
            'unit_cost' => 10,
            'movement_date' => '2026-02-10',
            'reference_number' => 'SI-1001',
            'notes' => 'Issued after the opening date.',
            'occurred_at' => '2026-02-10 10:00:00',
        ]);

        InventoryItem::query()->create([
            'company_id' => $company->getKey(),
            'code' => 'INV-NEW-001',
            'name' => 'Widget B',
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'item_type' => 'inventory',
            'quantity_on_hand' => 3,
            'cost_price' => 20,
            'stock_as_of' => '2026-02-12',
            'created_at' => '2026-02-12 09:00:00',
            'updated_at' => '2026-02-12 09:00:00',
        ]);

        $response = $this->withHeaders($headers)->getJson(sprintf(
            '/api/v1/general-ledger/accounts?startDate=%s&endDate=%s&accountIds[]=%s',
            '2026-02-01',
            '2026-02-28',
            $inventoryAssetAccount->getKey()
        ));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', (string) $inventoryAssetAccount->getKey())
            ->assertJsonPath('0.openingBalance', 170)
            ->assertJsonPath('0.debit', 20)
            ->assertJsonPath('0.credit', 0)
            ->assertJsonPath('0.balance', 190)
            ->assertJsonPath('0.transactions.0.journalNumber', 'JE-0002')
            ->assertJsonPath('0.transactions.0.balance', 190);
    }

    /**
     * @return array{0: Company, 1: array<string, string>}
     */
    private function actingAsCompanyOwner(): array
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'sanctum');

        $company = Company::create([
            'name' => 'General Ledger Co '.$user->id,
            'slug' => 'general-ledger-co-'.$user->id,
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
