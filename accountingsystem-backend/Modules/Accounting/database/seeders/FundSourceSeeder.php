<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FundSourceSeeder extends Seeder
{
    private const array FUND_SOURCES = [
        ['name' => 'Bank Loan Payable'],
        ['name' => 'Checking Account'],
        ['name' => 'Notes Payable'],
        ['name' => 'Owner\'s Capital'],
        ['name' => 'Petty Cash'],
        ['name' => 'Sales Revenue'],
        ['name' => 'Savings Account'],
        ['name' => 'Service Revenue'],
        ['name' => 'Share Capital'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed the fund_sources table with the pre-defined tax rates
        foreach (self::FUND_SOURCES as $fundSource) {
            \DB::table('fund_sources')->insert([
                'id' => Str::uuid(),
                'company_id' => null,
                'name' => $fundSource['name'],

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
