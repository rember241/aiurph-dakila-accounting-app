<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Users\Models\Company;

class SeedScreenshotData extends Command
{
    protected $signature = 'accounting:screenshot-data {company : Explicit company UUID} {--date= : Reporting date (YYYY-MM-DD)} {--allow-production : Explicitly allow demo data in a production-configured database}';
    protected $description = 'Add balanced, labeled screenshot journals to an empty company without changing existing records';

    public function handle(): int
    {
        if (app()->environment('production') && !$this->option('allow-production')) {
            $this->error('Production configuration detected. Explicit approval and --allow-production are required.');
            return self::FAILURE;
        }
        $company = Company::findOrFail($this->argument('company'));
        $dateText = $this->option('date') ?: now('Asia/Manila')->toDateString();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateText) || !Carbon::hasFormat($dateText, 'Y-m-d')) {
            $this->error('Use a valid YYYY-MM-DD reporting date.');
            return self::FAILURE;
        }
        $asOf = Carbon::createFromFormat('!Y-m-d', $dateText, 'Asia/Manila');
        $result = DB::transaction(function () use ($company, $asOf) {
            Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $existing = JournalEntry::where('company_id', $company->id);
            if ((clone $existing)->where('reference_number', 'like', 'SCREENSHOT-%')->exists()) {
                $this->closePriorDemoYear($company, $asOf);
                return 'Screenshot data already exists; verified prior-year demo closing.';
            }
            if ($existing->exists()) {
                throw new \RuntimeException('Refusing to mix screenshot data with existing journal entries.');
            }
            $codes = ['1210','1110','1310','1720','1730','2110','2410','3100','4120','4210','5201','5207','5209','5213','5217','5221'];
            $accounts = Account::where('company_id', $company->id)->where('is_posting', true)->whereIn('code', $codes)->get()->keyBy('code');
            foreach ($codes as $code) {
                if (!$accounts->has($code)) throw new \RuntimeException("Required posting account {$code} is missing.");
            }
            $fund = FundSource::firstOrCreate(['company_id' => $company->id, 'name' => 'Demo · Operating Fund'], ['status' => 'active']);
            $sequence = 0;
            $write = function (Carbon $date, string $description, string $debit, string $credit, int $amount, string $status = 'posted') use (&$sequence, $company, $fund, $accounts) {
                ++$sequence;
                $entry = JournalEntry::create([
                    'company_id' => $company->id, 'prefix' => 'DEMO', 'entry_number' => $sequence,
                    'entry_date' => $date->toDateString(), 'type' => 'general', 'fund_source_id' => $fund->id,
                    'reference_number' => sprintf('SCREENSHOT-%03d', $sequence),
                    'notes' => "[Demo] {$description}", 'amount' => $amount, 'status' => $status,
                ]);
                foreach ([[$debit, 'debit'], [$credit, 'credit']] as [$code, $side]) {
                    JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $accounts[$code]->id,
                        'description' => $description, 'type' => $side, 'amount' => $amount]);
                }
            };
            $first = $asOf->copy()->startOfMonth()->subMonthsNoOverflow(5);
            $write($first, 'Founders’ capital contribution', '1210', '3100', 850000);
            $write($first, 'Business expansion financing', '1210', '2410', 240000);
            $write($first, 'Workstations and studio equipment', '1720', '1210', 185000);
            $write($first, 'Office furnishings', '1730', '2110', 68000);
            $write($first, 'Petty cash allocation', '1110', '1210', 15000);
            for ($month = 0; $month < 6; $month++) {
                $start = $first->copy()->addMonthsNoOverflow($month);
                $day = fn (int $d) => $start->copy()->day(min($d, $month === 5 ? $asOf->day : $start->daysInMonth));
                $write($day(2), 'Monthly advisory retainers', '1210', '4120', 145000 + $month * 12000);
                $write($day(4), 'Digital transformation consulting', '1310', '4120', 92000 + $month * 8500);
                $write($day(8), 'Project milestone collection', '1210', '1310', 72000 + $month * 6500);
                $write($day(10), 'Team payroll', '5213', '1210', 76000 + $month * 2500);
                $write($day(12), 'Office lease', '5209', '1210', 22500);
                $write($day(15), 'Software and administrative services', '5201', '1210', 12800 + $month * 600);
                $write($day(18), 'Brand and community campaign', '5207', '1210', 8500 + $month * 450);
                $write($day(20), 'Internet and utilities', '5217', '2110', 6200 + $month * 180);
                $write($day(23), 'Supplier settlement', '2110', '1210', 4500);
                $write($day(25), 'Bank processing charges', '5221', '1210', 380 + $month * 25);
                $write($day(26), 'Interest earned on operating funds', '1210', '4210', 850 + $month * 90);
            }
            $write($asOf, 'Office supplies — awaiting review', '5201', '2110', 3850, 'draft');
            $write($asOf, 'Campaign accrual — awaiting review', '5207', '2110', 7200, 'draft');
            $this->closePriorDemoYear($company, $asOf);
            return "Created {$sequence} balanced demo journals for {$company->name}, through {$asOf->toDateString()}.";
        });
        $this->info($result);
        return self::SUCCESS;
    }
    private function closePriorDemoYear(Company $company, Carbon $asOf): void
    {
        $start = \Modules\Accounting\Models\PeriodManagement\FiscalYear::where('company_id', $company->id)
            ->whereDate('start_date', '<=', $asOf)->whereDate('end_date', '>=', $asOf)->value('start_date');
        if (!$start) return;
        $reference = 'SCREENSHOT-YEAR-CLOSE';
        if (JournalEntry::where('company_id', $company->id)->where('reference_number', $reference)->exists()) return;
        $rows = DB::table('journal_items as i')->join('journal_entries as j', 'j.id', '=', 'i.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'i.account_id')
            ->where('j.company_id', $company->id)->where('j.reference_number', 'like', 'SCREENSHOT-%')
            ->where('j.status', 'posted')->whereDate('j.entry_date', '<', $start)
            ->whereIn('a.type', ['revenue', 'expense'])->groupBy('a.id')
            ->select('a.id')->selectRaw("SUM(CASE WHEN i.type = 'credit' THEN i.amount ELSE -i.amount END) as net_credit")->get();
        if ($rows->isEmpty()) return;
        $retained = Account::where('company_id', $company->id)->where('code', '3400')->where('is_posting', true)->firstOrFail();
        $total = round($rows->sum('net_credit'), 2);
        $entry = JournalEntry::create(['company_id' => $company->id, 'prefix' => 'DEMO', 'entry_number' => 74,
            'entry_date' => Carbon::parse($start)->subDay()->toDateString(), 'type' => 'general', 'status' => 'posted',
            'fund_source_id' => FundSource::where('company_id', $company->id)->where('name', 'Demo · Operating Fund')->value('id'),
            'reference_number' => $reference, 'notes' => '[Demo] Prior-year earnings transferred to retained earnings',
            'amount' => $rows->filter(fn ($row) => $row->net_credit > 0)->sum('net_credit') + max(-$total, 0)]);
        foreach ($rows as $row) {
            if (abs((float) $row->net_credit) < 0.01) continue;
            JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $row->id,
                'description' => 'Demo prior-year income and expense closing',
                'type' => $row->net_credit > 0 ? 'debit' : 'credit', 'amount' => abs($row->net_credit)]);
        }
        if (abs($total) >= 0.01) JournalItem::create(['journal_entry_id' => $entry->id, 'account_id' => $retained->id,
            'description' => 'Demo retained earnings brought forward', 'type' => $total > 0 ? 'credit' : 'debit', 'amount' => abs($total)]);
    }

}
