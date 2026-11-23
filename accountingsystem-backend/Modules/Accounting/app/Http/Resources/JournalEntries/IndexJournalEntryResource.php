<?php

namespace Modules\Accounting\Http\Resources\JournalEntries;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IndexJournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prefix' => $this->prefix,
            'journalNumber' => $this->entry_number,
            'date' => $this->entry_date->format('Y-m-d'),
            'type' => $this->type,
            'fundSource' => $this->fundSource->name,
            'referenceNumber' => $this->reference_number ?? '',
            'note' => $this->notes ?? '',
            'amount' => $this->amount,
            'status' => $this->status,

            'journalItems' => $this->journalItems->map(function ($item) {
                return [
                    'accountCode' => $item->account->code,
                    'accountName' => $item->account->name,
                    'taxName'     => $item->tax?->name ?? '',
                    'taxRate'     => $item->tax?->rate ?? 0.0,
                    'description' => $item->description ?? '',
                    'type'        => $item->type,
                    'amount'      => (float) $item->amount,
                ];
            }),
        ];
    }
}
