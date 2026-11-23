<?php

namespace Modules\Accounting\Http\Resources\JournalEntries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FetchEntryForEditResource extends JsonResource
{
    // Disable wrapping the response with a 'data' key
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     * This Resource is only for requesting a single entry when editing
     * them. May need a separate resource for viewing purposes
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'prefix'          => $this->prefix,
            'journalNumber'   => $this->entry_number,
            'date'            => $this->entry_date,
            'type'            => $this->type,
            'fundSourceId'    => $this->fundSource->id,
            'referenceNumber' => $this->reference_number ?? '',
            'note'            => $this->notes ?? '',
            'status'          => $this->status,

            'journalItems' => $this->journalItems->map(function ($item) {
                $isDebit = $item->type === 'debit';

                return [
                    'accountId'   => $item->account->id,
                    'taxId'       => $item->tax?->id ?? '',
                    'description' => $item->description ?? '',
                    'debit'       => $isDebit ? (float) $item->amount : 0.0,
                    'credit'      => $isDebit ? 0.0 : (float) $item->amount,
                ];
            }),
        ];
    }
}
