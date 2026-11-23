<?php

namespace Modules\Accounting\Tests\Unit;

use Modules\Accounting\Support\GeneralBusinessChartOfAccountsTemplate;
use PHPUnit\Framework\TestCase;

class GeneralBusinessChartOfAccountsTemplateTest extends TestCase
{
    public function test_original_business_template_preserves_unique_codes_and_parent_hierarchy(): void
    {
        $entries = GeneralBusinessChartOfAccountsTemplate::entries();
        $this->assertCount(101, $entries);
        $seen = [];
        foreach ($entries as $entry) {
            $this->assertArrayNotHasKey($entry['code'], $seen);
            if ($entry['parent_code'] !== null) {
                $this->assertArrayHasKey($entry['parent_code'], $seen);
                $this->assertFalse($seen[$entry['parent_code']]['is_posting']);
            }
            $seen[$entry['code']] = $entry;
        }
        $this->assertSame('Sales Receivable', $seen['1310']['name']);
        $this->assertSame('Stock In Hand', $seen['1410']['name']);
        $this->assertSame('Bank Account', $seen['1210']['name']);
        $this->assertSame('Digital Wallet', $seen['1230']['name']);
    }
}
