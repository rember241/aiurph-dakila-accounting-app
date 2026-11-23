<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Support\Collection;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\JournalEntries\JournalItem;
use Modules\Accounting\Models\JournalEntries\JournalNumber;
use Modules\Accounting\Models\Taxes\Tax;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /*
         * Get a random journal number to assign to this entry.
         * Increment the starting number and set the is_used value to true
         * to flag this journal number has been used in a transaction already
         */
        $journalNumber = JournalNumber::inRandomOrder()->first();
        $entryNumber = $journalNumber->starting_number;
        $journalNumber->update([
            'starting_number' => ++$entryNumber,
            'is_used' => true,
        ]);

        // Get a random fund source to assign to this entry then set its is_used value to true
        $fundSource = FundSource::inRandomOrder()->first();
        $fundSource->update(['is_used' => true]);

        // Determine the journal type depending on some prefixes
        $journalType = function() use ($journalNumber) {
            return match ($journalNumber->prefix) {
                'JE' => 'general',
                'AJE' => 'adjusting',
                'CJE' => 'closing',
                default => fake()->randomElement(['general', 'adjusting', 'closing']),
            };
        };

        return [
            'company_id' => null,
            'org_unit_id' => null,
            'prefix' => $journalNumber->prefix,
            'entry_number' => $entryNumber,
            'entry_date' => fake()->dateTimeBetween('-1 year'),
            'type' => $journalType,
            'fund_source_id' => $fundSource->id,
            'reference_number' => fake()->bothify('REF-###???'),
            'notes' => fake()->realText(50, 5),
            'amount' => 0.0, // Placeholder value
            'status' => fake()->randomElement(['posted', 'draft', 'void']),
        ];
    }

    /**
     * Populate the journal_items junction table
     */
    public function configure(): static
    {
        return $this->afterCreating(function (JournalEntry $entry) {
            $debitItems  = $this->buildDebitItems();
            $creditItems = $this->buildCreditItems($debitItems->sum('gross'));

            $this->createJournalItems($entry, $debitItems, 'debit');
            $this->createJournalItems($entry, $creditItems, 'credit');

            // Replace the placeholder amount with the gross total
            $entry->update(['amount' => $debitItems->sum('gross')]);
        });
    }

    /**
     * Generates the debit side of the journal entry with random amounts and taxes
     *
     * @return Collection A collection of entry items with amounts on the debit side
     */
    private function buildDebitItems(): Collection
    {
        $accounts = Account::where('is_posting', true)
            ->where('status', 'active')
            ->inRandomOrder()
            ->limit(fake()->numberBetween(1, 3))
            ->get();

        return $accounts->map(function ($account) {
            $tax = $this->randomTax();
            $base = fake()->randomFloat(2, 1000, 50000);
            $taxAmount = $tax ? round($base * ($tax->rate / 100), 2) : 0;

            return [
                'account' => $account,
                'base' => $base,
                'tax' => $tax,
                'gross' => round($base + $taxAmount, 2),
            ];
        });
    }

    /**
     * Distributes the debit gross across credit accounts to ensure balance
     *
     * @param float $totalToMatch Gross total on the debit side
     * @return Collection
     */
    private function buildCreditItems(float $totalToMatch): Collection
    {
        $accounts = Account::where('is_posting', true)
            ->where('status', 'active')
            ->inRandomOrder()
            ->limit(fake()->numberBetween(1, 3))
            ->get();

        $items = collect();
        $remaining = $totalToMatch;

        foreach ($accounts as $index => $account) {
            $tax = $this->randomTax();
            $isLast = $index === $accounts->count() - 1;
            $gross = $isLast
                ? round($remaining, 2)
                : round(fake()->randomFloat(2, 1, $remaining - ($accounts->count() - $index - 1)), 2);

            $remaining = round($remaining - $gross, 2);
            $taxRate = $tax ? $tax->rate / 100 : 0;
            $base = $tax ? round($gross / (1 + $taxRate), 2) : $gross;

            $items->push([
                'account' => $account,
                'base' => $base,
                'tax' => $tax,
                'gross' => $gross,
            ]);
        }

        return $items;
    }

    /**
     * Creates journal items for the given entry
     *
     * @param JournalEntry $entry
     * @param Collection $items
     * @param string $type
     * @return void
     */
    private function createJournalItems(JournalEntry $entry, Collection $items, string $type): void
    {
        foreach ($items as $item) {
            JournalItem::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $item['account']->id,
                'tax_id'           => $item['tax']?->id,
                'description'      => fake()->realText(50),
                'type'             => $type,
                'amount'           => $item['base'],
            ]);
        }
    }

    /**
     * Assigns a random tax on a journal item
     *
     * @return Tax|null The tax to be assigned
     */
    private function randomTax(): ?Tax
    {
        // Return no tax if false
        if (!fake()->boolean(70)) return null;

        // Set the is_used column of the randomly retrieved tax
        // to true since it will be used in this seeded entry
        $randomTax = Tax::inRandomOrder()->first();
        if (!$randomTax->is_used) $randomTax->update(['is_used' => true]);
        return $randomTax;
    }
}
