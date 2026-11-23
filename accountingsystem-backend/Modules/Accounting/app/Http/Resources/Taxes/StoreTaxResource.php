<?php

namespace Modules\Accounting\Http\Resources\Taxes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreTaxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // TODO: Return better creation response
        return [
            'message' => 'Tax "' . $this->name . '" created',
        ];
    }
}
