<?php

namespace Modules\Accounting\Database\Seeders;

use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Illuminate\Database\Seeder;

class JournalEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 500 dump journal entries
        JournalEntry::factory()->count(500)->create();
    }
}
