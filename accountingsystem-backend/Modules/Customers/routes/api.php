<?php

use Illuminate\Support\Facades\Route;
use Modules\Customers\Http\Controllers\Api\CustomerBillingStatementController;
use Modules\Customers\Http\Controllers\Api\CustomersController;

// Use `auth:sanctum` (not bare `auth`) so unauthenticated requests return a
// JSON 401 — bare `auth` redirects to /login, which an SPA can't follow. Do
// NOT add the `web` middleware group here: routes/api.php is already loaded
// under the api group with statefulApi() applied in bootstrap/app.php, which
// provides StartSession + EncryptCookies + VerifyCsrfToken once. Adding `web`
// causes StartSession to run a SECOND time, which rotates the browser's
// session cookie on every request and 419s the next state-changing POST.
Route::middleware(['auth:sanctum', 'company.context', 'company.access'])->prefix('v1')->group(function () {
    Route::get('customers', [CustomersController::class, 'index'])
        ->middleware('company.ability:customers.read')
        ->name('customers.index');
    Route::get('customers/{customer}/billing-statement', CustomerBillingStatementController::class)
        ->middleware('company.ability:customers.read')
        ->name('customers.billing-statement');
    Route::get('customers/{customer}', [CustomersController::class, 'show'])
        ->middleware('company.ability:customers.read')
        ->name('customers.show');
    Route::post('customers', [CustomersController::class, 'store'])
        ->middleware('company.ability:customers.create')
        ->name('customers.store');
    Route::put('customers/{customer}', [CustomersController::class, 'update'])
        ->middleware('company.ability:customers.write')
        ->name('customers.update');
    Route::patch('customers/{customer}', [CustomersController::class, 'update'])
        ->middleware('company.ability:customers.write')
        ->name('customers.patch');
    Route::patch('customers/{customer}/status', [CustomersController::class, 'transition'])
        ->middleware('company.ability:customers.write')
        ->name('customers.status');
    Route::delete('customers/{customer}', [CustomersController::class, 'destroy'])
        ->middleware('company.ability:customers.delete')
        ->name('customers.destroy');
});
