<?php

namespace Modules\Accounting\Http\Resources\Cheques;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Accounting\Models\Cheques\ChequeDisbursement;

/**
 * @mixin ChequeDisbursement
 */
final class ChequeDisbursementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'company_id' => (string) $this->company_id,
            'cheque_book_id' => (string) $this->cheque_book_id,
            'bank_account_id' => (string) $this->bank_account_id,
            'cheque_template_version_id' => $this->cheque_template_version_id,
            'journal_entry_id' => $this->journal_entry_id,
            'reversal_journal_entry_id' => $this->reversal_journal_entry_id,
            'cheque_number' => $this->cheque_number,
            'cheque_date' => $this->cheque_date?->toDateString(),
            'payee_name' => (string) $this->payee_name,
            'amount' => (float) $this->amount,
            'amount_in_words' => (string) $this->amount_in_words,
            'crossing_type' => (string) $this->crossing_type,
            'bearer_action' => (string) $this->bearer_action,
            'reference_no' => $this->reference_no,
            'memo' => $this->memo,
            'status' => (string) $this->status,
            'approved_at' => $this->approved_at?->toISOString(),
            'printed_at' => $this->printed_at?->toISOString(),
            'posted_at' => $this->posted_at?->toISOString(),
            'released_at' => $this->released_at?->toISOString(),
            'voided_at' => $this->voided_at?->toISOString(),
            'void_reason' => $this->void_reason,
            'spoiled_at' => $this->spoiled_at?->toISOString(),
            'spoil_reason' => $this->spoil_reason,
            'cheque_book' => $this->whenLoaded('chequeBook', fn (): ?array => $this->chequeBook === null ? null : [
                'id' => (string) $this->chequeBook->getKey(),
                'name' => (string) $this->chequeBook->name,
                'bank_name' => (string) $this->chequeBook->bank_name,
            ]),
            'bank_account' => $this->whenLoaded('bankAccount', fn (): ?array => $this->bankAccount === null ? null : [
                'id' => (string) $this->bankAccount->getKey(),
                'code' => (string) $this->bankAccount->code,
                'name' => (string) $this->bankAccount->name,
            ]),
            'template_version' => $this->whenLoaded('templateVersion', fn (): ?array => $this->templateVersion === null ? null : [
                'id' => (string) $this->templateVersion->getKey(),
                'version' => (int) $this->templateVersion->version,
                'date_format' => (string) $this->templateVersion->date_format,
                'layout' => $this->templateVersion->layout,
                'template' => $this->templateVersion->relationLoaded('template') && $this->templateVersion->template !== null ? [
                    'id' => (string) $this->templateVersion->template->getKey(),
                    'name' => (string) $this->templateVersion->template->name,
                    'bank_name' => (string) $this->templateVersion->template->bank_name,
                    'width_mm' => (float) $this->templateVersion->template->width_mm,
                    'height_mm' => (float) $this->templateVersion->template->height_mm,
                ] : null,
            ]),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line): array => [
                'id' => (string) $line->getKey(),
                'account_id' => (string) $line->account_id,
                'description' => $line->description,
                'amount' => (float) $line->amount,
                'account' => $line->relationLoaded('account') && $line->account !== null ? [
                    'id' => (string) $line->account->getKey(),
                    'code' => (string) $line->account->code,
                    'name' => (string) $line->account->name,
                    'type' => (string) $line->account->type,
                ] : null,
            ])->values()),
            'print_logs' => $this->whenLoaded('printLogs', fn () => $this->printLogs->map(fn ($log): array => [
                'id' => (string) $log->getKey(),
                'print_type' => (string) $log->print_type,
                'x_offset_mm' => (float) $log->x_offset_mm,
                'y_offset_mm' => (float) $log->y_offset_mm,
                'reason' => $log->reason,
                'printed_at' => $log->printed_at?->toISOString(),
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
