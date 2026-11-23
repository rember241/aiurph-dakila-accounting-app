<?php

namespace Modules\Customers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer API resource.
 *
 * @mixin \Modules\Customers\Models\Customer
 */
final class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'company_id' => (string) $this->company_id,
            'tenant_id' => (string) $this->company_id,
            'code' => (string) $this->code,
            'name' => (string) $this->name,
            'customer_group' => $this->customer_group,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => (string) $this->status,
            'is_active' => (string) $this->status === 'active',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
