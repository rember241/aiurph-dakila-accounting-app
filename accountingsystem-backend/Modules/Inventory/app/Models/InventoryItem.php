<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Users\Models\Company;

/**
 * Company-scoped product and inventory item master data.
 *
 * @property string $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property string $unit_of_measure
 * @property float $quantity_on_hand
 * @property float|null $sales_price
 * @property float|null $cost_price
 * @property \Illuminate\Support\Carbon|null $pricing_as_of
 * @property \Illuminate\Support\Carbon|null $stock_as_of
 * @property int|null $reorder_point
 * @property string $item_type
 * @property string|null $category
 */
final class InventoryItem extends Model
{
    use HasUuids;

    protected $table = 'inventory_items';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'status',
        'unit_of_measure',
        'quantity_on_hand',
        'sales_price',
        'cost_price',
        'pricing_as_of',
        'stock_as_of',
        'reorder_point',
        'item_type',
        'category',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'quantity_on_hand' => 'float',
        'sales_price' => 'float',
        'cost_price' => 'float',
        'pricing_as_of' => 'date',
        'stock_as_of' => 'date',
        'reorder_point' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Stock ledger entries for this item.
     *
     * Inventory reports should read movement history from here instead of
     * trying to infer stock changes from sales documents alone.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id')->orderByDesc('occurred_at');
    }

    /**
     * Applies the mandatory company scope used by all item APIs.
     */
    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function isStockTracked(): bool
    {
        return $this->item_type === 'inventory';
    }

    public function hasReorderPoint(): bool
    {
        return $this->reorder_point !== null;
    }

    public function isLowStock(): bool
    {
        if ($this->status !== 'active' || !$this->isStockTracked() || !$this->hasReorderPoint()) {
            return false;
        }

        return (float) $this->quantity_on_hand <= (float) $this->reorder_point;
    }

    public function stockHealth(): string
    {
        if (!$this->isStockTracked()) {
            return 'not_applicable';
        }

        if ($this->status !== 'active') {
            return 'inactive';
        }

        if (!$this->hasReorderPoint()) {
            return 'reorder_point_missing';
        }

        return $this->isLowStock() ? 'low_stock' : 'healthy';
    }

    public function stockValue(): ?float
    {
        if (!$this->isStockTracked() || $this->cost_price === null) {
            return null;
        }

        return round((float) $this->quantity_on_hand * (float) $this->cost_price, 2);
    }
}
