<?php

namespace Modules\Accounting\Services\Cheques;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\Cheques\ChequeBook;
use Modules\Accounting\Models\Cheques\ChequeDisbursement;
use Modules\Accounting\Models\Cheques\ChequePrintLog;
use Modules\Accounting\Models\Cheques\ChequeTemplateVersion;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Models\JournalEntries\JournalNumber;
use Modules\Users\Models\Company;

final class ChequeDisbursementCommandService
{
    public function __construct(
        private readonly AmountToWordsService $amountToWordsService
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(string $companyId, array $payload): ChequeDisbursement
    {
        return DB::transaction(function () use ($companyId, $payload): ChequeDisbursement {
            $book = $this->resolveChequeBook($companyId, (string) $payload['cheque_book_id']);
            $bankAccount = $this->resolvePostingAccount($companyId, (string) $book->bank_account_id, ['asset']);
            $lines = $this->normalizeLines($companyId, $payload['lines'] ?? []);
            $amount = round((float) $payload['amount'], 2);
            $this->ensureLinesMatchAmount($lines, $amount);

            $templateVersionId = $payload['cheque_template_version_id'] ?? null;
            if (is_string($templateVersionId) && trim($templateVersionId) !== '') {
                $this->resolveTemplateVersion($companyId, trim($templateVersionId));
            } else {
                $templateVersionId = null;
            }

            $cheque = ChequeDisbursement::query()->create([
                'company_id' => $companyId,
                'cheque_book_id' => (string) $book->getKey(),
                'bank_account_id' => (string) $bankAccount->getKey(),
                'cheque_template_version_id' => $templateVersionId,
                'cheque_date' => (string) $payload['cheque_date'],
                'payee_name' => trim((string) $payload['payee_name']),
                'amount' => $amount,
                'amount_in_words' => $this->amountToWordsService->pesoAmount($amount),
                'crossing_type' => (string) ($payload['crossing_type'] ?? 'account_payee_only'),
                'bearer_action' => (string) ($payload['bearer_action'] ?? 'strike_bearer'),
                'reference_no' => isset($payload['reference_no']) ? trim((string) $payload['reference_no']) : null,
                'memo' => isset($payload['memo']) ? trim((string) $payload['memo']) : null,
                'status' => 'draft',
            ]);

            foreach ($lines as $line) {
                $cheque->lines()->create($line);
            }

            return $this->freshCheque($cheque);
        });
    }

    public function approve(ChequeDisbursement $cheque, ?string $userId): ChequeDisbursement
    {
        if ((string) $cheque->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft cheques can be approved.']);
        }

        $cheque->forceFill([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ])->save();

        return $this->freshCheque($cheque);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function print(ChequeDisbursement $cheque, array $payload, ?string $userId): ChequeDisbursement
    {
        $type = (string) ($payload['print_type'] ?? 'test');

        return DB::transaction(function () use ($cheque, $payload, $userId, $type): ChequeDisbursement {
            /** @var ChequeDisbursement $lockedCheque */
            $lockedCheque = ChequeDisbursement::query()->whereKey($cheque->getKey())->lockForUpdate()->firstOrFail();
            $templateVersion = $this->resolvePrintTemplate($lockedCheque, $payload);

            if ($type === 'test') {
                $this->recordPrintLog($lockedCheque, $templateVersion, $payload, $userId, 'test');

                return $this->freshCheque($lockedCheque);
            }

            if ($type === 'final') {
                if ((string) $lockedCheque->status !== 'approved') {
                    throw ValidationException::withMessages(['status' => 'Only approved cheques can be final-printed.']);
                }

                $book = ChequeBook::query()->whereKey($lockedCheque->cheque_book_id)->lockForUpdate()->firstOrFail();
                if ((string) $book->status !== 'active') {
                    throw ValidationException::withMessages(['cheque_book_id' => 'The selected cheque book is not active.']);
                }

                $chequeNumber = $book->nextChequeNumberLabel();
                $next = (int) $book->next_number + 1;

                $book->forceFill([
                    'next_number' => $next,
                    'status' => $next > (int) $book->end_number ? 'exhausted' : 'active',
                ])->save();

                $lockedCheque->forceFill([
                    'cheque_number' => $chequeNumber,
                    'cheque_template_version_id' => (string) $templateVersion->getKey(),
                    'status' => 'printed',
                    'printed_by' => $userId,
                    'printed_at' => now(),
                ])->save();

                $this->recordPrintLog($lockedCheque, $templateVersion, $payload, $userId, 'final');

                return $this->freshCheque($lockedCheque);
            }

            if ($type === 'reprint') {
                if (! in_array((string) $lockedCheque->status, ['printed', 'posted'], true)) {
                    throw ValidationException::withMessages(['status' => 'Only printed or posted cheques can be reprinted.']);
                }

                if (! isset($payload['reason']) || trim((string) $payload['reason']) === '') {
                    throw ValidationException::withMessages(['reason' => 'A reprint reason is required.']);
                }

                $this->recordPrintLog($lockedCheque, $templateVersion, $payload, $userId, 'reprint');

                return $this->freshCheque($lockedCheque);
            }

            throw ValidationException::withMessages(['print_type' => 'Print type must be test, final or reprint.']);
        });
    }

    public function post(ChequeDisbursement $cheque): ChequeDisbursement
    {
        return DB::transaction(function () use ($cheque): ChequeDisbursement {
            /** @var ChequeDisbursement $lockedCheque */
            $lockedCheque = ChequeDisbursement::query()
                ->with(['lines.account'])
                ->whereKey($cheque->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $lockedCheque->status !== 'printed') {
                throw ValidationException::withMessages(['status' => 'Only final-printed cheques can be posted.']);
            }

            $journalEntry = $this->createPostingJournalEntry($lockedCheque, reverse: false);

            $lockedCheque->forceFill([
                'journal_entry_id' => (string) $journalEntry->getKey(),
                'status' => 'posted',
                'posted_at' => now(),
            ])->save();

            return $this->freshCheque($lockedCheque);
        });
    }

    public function spoil(ChequeDisbursement $cheque, string $reason): ChequeDisbursement
    {
        if (! in_array((string) $cheque->status, ['approved', 'printed'], true)) {
            throw ValidationException::withMessages(['status' => 'Only approved or printed unposted cheques can be spoiled.']);
        }

        $cheque->forceFill([
            'status' => 'spoiled',
            'spoiled_at' => now(),
            'spoil_reason' => trim($reason),
        ])->save();

        return $this->freshCheque($cheque);
    }

    public function void(ChequeDisbursement $cheque, string $reason): ChequeDisbursement
    {
        return DB::transaction(function () use ($cheque, $reason): ChequeDisbursement {
            /** @var ChequeDisbursement $lockedCheque */
            $lockedCheque = ChequeDisbursement::query()
                ->with(['lines.account'])
                ->whereKey($cheque->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array((string) $lockedCheque->status, ['voided', 'spoiled', 'cleared'], true)) {
                throw ValidationException::withMessages(['status' => 'This cheque can no longer be voided.']);
            }

            $reversalId = null;
            if ((string) $lockedCheque->status === 'posted' && $lockedCheque->journal_entry_id !== null) {
                $reversal = $this->createPostingJournalEntry($lockedCheque, reverse: true);
                $reversalId = (string) $reversal->getKey();
            }

            $lockedCheque->forceFill([
                'status' => 'voided',
                'voided_at' => now(),
                'void_reason' => trim($reason),
                'reversal_journal_entry_id' => $reversalId,
            ])->save();

            return $this->freshCheque($lockedCheque);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawLines
     * @return list<array{account_id: string, description: ?string, amount: float}>
     */
    private function normalizeLines(string $companyId, array $rawLines): array
    {
        if ($rawLines === []) {
            throw ValidationException::withMessages(['lines' => 'At least one disbursement line is required.']);
        }

        $lines = [];
        foreach ($rawLines as $index => $line) {
            if (! is_array($line)) {
                throw ValidationException::withMessages(["lines.{$index}" => 'Disbursement line must be an object.']);
            }

            $accountId = trim((string) ($line['account_id'] ?? ''));
            $this->resolvePostingAccount($companyId, $accountId, ['asset', 'liability', 'equity', 'expense']);

            $amount = round((float) ($line['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.amount" => 'Line amount must be greater than zero.']);
            }

            $lines[] = [
                'account_id' => $accountId,
                'description' => isset($line['description']) ? trim((string) $line['description']) : null,
                'amount' => $amount,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{amount: float}>  $lines
     */
    private function ensureLinesMatchAmount(array $lines, float $amount): void
    {
        $lineTotal = round(array_sum(array_map(static fn (array $line): float => (float) $line['amount'], $lines)), 2);

        if (abs($lineTotal - $amount) > 0.009) {
            throw ValidationException::withMessages(['amount' => 'Cheque amount must equal the total of disbursement lines.']);
        }
    }

    /**
     * @param  list<string>  $allowedTypes
     */
    private function resolvePostingAccount(string $companyId, string $accountId, array $allowedTypes): Account
    {
        $account = Account::query()->whereKey($accountId)->first();

        if (! $account instanceof Account || (string) $account->company_id !== $companyId) {
            throw ValidationException::withMessages(['account_id' => 'Selected account must belong to the current company.']);
        }

        if (! in_array((string) $account->type, $allowedTypes, true)) {
            throw ValidationException::withMessages(['account_id' => 'Selected account has an invalid account type for this cheque.']);
        }

        if (! (bool) $account->is_posting || (string) $account->status !== 'active') {
            throw ValidationException::withMessages(['account_id' => 'Selected account must be an active posting account.']);
        }

        return $account;
    }

    private function resolveChequeBook(string $companyId, string $chequeBookId): ChequeBook
    {
        $book = ChequeBook::query()->forCompany($companyId)->whereKey($chequeBookId)->first();

        if (! $book instanceof ChequeBook) {
            throw ValidationException::withMessages(['cheque_book_id' => 'Selected cheque book was not found.']);
        }

        if ((string) $book->status !== 'active') {
            throw ValidationException::withMessages(['cheque_book_id' => 'Selected cheque book is not active.']);
        }

        return $book;
    }

    private function resolveTemplateVersion(string $companyId, string $templateVersionId): ChequeTemplateVersion
    {
        $version = ChequeTemplateVersion::query()
            ->forCompany($companyId)
            ->whereKey($templateVersionId)
            ->where('is_active', true)
            ->first();

        if (! $version instanceof ChequeTemplateVersion) {
            throw ValidationException::withMessages(['cheque_template_version_id' => 'Selected cheque template version was not found.']);
        }

        return $version;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePrintTemplate(ChequeDisbursement $cheque, array $payload): ChequeTemplateVersion
    {
        $templateVersionId = isset($payload['cheque_template_version_id'])
            ? trim((string) $payload['cheque_template_version_id'])
            : (string) $cheque->cheque_template_version_id;

        if ($templateVersionId === '') {
            throw ValidationException::withMessages(['cheque_template_version_id' => 'Select a cheque template before printing.']);
        }

        return $this->resolveTemplateVersion((string) $cheque->company_id, $templateVersionId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordPrintLog(
        ChequeDisbursement $cheque,
        ChequeTemplateVersion $version,
        array $payload,
        ?string $userId,
        string $type
    ): void {
        ChequePrintLog::query()->create([
            'company_id' => (string) $cheque->company_id,
            'cheque_disbursement_id' => (string) $cheque->getKey(),
            'cheque_template_version_id' => (string) $version->getKey(),
            'printed_by' => $userId,
            'print_type' => $type,
            'x_offset_mm' => round((float) ($payload['x_offset_mm'] ?? 0), 2),
            'y_offset_mm' => round((float) ($payload['y_offset_mm'] ?? 0), 2),
            'reason' => isset($payload['reason']) ? trim((string) $payload['reason']) : null,
            'payload_snapshot' => $this->printSnapshot($cheque->fresh(), $version),
            'printed_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function printSnapshot(?ChequeDisbursement $cheque, ChequeTemplateVersion $version): array
    {
        return [
            'cheque_number' => $cheque?->cheque_number,
            'cheque_date' => $cheque?->cheque_date?->toDateString(),
            'payee_name' => $cheque?->payee_name,
            'amount' => $cheque?->amount,
            'amount_in_words' => $cheque?->amount_in_words,
            'crossing_type' => $cheque?->crossing_type,
            'bearer_action' => $cheque?->bearer_action,
            'template_version_id' => (string) $version->getKey(),
            'layout' => $version->layout,
        ];
    }

    private function createPostingJournalEntry(ChequeDisbursement $cheque, bool $reverse): JournalEntry
    {
        $company = Company::query()->find($cheque->company_id);
        if (! $company instanceof Company) {
            throw ValidationException::withMessages(['company_id' => 'Company could not be resolved for cheque posting.']);
        }

        $journalNumber = $this->resolveJournalNumber($company);
        $fundSource = $this->resolveFundSource($company);
        $reference = trim((string) ($cheque->cheque_number ?: $cheque->reference_no ?: substr((string) $cheque->getKey(), 0, 8)));

        $entry = JournalEntry::query()->create([
            'company_id' => (string) $company->getKey(),
            'org_unit_id' => null,
            'prefix' => (string) $journalNumber->prefix,
            'entry_number' => (int) $journalNumber->starting_number,
            'entry_date' => (string) $cheque->cheque_date->toDateString(),
            'type' => 'general',
            'fund_source_id' => (string) $fundSource->getKey(),
            'reference_number' => ($reverse ? 'VOID-' : 'CHK-').$reference,
            'notes' => ($reverse ? 'Reversal for voided cheque payment to ' : 'Cheque payment to ').(string) $cheque->payee_name,
            'amount' => (float) $cheque->amount,
            'status' => 'posted',
        ]);

        foreach ($cheque->lines as $line) {
            JournalItem::query()->create([
                'journal_entry_id' => (string) $entry->getKey(),
                'account_id' => (string) $line->account_id,
                'tax_id' => null,
                'description' => $line->description ?: 'Cheque disbursement',
                'type' => $reverse ? 'credit' : 'debit',
                'amount' => (float) $line->amount,
            ]);
        }

        JournalItem::query()->create([
            'journal_entry_id' => (string) $entry->getKey(),
            'account_id' => (string) $cheque->bank_account_id,
            'tax_id' => null,
            'description' => ($reverse ? 'Reverse cheque payment ' : 'Cheque payment ').$reference,
            'type' => $reverse ? 'debit' : 'credit',
            'amount' => (float) $cheque->amount,
        ]);

        $journalNumber->forceFill([
            'starting_number' => (int) $journalNumber->starting_number + 1,
            'is_used' => true,
        ])->save();

        $fundSource->forceFill(['is_used' => true])->save();

        return $entry->load(['journalItems.account']);
    }

    private function resolveJournalNumber(Company $company): JournalNumber
    {
        $journalNumber = JournalNumber::query()
            ->withTrashed()
            ->where('company_id', (string) $company->getKey())
            ->where('prefix', 'CHK')
            ->first();

        if (! $journalNumber instanceof JournalNumber) {
            $journalNumber = JournalNumber::query()->create([
                'company_id' => (string) $company->getKey(),
                'name' => 'Cheque Disbursement Journal',
                'prefix' => 'CHK',
                'status' => 'active',
                'reset_interval' => 'daily',
            ]);
        } elseif ($journalNumber->trashed()) {
            $journalNumber->restore();
        }

        if ((string) $journalNumber->status !== 'active') {
            throw ValidationException::withMessages(['status' => 'The Cheque Disbursement journal number is inactive.']);
        }

        return $journalNumber;
    }

    private function resolveFundSource(Company $company): FundSource
    {
        $fundSource = FundSource::query()
            ->withTrashed()
            ->where('company_id', (string) $company->getKey())
            ->where('name', 'Cheque Disbursements')
            ->first();

        if (! $fundSource instanceof FundSource) {
            $fundSource = FundSource::query()->create([
                'company_id' => (string) $company->getKey(),
                'name' => 'Cheque Disbursements',
                'status' => 'active',
            ]);
        } elseif ($fundSource->trashed()) {
            $fundSource->restore();
        }

        if ((string) $fundSource->status !== 'active') {
            throw ValidationException::withMessages(['status' => 'The Cheque Disbursements fund source is inactive.']);
        }

        return $fundSource;
    }

    private function freshCheque(ChequeDisbursement $cheque): ChequeDisbursement
    {
        return $cheque->fresh([
            'chequeBook.bankAccount',
            'templateVersion.template',
            'bankAccount',
            'lines.account',
            'printLogs',
            'journalEntry',
            'reversalJournalEntry',
        ]);
    }
}
