<?php

namespace Modules\Accounting\Tests\Unit;

use Modules\Accounting\Support\CanonicalChartOfAccountsTemplate;
use PHPUnit\Framework\TestCase;

class CanonicalChartOfAccountsTemplateTest extends TestCase
{
    public function test_template_matches_the_workbook_account_contract(): void
    {
        $entries = CanonicalChartOfAccountsTemplate::entries();
        $byCode = collect($entries)->keyBy('code');

        $this->assertCount(246, $entries);
        $this->assertCount(246, $byCode);
        $this->assertSame([
            'asset' => 62,
            'liability' => 18,
            'equity' => 8,
            'revenue' => 24,
            'expense' => 134,
        ], collect($entries)->countBy('type')->all());
        $this->assertTrue(collect($entries)->every(
            fn (array $entry): bool => $entry['is_posting'] === true && $entry['parent_code'] === null
        ));

        $this->assertSame('CIB-PNB General Fund Account', $byCode['1100']['name']);
        $this->assertSame('Input Vat', $byCode['3004']['name']);
        $this->assertSame('Output Vat', $byCode['7015']['name']);
        $this->assertSame('Mission Work', $byCode['13133']['name']);
        $this->assertSame('Property, Plant, and Equipment', $byCode['5014']['detail_type']);
        $this->assertSame('5007', $byCode['6008']['contra_account_code']);
        $this->assertNull($byCode['6005']['contra_account_code']);
        $this->assertTrue($byCode['6005']['is_contra']);
    }

}
