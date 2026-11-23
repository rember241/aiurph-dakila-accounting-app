<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryController;

Route::middleware(['auth', 'verified', 'web'])->group(function () {
    Route::resource('inventories', InventoryController::class)->names('inventory');
});
