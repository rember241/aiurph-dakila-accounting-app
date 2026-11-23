<?php

namespace Modules\Accounting\Services\JournalEntries;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Models\JournalEntries\JournalNumber;
use Modules\Accounting\Models\Taxes\Tax;
use Modules\Accounting\Services\CurrentTenantContext;

/**
 * Handles journal-entry write operations.
 */
final class JournalEntryCommandService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentTenantContext $currentTenantContext
    ) {}

    /**
     * Creates a journal entry with balanced debit and credit lines.
     *
     * The request layer validates the period and balancing rules; this service
     * persists the entry, marks linked setup records as used, and writes the
     * audit trail for the financial transaction.
     */
    public function create(array $payload): JournalEntry
    {
        $companyId = $this->currentTenantContext->companyId();

        // Compute the amount for this journal entry
        $journalItems = collect($payload['journalItems']);
        $taxIds = $journalItems->pluck('taxId')->filter()->unique()->values();
        $payload['amount'] = $this->computeEntryAmount($journalItems, $taxIds);

        $createdEntry = DB::transaction(function () use ($companyId, $payload) {
            // Retrieve the journal number referenced in this entry
            $journalNumber = JournalNumber::find($payload['journalNumberId']);

            // Save the new journal entry in the journal_entries table
            $newEntry = JournalEntry::query()->create([
                'company_id' => $companyId,
                'org_unit_id' => null,
                'prefix' => $journalNumber->prefix,
                'entry_number' => $journalNumber->starting_number,
                'entry_date' => $payload['date'],
                'type' => $payload['type'],
                'fund_source_id' => $payload['fundSourceId'],
                'reference_number' => $payload['referenceNumber'] ?? null,
                'notes' => $payload['note'] ?? null,
                'amount' => $payload['amount'],
                'status' => $payload['status'],
            ]);

            // Save the journal items in the journal_items table
            $this->saveJournalItems($newEntry, $payload['journalItems']);
            $this->markTaxesAsUsed($payload['journalItems']);

            // Increment the starting number of the journal number used and flag it as used
            $journalNumber->update([
                'starting_number' => ++$journalNumber->starting_number,
                'is_used' => true,
            ]);

            // Set the is_used value of the fund source used in the journal entry to true
            FundSource::find($payload['fundSourceId'])
                ->update(['is_used' => true]);

            return $newEntry->load(['journalItems.account', 'orgUnit']);
        });

        // Fields to be used in the audit log
        $currentEntryState = $createdEntry->except(['id', 'created_at', 'updated_at']);
        $currentEntryState['journal_item_count'] = $journalItems->count();
        $currentEntryState['journal_item_account_ids'] = $journalItems
            ->pluck('accountId')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        // Update the audit log
        $this->auditLogService->recordCompanyScoped(
            'journal_entry.created',
            $createdEntry,
            null,
            $currentEntryState,
            [
                'request_id' => request()->header('X-Request-ID'),
            ]
        );

        return $createdEntry;
    }

    /**
     * Replaces the editable journal entry details and line distribution.
     *
     * Existing journal items are deleted and recreated so the stored entry
     * mirrors the submitted accounting distribution exactly.
     */
    public function update(array $payload, JournalEntry $journalEntry): JournalEntry
    {
        // The fields of this entry before the update to be used in the audit log service
        $beforeItems = $journalEntry->journalItems;
        $before = $journalEntry->except(['id', 'created_at', 'updated_at']);
        $before['journal_item_count'] = $beforeItems->count();
        $before['journal_item_account_ids'] = $beforeItems->pluck('account_id')
            ->map(static fn ($id) => (string) $id)->values()->all();

        // Compute the amount for this journal entry
        $journalItems = collect($payload['journalItems']);
        $taxIds = $journalItems->pluck('taxId')->filter()->unique()->values();
        $payload['amount'] = $this->computeEntryAmount($journalItems, $taxIds);

        $updatedEntry = DB::transaction(function () use ($payload, $journalEntry) {
            // Update the values of this entry
            $journalEntry->update([
                'entry_date' => $payload['date'],
                'type' => $payload['type'],
                'fund_source_id' => $payload['fundSourceId'],
                'reference_number' => $payload['referenceNumber'] ?? null,
                'notes' => $payload['note'] ?? null,
                'amount' => $payload['amount'],
                'status' => $payload['status'],
            ]);

            // Remove existing journal items then attach the new ones
            $journalEntry->journalItems()->delete();
            $this->saveJournalItems($journalEntry, $payload['journalItems']);

            // TODO: Add code to undo setting journal number, taxes and fund source's 'is_used' column to true

            return $journalEntry->load(['journalItems.account', 'orgUnit']);
        });

        // The current fields of the journal entry (after the update) for the audit service
        $after = $updatedEntry->except(['id', 'created_at', 'updated_at']);
        $after['journal_item_count'] = $journalItems->count();
        $after['journal_item_account_ids'] = $journalItems
            ->pluck('accountId')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        // Update the audit log
        $this->auditLogService->recordCompanyScoped(
            'journal_entry.updated',
            $updatedEntry,
            $before,
            $after,
            [
                'request_id' => request()->header('X-Request-ID'),
            ]
        );

        return $updatedEntry;
    }

    /**
     * Marks a journal entry as void through the single accounting status path.
     *
     * Source documents such as sales invoices should call this method instead
     * of creating a separate reversal journal when their original posting is
     * the entry being voided.
     */
    public function void(JournalEntry $journalEntry): JournalEntry
    {
        $before = $journalEntry->except(['id', 'created_at', 'updated_at']);

        $voidedEntry = DB::transaction(function () use ($journalEntry): JournalEntry {
            if ((string) $journalEntry->status !== 'void') {
                $journalEntry->update(['status' => 'void']);
            }

            return ($journalEntry->fresh() ?? $journalEntry)->load(['journalItems.account', 'orgUnit']);
        });

        $after = $voidedEntry->except(['id', 'created_at', 'updated_at']);

        if (($before['status'] ?? null) !== ($after['status'] ?? null)) {
            $this->auditLogService->recordCompanyScoped(
                'journal_entry.voided',
                $voidedEntry,
                $before,
                $after,
                [
                    'request_id' => request()->header('X-Request-ID'),
                ]
            );
        }

        return $voidedEntry;
    }

    /**
     * Computes the entry amount from the debit side.
     *
     * The debit side is safe to use because the FormRequest already confirmed
     * that debit and credit totals balance after tax adjustments.
     *
     * @return float|int
     */
    private function computeEntryAmount(Collection $journalItems, Collection $taxIds)
    {
        // Because the total has already been validated as
        // balanced, the debit side is chosen here arbitrarily
        if ($taxIds->isEmpty()) {
            return round($journalItems->sum('debit'), 2);
        } else {
            $total = 0;

            // Fetch the tax rates for all taxes used in this
            // journal entry then compute the total debit
            $taxes = Tax::whereIn('id', $taxIds)->pluck('rate', 'id');

            foreach ($journalItems as $item) {
                $debit = (float) ($item['debit'] ?? 0);
                $taxRate = isset($item['taxId']) ? (float) ($taxes->get($item['taxId']) ?? 0) : 0;

                $total += round($debit + ($debit * $taxRate * 0.01), 2);
            }

            return $total;
        }
    }

    /**
     * Saves journal items for a given journal entry
     */
    private function saveJournalItems(JournalEntry $entry, array $items): void
    {
        foreach ($this->withInputVatItems($entry, $items) as $item) {
            $debit = (float) ($item['debit'] ?? 0);
            $credit = (float) ($item['credit'] ?? 0);

            JournalItem::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $item['accountId'],
                'tax_id' => $item['taxId'] ?? null,
                'description' => $item['description'] ?? null,
                'type' => $debit > 0 ? 'debit' : 'credit',
                'amount' => $debit > 0 ? $debit : $credit,
            ]);
        }
    }

    /**
     * Expands tax-tagged manual journal rows into explicit Input VAT ledger rows.
     *
     * The form already treats tax as an additional debit/credit amount for balancing;
     * the General Ledger also needs a real journal item so Input VAT carries the tax
     * movement instead of burying it inside the expense/asset line.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function withInputVatItems(JournalEntry $entry, array $items): array
    {
        $taxIds = collect($items)->pluck('taxId')->filter()->unique()->values();

        if ($taxIds->isEmpty()) {
            return $items;
        }

        $taxes = Tax::whereIn('id', $taxIds)
            ->get(['id', 'name', 'rate'])
            ->keyBy(fn (Tax $tax): string => (string) $tax->getKey());

        $taxGroups = [];

        foreach ($items as $item) {
            $taxId = (string) ($item['taxId'] ?? '');

            if ($taxId === '' || ! $taxes->has($taxId)) {
                continue;
            }

            $debit = (float) ($item['debit'] ?? 0);
            $credit = (float) ($item['credit'] ?? 0);
            $baseAmount = $debit > 0 ? $debit : $credit;

            if ($baseAmount <= 0) {
                continue;
            }

            $tax = $taxes->get($taxId);
            $taxAmount = round($baseAmount * ((float) $tax->rate * 0.01), 2);

            if ($taxAmount <= 0) {
                continue;
            }

            $side = $debit > 0 ? 'debit' : 'credit';
            $key = $side;
            $taxGroups[$key] ??= [
                'taxNames' => [],
                'side' => $side,
                'amount' => 0.0,
            ];
            $taxGroups[$key]['taxNames'][(string) $tax->name] = (string) $tax->name;
            $taxGroups[$key]['amount'] = round((float) $taxGroups[$key]['amount'] + $taxAmount, 2);
        }

        if ($taxGroups === []) {
            return $items;
        }

        $inputVatAccount = $this->resolveInputVatAccount((string) $entry->company_id);

        foreach ($taxGroups as $key => $taxGroup) {
            $alreadyPostedAmount = $this->existingSubmittedTaxAccountAmount(
                $items,
                (string) $inputVatAccount->getKey(),
                (string) $taxGroup['side']
            );
            $amountToPost = round((float) $taxGroup['amount'] - $alreadyPostedAmount, 2);

            if ($amountToPost <= 0) {
                continue;
            }

            $items[] = [
                'accountId' => (string) $inputVatAccount->getKey(),
                'taxId' => null,
                'description' => sprintf('Input VAT - %s', implode(', ', array_values($taxGroup['taxNames']))),
                'debit' => $taxGroup['side'] === 'debit' ? $amountToPost : 0,
                'credit' => $taxGroup['side'] === 'credit' ? $amountToPost : 0,
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function existingSubmittedTaxAccountAmount(array $items, string $inputVatAccountId, string $side): float
    {
        return round((float) collect($items)
            ->filter(fn (array $item): bool => (string) ($item['accountId'] ?? '') === $inputVatAccountId)
            ->sum(fn (array $item): float => (float) ($item[$side] ?? 0)), 2);
    }

    private function resolveInputVatAccount(string $companyId): Account
    {
        $account = Account::query()
            ->where('company_id', $companyId)
            ->where('code', '3004')
            ->where('type', 'asset')
            ->where('is_posting', true)
            ->where('status', 'active')
            ->first()
            ?? Account::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', ['input vat'])
                ->where('type', 'asset')
                ->where('is_posting', true)
                ->where('status', 'active')
                ->first();

        if (! $account instanceof Account) {
            throw ValidationException::withMessages([
                'journalItems' => 'Set up an active posting Input VAT account before using tax on journal entry lines.',
            ]);
        }

        return $account;
    }

    /**
     * Marks all taxes used in journal items as used
     */
    private function markTaxesAsUsed(array $items): void
    {
        $taxIds = collect($items)
            ->pluck('taxId')
            ->filter()
            ->unique()
            ->values();

        if ($taxIds->isNotEmpty()) {
            Tax::whereIn('id', $taxIds)
                ->where('is_used', false)
                ->update(['is_used' => true]);
        }
    }
}
