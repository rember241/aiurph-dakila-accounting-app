<?php

namespace Modules\Accounting\Http\Resources\FundSources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreFundSourceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => "Fund source \"$this->name\" created successfully.",
        ];
    }
}
