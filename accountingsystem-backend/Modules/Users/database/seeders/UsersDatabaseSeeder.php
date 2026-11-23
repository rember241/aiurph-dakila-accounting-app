<?php

namespace Modules\Users\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Users\Models\Role;

/**
 * Seeds default roles used by the ERP.
 *
 * Step-by-step:
 * 1) Create or update the baseline roles.
 * 2) Assign the default `aiur_admin@demo.com` user to the Admin role (if present).
 */
class UsersDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::updateOrCreate(
            ['key' => 'admin'],
            [
                'system_generated' => true,
                'name' => 'Admin',
                'department' => 'Administration',
                'status' => 'active',
                'access_level' => 'write',
                'permission_scope' => 'global',
                'module_permissions' => [
                    'accounting' => ['read', 'create', 'write', 'delete'],
                    'customers' => ['read', 'create', 'write', 'delete'],
                    'inventory' => ['read', 'create', 'write', 'delete'],
                    'sales' => ['read', 'create', 'write', 'delete'],
                    'period' => ['read', 'create', 'write', 'delete'],
                    'journal' => ['read', 'create', 'write', 'delete'],
                    'users' => ['read', 'create', 'write', 'delete'],
                    'roles' => ['read', 'create', 'write', 'delete'],
                    'org_units' => ['read', 'create', 'write', 'delete'],
                ],
                'description' => 'Full access to the ERP.',
            ]
        );

        Role::updateOrCreate(
            ['key' => 'accountant'],
            [
                'system_generated' => true,
                'name' => 'Accountant',
                'department' => 'Accounting',
                'status' => 'active',
                'access_level' => 'write',
                'permission_scope' => 'global',
                'module_permissions' => [
                    'accounting' => ['read', 'write'],
                    'customers' => ['read', 'write'],
                    'inventory' => ['read'],
                    'sales' => ['read'],
                    'period' => ['read'],
                    'journal' => ['read', 'write'],
                    'org_units' => ['read'],
                ],
                'description' => 'Can manage accounting records and post journal entries.',
            ]
        );

        Role::updateOrCreate(
            ['key' => 'viewer'],
            [
                'system_generated' => true,
                'name' => 'Viewer',
                'department' => null,
                'status' => 'active',
                'access_level' => 'read',
                'permission_scope' => 'global',
                'module_permissions' => [
                    'accounting' => ['read'],
                    'customers' => ['read'],
                    'inventory' => ['read'],
                    'sales' => ['read'],
                    'period' => ['read'],
                    'journal' => ['read'],
                    'org_units' => ['read'],
                ],
                'description' => 'Read-only access for reporting and review.',
            ]
        );

        $admin = User::query()->where('email', 'aiur_admin@demo.com')->first();
        if ($admin) {
            // Keep future role assignments; only ensure admin role is present.
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
