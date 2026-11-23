<?php

use Illuminate\Support\Facades\Route;
use Modules\Expenses\Http\Controllers\ExpensesController;

Route::middleware(['auth:sanctum', 'company.context', 'company.access'])->prefix('v1')->group(function () {
    Route::get('expenses', [ExpensesController::class, 'index'])
        ->middleware('company.ability:expenses.read')
        ->name('expenses.index');
    Route::get('expenses/{expense}', [ExpensesController::class, 'show'])
        ->middleware('company.ability:expenses.read')
        ->name('expenses.show');
    Route::post('expenses', [ExpensesController::class, 'store'])
        ->middleware('company.ability:expenses.create')
        ->name('expenses.store');
    Route::put('expenses/{expense}', [ExpensesController::class, 'update'])
        ->middleware('company.ability:expenses.write')
        ->name('expenses.update');
    Route::patch('expenses/{expense}', [ExpensesController::class, 'update'])
        ->middleware('company.ability:expenses.write')
        ->name('expenses.patch');
    Route::delete('expenses/{expense}', [ExpensesController::class, 'destroy'])
        ->middleware('company.ability:expenses.delete')
        ->name('expenses.destroy');
});
