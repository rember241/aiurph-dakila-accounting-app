<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Models\JournalEntries\JournalNumber;
use Modules\Accounting\Models\Taxes\Tax;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoiceLine;
use Modules\Users\Models\Company;

/**
 * Creates financial postings for sales invoice issue and cancellation events.
 */
final class SalesInvoiceAccountingService
{
    /**
     * Creates the financial posting for an issued invoice.
     *
     * Revenue is posted for every line, while COGS and inventory relief are
     * posted only for lines that were snapshotted as stock-tracked inventory.
     */
    public function postIssuedInvoice(SalesInvoice $salesInvoice): JournalEntry
    {
        $salesInvoice->loadMissing(['lines', 'customer']);
        $company = $this->resolveCompanyForPosting($salesInvoice, (float) $salesInvoice->taxAmount() > 0);
        $lines = $salesInvoice->lines->sortBy('line_number')->values();

        return $this->createJournalEntry(
            company: $company,
            entryDate: (string) $salesInvoice->invoice_date->toDateString(),
            referenceNumber: (string) $salesInvoice->invoice_number,
            notes: sprintf('Financial posting for issued sales invoice %s.', (string) $salesInvoice->invoice_number),
            items: $this->buildIssueItems($salesInvoice, $company, $lines)
        );
    }

    /**
     * Creates the reversal journal entry for a cancelled invoice.
     *
     * The original journal entry is left intact for audit history; cancellation
     * is represented by an equal and opposite posting.
     *
     * @deprecated Sales invoice cancellation now voids the original linked
     * journal entry through JournalEntryCommandService instead of creating a
     * second reversal entry.
     */
    public function postCancelledInvoice(SalesInvoice $salesInvoice, string $postingDate): JournalEntry
    {
        $salesInvoice->loadMissing(['lines', 'customer']);
        $company = $this->resolveCompanyForPosting($salesInvoice, (float) $salesInvoice->taxAmount() > 0);
        $lines = $salesInvoice->lines->sortBy('line_number')->values();

        return $this->createJournalEntry(
            company: $company,
            entryDate: $postingDate,
            referenceNumber: (string) $salesInvoice->invoice_number,
            notes: sprintf('Financial reversal for cancelled sales invoice %s.', (string) $salesInvoice->invoice_number),
            items: $this->buildCancellationItems($salesInvoice, $company, $lines)
        );
    }

    /**
     * @param Collection<int, SalesInvoiceLine> $lines
     * @return array<int, array<string, mixed>>
     */
    private function buildIssueItems(SalesInvoice $salesInvoice, Company $company, Collection $lines): array
    {
        $inventoryCost = $this->inventoryCostTotal($lines);
        $taxBreakdown = $this->groupTaxAmounts($lines);
        $items = [
            $this->debitItem((string) $company->default_accounts_receivable_account_id, $salesInvoice->totalAmount(), 'Sales receivable'),
            $this->creditItem((string) $company->default_sales_income_account_id, (float) $salesInvoice->subtotal, 'Sales income'),
        ];

        foreach ($taxBreakdown as $taxLine) {
            $items[] = $this->creditItem(
                (string) $company->default_sales_tax_payable_account_id,
                (float) $taxLine['amount'],
                sprintf('Output tax - %s', (string) $taxLine['name']),
                $taxLine['tax_id']
            );
        }

        if ($inventoryCost > 0) {
            $items[] = $this->debitItem((string) $company->default_cogs_account_id, $inventoryCost, 'Cost of goods sold');
            $items[] = $this->creditItem((string) $company->default_inventory_asset_account_id, $inventoryCost, 'Inventory asset relief');
        }

        return $items;
    }

    /**
     * @param Collection<int, SalesInvoiceLine> $lines
     * @return array<int, array<string, mixed>>
     */
    private function buildCancellationItems(SalesInvoice $salesInvoice, Company $company, Collection $lines): array
    {
        $inventoryCost = $this->inventoryCostTotal($lines);
        $taxBreakdown = $this->groupTaxAmounts($lines);
        $items = [
            $this->creditItem((string) $company->default_accounts_receivable_account_id, $salesInvoice->totalAmount(), 'Reverse sales receivable'),
            $this->debitItem((string) $company->default_sales_income_account_id, (float) $salesInvoice->subtotal, 'Reverse sales income'),
        ];

        foreach ($taxBreakdown as $taxLine) {
            $items[] = $this->debitItem(
                (string) $company->default_sales_tax_payable_account_id,
                (float) $taxLine['amount'],
                sprintf('Reverse output tax - %s', (string) $taxLine['name']),
                $taxLine['tax_id']
            );
        }

        if ($inventoryCost > 0) {
            $items[] = $this->creditItem((string) $company->default_cogs_account_id, $inventoryCost, 'Reverse cost of goods sold');
            $items[] = $this->debitItem((string) $company->default_inventory_asset_account_id, $inventoryCost, 'Restore inventory asset');
        }

        return $items;
    }

    /**
     * @param Collection<int, SalesInvoiceLine> $lines
     * @return array<int, array{tax_id:string,name:string,amount:float}>
     */
    private function groupTaxAmounts(Collection $lines): array
    {
        return $lines
            ->filter(static fn (SalesInvoiceLine $line): bool => $line->tax_id !== null && (float) $line->tax_amount > 0)
            ->groupBy(static fn (SalesInvoiceLine $line): string => (string) $line->tax_id)
            ->map(function (Collection $group, string $taxId): array {
                /** @var SalesInvoiceLine $first */
                $first = $group->first();

                return [
                    'tax_id' => $taxId,
                    'name' => (string) ($first->tax_name_snapshot ?? 'Sales tax'),
                    'amount' => round((float) $group->sum('tax_amount'), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, SalesInvoiceLine> $lines
     */
    private function inventoryCostTotal(Collection $lines): float
    {
        $total = 0.0;

        foreach ($lines as $line) {
            if ((string) $line->item_type_snapshot !== 'inventory') {
                continue;
            }

            if ($line->unit_cost_snapshot === null) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Set a cost price for inventory item %s before issuing this invoice.',
                        (string) $line->item_name_snapshot
                    ),
                ]);
            }

            $total += round((float) $line->quantity * (float) $line->unit_cost_snapshot, 2);
        }

        return round($total, 2);
    }

    /**
     * Loads company accounting defaults required for invoice posting.
     *
     * Missing defaults fail the request before any journal entry is written, so
     * the invoice cannot be partially posted with an incomplete account map.
     */
    private function resolveCompanyForPosting(SalesInvoice $salesInvoice, bool $requiresTaxPayable): Company
    {
        $company = Company::query()
            ->with([
                'defaultAccountsReceivableAccount:id,company_id,code,name,type,status,is_posting',
                'defaultSalesIncomeAccount:id,company_id,code,name,type,status,is_posting',
                'defaultInventoryAssetAccount:id,company_id,code,name,type,status,is_posting',
                'defaultCogsAccount:id,company_id,code,name,type,status,is_posting',
                'defaultSalesTaxPayableAccount:id,company_id,code,name,type,status,is_posting',
            ])
            ->find($salesInvoice->company_id);

        if (!$company instanceof Company) {
            throw ValidationException::withMessages([
                'status' => 'Company settings could not be resolved for this invoice.',
            ]);
        }

        $this->ensurePostingAccountConfigured(
            $company->default_accounts_receivable_account_id,
            'Set a default Accounts Receivable account in Company Settings before issuing invoices.'
        );
        $this->ensurePostingAccountConfigured(
            $company->default_sales_income_account_id,
            'Set a default Sales Income account in Company Settings before issuing invoices.'
        );

        $requiresInventoryAccounts = $salesInvoice->lines->contains(
            static fn (SalesInvoiceLine $line): bool => (string) $line->item_type_snapshot === 'inventory'
        );

        if ($requiresInventoryAccounts) {
            $this->ensurePostingAccountConfigured(
                $company->default_inventory_asset_account_id,
                'Set a default Inventory Asset account in Company Settings before issuing inventory invoices.'
            );
            $this->ensurePostingAccountConfigured(
                $company->default_cogs_account_id,
                'Set a default Cost of Goods Sold account in Company Settings before issuing inventory invoices.'
            );
        }

        if ($requiresTaxPayable) {
            $this->ensurePostingAccountConfigured(
                $company->default_sales_tax_payable_account_id,
                'Set a default Sales Tax Payable account in Company Settings before issuing taxed invoices.'
            );
        }

        return $company;
    }

    private function ensurePostingAccountConfigured(?string $accountId, string $message): void
    {
        if (is_string($accountId) && trim($accountId) !== '') {
            return;
        }

        throw ValidationException::withMessages([
            'status' => $message,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function createJournalEntry(
        Company $company,
        string $entryDate,
        string $referenceNumber,
        string $notes,
        array $items
    ): JournalEntry {
        $journalNumber = $this->resolveJournalNumber($company);
        $fundSource = $this->resolveFundSource($company);
        $amount = round(collect($items)->where('type', 'debit')->sum('amount'), 2);

        $entry = JournalEntry::query()->create([
            'company_id' => (string) $company->getKey(),
            'org_unit_id' => null,
            'prefix' => (string) $journalNumber->prefix,
            'entry_number' => (int) $journalNumber->starting_number,
            'entry_date' => $entryDate,
            'type' => 'general',
            'fund_source_id' => (string) $fundSource->getKey(),
            'reference_number' => $referenceNumber,
            'notes' => $notes,
            'amount' => $amount,
            'status' => 'posted',
        ]);

        foreach ($items as $item) {
            JournalItem::query()->create([
                'journal_entry_id' => (string) $entry->getKey(),
                'account_id' => (string) $item['account_id'],
                'tax_id' => $item['tax_id'] ?? null,
                'description' => $item['description'] ?? null,
                'type' => (string) $item['type'],
                'amount' => (float) $item['amount'],
            ]);
        }

        $journalNumber->forceFill([
            'starting_number' => (int) $journalNumber->starting_number + 1,
            'is_used' => true,
        ])->save();

        $fundSource->forceFill([
            'is_used' => true,
        ])->save();

        $taxIds = collect($items)
            ->pluck('tax_id')
            ->filter()
            ->unique()
            ->values();

        if ($taxIds->isNotEmpty()) {
            Tax::query()
                ->whereIn('id', $taxIds)
                ->where('is_used', false)
                ->update(['is_used' => true]);
        }

        return $entry->load(['journalItems.account', 'journalItems.tax']);
    }

    /**
     * Resolves the journal number series used by automatic sales postings.
     *
     * This is a compatibility path from earlier invoice posting work. New
     * numbering changes should prefer the shared transaction-series allocator.
     */
    private function resolveJournalNumber(Company $company): JournalNumber
    {
        $journalNumber = JournalNumber::query()
            ->withTrashed()
            ->where('company_id', (string) $company->getKey())
            ->where('prefix', 'SINV')
            ->first();

        if (!$journalNumber instanceof JournalNumber) {
            $journalNumber = JournalNumber::query()->create([
                'company_id' => (string) $company->getKey(),
                'name' => 'Sales Invoice Journal',
                'prefix' => 'SINV',
                'status' => 'active',
                'reset_interval' => 'daily',
            ]);
        } elseif ($journalNumber->trashed()) {
            $journalNumber->restore();
        }

        if ((string) $journalNumber->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'The Sales Invoice journal number is inactive. Activate it before issuing invoices.',
            ]);
        }

        return $journalNumber;
    }

    private function resolveFundSource(Company $company): FundSource
    {
        $fundSource = FundSource::query()
            ->withTrashed()
            ->where('company_id', (string) $company->getKey())
            ->where('name', 'Sales Invoices')
            ->first();

        if (!$fundSource instanceof FundSource) {
            $fundSource = FundSource::query()->create([
                'company_id' => (string) $company->getKey(),
                'name' => 'Sales Invoices',
                'status' => 'active',
            ]);
        } elseif ($fundSource->trashed()) {
            $fundSource->restore();
        }

        if ((string) $fundSource->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'The Sales Invoices fund source is inactive. Activate it before issuing invoices.',
            ]);
        }

        return $fundSource;
    }

    /**
     * @return array<string, mixed>
     */
    private function debitItem(string $accountId, float $amount, string $description, ?string $taxId = null): array
    {
        return [
            'account_id' => $accountId,
            'tax_id' => $taxId,
            'description' => $description,
            'type' => 'debit',
            'amount' => round($amount, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function creditItem(string $accountId, float $amount, string $description, ?string $taxId = null): array
    {
        return [
            'account_id' => $accountId,
            'tax_id' => $taxId,
            'description' => $description,
            'type' => 'credit',
            'amount' => round($amount, 2),
        ];
    }
}
