<?php

namespace Modules\Accounting\Services\JournalEntries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Services\CurrentTenantContext;

/**
 * Handles journal-entry read queries in the shared-db company model.
 */
final class JournalEntryQueryService
{
    public function __construct(
        private readonly CurrentTenantContext $currentTenantContext
    )
    {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $companyId = $this->currentTenantContext->companyId();

        // Since the frontend requests 'date' instead of 'entry_date',
        // transform the 'sort' field from the request
        if (isset($filters['sort']) && $filters['sort'] === 'date') $filters['sort'] = 'entry_date';

        return JournalEntry::query()
            ->where('company_id', $companyId)
            ->with(['fundSource', 'journalItems.account', 'journalItems.tax'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $driver = $query->getConnection()->getDriverName();
                $normalized = str_replace('-', '', (string) $search);

                $query->where(function ($searchQuery) use ($driver, $search, $normalized) {
                    if ($driver === 'pgsql') {
                        $searchQuery->whereAny(['prefix', 'entry_number', 'reference_number','notes'],
                            'ilike',
                            "%$search%")
                            // Parses prefix and entry_number columns into a string representation of a
                            // journal number with the format: prefix-entry_number (e.g., GE-0001)
                            ->orWhereRaw(
                                "CONCAT(prefix, '-', LPAD(entry_number::text, 4, '0')) ILIKE ?",
                                ["%{$search}%"]
                            )
                            // Fallback if entered journal number does not contain hyphen
                            ->orWhereRaw(
                                "REPLACE(CONCAT(prefix, '-', LPAD(entry_number::text, 4, '0')), '-', '') ILIKE ?",
                                ["%{$normalized}%"]
                            );

                        return;
                    }

                    $like = '%'.strtolower((string) $search).'%';
                    $normalizedLike = '%'.strtolower($normalized).'%';

                    if ($driver === 'sqlite') {
                        $searchQuery->whereRaw('LOWER(prefix) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(CAST(entry_number AS TEXT)) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(reference_number) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(notes) LIKE ?', [$like])
                            ->orWhereRaw("LOWER(prefix || '-' || printf('%04d', entry_number)) LIKE ?", [$like])
                            ->orWhereRaw("LOWER(REPLACE(prefix || '-' || printf('%04d', entry_number), '-', '')) LIKE ?", [$normalizedLike]);

                        return;
                    }

                    $searchQuery->whereRaw('LOWER(prefix) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(CAST(entry_number AS CHAR)) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(reference_number) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(notes) LIKE ?', [$like])
                        ->orWhereRaw("LOWER(CONCAT(prefix, '-', LPAD(entry_number, 4, '0'))) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(REPLACE(CONCAT(prefix, '-', LPAD(entry_number, 4, '0')), '-', '')) LIKE ?", [$normalizedLike]);
                });
            })
            ->when($filters['type'] ?? null, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy($filters['sort'] ?? 'entry_date',$filters['order'] ?? 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 10);
    }
}
