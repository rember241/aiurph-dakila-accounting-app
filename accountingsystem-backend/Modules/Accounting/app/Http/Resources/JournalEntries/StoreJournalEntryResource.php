<?php

namespace Modules\Accounting\Http\Resources\JournalEntries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreJournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $journalNumber = $this->prefix . '-' . $this->entry_number;

        return [
            'message' => 'Journal Entry #' . $journalNumber . ' created',
            'data' => [
                'id' => (string) $this->id,
                'company_id' => $this->company_id,
                'org_unit_id' => $this->org_unit_id,
            ],
        ];
    }
}
