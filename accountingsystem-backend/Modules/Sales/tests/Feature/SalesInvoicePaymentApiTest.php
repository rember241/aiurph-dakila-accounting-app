<?php

namespace Modules\Sales\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Customers\Models\Customer;
use Modules\Inventory\Models\InventoryItem;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoicePayment;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class SalesInvoicePaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_invoice_payments_index_requires_authentication(): void
    {
        [$company, $invoice] = $this->bootstrapCompanyWithIssuedInvoice('pay-auth');

        $this->getJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments")
            ->assertStatus(401);
    }

    public function test_sales_invoice_payments_index_requires_sales_read_ability(): void
    {
        [$company, $member] = $this->createCompanyMemberWithRole([
            'customers' => ['read'],
            'inventory' => ['read'],
        ]);

        $customer = $this->createCustomer($company, ['code' => 'ABIL-CUST', 'name' => 'Ability Customer']);
        $item = $this->createServiceItem($company, ['code' => 'ABIL-ITEM', 'name' => 'Ability Item']);
        $invoice = $this->createIssuedInvoice($company, $customer, $item, 500.0);

        Sanctum::actingAs($member, [], 'sanctum');

        $this->getJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');
    }

    public function test_owner_can_record_cash_payment_and_journal_entry_is_posted_debiting_cash_and_crediting_ar(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('pay-cash');

        $accounts = $this->configureReceiptAccounts($company);
        $this->createOpenAccountingPeriod($company, '2026-04-01', '2026-04-30');

        $customer = $this->createCustomer($company, ['code' => 'CASH-CUST', 'name' => 'Cash Customer']);
        $item = $this->createServiceItem($company, ['code' => 'CASH-ITEM', 'name' => 'Cash Service']);
        $invoice = $this->createIssuedInvoice($company, $customer, $item, 1000.0);

        $response = $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 400,
            'payment_method' => 'cash',
            'receipt_account_id' => $accounts['cash']->getKey(),
            'reference_no' => 'PMT-CASH-001',
            'notes' => 'First cash payment.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Invoice payment recorded')
            ->assertJsonPath('data.company_id', (string) $company->getKey())
            ->assertJsonPath('data.sales_invoice_id', (string) $invoice->getKey())
            ->assertJsonPath('data.amount', 400)
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.reference_no', 'PMT-CASH-001')
            ->assertJsonPath('data.posting_status', 'posted')
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_void', true)
            ->assertJsonPath('data.can_delete', false);

        $this->assertIsString($response->json('data.journal_entry_id'));
        $this->assertIsString($response->json('data.accounting_posted_at'));
        $this->assertSame((string) $accounts['cash']->getKey(), (string) $response->json('data.receipt_account_id'));

        $paymentId = (string) $response->json('data.id');
        $journalEntryId = (string) $response->json('data.journal_entry_id');

        $this->assertDatabaseHas('sales_invoice_payments', [
            'id' => $paymentId,
            'journal_entry_id' => $journalEntryId,
            'receipt_account_id' => $accounts['cash']->getKey(),
        ]);

        $entry = JournalEntry::query()->findOrFail($journalEntryId);
        $this->assertSame((string) $company->getKey(), (string) $entry->company_id);
        $this->assertSame('posted', (string) $entry->status);
        $this->assertSame('SRCPT', (string) $entry->prefix);
        $this->assertSame(400.0, round((float) $entry->amount, 2));

        $items = JournalItem::query()->where('journal_entry_id', $journalEntryId)->get();
        $this->assertCount(2, $items);

        $debit = $items->firstWhere('type', 'debit');
        $credit = $items->firstWhere('type', 'credit');
        $this->assertNotNull($debit);
        $this->assertNotNull($credit);
        $this->assertSame((string) $accounts['cash']->getKey(), (string) $debit->account_id);
        $this->assertSame((string) $accounts['ar']->getKey(), (string) $credit->account_id);
        $this->assertSame(400.0, round((float) $debit->amount, 2));
        $this->assertSame(400.0, round((float) $credit->amount, 2));

        // Revenue must never be re-hit on receipt.
        $this->assertDatabaseMissing('journal_items', [
            'journal_entry_id' => $journalEntryId,
            'account_id' => $accounts['revenue']->getKey(),
        ]);
    }

    public function test_payment_method_controls_which_receipt_account_is_debited(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('pay-method-matrix');

        $accounts = $this->configureReceiptAccounts($company);
        $this->createOpenAccountingPeriod($company, '2026-04-01', '2026-04-30');

        $customer = $this->createCustomer($company, ['code' => 'MIX-CUST', 'name' => 'Matrix Customer']);
        $item = $this->createServiceItem($company, ['code' => 'MIX-ITEM', 'name' => 'Matrix Service']);
        $invoice = $this->createIssuedInvoice($company, $customer, $item, 4000.0);

        $methods = [
            'cash' => $accounts['cash']->getKey(),
            'check' => $accounts['check']->getKey(),
            'bank_transfer' => $accounts['bank']->getKey(),
            'online' => $accounts['online']->getKey(),
        ];

        foreach ($methods as $method => $expectedAccountId) {
            $response = $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
                'payment_date' => '2026-04-05',
                'amount' => 500,
                'payment_method' => $method,
                'receipt_account_id' => $expectedAccountId,
            ])->assertCreated();

            $this->assertSame((string) $expectedAccountId, (string) $response->json('data.receipt_account_id'));
            $this->assertSame($method, (string) $response->json('data.payment_method'));

            $entryId = (string) $response->json('data.journal_entry_id');
            $this->assertDatabaseHas('journal_items', [
                'journal_entry_id' => $entryId,
                'account_id' => $expectedAccountId,
                'type' => 'debit',
            ]);
        }
    }

    public function test_missing_receipt_account_configuration_blocks_posting_without_persisting_payment(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('pay-no-config');

        $ar = $this->createAccount($company, '1100', 'Accounts Receivable', 'asset');
        // Intentionally do NOT configure default cash receipt account.
        $company->forceFill([
            'default_accounts_receivable_account_id' => $ar->getKey(),
            'default_cash_receipt_account_id' => null,
        ])->save();

        $this->createOpenAccountingPeriod($company, '2026-04-01', '2026-04-30');

        $customer = $this->createCustomer($company, ['code' => 'NOCFG-CUST', 'name' => 'No Config Customer']);
        $item = $this->createServiceItem($company, ['code' => 'NOCFG-ITEM', 'name' => 'No Config Service']);
        $invoice = $this->createIssuedInvoice($company, $customer, $item, 200.0);

        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 100,
            'payment_method' => 'cash',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);

        $this->assertDatabaseMissing('sales_invoice_payments', [
            'sales_invoice_id' => $invoice->getKey(),
        ]);
    }

    public function test_payment_method_enum_is_rejected_when_invalid_or_missing(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('pay-enum');

        $this->configureReceiptAccounts($company);
        $this->createOpenAccountingPeriod($company, '2026-04-01', '2026-04-30');

        $customer = $this->createCustomer($company);
        $item = $this->createServiceItem($company);
        $invoice = $this->createIssuedInvoice($company, $customer, $item, 500.0);

        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 100,
            'payment_method' => 'paypal',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);

        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 100,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_payment_cannot_be_created_for_draft_or_cancelled_invoices(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('pay-invoice-status');

        $this->configureReceiptAccounts($company);
        $this->createOpenAccountingPeriod($company, '2026-04-01', '2026-04-30');

        $customer = $this->createCustomer($company);
        $item = $this->createServiceItem($company);

        $draft = $this->createSalesInvoiceDirect($company, $customer, $item, 400.0, ['status' => 'draft']);
        $cancelled = $this->createSalesInvoiceDirect($company, $customer, $item, 400.0, [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => 'Test cancellation.',
        ]);

        $this->postJson("/api/v1/sales-invoices/{$draft->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 100,
            'payment_method' => 'cash',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->postJson("/api/v1/sales-invoices/{$cancelled->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 100,
            'payment_method' => 'cash',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_payment_amount_cannot_exceed_outstanding_balance_and_full_payment_flips_display_status_to_paid(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('pay-balance');

        $this->configureReceiptAccounts($company);
        $this->createOpenAccountingPeriod($company, '2026-04-01', '2026-04-30');

        $customer = $this->createCustomer($company);
        $item = $this->createServiceItem($company);
        $invoice = $this->createIssuedInvoice($company, $customer, $item, 1000.0);

        // Overpay attempt is rejected.
        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 1500,
            'payment_method' => 'cash',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // Partial payment → partially_paid.
        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 300,
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->getJson("/api/v1/sales-invoices/{$invoice->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.display_status', 'partially_paid')
            ->assertJsonPath('data.amount_paid', 300)
            ->assertJsonPath('data.balance_due', 700);

        // Overpay second payment on remaining balance is rejected.
        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-06',
            'amount' => 800,
            'payment_method' => 'cash',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // Final payment on the remaining balance → paid.
        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-06',
            'amount' => 700,
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->getJson("/api/v1/sales-invoices/{$invoice->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.display_status', 'paid')
            ->assertJsonPath('data.amount_paid', 1000)
            ->assertJsonPath('data.balance_due', 0);

        // A fully paid invoice rejects new payments.
        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-07',
            'amount' => 1,
            'payment_method' => 'cash',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_fully_paid_invoice_remains_visible_in_history_and_in_listings(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('pay-visible');

        $this->configureReceiptAccounts($company);
        $this->createOpenAccountingPeriod($company, '2026-04-01', '2026-04-30');

        $customer = $this->createCustomer($company, ['code' => 'VIS-CUST', 'name' => 'Visible Customer']);
        $item = $this->createServiceItem($company, ['code' => 'VIS-ITEM', 'name' => 'Visible Service']);
        $invoice = $this->createIssuedInvoice($company, $customer, $item, 500.0);

        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 500,
            'payment_method' => 'bank_transfer',
            'reference_no' => 'TRF-500',
        ])->assertCreated();

        // History listing on the invoice still surfaces the payment.
        $this->getJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 500)
            ->assertJsonPath('data.0.payment_method', 'bank_transfer')
            ->assertJsonPath('data.0.posting_status', 'posted');

        // Invoice listing still includes the paid invoice for audit.
        $this->getJson('/api/v1/sales-invoices')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', (string) $invoice->getKey())
            ->assertJsonPath('data.0.display_status', 'paid');
    }

    public function test_posted_payment_cannot_be_edited_or_deleted_but_can_be_voided_with_reversal(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('pay-void');

        $accounts = $this->configureReceiptAccounts($company);
        $this->createOpenAccountingPeriod($company, '2026-04-01', '2026-04-30');

        $customer = $this->createCustomer($company);
        $item = $this->createServiceItem($company);
        $invoice = $this->createIssuedInvoice($company, $customer, $item, 1200.0);

        $createResponse = $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 500,
            'payment_method' => 'bank_transfer',
        ])->assertCreated();

        $paymentId = (string) $createResponse->json('data.id');
        $originalJournalId = (string) $createResponse->json('data.journal_entry_id');

        // Editing a posted payment is rejected.
        $this->putJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments/{$paymentId}", [
            'payment_date' => '2026-04-06',
            'amount' => 600,
            'payment_method' => 'bank_transfer',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Deleting a posted payment is rejected.
        $this->deleteJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments/{$paymentId}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Void creates a reversal entry and marks the payment voided.
        $voidResponse = $this->postJson(
            "/api/v1/sales-invoices/{$invoice->getKey()}/payments/{$paymentId}/void",
            [
                'reason' => 'Customer requested reversal.',
                'posting_date' => '2026-04-10',
            ]
        );

        $voidResponse->assertOk()
            ->assertJsonPath('data.posting_status', 'voided')
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_void', false)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.void_reason', 'Customer requested reversal.');

        $reversalJournalId = (string) $voidResponse->json('data.reversal_journal_entry_id');
        $this->assertNotSame('', $reversalJournalId);
        $this->assertNotSame($originalJournalId, $reversalJournalId);

        // Original entry remains intact.
        $this->assertDatabaseHas('journal_entries', [
            'id' => $originalJournalId,
            'status' => 'posted',
        ]);

        // Reversal flips debit/credit sides for the same amount.
        $reversalItems = JournalItem::query()->where('journal_entry_id', $reversalJournalId)->get();
        $this->assertCount(2, $reversalItems);
        $debitAccountId = (string) $reversalItems->firstWhere('type', 'debit')?->account_id;
        $creditAccountId = (string) $reversalItems->firstWhere('type', 'credit')?->account_id;
        $this->assertSame((string) $accounts['ar']->getKey(), $debitAccountId);
        $this->assertSame((string) $accounts['bank']->getKey(), $creditAccountId);

        // After void, balance returns to full outstanding.
        $this->getJson("/api/v1/sales-invoices/{$invoice->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.amount_paid', 0)
            ->assertJsonPath('data.balance_due', 1200);

        // Voided payment cannot be voided again.
        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments/{$paymentId}/void")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cancel_of_invoice_with_active_payment_is_blocked(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('pay-cancel-block');

        $this->configureReceiptAccounts($company);
        $this->createOpenAccountingPeriod($company, '2026-04-01', '2026-04-30');

        $customer = $this->createCustomer($company);
        $item = $this->createServiceItem($company);
        $invoice = $this->createIssuedInvoice($company, $customer, $item, 900.0);

        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 100,
            'payment_method' => 'cash',
        ])->assertCreated();

        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/cancel", [
            'cancelled_reason' => 'Should not cancel while payments exist.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_payment_routes_are_scoped_to_the_current_company(): void
    {
        [$ownerA, $companyA] = $this->actingAsCompanyOwner('pay-scope-a');
        [$ownerB, $companyB] = $this->createCompany('pay-scope-b');

        $this->configureReceiptAccounts($companyB);
        $this->createOpenAccountingPeriod($companyB, '2026-04-01', '2026-04-30');

        $hiddenCustomer = $this->createCustomer($companyB, ['code' => 'HID-CUST', 'name' => 'Hidden Customer']);
        $hiddenItem = $this->createServiceItem($companyB, ['code' => 'HID-ITEM', 'name' => 'Hidden Service']);
        $hiddenInvoice = $this->createIssuedInvoice($companyB, $hiddenCustomer, $hiddenItem, 500.0);

        Sanctum::actingAs($ownerA, [], 'sanctum');

        $this->getJson("/api/v1/sales-invoices/{$hiddenInvoice->getKey()}/payments")
            ->assertStatus(404);

        $this->postJson("/api/v1/sales-invoices/{$hiddenInvoice->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 100,
            'payment_method' => 'cash',
        ])->assertStatus(404);
    }

    public function test_payment_date_cannot_predate_the_invoice_date(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('pay-date-guard');

        $this->configureReceiptAccounts($company);
        $this->createOpenAccountingPeriod($company, '2026-03-01', '2026-04-30');

        $customer = $this->createCustomer($company);
        $item = $this->createServiceItem($company);
        $invoice = $this->createIssuedInvoice($company, $customer, $item, 200.0, ['invoice_date' => '2026-04-01']);

        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-03-31',
            'amount' => 50,
            'payment_method' => 'cash',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['payment_date']);
    }

    public function test_top_level_sales_payments_index_lists_only_current_company_payments_and_supports_filters(): void
    {
        [$ownerA, $companyA] = $this->actingAsCompanyOwner('pay-top-a');
        [$ownerB, $companyB] = $this->createCompany('pay-top-b');

        $this->configureReceiptAccounts($companyA);
        $this->configureReceiptAccounts($companyB);
        $this->createOpenAccountingPeriod($companyA, '2026-04-01', '2026-04-30');
        $this->createOpenAccountingPeriod($companyB, '2026-04-01', '2026-04-30');

        $customerA = $this->createCustomer($companyA, ['code' => 'A-CUST', 'name' => 'Company A Customer']);
        $itemA = $this->createServiceItem($companyA, ['code' => 'A-ITEM', 'name' => 'A Item']);
        $invoiceA = $this->createIssuedInvoice($companyA, $customerA, $itemA, 2000.0, ['invoice_number' => 'SI-TOP-A-1']);

        $customerB = $this->createCustomer($companyB, ['code' => 'B-CUST', 'name' => 'Company B Customer']);
        $itemB = $this->createServiceItem($companyB, ['code' => 'B-ITEM', 'name' => 'B Item']);
        $invoiceB = $this->createIssuedInvoice($companyB, $customerB, $itemB, 2000.0, ['invoice_number' => 'SI-TOP-B-1']);

        // Payment in company A as owner A.
        $this->postJson("/api/v1/sales-invoices/{$invoiceA->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 300,
            'payment_method' => 'cash',
            'reference_no' => 'A-PMT-001',
        ])->assertCreated();

        $this->postJson("/api/v1/sales-invoices/{$invoiceA->getKey()}/payments", [
            'payment_date' => '2026-04-06',
            'amount' => 200,
            'payment_method' => 'bank_transfer',
            'reference_no' => 'A-PMT-002',
        ])->assertCreated();

        // Payment in company B as owner B.
        Sanctum::actingAs($ownerB, [], 'sanctum');
        $this->postJson("/api/v1/sales-invoices/{$invoiceB->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 999,
            'payment_method' => 'cash',
            'reference_no' => 'B-PMT-001',
        ])->assertCreated();

        // Back to owner A: they should only see A-scoped payments.
        Sanctum::actingAs($ownerA, [], 'sanctum');

        $this->getJson('/api/v1/sales-payments')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonMissing(['reference_no' => 'B-PMT-001']);

        $this->getJson('/api/v1/sales-payments?payment_method=bank_transfer')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.reference_no', 'A-PMT-002');

        $this->getJson('/api/v1/sales-payments?status=posted')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->getJson('/api/v1/sales-payments?search=A-PMT-001')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.reference_no', 'A-PMT-001');

        $this->getJson("/api/v1/sales-payments?sales_invoice_id={$invoiceA->getKey()}")
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_top_level_sales_payments_index_rejects_company_id_and_tenant_id_in_payload(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('pay-top-prohibit');

        $this->getJson('/api/v1/sales-payments?company_id=anything')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);

        $this->getJson('/api/v1/sales-payments?tenant_id=anything')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_id']);
    }

    public function test_nested_payment_store_rejects_company_id_tenant_id_and_sales_invoice_id_in_payload(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('pay-store-prohibit');

        $this->configureReceiptAccounts($company);
        $this->createOpenAccountingPeriod($company, '2026-04-01', '2026-04-30');

        $customer = $this->createCustomer($company);
        $item = $this->createServiceItem($company);
        $invoice = $this->createIssuedInvoice($company, $customer, $item, 500.0);

        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/payments", [
            'payment_date' => '2026-04-05',
            'amount' => 100,
            'payment_method' => 'cash',
            'company_id' => 'shouldbe-prohibited',
            'tenant_id' => 'shouldbe-prohibited',
            'sales_invoice_id' => 'shouldbe-prohibited',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['company_id', 'tenant_id', 'sales_invoice_id']);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * @return array{0: Tenant, 1: SalesInvoice}
     */
    private function bootstrapCompanyWithIssuedInvoice(string $slug): array
    {
        [$owner, $company] = $this->createCompany($slug);
        $customer = $this->createCustomer($company);
        $item = $this->createServiceItem($company);
        $invoice = $this->createIssuedInvoice($company, $customer, $item, 100.0);

        return [$company, $invoice];
    }

    /**
     * @param array<string, array<int, string>> $modulePermissions
     * @return array{0: Tenant, 1: User}
     */
    private function createCompanyMemberWithRole(array $modulePermissions): array
    {
        [$owner, $company] = $this->createCompany('pay-member');
        $member = User::factory()->create(['company_id' => $company->getKey()]);

        $membership = TenantMembership::query()->create([
            'tenant_id' => $company->getKey(),
            'user_id' => $member->getKey(),
            'status' => 'active',
            'invited_by_user_id' => $owner->getKey(),
            'joined_at' => now(),
        ]);

        $role = Role::query()->create([
            'tenant_id' => $company->getKey(),
            'system_generated' => false,
            'key' => 'payments-reader',
            'name' => 'Payments Reader',
            'department' => 'Sales',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'department',
            'module_permissions' => $modulePermissions,
            'description' => 'Read-only role for payments tests.',
        ]);

        $membership->roles()->attach($role->getKey());

        return [$company, $member];
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function actingAsCompanyOwner(string $slug): array
    {
        [$owner, $company] = $this->createCompany($slug);
        Sanctum::actingAs($owner, [], 'sanctum');

        return [$owner, $company];
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function createCompany(string $slug): array
    {
        $owner = User::factory()->create(['company_id' => null]);

        $company = Tenant::query()->create([
            'name' => 'Payments '.strtoupper($slug),
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $owner->getKey(),
        ]);

        $owner->forceFill(['company_id' => $company->getKey()])->save();

        return [$owner, $company];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createCustomer(Tenant $company, array $attributes = []): Customer
    {
        return Customer::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'code' => 'CUST-'.strtoupper(substr((string) str()->uuid(), 0, 8)),
            'name' => 'Customer '.substr((string) str()->uuid(), 0, 8),
            'status' => 'active',
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createServiceItem(Tenant $company, array $attributes = []): InventoryItem
    {
        return InventoryItem::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'code' => 'ITEM-'.strtoupper(substr((string) str()->uuid(), 0, 8)),
            'name' => 'Service '.substr((string) str()->uuid(), 0, 8),
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'quantity_on_hand' => 0,
            'sales_price' => 100,
            'cost_price' => 0,
            'item_type' => 'service',
            'category' => 'Services',
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $invoiceAttributes
     */
    private function createIssuedInvoice(
        Tenant $company,
        Customer $customer,
        InventoryItem $item,
        float $totalAmount,
        array $invoiceAttributes = []
    ): SalesInvoice {
        return $this->createSalesInvoiceDirect($company, $customer, $item, $totalAmount, array_merge([
            'status' => 'issued',
            'issued_at' => now(),
        ], $invoiceAttributes));
    }

    /**
     * @param array<string, mixed> $invoiceAttributes
     */
    private function createSalesInvoiceDirect(
        Tenant $company,
        Customer $customer,
        InventoryItem $item,
        float $totalAmount,
        array $invoiceAttributes = []
    ): SalesInvoice {
        $invoice = SalesInvoice::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'invoice_number' => 'SI-'.strtoupper(substr((string) str()->uuid(), 0, 8)),
            'status' => 'draft',
            'invoice_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'issued_at' => null,
            'cancelled_at' => null,
            'cancelled_reason' => null,
            'notes' => null,
            'subtotal' => $totalAmount,
            'tax_amount' => 0,
            'total_amount' => $totalAmount,
        ], $invoiceAttributes));

        $invoice->lines()->create([
            'company_id' => $company->getKey(),
            'inventory_item_id' => $item->getKey(),
            'line_number' => 1,
            'item_code_snapshot' => (string) $item->code,
            'item_name_snapshot' => (string) $item->name,
            'item_description_snapshot' => $item->description,
            'unit_of_measure_snapshot' => (string) $item->unit_of_measure,
            'item_type_snapshot' => (string) $item->item_type,
            'tax_id' => null,
            'tax_name_snapshot' => null,
            'tax_rate_snapshot' => null,
            'unit_cost_snapshot' => $item->cost_price === null ? null : (float) $item->cost_price,
            'quantity' => 1,
            'unit_price' => $totalAmount,
            'line_subtotal' => $totalAmount,
            'tax_amount' => 0,
            'line_total' => $totalAmount,
        ]);

        return $invoice->fresh()->load(['customer', 'lines']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createAccount(Tenant $company, string $code, string $name, string $type, array $overrides = []): Account
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

    /**
     * Configures the AR + all four receipt destination accounts for a company.
     *
     * @return array{ar: Account, revenue: Account, cash: Account, check: Account, bank: Account, online: Account}
     */
    private function configureReceiptAccounts(Tenant $company): array
    {
        $ar = $this->createAccount($company, '1100', 'Accounts Receivable', 'asset');
        $revenue = $this->createAccount($company, '4100', 'Sales Income', 'revenue');
        $cash = $this->createAccount($company, '1010', 'Cash on Hand', 'asset');
        $check = $this->createAccount($company, '1020', 'Undeposited Checks', 'asset');
        $bank = $this->createAccount($company, '1030', 'Bank Operating', 'asset');
        $online = $this->createAccount($company, '1040', 'Online Receivable Clearing', 'asset');

        $company->forceFill([
            'default_accounts_receivable_account_id' => $ar->getKey(),
            'default_sales_income_account_id' => $revenue->getKey(),
            'default_cash_receipt_account_id' => $cash->getKey(),
            'default_check_receipt_account_id' => $check->getKey(),
            'default_bank_receipt_account_id' => $bank->getKey(),
            'default_online_receipt_account_id' => $online->getKey(),
        ])->save();

        return [
            'ar' => $ar,
            'revenue' => $revenue,
            'cash' => $cash,
            'check' => $check,
            'bank' => $bank,
            'online' => $online,
        ];
    }

    private function createOpenAccountingPeriod(Tenant $company, string $start, string $end): AccountingPeriod
    {
        $fiscalYear = FiscalYear::query()->create([
            'tenant_id' => $company->getKey(),
            'company_id' => $company->getKey(),
            'code' => 'FY-'.strtoupper(substr((string) str()->uuid(), 0, 6)),
            'name' => 'Fiscal Year '.$start,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'open',
            'is_current' => true,
        ]);

        return AccountingPeriod::query()->create([
            'tenant_id' => $company->getKey(),
            'company_id' => $company->getKey(),
            'fiscal_year_id' => $fiscalYear->getKey(),
            'period_number' => 1,
            'name' => 'Period '.$start,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);
    }
}
