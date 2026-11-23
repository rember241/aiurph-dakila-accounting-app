<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Models\FundSources\FundSource;
use Modules\Accounting\Models\JournalEntries\JournalEntry;
use Modules\Users\Models\Tenant;
use Tests\TestCase;

class JournalEntryPostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_scoped_journal_entry_can_be_posted_with_journal_write(): void
    {
        [$company, $headers] = $this->actingAsJournalWriter('journal-post-a', ['journal.write']);
        $entry = $this->draftEntry($company);

        $response = $this->withHeaders($headers)
            ->patchJson("/api/v1/journal-entries/{$entry->getKey()}/post");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $entry->getKey())
            ->assertJsonPath('message', 'Journal Entry #JE-1 posted');

        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->getKey(),
            'company_id' => $company->getKey(),
            'status' => 'posted',
        ]);
    }

    public function test_company_scoped_journal_entry_can_be_posted_with_accounting_write_compatibility(): void
    {
        [$company, $headers] = $this->actingAsJournalWriter('journal-post-b', ['accounting.write']);
        $entry = $this->draftEntry($company);

        $response = $this->withHeaders($headers)
            ->patchJson("/api/v1/journal-entries/{$entry->getKey()}/post");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $entry->getKey())
            ->assertJsonPath('message', 'Journal Entry #JE-1 posted');

        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->getKey(),
            'company_id' => $company->getKey(),
            'status' => 'posted',
        ]);
    }

    /**
     * @param array<int, string> $abilities
     * @return array{0: Tenant, 1: array<string, string>}
     */
    private function actingAsJournalWriter(string $slug, array $abilities): array
    {
        $user = User::factory()->create([
            'company_id' => null,
        ]);

        $company = Tenant::create([
            'name' => 'Journal Post '.strtoupper($slug),
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'UTC',
            'owner_user_id' => $user->getKey(),
        ]);

        $user->forceFill(['company_id' => $company->getKey()])->save();

        Sanctum::actingAs($user, $abilities, 'sanctum');

        return [$company, [
            'X-Company-ID' => (string) $company->getKey(),
        ]];
    }

    private function draftEntry(Tenant $company): JournalEntry
    {
        $fundSource = FundSource::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Operating Cash',
            'status' => 'active',
        ]);

        return JournalEntry::query()->create([
            'company_id' => $company->getKey(),
            'org_unit_id' => null,
            'prefix' => 'JE',
            'entry_number' => 1,
            'entry_date' => '2046-02-10',
            'fund_source_id' => $fundSource->getKey(),
            'reference_number' => 'REF-POST-1',
            'type' => 'general',
            'status' => 'draft',
            'notes' => 'Post me',
            'amount' => 250,
        ]);
    }
}
