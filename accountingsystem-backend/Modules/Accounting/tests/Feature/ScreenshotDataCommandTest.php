<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Users\Models\Company;
use Tests\TestCase;

class ScreenshotDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_journals_are_balanced_scoped_and_not_duplicated(): void
    {
        $company = Company::create(['name' => 'Screenshot Demo', 'slug' => 'screenshot-demo', 'status' => 'active', 'timezone' => 'Asia/Manila', 'owner_user_id' => User::factory()->create()->id]);
        foreach (['1210','1110','1310','1720','1730','2110','2410','3100','3400','4120','4210','5201','5207','5209','5213','5217','5221'] as $code) {
            Account::factory()->create(['company_id' => $company->id, 'tenant_id' => $company->id, 'code' => $code, 'is_posting' => true]);
        }
        \Modules\Accounting\Models\PeriodManagement\FiscalYear::create(['company_id' => $company->id, 'tenant_id' => $company->id, 'code' => 'FY2027', 'name' => 'FY2027', 'start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'status' => 'open', 'is_current' => true]);
        $args = ['company' => $company->id, '--date' => '2026-11-05'];
        $this->artisan('accounting:screenshot-data', $args)->assertSuccessful();
        $this->assertSame(74, JournalEntry::count());
        $this->assertSame(2, JournalEntry::where('status', 'draft')->count());
        foreach (JournalEntry::with('journalItems')->get() as $entry) {
            $this->assertSame($company->id, $entry->company_id);
            $this->assertTrue($entry->entry_date->toDateString() <= '2026-11-05');
            $this->assertEquals($entry->journalItems->where('type', 'debit')->sum('amount'), $entry->journalItems->where('type', 'credit')->sum('amount'));
        }
        $this->artisan('accounting:screenshot-data', $args)->assertSuccessful();
        $this->assertSame(74, JournalEntry::count());
    }
}
