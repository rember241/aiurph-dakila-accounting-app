<?php

use Illuminate\Support\Facades\Route;

// Accounting module currently exposes API routes only.
Route::middleware(['web'])->group(function () {
    // Reserved for future accounting web views.
});
