<?php

namespace Modules\Customers\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\Company;

/**
 * Company-scoped customer master data.
 *
 * @property string $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string|null $customer_group
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string $status
 */
final class Customer extends Model
{
    use HasUuids;

    protected $table = 'customers';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'customer_group',
        'email',
        'phone',
        'address',
        'latitude',
        'longitude',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'latitude'   => 'float',
        'longitude'  => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
