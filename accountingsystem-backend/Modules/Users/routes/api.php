<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\Api\CompaniesController;
use Modules\Users\Http\Controllers\Api\OrgUnitsController;
use Modules\Users\Http\Controllers\Api\RolesController;
use Modules\Users\Http\Controllers\Api\SettingsController;
use Modules\Users\Http\Controllers\Api\TenantsController;
use Modules\Users\Http\Controllers\Api\UsersController;

/*
|--------------------------------------------------------------------------
| Primary v1 API (used by frontend proxies)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Current-user and current-company settings routes. These resolve context
    // from the authenticated session, so callers should not send company IDs.
    Route::get('me/onboarding-status', [UsersController::class, 'onboardingStatus']);
    Route::post('me/onboarding/company-setup', [UsersController::class, 'completeCompanySetup']);
    if (\App\Support\ModuleAvailability::isEnabled('settings')) {
        Route::get('settings/user', [SettingsController::class, 'showUser']);
        Route::put('settings/user', [SettingsController::class, 'updateUser']);
        Route::post('settings/user/avatar', [SettingsController::class, 'uploadUserAvatar']);
        Route::delete('settings/user/avatar', [SettingsController::class, 'deleteUserAvatar']);
    }
    Route::get('company', [CompaniesController::class, 'current']);
    Route::get('companies', [CompaniesController::class, 'index']);
    Route::get('companies/{company}', [CompaniesController::class, 'show']);

    Route::middleware(['company.context', 'company.access'])->group(function () {
        Route::get('me/access', [UsersController::class, 'currentAccess']);

        // Company settings
        Route::get('settings/company', [SettingsController::class, 'showCompany'])->middleware('company.ability:settings.read,accounting.read');
        Route::put('settings/company', [SettingsController::class, 'updateCompany'])->middleware('company.ability:settings.write');
        Route::post('settings/company/logo', [SettingsController::class, 'uploadCompanyLogo'])->middleware('company.ability:settings.write');
        Route::delete('settings/company/logo', [SettingsController::class, 'deleteCompanyLogo'])->middleware('company.ability:settings.write');

        // Users
        Route::get('users/stats', [UsersController::class, 'stats'])->middleware('company.ability:users.read');
        Route::get('users', [UsersController::class, 'index'])->middleware('company.ability:users.read');
        Route::post('users', [UsersController::class, 'store'])->middleware('company.ability:users.create');
        Route::get('users/{user}', [UsersController::class, 'show'])->middleware('company.ability:users.read');
        Route::put('users/{user}', [UsersController::class, 'update'])->middleware('company.ability:users.write');
        Route::patch('users/{user}/status', [UsersController::class, 'transition'])->middleware('company.ability:users.write');
        Route::delete('users/{user}', [UsersController::class, 'destroy'])->middleware('company.ability:users.delete');

        // Roles
        Route::get('roles/stats', [RolesController::class, 'stats'])->middleware('company.ability:roles.read');
        Route::get('roles', [RolesController::class, 'index'])->middleware('company.ability:roles.read');
        Route::post('roles', [RolesController::class, 'store'])->middleware('company.ability:roles.create');
        Route::get('roles/{role}', [RolesController::class, 'show'])->middleware('company.ability:roles.read');
        Route::put('roles/{role}', [RolesController::class, 'update'])->middleware('company.ability:roles.write');
        Route::patch('roles/{role}/status', [RolesController::class, 'transition'])->middleware('company.ability:roles.write');
        Route::delete('roles/{role}', [RolesController::class, 'destroy'])->middleware('company.ability:roles.delete');

        // Organization units
        Route::get('org-units/stats', [OrgUnitsController::class, 'stats'])->middleware('company.ability:org_units.read');
        Route::get('org-units', [OrgUnitsController::class, 'index'])->middleware('company.ability:org_units.read');
        Route::post('org-units', [OrgUnitsController::class, 'store'])->middleware('company.ability:org_units.create');
        Route::get('org-units/{orgUnit}', [OrgUnitsController::class, 'show'])->middleware('company.ability:org_units.read');
        Route::put('org-units/{orgUnit}', [OrgUnitsController::class, 'update'])->middleware('company.ability:org_units.write');
        Route::patch('org-units/{orgUnit}/status', [OrgUnitsController::class, 'transition'])->middleware('company.ability:org_units.write');
        Route::delete('org-units/{orgUnit}', [OrgUnitsController::class, 'destroy'])->middleware('company.ability:org_units.delete');
    });

    // Legacy tenant-named surface. Keep behavior stable for now, but make the
    // deprecation explicit while new integrations move to company-named APIs.
    Route::get('tenants', [TenantsController::class, 'index'])->middleware('legacy.tenant.api:/api/v1/companies');
    Route::post('tenants', [TenantsController::class, 'store'])->middleware('legacy.tenant.api');
    Route::post('tenants/company-setup', [TenantsController::class, 'storeCompanySetup'])->middleware('legacy.tenant.api:/api/v1/me/onboarding/company-setup');
    Route::get('tenants/{tenant}', [TenantsController::class, 'show'])->middleware('legacy.tenant.api:/api/v1/companies');
});
