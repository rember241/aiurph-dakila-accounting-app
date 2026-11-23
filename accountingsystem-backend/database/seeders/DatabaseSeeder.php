<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder;
use Modules\Users\Database\Seeders\UsersDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'aiur_admin@demo.com',
            'password' => 'admin1234',
        ]);


        /*
         * Run the account and Journals entry seeders. Populates
         * the tables with data and enforces their relationships
         */
        $this->call([
            UsersDatabaseSeeder::class,
            AccountingDatabaseSeeder::class,
        ]);
    }
}
