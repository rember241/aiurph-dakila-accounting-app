<?php

namespace Modules\Sales\Services;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Services\JournalEntries\JournalEntryCommandService;
use Modules\Accounting\Models\Taxes\Tax;
use Modules\Customers\Models\Customer;
use Modules\Inventory\Services\InventoryMovementCommandService;
use Modules\Inventory\Models\InventoryItem;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoiceLine;
use Throwable;

/**
 * Handles company-scoped sales invoice write operations.
 */
final class SalesInvoiceCommandService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentCompanyContext $currentCompanyContext,
        private readonly InventoryMovementCommandService $inventoryMovementCommandService,
        private readonly SalesInvoiceNumberingService $salesInvoiceNumberingService,
        private readonly SalesInvoiceAccountingService $salesInvoiceAccountingService,
        private readonly JournalEntryCommandService $journalEntryCommandService
    ) {
    }

    /**
     * Creates a draft invoice and optionally issues it in the same request.
     *
     * Lines are snapshotted from the item master here so later changes to item
     * names, prices, units, or types do not rewrite the invoice history.
     *
     * @param array<string, mixed> $payload
     * @throws Throwable
     */
    public function create(array $payload): SalesInvoice
    {
        $companyId = $this->currentCompanyContext->companyId();
        $submitAction = $this->resolveSubmitAction($payload);
        $normalizedPayload = $this->normalizePayload($payload);
        $customer = $this->resolveCustomer($companyId, (string) $normalizedPayload['customer_id']);
        $linePayloads = $this->buildLinePayloads($companyId, $normalizedPayload['lines']);
        $subtotal = $this->calculateSubtotal($linePayloads);
        $taxAmount = $this->calculateTaxAmount($linePayloads);
        $totalAmount = $this->calculateTotalAmount($subtotal, $taxAmount);
        $draftSnapshot = null;

        $salesInvoice = DB::transaction(function () use (
            $companyId,
            $customer,
            $normalizedPayload,
            $linePayloads,
            $subtotal,
            $taxAmount,
            $totalAmount,
            $submitAction,
            &$draftSnapshot
        ): SalesInvoice {
            $draftInvoice = $this->createDraftInvoiceRecord(
                companyId: $companyId,
                customer: $customer,
                normalizedPayload: $normalizedPayload,
                linePayloads: $linePayloads,
                subtotal: $subtotal,
                taxAmount: $taxAmount,
                totalAmount: $totalAmount
            );

            $draftSnapshot = $this->snapshot($draftInvoice);

            if ($submitAction !== 'post') {
                return $draftInvoice;
            }

            return $this->issueLockedInvoice($draftInvoice);
        });

        $this->auditLogService->recordCompanyScoped(
            'sales_invoice.created',
            $salesInvoice,
            null,
            $draftSnapshot ?? $this->snapshot($salesInvoice),
            [
                'company_id' => (string) $salesInvoice->company_id,
                'request_id' => request()->header('X-Request-ID'),
            ]
        );

        if ($submitAction === 'post' && $draftSnapshot !== null) {
            $this->recordIssuedAudit($salesInvoice, $draftSnapshot);
        }

        return $salesInvoice;
    }

    /**
     * @param array<string, mixed> $payload
     * @throws Throwable
     */
    public function update(SalesInvoice $salesInvoice, array $payload): SalesInvoice
    {
        $this->ensureCurrentCompanyScope($salesInvoice);
        $this->ensureDraft($salesInvoice);

        $normalizedPayload = $this->normalizePayload($payload);
        $customer = $this->resolveCustomer((string) $salesInvoice->company_id, (string) $normalizedPayload['customer_id']);
        $linePayloads = $this->buildLinePayloads((string) $salesInvoice->company_id, $normalizedPayload['lines']);
        $subtotal = $this->calculateSubtotal($linePayloads);
        $taxAmount = $this->calculateTaxAmount($linePayloads);
        $totalAmount = $this->calculateTotalAmount($subtotal, $taxAmount);

        $salesInvoice->loadMissing(['customer', 'lines'])->loadCount('lines');
        $before = $this->snapshot($salesInvoice);

        $updatedInvoice = DB::transaction(function () use ($salesInvoice, $customer, $normalizedPayload, $linePayloads, $subtotal, $taxAmount, $totalAmount): SalesInvoice {
            $salesInvoice->fill([
                'customer_id' => (string) $customer->getKey(),
                'invoice_date' => (string) $normalizedPayload['invoice_date'],
                'due_date' => $normalizedPayload['due_date'] ?? null,
                'notes' => $normalizedPayload['notes'] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ]);

            if ($salesInvoice->isDirty()) {
                $salesInvoice->save();
            }

            $salesInvoice->lines()->delete();
            $salesInvoice->lines()->createMany($linePayloads);

            return $salesInvoice->fresh()->load(['customer', 'lines'])->loadCount('lines');
        });

        $after = $this->snapshot($updatedInvoice);

        if ($before !== $after) {
            $this->auditLogService->recordCompanyScoped(
                'sales_invoice.updated',
                $updatedInvoice,
                $before,
                $after,
                [
                    'company_id' => (string) $updatedInvoice->company_id,
                    'request_id' => request()->header('X-Request-ID'),
                ]
            );
        }

        return $updatedInvoice;
    }

    /**
     * @throws Throwable
     */
    public function delete(SalesInvoice $salesInvoice): void
    {
        $this->ensureCurrentCompanyScope($salesInvoice);
        $this->ensureDraft($salesInvoice);
        $salesInvoice->loadMissing(['customer', 'lines'])->loadCount('lines');

        DB::transaction(function () use ($salesInvoice): void {
            $before = $this->snapshot($salesInvoice);

            $this->auditLogService->recordCompanyScoped(
                'sales_invoice.deleted',
                $salesInvoice,
                $before,
                [],
                [
                    'company_id' => (string) $salesInvoice->company_id,
                    'request_id' => request()->header('X-Request-ID'),
                ]
            );

            $salesInvoice->delete();
        });
    }

    /**
     * Issues a draft invoice and applies the current downstream side effects.
     *
     * At the time of this handover, issuing also drives inventory movement and
     * accounting entry creation. Treat this as a sensitive workflow boundary if
     * the team later separates issuing from accounting posting.
     *
     * @throws Throwable
     */
    public function issue(SalesInvoice $salesInvoice): SalesInvoice
    {
        $this->ensureCurrentCompanyScope($salesInvoice);
        $this->ensureDraft($salesInvoice);

        $salesInvoice->loadMissing(['customer', 'lines'])->loadCount('lines');
        $before = $this->snapshot($salesInvoice);

        $issuedInvoice = DB::transaction(function () use ($salesInvoice): SalesInvoice {
            return $this->issueLockedInvoice($salesInvoice);
        });

        $this->recordIssuedAudit($issuedInvoice, $before);

        return $issuedInvoice;
    }

    /**
     * Compatibility alias for older callers still using the posting terminology.
     *
     * @throws Throwable
     */
    public function post(SalesInvoice $salesInvoice): SalesInvoice
    {
        return $this->issue($salesInvoice);
    }

    /**
     * @throws Throwable
     */
    public function cancel(SalesInvoice $salesInvoice, ?string $cancelledReason = null): SalesInvoice
    {
        $this->ensureCurrentCompanyScope($salesInvoice);
        $salesInvoice->loadMissing(['customer', 'lines'])->loadCount('lines');
        $before = $this->snapshot($salesInvoice);

        $cancelledInvoice = DB::transaction(function () use ($salesInvoice, $cancelledReason): SalesInvoice {
            /** @var SalesInvoice|null $lockedInvoice */
            $lockedInvoice = SalesInvoice::query()
                ->withSum('activePayments as amount_paid_total', 'amount')
                ->whereKey($salesInvoice->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedInvoice instanceof SalesInvoice) {
                abort(404, 'Sales invoice not found.');
            }

            if ((string) $lockedInvoice->company_id !== $this->currentCompanyContext->companyId()) {
                abort(404, 'Sales invoice not found.');
            }

            if (!$lockedInvoice->canCancel()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft invoices or unpaid issued invoices can be cancelled.',
                ]);
            }

            $cancelSummary = [
                'movement_count' => 0,
                'stock_line_count' => 0,
                'quantity_total' => 0.0,
            ];

            if ((string) $lockedInvoice->status === 'issued') {
                $cancelSummary = $this->inventoryMovementCommandService->cancelSalesInvoice($lockedInvoice);
                $this->voidLinkedJournalEntry($lockedInvoice);
            }

            $lockedInvoice->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_reason' => $cancelledReason === null || trim($cancelledReason) === '' ? null : trim($cancelledReason),
                'reversal_journal_entry_id' => null,
            ])->save();

            $cancelledInvoice = $lockedInvoice->fresh()
                ->load(['customer', 'lines'])
                ->loadCount('lines')
                ->loadSum('activePayments as amount_paid_total', 'amount');

            $cancelledInvoice->setAttribute('cancel_summary', $cancelSummary);

            return $cancelledInvoice;
        });

        $after = $this->snapshot($cancelledInvoice);

        $this->auditLogService->recordCompanyScoped(
            'sales_invoice.cancelled',
            $cancelledInvoice,
            $before,
            $after,
            [
                'company_id' => (string) $cancelledInvoice->company_id,
                'request_id' => request()->header('X-Request-ID'),
                'movement_count' => $cancelledInvoice->getAttribute('cancel_summary')['movement_count'] ?? 0,
                'stock_line_count' => $cancelledInvoice->getAttribute('cancel_summary')['stock_line_count'] ?? 0,
                'quantity_total' => $cancelledInvoice->getAttribute('cancel_summary')['quantity_total'] ?? 0,
            ]
        );

        return $cancelledInvoice;
    }

    private function voidLinkedJournalEntry(SalesInvoice $salesInvoice): void
    {
        $journalEntryId = $salesInvoice->journal_entry_id;

        if (!is_string($journalEntryId) || trim($journalEntryId) === '') {
            return;
        }

        /** @var JournalEntry|null $journalEntry */
        $journalEntry = JournalEntry::query()
            ->whereKey($journalEntryId)
            ->where('company_id', (string) $salesInvoice->company_id)
            ->lockForUpdate()
            ->first();

        if (!$journalEntry instanceof JournalEntry) {
            return;
        }

        $this->journalEntryCommandService->void($journalEntry);
    }

    private function ensureCurrentCompanyScope(SalesInvoice $salesInvoice): void
    {
        $companyId = $this->currentCompanyContext->companyId();

        if ((string) $salesInvoice->company_id === $companyId) {
            return;
        }

        abort(404, 'Sales invoice not found.');
    }

    private function ensureDraft(SalesInvoice $salesInvoice): void
    {
        if ((string) $salesInvoice->status === 'draft') {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Only draft invoices can be changed.',
        ]);
    }

    private function resolveSubmitAction(array $payload): string
    {
        $submitAction = $payload['submit_action'] ?? 'draft';

        return is_string($submitAction) && trim($submitAction) !== '' ? trim($submitAction) : 'draft';
    }

    /**
     * @param array<string, mixed> $normalizedPayload
     * @param array<int, array<string, mixed>> $linePayloads
     */
    private function createDraftInvoiceRecord(
        string $companyId,
        Customer $customer,
        array $normalizedPayload,
        array $linePayloads,
        float $subtotal,
        float $taxAmount,
        float $totalAmount
    ): SalesInvoice {
        $invoiceNumber = $this->salesInvoiceNumberingService->allocate($companyId);

        /** @var SalesInvoice $salesInvoice */
        $salesInvoice = SalesInvoice::query()->create([
            'company_id' => $companyId,
            'customer_id' => (string) $customer->getKey(),
            'invoice_number' => $invoiceNumber,
            'status' => 'draft',
            'invoice_date' => (string) $normalizedPayload['invoice_date'],
            'due_date' => $normalizedPayload['due_date'] ?? null,
            'notes' => $normalizedPayload['notes'] ?? null,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);

        $salesInvoice->lines()->createMany($linePayloads);

        return $salesInvoice->load(['customer', 'lines'])->loadCount('lines');
    }

    /**
     * Runs the issue transition under a row lock so the invoice cannot be
     * issued twice by overlapping requests.
     */
    private function issueLockedInvoice(SalesInvoice $salesInvoice): SalesInvoice
    {
        /** @var SalesInvoice|null $lockedInvoice */
        $lockedInvoice = SalesInvoice::query()
            ->whereKey($salesInvoice->getKey())
            ->lockForUpdate()
            ->first();

        if (!$lockedInvoice instanceof SalesInvoice) {
            abort(404, 'Sales invoice not found.');
        }

        if ((string) $lockedInvoice->company_id !== $this->currentCompanyContext->companyId()) {
            abort(404, 'Sales invoice not found.');
        }

        if ((string) $lockedInvoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft invoices can be issued.',
            ]);
        }

        $lockedInvoice->load(['customer', 'lines'])->loadCount('lines');

        $movementSummary = $this->inventoryMovementCommandService->issueSalesInvoice($lockedInvoice);
        $issuedAt = now();

        $journalEntry = $this->salesInvoiceAccountingService->postIssuedInvoice($lockedInvoice);

        $lockedInvoice->forceFill([
            'status' => 'issued',
            'issued_at' => $issuedAt,
            'journal_entry_id' => (string) $journalEntry->getKey(),
            'accounting_posted_at' => $issuedAt,
        ])->save();

        $issuedInvoice = $lockedInvoice->fresh()->load(['customer', 'lines'])->loadCount('lines');
        $issuedInvoice->setAttribute('issue_summary', $movementSummary);

        return $issuedInvoice;
    }

    /**
     * @param array<string, mixed> $before
     */
    private function recordIssuedAudit(SalesInvoice $issuedInvoice, array $before): void
    {
        $after = $this->snapshot($issuedInvoice);

        $this->auditLogService->recordCompanyScoped(
            'sales_invoice.issued',
            $issuedInvoice,
            $before,
            $after,
            [
                'company_id' => (string) $issuedInvoice->company_id,
                'request_id' => request()->header('X-Request-ID'),
                'movement_count' => $issuedInvoice->getAttribute('issue_summary')['movement_count'] ?? 0,
                'stock_line_count' => $issuedInvoice->getAttribute('issue_summary')['stock_line_count'] ?? 0,
                'quantity_total' => $issuedInvoice->getAttribute('issue_summary')['quantity_total'] ?? 0,
            ]
        );
    }

    /**
     * Resolves the customer against the current company and active status.
     *
     * Sales documents should never cross company boundaries, even if a crafted
     * payload contains a valid customer UUID from another company.
     */
    private function resolveCustomer(string $companyId, string $customerId): Customer
    {
        $customer = Customer::query()
            ->forCompany($companyId)
            ->whereKey($customerId)
            ->first();

        if (!$customer instanceof Customer || (string) $customer->status !== 'active') {
            throw ValidationException::withMessages([
                'customer_id' => 'Select a valid active customer for this company.',
            ]);
        }

        return $customer;
    }

    /**
     * Converts front-end line selections into durable invoice line snapshots.
     *
     * This is where product master data, tax setup, pricing, and unit cost are
     * frozen onto the invoice so reports and printouts remain stable later.
     *
     * @param string $companyId
     * @param array<int, array<string, mixed>> $lines
     * @return array<int, array<string, mixed>>
     */
    private function buildLinePayloads(string $companyId, array $lines): array
    {
        $itemIds = array_values(array_unique(array_map(
            static fn (array $line): string => (string) $line['inventory_item_id'],
            $lines
        )));
        $taxIds = array_values(array_unique(array_filter(array_map(
            static fn (array $line): ?string => isset($line['tax_id']) && $line['tax_id'] !== null && trim((string) $line['tax_id']) !== ''
                ? trim((string) $line['tax_id'])
                : null,
            $lines
        ))));

        $items = InventoryItem::query()
            ->forCompany($companyId)
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy(fn (InventoryItem $item) => (string) $item->getKey());
        $taxes = Tax::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $taxIds)
            ->get()
            ->keyBy(fn (Tax $tax) => (string) $tax->getKey());

        $linePayloads = [];

        foreach ($lines as $index => $line) {
            $inventoryItemId = (string) $line['inventory_item_id'];
            $inventoryItem = $items->get($inventoryItemId);

            if (
                !$inventoryItem instanceof InventoryItem
                || (string) $inventoryItem->status !== 'active'
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.inventory_item_id" => 'Select a valid active item for this company.',
                ]);
            }

            $quantity = round((float) $line['quantity'], 2);
            $salesPrice = $inventoryItem->sales_price;
            $taxId = isset($line['tax_id']) && $line['tax_id'] !== null && trim((string) $line['tax_id']) !== ''
                ? trim((string) $line['tax_id'])
                : null;

            if ($salesPrice === null) {
                throw ValidationException::withMessages([
                    "lines.{$index}.inventory_item_id" => 'No sales price is set for this item in Products / Items.',
                ]);
            }

            $tax = $taxId === null ? null : $taxes->get($taxId);

            if ($taxId !== null && (!$tax instanceof Tax || (string) $tax->status !== 'active')) {
                throw ValidationException::withMessages([
                    "lines.{$index}.tax_id" => 'Select a valid active tax for this company.',
                ]);
            }

            $unitPrice = round((float) $salesPrice, 2);
            $lineSubtotal = round($quantity * $unitPrice, 2);
            $taxRate = $tax ? round((float) $tax->rate, 4) : null;
            $taxAmount = $taxRate === null ? 0.0 : round($lineSubtotal * ($taxRate / 100), 2);
            $lineTotal = round($lineSubtotal + $taxAmount, 2);

            $linePayloads[] = [
                'company_id' => $companyId,
                'inventory_item_id' => $inventoryItemId,
                'line_number' => $index + 1,
                'item_code_snapshot' => (string) $inventoryItem->code,
                'item_name_snapshot' => (string) $inventoryItem->name,
                'item_description_snapshot' => $inventoryItem->description,
                'unit_of_measure_snapshot' => (string) $inventoryItem->unit_of_measure,
                'item_type_snapshot' => (string) $inventoryItem->item_type,
                'tax_id' => $tax?->getKey(),
                'tax_name_snapshot' => $tax ? (string) $tax->name : null,
                'tax_rate_snapshot' => $taxRate,
                'unit_cost_snapshot' => $inventoryItem->cost_price === null ? null : (float) $inventoryItem->cost_price,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
            ];
        }

        return $linePayloads;
    }

    /**
     * @param array<int, array<string, mixed>> $linePayloads
     */
    private function calculateSubtotal(array $linePayloads): float
    {
        return round(array_reduce(
            $linePayloads,
            static fn (float $carry, array $line): float => $carry + (float) $line['line_subtotal'],
            0.0
        ), 2);
    }

    /**
     * @param array<int, array<string, mixed>> $linePayloads
     */
    private function calculateTaxAmount(array $linePayloads): float
    {
        return round(array_reduce(
            $linePayloads,
            static fn (float $carry, array $line): float => $carry + (float) ($line['tax_amount'] ?? 0),
            0.0
        ), 2);
    }

    private function calculateTotalAmount(float $subtotal, float $taxAmount): float
    {
        return round($subtotal + $taxAmount, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(SalesInvoice $salesInvoice): array
    {
        $salesInvoice->loadMissing(['customer', 'lines'])->loadCount('lines');

        return [
            'id' => (string) $salesInvoice->getKey(),
            'company_id' => (string) $salesInvoice->company_id,
            'customer_id' => (string) $salesInvoice->customer_id,
            'customer_code' => $salesInvoice->customer ? (string) $salesInvoice->customer->code : null,
            'customer_name' => $salesInvoice->customer ? (string) $salesInvoice->customer->name : null,
            'invoice_number' => (string) $salesInvoice->invoice_number,
            'status' => (string) $salesInvoice->status,
            'invoice_date' => $salesInvoice->invoice_date?->toDateString(),
            'due_date' => $salesInvoice->due_date?->toDateString(),
            'issued_at' => $salesInvoice->issued_at?->toISOString(),
            'cancelled_at' => $salesInvoice->cancelled_at?->toISOString(),
            'cancelled_reason' => $salesInvoice->cancelled_reason,
            'journal_entry_id' => $salesInvoice->journal_entry_id ? (string) $salesInvoice->journal_entry_id : null,
            'reversal_journal_entry_id' => $salesInvoice->reversal_journal_entry_id ? (string) $salesInvoice->reversal_journal_entry_id : null,
            'notes' => $salesInvoice->notes,
            'subtotal' => (float) $salesInvoice->subtotal,
            'tax_amount' => $salesInvoice->taxAmount(),
            'total_amount' => $salesInvoice->totalAmount(),
            'amount_paid' => $salesInvoice->amountPaid(),
            'balance_due' => $salesInvoice->balanceDue(),
            'display_status' => $salesInvoice->displayStatus(),
            'display_status_label' => $salesInvoice->displayStatusLabel(),
            'is_overdue' => $salesInvoice->isOverdue(),
            'can_edit' => $salesInvoice->canEdit(),
            'can_delete' => $salesInvoice->canDelete(),
            'can_issue' => $salesInvoice->canIssue(),
            'can_cancel' => $salesInvoice->canCancel(),
            'line_count' => isset($salesInvoice->lines_count) ? (int) $salesInvoice->lines_count : $salesInvoice->lines->count(),
            'lines' => $salesInvoice->lines
                ->sortBy('line_number')
                ->values()
                ->map(static fn (SalesInvoiceLine $line): array => [
                    'inventory_item_id' => (string) $line->inventory_item_id,
                    'line_number' => (int) $line->line_number,
                    'item_code_snapshot' => (string) $line->item_code_snapshot,
                    'item_name_snapshot' => (string) $line->item_name_snapshot,
                    'item_description_snapshot' => $line->item_description_snapshot,
                    'unit_of_measure_snapshot' => (string) $line->unit_of_measure_snapshot,
                    'item_type_snapshot' => (string) $line->item_type_snapshot,
                    'tax_id' => $line->tax_id === null ? null : (string) $line->tax_id,
                    'tax_name_snapshot' => $line->tax_name_snapshot,
                    'tax_rate_snapshot' => $line->tax_rate_snapshot === null ? null : (float) $line->tax_rate_snapshot,
                    'unit_cost_snapshot' => $line->unit_cost_snapshot === null ? null : (float) $line->unit_cost_snapshot,
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'line_subtotal' => (float) $line->line_subtotal,
                    'tax_amount' => (float) $line->tax_amount,
                    'line_total' => (float) $line->line_total,
                ])
                ->all(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        return [
            'invoice_date' => trim((string) $payload['invoice_date']),
            'due_date' => array_key_exists('due_date', $payload) && $payload['due_date'] !== null
                ? trim((string) $payload['due_date'])
                : null,
            'customer_id' => trim((string) $payload['customer_id']),
            'notes' => array_key_exists('notes', $payload) && $payload['notes'] !== null
                ? trim((string) $payload['notes'])
                : null,
            'lines' => array_map(static fn (array $line): array => [
                'inventory_item_id' => trim((string) $line['inventory_item_id']),
                'tax_id' => array_key_exists('tax_id', $line) && $line['tax_id'] !== null
                    ? trim((string) $line['tax_id'])
                    : null,
                'quantity' => (float) $line['quantity'],
            ], $payload['lines'] ?? []),
        ];
    }
}
