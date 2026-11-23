<?php

namespace Modules\Accounting\Database\Factories;

use Modules\Accounting\Models\ChartOfAccounts\Account;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Accounting\Models\ChartOfAccounts\Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    private const TYPE_BASE_CODE = [
        'asset' => 1000,
        'liability' => 2000,
        'equity' => 3000,
        'revenue' => 4000,
        'expense' => 5000,
    ];

    private static array $sequenceByType = [
        'asset' => 999,
        'liability' => 1999,
        'equity' => 2999,
        'revenue' => 3999,
        'expense' => 4999,
    ];

    public function definition(): array
    {
        $type = $this->faker->randomElement([
            'asset',
            'liability',
            'equity',
            'revenue',
            'expense'
        ]);

        // Determine the normal balance based on the account type.
        // Assets and Expenses typically have a debit normal balance,
        // while Liabilities, Equity, Revenue typically have a credit normal balance.
        $normalBalance = match (strtolower($type)) {
            'asset', 'expense' => 'debit',
            'liability', 'equity', 'revenue' => 'credit',
            default => throw new \InvalidArgumentException("Invalid account type: $type"),
        };

        $baseCode = self::TYPE_BASE_CODE[$type];
        self::$sequenceByType[$type] = (self::$sequenceByType[$type] ?? ($baseCode - 1)) + 1;
        if (self::$sequenceByType[$type] > ($baseCode + 999)) {
            self::$sequenceByType[$type] = $baseCode;
        }

        $code = (string)self::$sequenceByType[$type];

        return [
            'id' => (string)Str::uuid(),
            'company_id' => null,
            'tenant_id' => null,
            'code' => $code,
            'name' => ucfirst($this->faker->words(3, true)),
            'type' => $type,
            'parent_id' => null, // assign later
            'is_posting' => $this->faker->boolean(80),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'normal_balance' => $normalBalance,
            'is_contra' => $this->faker->boolean(10),
            'description' => $this->faker->optional()->sentence(),
        ];
    }

}
