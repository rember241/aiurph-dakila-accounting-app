<?php

namespace Modules\Accounting\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Database\Seeders\AccountSeeder;
use Modules\Accounting\Database\Seeders\CoaAccountTypeSeeder;
use Modules\Accounting\Database\Seeders\CoaDetailTypeSeeder;
use Modules\Accounting\Models\ChartOfAccounts\Account;
use Tests\TestCase;

class ChurchFarmAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_the_workbook_chart_with_accounting_metadata(): void
    {
        $this->seed(CoaAccountTypeSeeder::class);
        $this->seed(CoaDetailTypeSeeder::class);
        $this->seed(AccountSeeder::class);

        $this->assertSame(
            246,
            Account::query()->whereNull('company_id')->whereNull('tenant_id')->count()
        );

        $inputVat = Account::query()->where('code', '3004')->firstOrFail();
        $this->assertSame('Input Vat', $inputVat->name);
        $this->assertSame('Assets', DB::table('account_types')->where('id', $inputVat->account_type_id)->value('name'));
        $this->assertSame(
            'Prepayments and Other Current Assets',
            DB::table('detail_types')->where('id', $inputVat->detail_type_id)->value('name')
        );

        $churchBuilding = Account::query()->where('code', '5007')->firstOrFail();
        $churchBuildingDepreciation = Account::query()->where('code', '6008')->firstOrFail();
        $this->assertSame($churchBuilding->getKey(), $churchBuildingDepreciation->contra_account_id);
        $this->assertTrue($churchBuildingDepreciation->is_contra);
        $this->assertSame('credit', $churchBuildingDepreciation->normal_balance);

        $standaloneDepreciation = Account::query()->where('code', '6005')->firstOrFail();
        $this->assertNull($standaloneDepreciation->contra_account_id);
        $this->assertTrue($standaloneDepreciation->is_contra);
        $this->assertSame('credit', $standaloneDepreciation->normal_balance);

        $this->assertDatabaseHas('accounts', [
            'code' => '13133',
            'name' => 'Mission Work',
            'type' => 'expense',
            'is_posting' => true,
            'status' => 'active',
        ]);
    }
}
