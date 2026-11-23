<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;

class AccountingDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            CoaAccountTypeSeeder::class,
            CoaDetailTypeSeeder::class,
            AccountSeeder::class,
//            TaxSeeder::class,
//            FundSourceSeeder::class,
//            JournalNumberSeeder::class,
//            JournalEntrySeeder::class,
        ]);
    }
}
