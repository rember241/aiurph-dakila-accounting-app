<?php

namespace Modules\Users\Tests\Unit;

use App\Support\ModuleAvailability;
use Tests\TestCase;

final class ModuleAvailabilityTest extends TestCase
{
    public function test_accounting_only_profile_disables_other_modules(): void
    {
        self::assertTrue(ModuleAvailability::isEnabled('accounting'));
        self::assertTrue(ModuleAvailability::isEnabled('journal'));
        self::assertTrue(ModuleAvailability::isEnabled('period'));
        self::assertTrue(ModuleAvailability::isEnabled('roles'));
        self::assertFalse(ModuleAvailability::isEnabled('dashboard_reports'));
        foreach (['sales', 'customers', 'inventory', 'expenses', 'users', 'org_units', 'settings'] as $module) {
            self::assertFalse(ModuleAvailability::isEnabled($module));
        }
        self::assertSame(['customers', 'sales', 'expenses', 'inventory', 'users', 'dashboard_reports', 'org_units', 'settings'], ModuleAvailability::disabledModules());
        self::assertFalse(config('module_availability.cheque_disbursements_enabled'));
    }
}
