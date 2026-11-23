<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\Api\InventoryItemsController;

Route::middleware(['auth:sanctum', 'company.context', 'company.access'])->prefix('v1')->group(function () {
    // Product master routes consumed by Products / Items in the front-end.
    // Stock movements are recorded by operational workflows, not by this CRUD
    // surface except for the current quantity fields on the item master.
    Route::get('inventories', [InventoryItemsController::class, 'index'])
        ->middleware('company.ability:inventory.read')
        ->name('inventory.index');
    Route::get('inventories/{inventory}', [InventoryItemsController::class, 'show'])
        ->middleware('company.ability:inventory.read')
        ->name('inventory.show');
    Route::post('inventories', [InventoryItemsController::class, 'store'])
        ->middleware('company.ability:inventory.create')
        ->name('inventory.store');
    Route::put('inventories/{inventory}', [InventoryItemsController::class, 'update'])
        ->middleware('company.ability:inventory.write')
        ->name('inventory.update');
    Route::patch('inventories/{inventory}', [InventoryItemsController::class, 'update'])
        ->middleware('company.ability:inventory.write')
        ->name('inventory.patch');
    Route::patch('inventories/{inventory}/status', [InventoryItemsController::class, 'transition'])
        ->middleware('company.ability:inventory.write')
        ->name('inventory.status');
    Route::delete('inventories/{inventory}', [InventoryItemsController::class, 'destroy'])
        ->middleware('company.ability:inventory.delete')
        ->name('inventory.destroy');
});
