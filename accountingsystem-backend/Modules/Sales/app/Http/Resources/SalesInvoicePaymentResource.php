<?php

namespace Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Modules\Sales\Models\SalesInvoicePayment
 */
final class SalesInvoicePaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $salesInvoice = $this->relationLoaded('salesInvoice') ? $this->salesInvoice : null;
        $customer = $salesInvoice !== null && $salesInvoice->relationLoaded('customer')
            ? $salesInvoice->customer
            : null;

        return [
            'id' => (string) $this->getKey(),
            'company_id' => (string) $this->company_id,
            'sales_invoice_id' => (string) $this->sales_invoice_id,
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => (float) $this->amount,
            'reference_no' => $this->reference_no,
            'payment_method' => $this->payment_method,
            'payment_channel' => $this->payment_channel,
            'notes' => $this->notes,
            'receipt_account_id' => $this->receipt_account_id === null ? null : (string) $this->receipt_account_id,
            'journal_entry_id' => $this->journal_entry_id === null ? null : (string) $this->journal_entry_id,
            'reversal_journal_entry_id' => $this->reversal_journal_entry_id === null
                ? null
                : (string) $this->reversal_journal_entry_id,
            'accounting_posted_at' => $this->accounting_posted_at?->toISOString(),
            'voided_at' => $this->voided_at?->toISOString(),
            'void_reason' => $this->void_reason,
            'posting_status' => $this->postingStatus(),
            'can_edit' => $this->canEdit(),
            'can_void' => $this->canVoid(),
            'can_delete' => $this->canDelete(),
            'sales_invoice' => $salesInvoice === null ? null : [
                'id' => (string) $salesInvoice->getKey(),
                'invoice_number' => (string) $salesInvoice->invoice_number,
                'invoice_date' => $salesInvoice->invoice_date?->toDateString(),
                'status' => $salesInvoice->status === null ? null : (string) $salesInvoice->status,
                'total_amount' => $salesInvoice->total_amount === null ? null : (float) $salesInvoice->total_amount,
                'customer' => $customer === null ? null : [
                    'id' => (string) $customer->getKey(),
                    'code' => $customer->code === null ? '' : (string) $customer->code,
                    'name' => $customer->name === null ? '' : (string) $customer->name,
                ],
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
