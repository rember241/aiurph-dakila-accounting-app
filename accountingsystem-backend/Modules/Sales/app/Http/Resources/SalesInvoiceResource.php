<?php

namespace Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Sales\Enums\SalesInvoiceStatus;

/**
 * Sales invoice API resource.
 *
 * @mixin \Modules\Sales\Models\SalesInvoice
 */
final class SalesInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lineCount = isset($this->lines_count)
            ? (int) $this->lines_count
            : ($this->relationLoaded('lines') ? $this->lines->count() : 0);
        $subtotal = (float) $this->subtotal;
        $taxAmount = $this->resource->taxAmount();
        $totalAmount = $this->resource->totalAmount();
        $amountPaid = $this->resource->amountPaid();
        $balanceDue = $this->resource->balanceDue();
        $displayStatus = $this->resource->displayStatus();

        return [
            'id' => (string) $this->getKey(),
            'company_id' => (string) $this->company_id,
            'tenant_id' => (string) $this->company_id,
            'customer_id' => (string) $this->customer_id,
            'invoice_number' => (string) $this->invoice_number,
            'status' => (string) $this->status,
            'status_label' => SalesInvoiceStatus::label((string) $this->status),
            'display_status' => $displayStatus,
            'display_status_label' => $this->resource->displayStatusLabel(),
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'issued_at' => $this->issued_at?->toISOString(),
            'posted_at' => $this->issued_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancelled_reason' => $this->cancelled_reason,
            'journal_entry_id' => $this->journal_entry_id ? (string) $this->journal_entry_id : null,
            'reversal_journal_entry_id' => $this->reversal_journal_entry_id ? (string) $this->reversal_journal_entry_id : null,
            'accounting_posted_at' => $this->accounting_posted_at?->toISOString(),
            'notes' => $this->notes,
            'subtotal' => $subtotal,
            'subtotal_amount' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'amount_paid' => $amountPaid,
            'balance_due' => $balanceDue,
            'is_overdue' => $this->resource->isOverdue(),
            'can_edit' => $this->resource->canEdit(),
            'can_delete' => $this->resource->canDelete(),
            'can_issue' => $this->resource->canIssue(),
            'can_cancel' => $this->resource->canCancel(),
            'line_count' => $lineCount,
            'company' => $this->whenLoaded('company', function () {
                if (!$this->company) {
                    return null;
                }

                return [
                    'id' => (string) $this->company->getKey(),
                    'name' => (string) $this->company->name,
                    'display_name' => $this->company->display_name === null ? null : (string) $this->company->display_name,
                    'slug' => (string) $this->company->slug,
                    'email' => $this->company->email === null ? null : (string) $this->company->email,
                    'phone' => $this->company->phone === null ? null : (string) $this->company->phone,
                    'address' => $this->company->address === null ? null : (string) $this->company->address,
                    'timezone' => (string) $this->company->timezone,
                    'invoice_header_title' => $this->company->invoice_header_title === null ? null : (string) $this->company->invoice_header_title,
                    'invoice_footer_note' => $this->company->invoice_footer_note === null ? null : (string) $this->company->invoice_footer_note,
                    'invoice_payment_note' => $this->company->invoice_payment_note === null ? null : (string) $this->company->invoice_payment_note,
                ];
            }),
            'customer' => $this->whenLoaded('customer', function () {
                if (!$this->customer) {
                    return null;
                }

                return [
                    'id' => (string) $this->customer->getKey(),
                    'code' => (string) $this->customer->code,
                    'name' => (string) $this->customer->name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
                    'address' => $this->customer->address,
                    'status' => (string) $this->customer->status,
                ];
            }),
            'lines' => $this->whenLoaded('lines', fn () => SalesInvoiceLineResource::collection($this->lines)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
