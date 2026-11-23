<?php

namespace Modules\Accounting\Http\Resources\JournalNumbers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IndexJournalNumberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' =>             $this->id,
            'name' =>           $this->name,
            'prefix' =>         $this->prefix,
            'startingNumber' => $this->starting_number,
            'resetInterval' =>  $this->reset_interval,
            'status' =>         $this->status,
            'isUsed' =>         $this->is_used,
        ];
    }
}
