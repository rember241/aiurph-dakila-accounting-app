<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesInvoiceLine;
use Modules\Users\Models\Company;
use Modules\Users\Models\OrgUnit;

/**
 * Company-scoped inventory movement ledger entry.
 *
 * @property string $id
 * @property string $company_id
 * @property string $inventory_item_id
 * @property string|null $org_unit_id
 * @property string|null $sales_invoice_id
 * @property string|null $sales_invoice_line_id
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string $movement_type
 * @property string $direction
 * @property float $quantity
 * @property float $quantity_before
 * @property float $quantity_after
 * @property float|null $unit_cost
 * @property \Illuminate\Support\Carbon|null $movement_date
 * @property string|null $reference_number
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $occurred_at
 */
final class InventoryMovement extends Model
{
    use HasUuids;

    protected $table = 'inventory_movements';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'inventory_item_id',
        'org_unit_id',
        'sales_invoice_id',
        'sales_invoice_line_id',
        'source_type',
        'source_id',
        'movement_type',
        'direction',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'movement_date',
        'reference_number',
        'notes',
        'occurred_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'occurred_at' => 'datetime',
        'movement_date' => 'date',
        'quantity' => 'float',
        'quantity_before' => 'float',
        'quantity_after' => 'float',
        'unit_cost' => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function salesInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceLine::class, 'sales_invoice_line_id');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
