<?php

namespace Modules\Accounting\Http\Resources\JournalNumbers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UpdateJournalNumberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => "\"$this->name\" updated successfully"
        ];
    }
}
