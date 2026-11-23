<?php

namespace Modules\Sales\Models;

use Modules\Accounting\Models\Taxes\Tax;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\InventoryItem;

/**
 * Frozen sales invoice line snapshot.
 *
 * Each line stores item master snapshots so draft history remains stable even
 * when the underlying product master changes later.
 *
 * @property string $id
 * @property string $sales_invoice_id
 * @property string $company_id
 * @property string $inventory_item_id
 * @property int $line_number
 * @property string $item_code_snapshot
 * @property string $item_name_snapshot
 * @property string|null $item_description_snapshot
 * @property string $unit_of_measure_snapshot
 * @property string $item_type_snapshot
 * @property float|null $unit_cost_snapshot
 * @property float $quantity
 * @property float $unit_price
 * @property float $line_subtotal
 * @property string|null $tax_id
 * @property string|null $tax_name_snapshot
 * @property float|null $tax_rate_snapshot
 * @property float $tax_amount
 * @property float $line_total
 */
final class SalesInvoiceLine extends Model
{
    use HasUuids;

    protected $table = 'sales_invoice_lines';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'sales_invoice_id',
        'company_id',
        'inventory_item_id',
        'line_number',
        'item_code_snapshot',
        'item_name_snapshot',
        'item_description_snapshot',
        'unit_of_measure_snapshot',
        'item_type_snapshot',
        'tax_id',
        'tax_name_snapshot',
        'tax_rate_snapshot',
        'unit_cost_snapshot',
        'quantity',
        'unit_price',
        'line_subtotal',
        'tax_amount',
        'line_total',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'line_number' => 'integer',
        'tax_rate_snapshot' => 'float',
        'unit_cost_snapshot' => 'float',
        'quantity' => 'float',
        'unit_price' => 'float',
        'line_subtotal' => 'float',
        'tax_amount' => 'float',
        'line_total' => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }
}
