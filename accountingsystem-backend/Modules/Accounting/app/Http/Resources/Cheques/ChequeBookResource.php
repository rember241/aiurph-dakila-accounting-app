<?php

namespace Modules\Accounting\Http\Resources\Cheques;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Accounting\Models\Cheques\ChequeBook;

/**
 * @mixin ChequeBook
 */
final class ChequeBookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'company_id' => (string) $this->company_id,
            'bank_account_id' => (string) $this->bank_account_id,
            'name' => (string) $this->name,
            'bank_name' => (string) $this->bank_name,
            'bank_branch' => $this->bank_branch,
            'bank_account_number' => $this->bank_account_number,
            'cheque_number_prefix' => $this->cheque_number_prefix,
            'start_number' => (int) $this->start_number,
            'end_number' => (int) $this->end_number,
            'next_number' => (int) $this->next_number,
            'next_cheque_number' => $this->nextChequeNumberLabel(),
            'status' => (string) $this->status,
            'bank_account' => $this->whenLoaded('bankAccount', fn (): ?array => $this->bankAccount === null ? null : [
                'id' => (string) $this->bankAccount->getKey(),
                'code' => (string) $this->bankAccount->code,
                'name' => (string) $this->bankAccount->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
