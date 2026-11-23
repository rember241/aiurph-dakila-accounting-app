<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TaxSeeder extends Seeder
{
    // Pre-defined tax rates based on current Philipine tax rates (as of Feb. 27 2026)
    private const TAX_RATES = [
        ['name' => 'Income Tax', 'rate' => 20.00],
        ['name' => 'VAT', 'rate' => 12.00],
        ['name' => 'Withholding Tax','rate' =>  8.00],
    ];

    /**
     * Run the database seeds
     */
    public function run(): void
    {
        // Seed the tax_rates table with the pre-defined tax rates
        foreach (self::TAX_RATES as $rate) {
            \DB::table('taxes')->insert([
                'id' => Str::uuid(),
                'company_id' => null,
                'name' => $rate['name'],
                'rate' => $rate['rate'],

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
