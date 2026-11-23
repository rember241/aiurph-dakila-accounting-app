<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\JournalEntries\JournalNumber;

/**
 * Allocates company-scoped transaction series numbers safely under row locks.
 *
 * Reset-interval handling is centralized here so future reset logic can be added
 * without changing document modules.
 */
final class TransactionSeriesAllocatorService
{
    /**
     * Allocates the next number from a transaction series under a row lock.
     *
     * Any document workflow that needs an official number should call this
     * inside its own transaction so the document and counter advance together.
     *
     * @return array{series_id:string,prefix:string,sequence_number:int,formatted_number:string}
     */
    public function allocate(JournalNumber|string $transactionSeries): array
    {
        $seriesId = $transactionSeries instanceof JournalNumber
            ? (string) $transactionSeries->getKey()
            : trim((string) $transactionSeries);

        /** @var JournalNumber|null $lockedSeries */
        $lockedSeries = JournalNumber::query()
            ->whereKey($seriesId)
            ->lockForUpdate()
            ->first();

        if (!$lockedSeries instanceof JournalNumber) {
            throw ValidationException::withMessages([
                'transaction_series_id' => 'Select a valid transaction series.',
            ]);
        }

        if ((string) $lockedSeries->status !== 'active') {
            throw ValidationException::withMessages([
                'transaction_series_id' => 'The selected transaction series is inactive.',
            ]);
        }

        $this->applyResetPolicyIfNeeded($lockedSeries);

        $sequenceNumber = max(1, (int) $lockedSeries->starting_number);
        $formattedNumber = $this->formatNumber((string) $lockedSeries->prefix, $sequenceNumber);

        $lockedSeries->forceFill([
            'starting_number' => $sequenceNumber + 1,
            'is_used' => true,
        ])->save();

        return [
            'series_id' => (string) $lockedSeries->getKey(),
            'prefix' => (string) $lockedSeries->prefix,
            'sequence_number' => $sequenceNumber,
            'formatted_number' => $formattedNumber,
        ];
    }

    public function allocateInOwnTransaction(JournalNumber|string $transactionSeries): array
    {
        return DB::transaction(fn (): array => $this->allocate($transactionSeries));
    }

    private function formatNumber(string $prefix, int $sequenceNumber): string
    {
        return sprintf('%s-%s', strtoupper(trim($prefix)), str_pad((string) $sequenceNumber, 4, '0', STR_PAD_LEFT));
    }

    private function applyResetPolicyIfNeeded(JournalNumber $transactionSeries): void
    {
        // Reset intervals are modeled on the series, but the current schema does
        // not yet persist last-reset markers. Centralizing the hook here keeps
        // later reset support additive instead of invasive.
    }
}
