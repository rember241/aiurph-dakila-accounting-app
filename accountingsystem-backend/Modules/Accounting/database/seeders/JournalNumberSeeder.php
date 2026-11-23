<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class JournalNumberSeeder extends Seeder
{
    // Pre-defined journal numbers
    private const JOURNAL_NUMBERS = [
        ['name' => 'General Journal Entry', 'prefix' => 'JE'],
        ['name' => 'Adjusting Entry', 'prefix' => 'AJE'],
        ['name' => 'Reversing Entry', 'prefix' => 'RJE'],
        ['name' => 'Closing Entry', 'prefix' => 'CJE'],
        ['name' => 'Recurring Entry', 'prefix' => 'REC'],
        ['name' => 'Accrual Entry', 'prefix' => 'ACCR'],
        ['name' => 'Depreciation Entry', 'prefix' => 'DEP'],
        ['name' => 'Payroll Entry', 'prefix' => 'PAY'],
        ['name' => 'Inventory Entry', 'prefix' => 'INV'],
        ['name' => 'TAX Entry', 'prefix' => 'TAX'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::JOURNAL_NUMBERS as $journalNumber) {
            \DB::table('journal_numbers')->insert([
                'id' => Str::uuid(),
                'company_id' => null,
                'name' => $journalNumber['name'],
                'prefix' => $journalNumber['prefix'],

                'created_at' => now(),
            ]);
        }
    }
}
