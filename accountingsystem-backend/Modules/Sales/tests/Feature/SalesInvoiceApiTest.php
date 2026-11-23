<?php

namespace Modules\Sales\Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\PeriodManagement\AccountingPeriod;
use Modules\Accounting\Models\PeriodManagement\FiscalYear;
use Modules\Accounting\Models\Taxes\Tax;
use Modules\Customers\Models\Customer;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoicePayment;
use Modules\Users\Models\Role;
use Modules\Users\Models\Tenant;
use Modules\Users\Models\TenantMembership;
use Tests\TestCase;

class SalesInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_invoice_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/sales-invoices')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_sales_invoice_index_requires_sales_read_ability_for_non_admin_members(): void
    {
        [$company, $member] = $this->createCompanyMemberWithRole([
            'customers' => ['read'],
            'inventory' => ['read'],
        ]);

        Sanctum::actingAs($member, [], 'sanctum');

        $this->getJson('/api/v1/sales-invoices')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');
    }

    public function test_sales_read_member_can_view_printable_sales_invoice_details_with_status_fields(): void
    {
        [$company, $member] = $this->createCompanyMemberWithRole([
            'sales' => ['read'],
            'customers' => ['read'],
            'inventory' => ['read'],
        ]);

        $company->forceFill([
            'display_name' => 'Northwind',
            'email' => 'billing@northwind.test',
            'phone' => '+1 555 1000',
            'address' => '12 Harbor Road, Seattle',
            'invoice_header_title' => 'Sales Invoice',
            'invoice_footer_note' => 'Thank you for your business.',
            'invoice_payment_note' => 'Payment due within 30 days.',
        ])->save();

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-PRINT-001',
            'name' => 'Acme Distribution Corp',
            'email' => 'ap@acme.test',
            'phone' => '+1 555 2200',
            'address' => '88 Market Street, Seattle',
            'status' => 'active',
        ]);

        $item = $this->createInventoryItem($company, [
            'code' => 'PRINT-ITEM-001',
            'name' => 'Finished Good 1001',
            'unit_of_measure' => 'PCS',
            'sales_price' => 1500,
        ]);

        $invoice = $this->createSalesInvoice($company, $customer, [
            'invoice_number' => 'SI-PRINT-001',
            'status' => 'issued',
            'invoice_date' => '2026-03-31',
            'due_date' => '2026-04-30',
            'issued_at' => now(),
            'notes' => 'Deliver during business hours.',
        ], [
            [
                'inventory_item' => $item,
                'quantity' => 2,
                'unit_price' => 1500,
                'unit_cost_snapshot' => 900,
            ],
        ]);

        Sanctum::actingAs($member, [], 'sanctum');

        $response = $this->getJson("/api/v1/sales-invoices/{$invoice->getKey()}");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $invoice->getKey())
            ->assertJsonPath('data.invoice_number', 'SI-PRINT-001')
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.status_label', 'Issued')
            ->assertJsonPath('data.display_status', 'issued')
            ->assertJsonPath('data.display_status_label', 'Issued')
            ->assertJsonPath('data.invoice_date', '2026-03-31')
            ->assertJsonPath('data.due_date', '2026-04-30')
            ->assertJsonPath('data.notes', 'Deliver during business hours.')
            ->assertJsonPath('data.subtotal', 3000)
            ->assertJsonPath('data.total_amount', 3000)
            ->assertJsonPath('data.amount_paid', 0)
            ->assertJsonPath('data.balance_due', 3000)
            ->assertJsonPath('data.is_overdue', false)
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.can_issue', false)
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.company.id', (string) $company->getKey())
            ->assertJsonPath('data.company.name', (string) $company->name)
            ->assertJsonPath('data.company.display_name', 'Northwind')
            ->assertJsonPath('data.company.email', 'billing@northwind.test')
            ->assertJsonPath('data.company.phone', '+1 555 1000')
            ->assertJsonPath('data.company.address', '12 Harbor Road, Seattle')
            ->assertJsonPath('data.company.invoice_header_title', 'Sales Invoice')
            ->assertJsonPath('data.company.invoice_footer_note', 'Thank you for your business.')
            ->assertJsonPath('data.company.invoice_payment_note', 'Payment due within 30 days.')
            ->assertJsonPath('data.customer.code', 'CUST-PRINT-001')
            ->assertJsonPath('data.customer.name', 'Acme Distribution Corp')
            ->assertJsonPath('data.customer.address', '88 Market Street, Seattle')
            ->assertJsonPath('data.customer.status', 'active')
            ->assertJsonPath('data.lines.0.item_code_snapshot', 'PRINT-ITEM-001')
            ->assertJsonPath('data.lines.0.item_name_snapshot', 'Finished Good 1001')
            ->assertJsonPath('data.lines.0.unit_of_measure_snapshot', 'PCS')
            ->assertJsonPath('data.lines.0.item_type_snapshot', 'inventory')
            ->assertJsonPath('data.lines.0.quantity', 2)
            ->assertJsonPath('data.lines.0.unit_price', 1500)
            ->assertJsonPath('data.lines.0.line_subtotal', 3000)
            ->assertJsonPath('data.line_count', 1);

        $this->assertIsString($response->json('data.issued_at'));
    }

    public function test_sales_invoice_owner_can_create_update_and_delete_draft_invoice_with_due_date(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-owner-company');

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-1001',
            'name' => 'Acme Distribution Corp',
        ]);
        $tax = $this->createTax($company, [
            'name' => 'VAT',
            'rate' => 12,
        ]);

        $itemA = $this->createInventoryItem($company, [
            'code' => 'FG-1001',
            'name' => 'Finished Good 1001',
            'unit_of_measure' => 'PCS',
            'sales_price' => 1500,
        ]);

        $itemB = $this->createInventoryItem($company, [
            'code' => 'FG-1002',
            'name' => 'Finished Good 1002',
            'unit_of_measure' => 'BOX',
            'sales_price' => 2250,
        ]);

        $createResponse = $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'SI-DRAFT-1001',
            'invoice_date' => '2026-03-30',
            'due_date' => '2026-04-14',
            'customer_id' => (string) $customer->getKey(),
            'notes' => 'Initial draft invoice.',
            'lines' => [
                [
                    'inventory_item_id' => (string) $itemA->getKey(),
                    'tax_id' => (string) $tax->getKey(),
                    'quantity' => 2,
                    'unit_price' => 999,
                ],
                [
                    'inventory_item_id' => (string) $itemB->getKey(),
                    'quantity' => 1,
                    'unit_price' => 1,
                ],
            ],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('message', 'Sales invoice draft created')
            ->assertJsonPath('data.company_id', (string) $company->getKey())
            ->assertJsonPath('data.customer_id', (string) $customer->getKey())
            ->assertJsonPath('data.invoice_number', 'SI-DRAFT-1001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.display_status', 'draft')
            ->assertJsonPath('data.invoice_date', '2026-03-30')
            ->assertJsonPath('data.due_date', '2026-04-14')
            ->assertJsonPath('data.subtotal', 5250)
            ->assertJsonPath('data.tax_amount', 360)
            ->assertJsonPath('data.total_amount', 5610)
            ->assertJsonPath('data.amount_paid', 0)
            ->assertJsonPath('data.balance_due', 5610)
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonPath('data.can_issue', true)
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.line_count', 2)
            ->assertJsonPath('data.lines.0.item_code_snapshot', 'FG-1001')
            ->assertJsonPath('data.lines.0.item_name_snapshot', 'Finished Good 1001')
            ->assertJsonPath('data.lines.0.quantity', 2)
            ->assertJsonPath('data.lines.0.unit_price', 1500)
            ->assertJsonPath('data.lines.0.line_subtotal', 3000)
            ->assertJsonPath('data.lines.0.tax_name_snapshot', 'VAT')
            ->assertJsonPath('data.lines.0.tax_rate_snapshot', 12)
            ->assertJsonPath('data.lines.0.tax_amount', 360)
            ->assertJsonPath('data.lines.0.line_total', 3360)
            ->assertJsonPath('data.lines.1.item_code_snapshot', 'FG-1002')
            ->assertJsonPath('data.lines.1.unit_price', 2250)
            ->assertJsonPath('data.lines.1.line_subtotal', 2250)
            ->assertJsonPath('data.lines.1.tax_amount', 0)
            ->assertJsonPath('data.lines.1.line_total', 2250);

        $salesInvoiceId = (string) $createResponse->json('data.id');

        $this->assertDatabaseHas('sales_invoices', [
            'id' => $salesInvoiceId,
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'invoice_number' => 'SI-DRAFT-1001',
            'status' => 'draft',
            'subtotal' => 5250,
            'tax_amount' => 360,
            'total_amount' => 5610,
        ]);
        $this->assertSame('2026-04-14', SalesInvoice::query()->findOrFail($salesInvoiceId)->due_date?->toDateString());

        $updateResponse = $this->putJson("/api/v1/sales-invoices/{$salesInvoiceId}", [
            'invoice_number' => 'SI-DRAFT-1001A',
            'invoice_date' => '2026-03-31',
            'due_date' => '2026-04-20',
            'customer_id' => (string) $customer->getKey(),
            'notes' => 'Updated draft invoice.',
            'lines' => [
                [
                    'inventory_item_id' => (string) $itemB->getKey(),
                    'tax_id' => (string) $tax->getKey(),
                    'quantity' => 3,
                    'unit_price' => 2100,
                ],
            ],
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('message', 'Sales invoice draft updated')
            ->assertJsonPath('data.invoice_number', 'SI-DRAFT-1001A')
            ->assertJsonPath('data.invoice_date', '2026-03-31')
            ->assertJsonPath('data.due_date', '2026-04-20')
            ->assertJsonPath('data.notes', 'Updated draft invoice.')
            ->assertJsonPath('data.subtotal', 6750)
            ->assertJsonPath('data.tax_amount', 810)
            ->assertJsonPath('data.total_amount', 7560)
            ->assertJsonPath('data.line_count', 1)
            ->assertJsonPath('data.lines.0.item_code_snapshot', 'FG-1002')
            ->assertJsonPath('data.lines.0.quantity', 3)
            ->assertJsonPath('data.lines.0.unit_price', 2250)
            ->assertJsonPath('data.lines.0.line_subtotal', 6750)
            ->assertJsonPath('data.lines.0.tax_name_snapshot', 'VAT')
            ->assertJsonPath('data.lines.0.tax_amount', 810)
            ->assertJsonPath('data.lines.0.line_total', 7560);

        $this->deleteJson("/api/v1/sales-invoices/{$salesInvoiceId}")
            ->assertOk()
            ->assertJsonPath('message', 'Sales invoice draft deleted');

        $this->assertDatabaseMissing('sales_invoices', [
            'id' => $salesInvoiceId,
        ]);
    }

    public function test_sales_invoice_line_unit_price_is_derived_from_the_inventory_item_sales_price(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-derived-price-company');

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-DERIVED-PRICE',
            'name' => 'Derived Price Customer',
        ]);

        $item = $this->createInventoryItem($company, [
            'code' => 'ITEM-DERIVED-PRICE',
            'name' => 'Derived Price Item',
            'sales_price' => 1850,
        ]);

        $createResponse = $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'SI-DERIVED-PRICE-001',
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'quantity' => 2,
                    'unit_price' => 9999,
                ],
            ],
        ]);

        $invoiceId = (string) $createResponse->json('data.id');

        $createResponse->assertCreated()
            ->assertJsonPath('data.subtotal', 3700)
            ->assertJsonPath('data.lines.0.unit_price', 1850)
            ->assertJsonPath('data.lines.0.line_subtotal', 3700);

        $item->forceFill(['sales_price' => 2100])->save();

        $this->putJson("/api/v1/sales-invoices/{$invoiceId}", [
            'invoice_number' => 'SI-DERIVED-PRICE-001A',
            'invoice_date' => '2026-03-31',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'quantity' => 3,
                    'unit_price' => 1,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.subtotal', 6300)
            ->assertJsonPath('data.lines.0.unit_price', 2100)
            ->assertJsonPath('data.lines.0.line_subtotal', 6300);
    }

    public function test_sales_invoice_create_snapshots_tax_and_computes_totals(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-taxed-invoice-company');

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-TAX-001',
            'name' => 'Tax Customer',
        ]);

        $item = $this->createInventoryItem($company, [
            'code' => 'ITEM-TAX-001',
            'name' => 'Taxed Item',
            'sales_price' => 1000,
        ]);

        $tax = $this->createTax($company, [
            'name' => 'Sales VAT',
            'rate' => 12,
        ]);

        $response = $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'SI-TAX-001',
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'tax_id' => (string) $tax->getKey(),
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', 2000)
            ->assertJsonPath('data.tax_amount', 240)
            ->assertJsonPath('data.total_amount', 2240)
            ->assertJsonPath('data.balance_due', 2240)
            ->assertJsonPath('data.lines.0.tax_id', (string) $tax->getKey())
            ->assertJsonPath('data.lines.0.tax_name_snapshot', 'Sales VAT')
            ->assertJsonPath('data.lines.0.tax_rate_snapshot', 12)
            ->assertJsonPath('data.lines.0.tax_amount', 240)
            ->assertJsonPath('data.lines.0.line_total', 2240);
    }

    public function test_sales_invoice_create_rejects_cross_company_or_inactive_tax_selection(): void
    {
        [$ownerA, $companyA] = $this->actingAsCompanyOwner('sales-tax-validation-a');
        [$ownerB, $companyB] = $this->createCompany('sales-tax-validation-b');

        $customer = $this->createCustomer($companyA);
        $item = $this->createInventoryItem($companyA);
        $otherTax = $this->createTax($companyB, ['name' => 'Other Tax', 'rate' => 10]);
        $inactiveTax = $this->createTax($companyA, ['name' => 'Inactive Tax', 'rate' => 5, 'status' => 'inactive']);

        $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'SI-TAX-INVALID-001',
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'tax_id' => (string) $otherTax->getKey(),
                    'quantity' => 1,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.tax_id']);

        $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'SI-TAX-INVALID-002',
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'tax_id' => (string) $inactiveTax->getKey(),
                    'quantity' => 1,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.tax_id']);
    }

    public function test_sales_invoice_create_rejects_invalid_payloads(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-invalid-payload-company');

        $response = $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'invalid code',
            'invoice_date' => 'not-a-date',
            'due_date' => '2026-03-01',
            'customer_id' => 'bad-id',
            'lines' => [
                [
                    'inventory_item_id' => 'bad-item-id',
                    'quantity' => 0,
                    'unit_price' => -5,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invoice_number', 'invoice_date', 'customer_id', 'lines.0.inventory_item_id', 'lines.0.quantity']);

        $customer = $this->createCustomer($company);
        $item = $this->createInventoryItem($company);

        $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'SI-DATE-001',
            'invoice_date' => '2026-03-30',
            'due_date' => '2026-03-29',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'quantity' => 1,
                    'unit_price' => 10,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);

        $this->assertDatabaseCount('sales_invoices', 0);
    }

    public function test_sales_invoice_create_rejects_cross_company_customer_and_item_selection(): void
    {
        [$ownerA, $companyA] = $this->actingAsCompanyOwner('sales-validation-a');
        [$ownerB, $companyB] = $this->createCompany('sales-validation-b');

        $otherCustomer = $this->createCustomer($companyB, ['code' => 'OTHER-CUST', 'name' => 'Other Customer']);
        $otherItem = $this->createInventoryItem($companyB, ['code' => 'OTHER-ITEM', 'name' => 'Other Item']);

        $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'CROSS-SI-1',
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $otherCustomer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $otherItem->getKey(),
                    'quantity' => 1,
                    'unit_price' => 10,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_sales_invoice_listing_is_scoped_to_the_current_company(): void
    {
        [$ownerA, $companyA] = $this->actingAsCompanyOwner('sales-scope-a');
        [$ownerB, $companyB] = $this->createCompany('sales-scope-b');

        $visibleCustomer = $this->createCustomer($companyA, ['code' => 'VISIBLE-CUST', 'name' => 'Visible Customer']);
        $hiddenCustomer = $this->createCustomer($companyB, ['code' => 'HIDDEN-CUST', 'name' => 'Hidden Customer']);
        $visibleItem = $this->createInventoryItem($companyA, ['code' => 'VISIBLE-ITEM', 'name' => 'Visible Item']);
        $hiddenItem = $this->createInventoryItem($companyB, ['code' => 'HIDDEN-ITEM', 'name' => 'Hidden Item']);

        $visibleInvoice = $this->createSalesInvoice($companyA, $visibleCustomer, [
            'invoice_number' => 'VISIBLE-SI',
            'status' => 'draft',
        ], [
            [
                'inventory_item' => $visibleItem,
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ]);

        $this->createSalesInvoice($companyB, $hiddenCustomer, [
            'invoice_number' => 'HIDDEN-SI',
            'status' => 'draft',
        ], [
            [
                'inventory_item' => $hiddenItem,
                'quantity' => 1,
                'unit_price' => 200,
            ],
        ]);

        Sanctum::actingAs($ownerA, [], 'sanctum');

        $this->getJson('/api/v1/sales-invoices')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', (string) $visibleInvoice->getKey())
            ->assertJsonMissing(['invoice_number' => 'HIDDEN-SI']);
    }

    public function test_sales_invoice_show_returns_not_found_for_other_company_invoice(): void
    {
        [$ownerA, $companyA] = $this->actingAsCompanyOwner('sales-show-a');
        [$ownerB, $companyB] = $this->createCompany('sales-show-b');

        $customerB = $this->createCustomer($companyB, ['code' => 'SHOW-CUST-B', 'name' => 'Hidden Customer']);
        $itemB = $this->createInventoryItem($companyB, ['code' => 'SHOW-ITEM-B', 'name' => 'Hidden Item']);

        $hiddenInvoice = $this->createSalesInvoice($companyB, $customerB, [
            'invoice_number' => 'HIDDEN-SHOW',
            'status' => 'draft',
        ], [
            [
                'inventory_item' => $itemB,
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ]);

        Sanctum::actingAs($ownerA, [], 'sanctum');

        $this->getJson("/api/v1/sales-invoices/{$hiddenInvoice->getKey()}")
            ->assertStatus(404);
    }

    public function test_sales_invoice_requests_reject_mismatched_company_headers(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-header-company');
        [$otherOwner, $otherCompany] = $this->createCompany('sales-header-other');

        Sanctum::actingAs($owner, [], 'sanctum');

        $this->withHeaders([
            'X-Tenant-ID' => (string) $otherCompany->getKey(),
        ])->getJson('/api/v1/sales-invoices')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Company switching is no longer supported for this account.');
    }

    public function test_sales_invoice_mutation_routes_enforce_sales_create_write_and_delete_abilities_for_non_admin_members(): void
    {
        [$company, $member] = $this->createCompanyMemberWithRole([
            'sales' => ['read'],
            'customers' => ['read'],
            'inventory' => ['read'],
        ]);

        $customer = $this->createCustomer($company, ['code' => 'NO-WRITE-CUST', 'name' => 'No Write Customer']);
        $item = $this->createInventoryItem($company, ['code' => 'NO-WRITE-ITEM', 'name' => 'No Write Item']);
        $invoice = $this->createSalesInvoice($company, $customer, [
            'invoice_number' => 'NO-WRITE-SI-EXISTING',
        ], [
            [
                'inventory_item' => $item,
                'quantity' => 1,
                'unit_price' => 10,
            ],
        ]);

        Sanctum::actingAs($member, [], 'sanctum');

        $this->getJson("/api/v1/sales-invoices/{$invoice->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $invoice->getKey());

        $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'NO-WRITE-SI',
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'quantity' => 1,
                    'unit_price' => 10,
                ],
            ],
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $this->putJson("/api/v1/sales-invoices/{$invoice->getKey()}", [
            'invoice_number' => 'NO-WRITE-SI-UPDATED',
            'invoice_date' => '2026-03-31',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'quantity' => 2,
                    'unit_price' => 10,
                ],
            ],
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/issue")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/cancel", [
            'cancelled_reason' => 'Read-only members cannot cancel.',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');

        $this->deleteJson("/api/v1/sales-invoices/{$invoice->getKey()}")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Insufficient company permissions.');
    }

    public function test_draft_sales_invoice_mutations_do_not_affect_stock_or_create_movements(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-draft-no-stock-company');

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-DRAFT-STOCK',
            'name' => 'Draft Stock Customer',
        ]);

        $item = $this->createInventoryItem($company, [
            'code' => 'ITEM-DRAFT-STOCK',
            'name' => 'Draft Stock Item',
            'quantity_on_hand' => 18,
            'sales_price' => 1250,
            'cost_price' => 900,
        ]);

        $createResponse = $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'SI-DRAFT-STOCK-001',
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'quantity' => 2,
                    'unit_price' => 1250,
                ],
            ],
        ]);

        $invoiceId = (string) $createResponse->json('data.id');

        $this->assertSame(18.0, (float) $item->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_movements', 0);

        $this->putJson("/api/v1/sales-invoices/{$invoiceId}", [
            'invoice_number' => 'SI-DRAFT-STOCK-001A',
            'invoice_date' => '2026-03-31',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'quantity' => 4,
                    'unit_price' => 1200,
                ],
            ],
        ])->assertOk();

        $this->assertSame(18.0, (float) $item->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_movements', 0);

        $this->deleteJson("/api/v1/sales-invoices/{$invoiceId}")
            ->assertOk();

        $this->assertSame(18.0, (float) $item->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_issuing_draft_invoice_reduces_stock_for_inventory_lines_only_and_records_movements(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-issue-stock-company');

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-ISSUE-STOCK',
            'name' => 'Issuing Customer',
        ]);

        $inventoryItem = $this->createInventoryItem($company, [
            'code' => 'ITEM-ISSUE-STOCK',
            'name' => 'Warehouse Item',
            'quantity_on_hand' => 15,
            'sales_price' => 1400,
            'cost_price' => 950,
        ]);

        $nonInventoryItem = $this->createInventoryItem($company, [
            'code' => 'ITEM-NONSTOCK',
            'name' => 'Handling Charge',
            'item_type' => 'non_inventory',
            'quantity_on_hand' => 0,
            'sales_price' => 350,
            'cost_price' => null,
        ]);

        $serviceItem = $this->createInventoryItem($company, [
            'code' => 'ITEM-SERVICE',
            'name' => 'Implementation Service',
            'item_type' => 'service',
            'quantity_on_hand' => 0,
            'sales_price' => 5000,
            'cost_price' => 2200,
        ]);

        $createResponse = $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'SI-ISSUE-001',
            'invoice_date' => '2026-03-30',
            'due_date' => '2026-04-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $inventoryItem->getKey(),
                    'quantity' => 3,
                    'unit_price' => 1400,
                ],
                [
                    'inventory_item_id' => (string) $nonInventoryItem->getKey(),
                    'quantity' => 2,
                    'unit_price' => 350,
                ],
                [
                    'inventory_item_id' => (string) $serviceItem->getKey(),
                    'quantity' => 1,
                    'unit_price' => 5000,
                ],
            ],
        ]);

        $invoiceId = (string) $createResponse->json('data.id');

        $issueResponse = $this->postJson("/api/v1/sales-invoices/{$invoiceId}/issue");

        $issueResponse->assertOk()
            ->assertJsonPath('message', 'Sales invoice issued')
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.display_status', 'issued')
            ->assertJsonPath('data.can_issue', false)
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.can_cancel', true);

        $this->assertIsString($issueResponse->json('data.issued_at'));

        $this->assertSame(12.0, (float) $inventoryItem->fresh()->quantity_on_hand);
        $this->assertSame(0.0, (float) $nonInventoryItem->fresh()->quantity_on_hand);
        $this->assertSame(0.0, (float) $serviceItem->fresh()->quantity_on_hand);

        $movement = InventoryMovement::query()
            ->where('sales_invoice_id', $invoiceId)
            ->firstOrFail();

        $this->assertSame((string) $company->getKey(), (string) $movement->company_id);
        $this->assertSame((string) $inventoryItem->getKey(), (string) $movement->inventory_item_id);
        $this->assertNull($movement->org_unit_id);
        $this->assertSame('sales_invoice', (string) $movement->source_type);
        $this->assertSame($invoiceId, (string) $movement->source_id);
        $this->assertSame('sales_invoice_issue', (string) $movement->movement_type);
        $this->assertSame('out', (string) $movement->direction);
        $this->assertSame(3.0, (float) $movement->quantity);
        $this->assertSame(15.0, (float) $movement->quantity_before);
        $this->assertSame(12.0, (float) $movement->quantity_after);
        $this->assertSame(950.0, (float) $movement->unit_cost);
        $this->assertSame(now()->toDateString(), $movement->movement_date?->toDateString());
        $this->assertSame('SI-ISSUE-001', (string) $movement->reference_number);
        $this->assertSame('Issued from sales invoice SI-ISSUE-001.', (string) $movement->notes);

        $this->assertDatabaseCount('inventory_movements', 1);

        $issuedAudit = AuditLog::query()
            ->where('action', 'sales_invoice.issued')
            ->where('tenant_id', $company->getKey())
            ->firstOrFail();

        $this->assertSame((string) $company->getKey(), $issuedAudit->meta['company_id'] ?? null);
        $this->assertSame(1, (int) ($issuedAudit->meta['movement_count'] ?? 0));
        $this->assertSame(1, (int) ($issuedAudit->meta['stock_line_count'] ?? 0));
        $this->assertSame(3.0, (float) ($issuedAudit->meta['quantity_total'] ?? 0));
        $this->assertSame('issued', $issuedAudit->after['status'] ?? null);
        $this->assertIsString($issuedAudit->after['issued_at'] ?? null);
    }

    public function test_issuing_sales_invoice_rolls_back_when_any_stock_line_fails_validation(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-issue-rollback');

        $customer = $this->createCustomer($company, ['code' => 'ROLLBACK-CUST', 'name' => 'Rollback Customer']);
        $itemA = $this->createInventoryItem($company, [
            'code' => 'ROLLBACK-ITEM-A',
            'name' => 'Rollback Item A',
            'quantity_on_hand' => 10,
            'cost_price' => 90,
        ]);
        $itemB = $this->createInventoryItem($company, [
            'code' => 'ROLLBACK-ITEM-B',
            'name' => 'Rollback Item B',
            'quantity_on_hand' => 1,
            'cost_price' => 60,
        ]);

        $createResponse = $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'ROLLBACK-SI-001',
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $itemA->getKey(),
                    'quantity' => 2,
                    'unit_price' => 150,
                ],
                [
                    'inventory_item_id' => (string) $itemB->getKey(),
                    'quantity' => 3,
                    'unit_price' => 175,
                ],
            ],
        ]);

        $salesInvoiceId = (string) $createResponse->json('data.id');

        $this->postJson("/api/v1/sales-invoices/{$salesInvoiceId}/issue")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.1.quantity']);

        $invoice = SalesInvoice::query()->findOrFail($salesInvoiceId);
        $this->assertSame('draft', (string) $invoice->status);
        $this->assertNull($invoice->issued_at);

        $this->assertSame(10.0, (float) $itemA->fresh()->quantity_on_hand);
        $this->assertSame(1.0, (float) $itemB->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_movements', 0);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'sales_invoice.issued',
            'tenant_id' => $company->getKey(),
            'auditable_id' => $salesInvoiceId,
        ]);
    }

    public function test_sales_invoice_can_be_created_and_posted_in_one_request(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-create-post-company');

        $accountsReceivableAccount = $this->createCompanyAccount($company, '1100', 'Accounts Receivable', 'asset');
        $salesIncomeAccount = $this->createCompanyAccount($company, '4100', 'Sales Income', 'revenue');

        $company->forceFill([
            'default_accounts_receivable_account_id' => $accountsReceivableAccount->getKey(),
            'default_sales_income_account_id' => $salesIncomeAccount->getKey(),
        ])->save();

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-CREATE-POST',
            'name' => 'Create Post Customer',
        ]);

        $serviceItem = $this->createInventoryItem($company, [
            'code' => 'ITEM-CREATE-POST',
            'name' => 'Create Post Service',
            'item_type' => 'service',
            'quantity_on_hand' => 0,
            'sales_price' => 2400,
            'cost_price' => 600,
        ]);

        $response = $this->postJson('/api/v1/sales-invoices', [
            'submit_action' => 'post',
            'invoice_date' => '2026-03-30',
            'due_date' => '2026-04-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $serviceItem->getKey(),
                    'quantity' => 2,
                    'unit_price' => 999,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Sales invoice created and posted')
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.display_status', 'issued')
            ->assertJsonPath('data.can_issue', false)
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.can_cancel', true);

        $this->assertIsString($response->json('data.issued_at'));
        $this->assertIsString($response->json('data.journal_entry_id'));
        $this->assertIsString($response->json('data.accounting_posted_at'));

        $invoiceId = (string) $response->json('data.id');

        $this->assertDatabaseHas('sales_invoices', [
            'id' => $invoiceId,
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'status' => 'issued',
        ]);

        $createdAudit = AuditLog::query()
            ->where('action', 'sales_invoice.created')
            ->where('tenant_id', $company->getKey())
            ->where('auditable_id', $invoiceId)
            ->firstOrFail();

        $issuedAudit = AuditLog::query()
            ->where('action', 'sales_invoice.issued')
            ->where('tenant_id', $company->getKey())
            ->where('auditable_id', $invoiceId)
            ->firstOrFail();

        $this->assertSame('draft', $createdAudit->after['status'] ?? null);
        $this->assertSame('issued', $issuedAudit->after['status'] ?? null);
    }

    public function test_create_and_post_sales_invoice_fails_cleanly_without_leaving_a_draft(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-create-post-fail-company');

        $company->forceFill([
            'default_accounts_receivable_account_id' => null,
            'default_sales_income_account_id' => null,
            'default_inventory_asset_account_id' => null,
            'default_cogs_account_id' => null,
            'default_sales_tax_payable_account_id' => null,
        ])->save();

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-CREATE-POST-FAIL',
            'name' => 'Create Post Fail Customer',
        ]);

        $serviceItem = $this->createInventoryItem($company, [
            'code' => 'ITEM-CREATE-POST-FAIL',
            'name' => 'Create Post Fail Service',
            'item_type' => 'service',
            'quantity_on_hand' => 0,
            'sales_price' => 1200,
            'cost_price' => 350,
        ]);

        $this->postJson('/api/v1/sales-invoices', [
            'submit_action' => 'post',
            'invoice_date' => '2026-03-30',
            'due_date' => '2026-04-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $serviceItem->getKey(),
                    'quantity' => 1,
                    'unit_price' => 500,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseMissing('sales_invoices', [
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'sales_invoice.created',
            'tenant_id' => $company->getKey(),
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'sales_invoice.issued',
            'tenant_id' => $company->getKey(),
        ]);
    }

    public function test_issued_sales_invoice_cannot_be_reissued_updated_or_deleted(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-issued-lock');

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-ISSUED-LOCK',
            'name' => 'Issued Lock Customer',
        ]);

        $item = $this->createInventoryItem($company, [
            'code' => 'ITEM-ISSUED-LOCK',
            'name' => 'Issued Lock Item',
            'quantity_on_hand' => 8,
            'sales_price' => 1000,
            'cost_price' => 700,
        ]);

        $createResponse = $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'SI-ISSUED-LOCK-001',
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'quantity' => 2,
                    'unit_price' => 1000,
                ],
            ],
        ]);

        $invoiceId = (string) $createResponse->json('data.id');

        $this->postJson("/api/v1/sales-invoices/{$invoiceId}/issue")
            ->assertOk()
            ->assertJsonPath('data.status', 'issued');

        $this->postJson("/api/v1/sales-invoices/{$invoiceId}/issue")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->putJson("/api/v1/sales-invoices/{$invoiceId}", [
            'invoice_number' => 'SI-ISSUED-LOCK-002',
            'invoice_date' => '2026-03-31',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'quantity' => 1,
                    'unit_price' => 1000,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->deleteJson("/api/v1/sales-invoices/{$invoiceId}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('sales_invoices', [
            'id' => $invoiceId,
            'status' => 'issued',
        ]);
    }

    public function test_cancel_action_allows_draft_and_unpaid_issued_invoices(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-cancel-company');

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-CANCEL',
            'name' => 'Cancellation Customer',
        ]);

        $inventoryItem = $this->createInventoryItem($company, [
            'code' => 'ITEM-CANCEL-DRAFT',
            'name' => 'Draft Cancel Item',
            'quantity_on_hand' => 5,
            'sales_price' => 250,
            'cost_price' => 100,
        ]);

        $serviceItem = $this->createInventoryItem($company, [
            'code' => 'ITEM-CANCEL-SERVICE',
            'name' => 'Cancellation Service',
            'item_type' => 'service',
            'quantity_on_hand' => 0,
            'sales_price' => 800,
            'cost_price' => 200,
        ]);
        $accountsReceivableAccount = $this->createCompanyAccount($company, '1100-CANCEL', 'Accounts Receivable', 'asset');
        $salesIncomeAccount = $this->createCompanyAccount($company, '4100-CANCEL', 'Sales Income', 'revenue');

        $company->forceFill([
            'default_accounts_receivable_account_id' => $accountsReceivableAccount->getKey(),
            'default_sales_income_account_id' => $salesIncomeAccount->getKey(),
        ])->save();

        $draftCreateResponse = $this->postJson('/api/v1/sales-invoices', [
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $inventoryItem->getKey(),
                    'quantity' => 1,
                    'unit_price' => 250,
                ],
            ],
        ]);

        $draftInvoiceId = (string) $draftCreateResponse->json('data.id');

        $this->postJson("/api/v1/sales-invoices/{$draftInvoiceId}/cancel", [
            'cancelled_reason' => 'Customer changed their mind.',
        ])->assertOk()
            ->assertJsonPath('message', 'Sales invoice cancelled')
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.display_status', 'cancelled')
            ->assertJsonPath('data.cancelled_reason', 'Customer changed their mind.')
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.can_issue', false)
            ->assertJsonPath('data.can_cancel', false);

        $this->assertDatabaseHas('sales_invoices', [
            'id' => $draftInvoiceId,
            'status' => 'cancelled',
            'cancelled_reason' => 'Customer changed their mind.',
        ]);

        $issuedCreateResponse = $this->postJson('/api/v1/sales-invoices', [
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $serviceItem->getKey(),
                    'quantity' => 1,
                    'unit_price' => 800,
                ],
            ],
        ]);

        $issuedInvoiceId = (string) $issuedCreateResponse->json('data.id');

        $this->postJson("/api/v1/sales-invoices/{$issuedInvoiceId}/issue")
            ->assertOk();

        $cancelIssuedResponse = $this->postJson("/api/v1/sales-invoices/{$issuedInvoiceId}/cancel", [
            'cancelled_reason' => 'Issued in error.',
        ]);

        $cancelIssuedResponse->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.display_status', 'cancelled')
            ->assertJsonPath('data.cancelled_reason', 'Issued in error.');

        $this->assertIsString($cancelIssuedResponse->json('data.cancelled_at'));
        $this->assertNull($cancelIssuedResponse->json('data.reversal_journal_entry_id'));

        $cancelledAudit = AuditLog::query()
            ->where('action', 'sales_invoice.cancelled')
            ->where('tenant_id', $company->getKey())
            ->where('auditable_id', $issuedInvoiceId)
            ->firstOrFail();

        $this->assertSame((string) $company->getKey(), $cancelledAudit->meta['company_id'] ?? null);
        $this->assertSame('cancelled', $cancelledAudit->after['status'] ?? null);
        $this->assertSame('Cancelled', $cancelledAudit->after['display_status_label'] ?? null);
    }

    public function test_cancel_action_voids_original_accounting_entry_and_restores_stock_for_unpaid_issued_inventory_invoice(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-cancel-reverse-company');

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-CANCEL-REVERSE',
            'name' => 'Cancellation Reversal Customer',
        ]);

        $inventoryItem = $this->createInventoryItem($company, [
            'code' => 'ITEM-CANCEL-REVERSE',
            'name' => 'Reversible Stock Item',
            'quantity_on_hand' => 8,
            'sales_price' => 500,
            'cost_price' => 125,
        ]);
        $accountsReceivableAccount = $this->createCompanyAccount($company, '1100-REV', 'Accounts Receivable', 'asset');
        $salesIncomeAccount = $this->createCompanyAccount($company, '4100-REV', 'Sales Income', 'revenue');
        $inventoryAssetAccount = $this->createCompanyAccount($company, '1200-REV', 'Inventory Asset', 'asset');
        $cogsAccount = $this->createCompanyAccount($company, '5100-REV', 'Cost of Goods Sold', 'expense');

        $company->forceFill([
            'default_accounts_receivable_account_id' => $accountsReceivableAccount->getKey(),
            'default_sales_income_account_id' => $salesIncomeAccount->getKey(),
            'default_inventory_asset_account_id' => $inventoryAssetAccount->getKey(),
            'default_cogs_account_id' => $cogsAccount->getKey(),
        ])->save();

        $createResponse = $this->postJson('/api/v1/sales-invoices', [
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $inventoryItem->getKey(),
                    'quantity' => 2,
                    'unit_price' => 500,
                ],
            ],
        ]);

        $invoiceId = (string) $createResponse->json('data.id');
        $invoiceNumber = (string) $createResponse->json('data.invoice_number');

        $issueResponse = $this->postJson("/api/v1/sales-invoices/{$invoiceId}/issue")
            ->assertOk();

        $originalJournalEntryId = (string) $issueResponse->json('data.journal_entry_id');
        $this->assertSame(6.0, (float) $inventoryItem->fresh()->quantity_on_hand);

        $cancelResponse = $this->postJson("/api/v1/sales-invoices/{$invoiceId}/cancel", [
            'cancelled_reason' => 'Issued to the wrong customer.',
        ]);

        $cancelResponse->assertOk()
            ->assertJsonPath('message', 'Sales invoice cancelled')
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_reason', 'Issued to the wrong customer.');

        $this->assertNull($cancelResponse->json('data.reversal_journal_entry_id'));
        $this->assertSame(8.0, (float) $inventoryItem->fresh()->quantity_on_hand);

        $restoreMovement = InventoryMovement::query()
            ->where('sales_invoice_id', $invoiceId)
            ->where('movement_type', 'sales_invoice_cancel')
            ->firstOrFail();

        $this->assertSame('in', (string) $restoreMovement->direction);
        $this->assertSame(2.0, (float) $restoreMovement->quantity);
        $this->assertSame(6.0, (float) $restoreMovement->quantity_before);
        $this->assertSame(8.0, (float) $restoreMovement->quantity_after);
        $this->assertSame("Restored from cancelled sales invoice {$invoiceNumber}.", (string) $restoreMovement->notes);

        $originalJournalEntry = JournalEntry::query()->findOrFail($originalJournalEntryId);

        $this->assertSame('void', (string) $originalJournalEntry->status);
        $this->assertSame($invoiceNumber, (string) $originalJournalEntry->reference_number);
        $this->assertSame(1, JournalEntry::query()->where('reference_number', $invoiceNumber)->count());
    }

    public function test_voiding_sales_invoice_journal_entry_cancels_invoice_without_creating_a_reversal(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-journal-void-company');
        $this->createOpenPeriod($company, '2026-03-30');

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-JE-VOID',
            'name' => 'Journal Void Customer',
        ]);

        $serviceItem = $this->createInventoryItem($company, [
            'code' => 'ITEM-JE-VOID',
            'name' => 'Journal Void Service',
            'item_type' => 'service',
            'quantity_on_hand' => 0,
            'sales_price' => 700,
            'cost_price' => 0,
        ]);

        $accountsReceivableAccount = $this->createCompanyAccount($company, '1100-JV', 'Accounts Receivable', 'asset');
        $salesIncomeAccount = $this->createCompanyAccount($company, '4100-JV', 'Sales Income', 'revenue');

        $company->forceFill([
            'default_accounts_receivable_account_id' => $accountsReceivableAccount->getKey(),
            'default_sales_income_account_id' => $salesIncomeAccount->getKey(),
        ])->save();

        $createResponse = $this->postJson('/api/v1/sales-invoices', [
            'invoice_date' => '2026-03-30',
            'customer_id' => (string) $customer->getKey(),
            'lines' => [
                [
                    'inventory_item_id' => (string) $serviceItem->getKey(),
                    'quantity' => 1,
                    'unit_price' => 700,
                ],
            ],
        ]);

        $invoiceId = (string) $createResponse->json('data.id');
        $invoiceNumber = (string) $createResponse->json('data.invoice_number');

        $issueResponse = $this->postJson("/api/v1/sales-invoices/{$invoiceId}/issue")
            ->assertOk();

        $journalEntryId = (string) $issueResponse->json('data.journal_entry_id');

        $this->patchJson("/api/v1/journal-entries/{$journalEntryId}/void")
            ->assertOk()
            ->assertJsonPath('message', sprintf(
                'Journal Entry #%s-%s voided',
                (string) JournalEntry::query()->findOrFail($journalEntryId)->prefix,
                (string) JournalEntry::query()->findOrFail($journalEntryId)->entry_number
            ));

        $this->assertDatabaseHas('sales_invoices', [
            'id' => $invoiceId,
            'status' => 'cancelled',
            'cancelled_reason' => 'Voided from linked journal entry.',
            'reversal_journal_entry_id' => null,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $journalEntryId,
            'status' => 'void',
            'reference_number' => $invoiceNumber,
        ]);

        $this->assertSame(1, JournalEntry::query()->where('reference_number', $invoiceNumber)->count());
    }

    public function test_cancel_action_rejects_issued_invoice_with_applied_payments(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-cancel-paid-company');

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-CANCEL-PAID',
            'name' => 'Paid Customer',
        ]);

        $serviceItem = $this->createInventoryItem($company, [
            'code' => 'ITEM-CANCEL-PAID',
            'name' => 'Paid Service',
            'item_type' => 'service',
            'quantity_on_hand' => 0,
            'sales_price' => 500,
            'cost_price' => 100,
        ]);

        $invoice = $this->createSalesInvoice($company, $customer, [
            'invoice_number' => 'SI-CANCEL-PAID-001',
            'status' => 'issued',
            'invoice_date' => '2026-03-30',
            'due_date' => '2026-04-10',
            'issued_at' => now(),
        ], [
            [
                'inventory_item' => $serviceItem,
                'quantity' => 1,
                'unit_price' => 500,
            ],
        ]);

        $this->createPayment($company, $invoice, 100.0, [
            'payment_date' => '2026-04-01',
            'reference_no' => 'PMT-100',
        ]);

        $this->postJson("/api/v1/sales-invoices/{$invoice->getKey()}/cancel", [
            'cancelled_reason' => 'Should fail.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('sales_invoices', [
            'id' => $invoice->getKey(),
            'status' => 'issued',
        ]);
    }

    public function test_sales_invoice_display_status_resolves_overdue_from_due_date(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-overdue-company');

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-OVERDUE',
            'name' => 'Overdue Customer',
        ]);

        $serviceItem = $this->createInventoryItem($company, [
            'code' => 'ITEM-OVERDUE',
            'name' => 'Overdue Service',
            'item_type' => 'service',
            'quantity_on_hand' => 0,
            'sales_price' => 900,
            'cost_price' => 100,
        ]);

        $invoice = $this->createSalesInvoice($company, $customer, [
            'invoice_number' => 'SI-OVERDUE-001',
            'status' => 'issued',
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-15',
            'issued_at' => '2026-03-01 08:00:00',
        ], [
            [
                'inventory_item' => $serviceItem,
                'quantity' => 1,
                'unit_price' => 900,
            ],
        ]);

        $this->getJson("/api/v1/sales-invoices/{$invoice->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.display_status', 'overdue')
            ->assertJsonPath('data.display_status_label', 'Overdue')
            ->assertJsonPath('data.amount_paid', 0)
            ->assertJsonPath('data.balance_due', 900)
            ->assertJsonPath('data.is_overdue', true)
            ->assertJsonPath('data.can_cancel', true);
    }

    public function test_sales_invoice_display_status_resolves_partially_paid_and_paid_from_payments(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-payments-company');

        $customer = $this->createCustomer($company, [
            'code' => 'CUST-PAYMENTS',
            'name' => 'Payments Customer',
        ]);

        $serviceItem = $this->createInventoryItem($company, [
            'code' => 'ITEM-PAYMENTS',
            'name' => 'Payments Service',
            'item_type' => 'service',
            'quantity_on_hand' => 0,
            'sales_price' => 1000,
            'cost_price' => 250,
        ]);

        $invoice = $this->createSalesInvoice($company, $customer, [
            'invoice_number' => 'SI-PAYMENTS-001',
            'status' => 'issued',
            'invoice_date' => '2026-03-30',
            'due_date' => '2026-04-30',
            'issued_at' => now(),
        ], [
            [
                'inventory_item' => $serviceItem,
                'quantity' => 1,
                'unit_price' => 1000,
            ],
        ]);

        $this->createPayment($company, $invoice, 250.0, [
            'payment_date' => '2026-04-01',
            'reference_no' => 'PMT-250',
        ]);

        $this->getJson("/api/v1/sales-invoices/{$invoice->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.display_status', 'partially_paid')
            ->assertJsonPath('data.display_status_label', 'Partially Paid')
            ->assertJsonPath('data.amount_paid', 250)
            ->assertJsonPath('data.balance_due', 750)
            ->assertJsonPath('data.is_overdue', false)
            ->assertJsonPath('data.can_cancel', false);

        $this->createPayment($company, $invoice, 750.0, [
            'payment_date' => '2026-04-10',
            'reference_no' => 'PMT-750',
        ]);

        $this->getJson("/api/v1/sales-invoices/{$invoice->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.display_status', 'paid')
            ->assertJsonPath('data.display_status_label', 'Paid')
            ->assertJsonPath('data.amount_paid', 1000)
            ->assertJsonPath('data.balance_due', 0)
            ->assertJsonPath('data.is_overdue', false)
            ->assertJsonPath('data.can_cancel', false);
    }

    public function test_sales_invoice_mutations_are_audited_with_company_context(): void
    {
        [$owner, $company] = $this->actingAsCompanyOwner('sales-audit-company');

        $customer = $this->createCustomer($company, ['code' => 'AUD-CUST', 'name' => 'Audit Customer']);
        $item = $this->createInventoryItem($company, [
            'code' => 'AUD-ITEM',
            'name' => 'Audit Item',
            'sales_price' => 500,
        ]);

        $createResponse = $this->postJson('/api/v1/sales-invoices', [
            'invoice_number' => 'AUD-SI-1001',
            'invoice_date' => '2026-03-30',
            'due_date' => '2026-04-15',
            'customer_id' => (string) $customer->getKey(),
            'notes' => 'Initial audit draft.',
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'quantity' => 2,
                    'unit_price' => 500,
                ],
            ],
        ]);

        $salesInvoiceId = (string) $createResponse->json('data.id');

        $this->putJson("/api/v1/sales-invoices/{$salesInvoiceId}", [
            'invoice_number' => 'AUD-SI-1001',
            'invoice_date' => '2026-03-31',
            'due_date' => '2026-04-20',
            'customer_id' => (string) $customer->getKey(),
            'notes' => 'Updated audit draft.',
            'lines' => [
                [
                    'inventory_item_id' => (string) $item->getKey(),
                    'quantity' => 3,
                    'unit_price' => 450,
                ],
            ],
        ])->assertOk();

        $this->deleteJson("/api/v1/sales-invoices/{$salesInvoiceId}")
            ->assertOk();

        $createdAudit = AuditLog::query()
            ->where('action', 'sales_invoice.created')
            ->where('tenant_id', $company->getKey())
            ->firstOrFail();

        $updatedAudit = AuditLog::query()
            ->where('action', 'sales_invoice.updated')
            ->where('tenant_id', $company->getKey())
            ->firstOrFail();

        $deletedAudit = AuditLog::query()
            ->where('action', 'sales_invoice.deleted')
            ->where('tenant_id', $company->getKey())
            ->firstOrFail();

        $this->assertSame((string) $company->getKey(), $createdAudit->meta['company_id'] ?? null);
        $this->assertSame('AUD-SI-1001', $createdAudit->after['invoice_number'] ?? null);
        $this->assertSame('2026-04-15', $createdAudit->after['due_date'] ?? null);
        $this->assertSame(1000.0, (float) ($createdAudit->after['subtotal'] ?? 0));

        $this->assertSame((string) $company->getKey(), $updatedAudit->meta['company_id'] ?? null);
        $this->assertSame('Updated audit draft.', $updatedAudit->after['notes'] ?? null);
        $this->assertSame('2026-04-20', $updatedAudit->after['due_date'] ?? null);
        $this->assertSame(1500.0, (float) ($updatedAudit->after['subtotal'] ?? 0));

        $this->assertSame((string) $company->getKey(), $deletedAudit->meta['company_id'] ?? null);
        $this->assertSame($salesInvoiceId, (string) $deletedAudit->auditable_id);
    }

    /**
     * @param array<string, array<int, string>> $modulePermissions
     * @return array{0: Tenant, 1: User}
     */
    private function createCompanyMemberWithRole(array $modulePermissions): array
    {
        [$owner, $company] = $this->createCompany('sales-member-company');
        $member = User::factory()->create([
            'company_id' => $company->getKey(),
        ]);

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
            'key' => 'sales-clerk',
            'name' => 'Sales Clerk',
            'department' => 'Sales',
            'status' => 'active',
            'access_level' => 'read',
            'permission_scope' => 'department',
            'module_permissions' => $modulePermissions,
            'description' => 'Sales role.',
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
        $owner = User::factory()->create([
            'company_id' => null,
        ]);

        $company = Tenant::query()->create([
            'name' => 'Sales '.strtoupper($slug),
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
    private function createInventoryItem(Tenant $company, array $attributes = []): InventoryItem
    {
        return InventoryItem::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'code' => 'ITEM-'.strtoupper(substr((string) str()->uuid(), 0, 8)),
            'name' => 'Item '.substr((string) str()->uuid(), 0, 8),
            'description' => null,
            'status' => 'active',
            'unit_of_measure' => 'PCS',
            'quantity_on_hand' => 0,
            'sales_price' => 100,
            'cost_price' => 75,
            'item_type' => 'inventory',
            'category' => 'Goods',
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createTax(Tenant $company, array $attributes = []): Tax
    {
        return Tax::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'name' => 'VAT',
            'rate' => 12,
            'status' => 'active',
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $invoiceAttributes
     * @param array<int, array<string, mixed>> $lines
     */
    private function createSalesInvoice(Tenant $company, Customer $customer, array $invoiceAttributes = [], array $lines = []): SalesInvoice
    {
        $invoice = SalesInvoice::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'invoice_number' => 'SI-'.strtoupper(substr((string) str()->uuid(), 0, 8)),
            'status' => 'draft',
            'invoice_date' => '2026-03-30',
            'due_date' => null,
            'issued_at' => null,
            'cancelled_at' => null,
            'cancelled_reason' => null,
            'notes' => null,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
        ], $invoiceAttributes));

        $subtotal = 0.0;
        $taxAmount = 0.0;

        foreach ($lines as $index => $line) {
            /** @var InventoryItem $inventoryItem */
            $inventoryItem = $line['inventory_item'];
            $quantity = round((float) ($line['quantity'] ?? 1), 2);
            $unitPrice = round((float) ($line['unit_price'] ?? ($inventoryItem->sales_price ?? 0)), 2);
            $lineSubtotal = round((float) ($line['line_subtotal'] ?? ($quantity * $unitPrice)), 2);
            $tax = $line['tax'] ?? null;
            $taxRate = array_key_exists('tax_rate_snapshot', $line)
                ? $line['tax_rate_snapshot']
                : ($tax instanceof Tax ? (float) $tax->rate : null);
            $lineTaxAmount = round((float) ($line['tax_amount'] ?? ($taxRate === null ? 0 : ($lineSubtotal * ((float) $taxRate / 100)))), 2);
            $lineTotal = round((float) ($line['line_total'] ?? ($lineSubtotal + $lineTaxAmount)), 2);
            $subtotal = round($subtotal + $lineSubtotal, 2);
            $taxAmount = round($taxAmount + $lineTaxAmount, 2);

            $invoice->lines()->create([
                'company_id' => $company->getKey(),
                'inventory_item_id' => $inventoryItem->getKey(),
                'line_number' => $index + 1,
                'item_code_snapshot' => (string) ($line['item_code_snapshot'] ?? $inventoryItem->code),
                'item_name_snapshot' => (string) ($line['item_name_snapshot'] ?? $inventoryItem->name),
                'item_description_snapshot' => $line['item_description_snapshot'] ?? $inventoryItem->description,
                'unit_of_measure_snapshot' => (string) ($line['unit_of_measure_snapshot'] ?? $inventoryItem->unit_of_measure),
                'item_type_snapshot' => (string) ($line['item_type_snapshot'] ?? $inventoryItem->item_type),
                'tax_id' => $tax instanceof Tax ? $tax->getKey() : ($line['tax_id'] ?? null),
                'tax_name_snapshot' => $line['tax_name_snapshot'] ?? ($tax instanceof Tax ? $tax->name : null),
                'tax_rate_snapshot' => $taxRate,
                'unit_cost_snapshot' => array_key_exists('unit_cost_snapshot', $line)
                    ? $line['unit_cost_snapshot']
                    : ($inventoryItem->cost_price === null ? null : (float) $inventoryItem->cost_price),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'tax_amount' => $lineTaxAmount,
                'line_total' => $lineTotal,
            ]);
        }

        if ($lines !== []) {
            $invoice->forceFill([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => round($subtotal + $taxAmount, 2),
            ])->save();
        }

        return $invoice->fresh()->load(['customer', 'lines']);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createPayment(Tenant $company, SalesInvoice $invoice, float $amount, array $attributes = []): SalesInvoicePayment
    {
        return SalesInvoicePayment::query()->create(array_merge([
            'company_id' => $company->getKey(),
            'sales_invoice_id' => $invoice->getKey(),
            'payment_date' => '2026-04-01',
            'amount' => $amount,
            'reference_no' => null,
            'payment_method' => null,
            'notes' => null,
        ], $attributes));
    }

    private function createOpenPeriod(Tenant $company, string $entryDate): void
    {
        $fiscalYear = FiscalYear::query()->create([
            'tenant_id' => $company->getKey(),
            'company_id' => $company->getKey(),
            'code' => 'FY-'.str_replace('-', '', $entryDate),
            'name' => 'Sales Test Fiscal Year',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);

        AccountingPeriod::query()->create([
            'tenant_id' => $company->getKey(),
            'company_id' => $company->getKey(),
            'fiscal_year_id' => $fiscalYear->getKey(),
            'period_number' => 3,
            'name' => 'Mar 2026',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'status' => 'open',
            'lock_ap' => false,
            'lock_ar' => false,
            'lock_inventory' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createCompanyAccount(Tenant $company, string $code, string $name, string $type, array $overrides = []): Account
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
