<?php

namespace Modules\Accounting\Tests\Feature\Cheques;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\Cheques\ChequeBook;
use Modules\Accounting\Models\Cheques\ChequeTemplate;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Services\Cheques\ChequeDisbursementCommandService;
use Modules\Users\Models\Tenant;
use Tests\TestCase;

final class ChequeDisbursementCommandServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cheque_workflow_controls_printing_posting_and_void_reversal(): void
    {
        $user = User::factory()->create();
        $company = Tenant::query()->create([
            'name' => 'Cheque Co',
            'slug' => 'cheque-co',
            'status' => 'active',
            'timezone' => 'Asia/Manila',
            'owner_user_id' => $user->getKey(),
        ]);

        $bankAccount = $this->createAccount($company, '1100', 'PNB Checking', 'asset');
        $expenseAccount = $this->createAccount($company, '13051', 'Office Supplies', 'expense');

        $book = ChequeBook::query()->create([
            'company_id' => (string) $company->getKey(),
            'bank_account_id' => (string) $bankAccount->getKey(),
            'name' => 'PNB Main Book',
            'bank_name' => 'Philippine National Bank',
            'bank_account_number' => '001-23-45678-9',
            'start_number' => 1001,
            'end_number' => 1005,
            'next_number' => 1001,
            'status' => 'active',
        ]);

        $template = ChequeTemplate::query()->create([
            'company_id' => (string) $company->getKey(),
            'name' => 'PNB Standard',
            'bank_name' => 'Philippine National Bank',
            'width_mm' => 203.2,
            'height_mm' => 76.2,
            'status' => 'active',
            'is_default' => true,
        ]);
        $version = $template->versions()->create([
            'company_id' => (string) $company->getKey(),
            'version' => 1,
            'date_format' => 'MM-DD-YYYY',
            'layout' => ['payee' => ['x_mm' => 42, 'y_mm' => 37]],
            'is_active' => true,
        ]);

        $service = app(ChequeDisbursementCommandService::class);
        $cheque = $service->create((string) $company->getKey(), [
            'cheque_book_id' => (string) $book->getKey(),
            'cheque_template_version_id' => (string) $version->getKey(),
            'cheque_date' => '2026-06-16',
            'payee_name' => 'Manny E. Manuel',
            'amount' => 10000.25,
            'lines' => [
                ['account_id' => (string) $expenseAccount->getKey(), 'description' => 'Supplies', 'amount' => 10000.25],
            ],
        ]);

        $this->assertSame('draft', $cheque->status);
        $this->assertSame('Ten Thousand Pesos and Twenty-Five Centavos Only', $cheque->amount_in_words);

        $cheque = $service->approve($cheque, (string) $user->getKey());
        $cheque = $service->print($cheque, [
            'print_type' => 'test',
            'cheque_template_version_id' => (string) $version->getKey(),
        ], (string) $user->getKey());

        $this->assertNull($cheque->cheque_number);
        $this->assertSame(1001, $book->fresh()->next_number);
        $this->assertSame(1, $cheque->printLogs()->count());

        $cheque = $service->print($cheque, [
            'print_type' => 'final',
            'cheque_template_version_id' => (string) $version->getKey(),
        ], (string) $user->getKey());

        $this->assertSame('printed', $cheque->status);
        $this->assertSame('1001', $cheque->cheque_number);
        $this->assertSame(1002, $book->fresh()->next_number);

        $cheque = $service->post($cheque);

        $this->assertSame('posted', $cheque->status);
        $this->assertNotNull($cheque->journal_entry_id);
        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $cheque->journal_entry_id,
            'account_id' => $expenseAccount->getKey(),
            'type' => 'debit',
            'amount' => 10000.25,
        ]);
        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $cheque->journal_entry_id,
            'account_id' => $bankAccount->getKey(),
            'type' => 'credit',
            'amount' => 10000.25,
        ]);

        $cheque = $service->void($cheque, 'Incorrect payee');

        $this->assertSame('voided', $cheque->status);
        $this->assertNotNull($cheque->reversal_journal_entry_id);
        $this->assertSame(
            2,
            JournalItem::query()->where('journal_entry_id', $cheque->reversal_journal_entry_id)->count()
        );
        $this->assertDatabaseHas('journal_items', [
            'journal_entry_id' => $cheque->reversal_journal_entry_id,
            'account_id' => $expenseAccount->getKey(),
            'type' => 'credit',
            'amount' => 10000.25,
        ]);
    }

    private function createAccount(Tenant $company, string $code, string $name, string $type): Account
    {
        return Account::query()->create([
            'company_id' => (string) $company->getKey(),
            'tenant_id' => (string) $company->getKey(),
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_posting' => true,
            'status' => 'active',
            'is_system' => false,
        ]);
    }
}
