<?php

namespace Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sales invoice line API resource.
 *
 * @mixin \Modules\Sales\Models\SalesInvoiceLine
 */
final class SalesInvoiceLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'sales_invoice_id' => (string) $this->sales_invoice_id,
            'company_id' => (string) $this->company_id,
            'inventory_item_id' => (string) $this->inventory_item_id,
            'line_number' => (int) $this->line_number,
            'item_code_snapshot' => (string) $this->item_code_snapshot,
            'item_name_snapshot' => (string) $this->item_name_snapshot,
            'item_description_snapshot' => $this->item_description_snapshot,
            'unit_of_measure_snapshot' => (string) $this->unit_of_measure_snapshot,
            'item_type_snapshot' => (string) $this->item_type_snapshot,
            'tax_id' => $this->tax_id === null ? null : (string) $this->tax_id,
            'tax_name_snapshot' => $this->tax_name_snapshot,
            'tax_rate_snapshot' => $this->tax_rate_snapshot === null ? null : (float) $this->tax_rate_snapshot,
            'unit_cost_snapshot' => $this->unit_cost_snapshot === null ? null : (float) $this->unit_cost_snapshot,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'line_subtotal' => (float) $this->line_subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'line_total' => (float) $this->line_total,
        ];
    }
}
