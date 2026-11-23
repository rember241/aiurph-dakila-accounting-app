<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\Api\SalesInvoicePaymentsController;
use Modules\Sales\Http\Controllers\Api\SalesInvoicesController;
use Modules\Sales\Http\Controllers\Api\SalesPaymentsController;

Route::middleware(['auth:sanctum', 'company.context', 'company.access'])->prefix('v1')->group(function () {
    // Sales invoice workbench APIs used by the Sales > Invoices screens.
    // Draft edits are isolated here; issuing is the boundary where downstream
    // inventory/accounting side effects currently begin.
    Route::get('sales-invoices', [SalesInvoicesController::class, 'index'])
        ->middleware('company.ability:sales.read')
        ->name('sales-invoices.index');
    Route::get('sales-invoices/{salesInvoice}', [SalesInvoicesController::class, 'show'])
        ->middleware('company.ability:sales.read')
        ->name('sales-invoices.show');
    Route::post('sales-invoices', [SalesInvoicesController::class, 'store'])
        ->middleware('company.ability:sales.create')
        ->name('sales-invoices.store');
    Route::put('sales-invoices/{salesInvoice}', [SalesInvoicesController::class, 'update'])
        ->middleware('company.ability:sales.write')
        ->name('sales-invoices.update');
    Route::patch('sales-invoices/{salesInvoice}', [SalesInvoicesController::class, 'update'])
        ->middleware('company.ability:sales.write')
        ->name('sales-invoices.patch');
    Route::post('sales-invoices/{salesInvoice}/issue', [SalesInvoicesController::class, 'issue'])
        ->middleware('company.ability:sales.write')
        ->name('sales-invoices.issue');
    Route::post('sales-invoices/{salesInvoice}/post', [SalesInvoicesController::class, 'post'])
        ->middleware('company.ability:sales.write')
        ->name('sales-invoices.post');
    Route::post('sales-invoices/{salesInvoice}/cancel', [SalesInvoicesController::class, 'cancel'])
        ->middleware('company.ability:sales.write')
        ->name('sales-invoices.cancel');
    Route::delete('sales-invoices/{salesInvoice}', [SalesInvoicesController::class, 'destroy'])
        ->middleware('company.ability:sales.delete')
        ->name('sales-invoices.destroy');

    // Nested sales invoice payment APIs. Each receipt posts a matching
    // debit-receipt / credit-AR journal entry via the command service; voiding
    // creates a reversal entry instead of destructive deletion.
    Route::get('sales-invoices/{salesInvoice}/payments', [SalesInvoicePaymentsController::class, 'index'])
        ->middleware('company.ability:sales.read')
        ->name('sales-invoices.payments.index');
    Route::get('sales-invoices/{salesInvoice}/payments/{salesInvoicePayment}', [SalesInvoicePaymentsController::class, 'show'])
        ->middleware('company.ability:sales.read')
        ->name('sales-invoices.payments.show');
    Route::post('sales-invoices/{salesInvoice}/payments', [SalesInvoicePaymentsController::class, 'store'])
        ->middleware('company.ability:sales.create')
        ->name('sales-invoices.payments.store');
    Route::put('sales-invoices/{salesInvoice}/payments/{salesInvoicePayment}', [SalesInvoicePaymentsController::class, 'update'])
        ->middleware('company.ability:sales.write')
        ->name('sales-invoices.payments.update');
    Route::patch('sales-invoices/{salesInvoice}/payments/{salesInvoicePayment}', [SalesInvoicePaymentsController::class, 'update'])
        ->middleware('company.ability:sales.write')
        ->name('sales-invoices.payments.patch');
    Route::post('sales-invoices/{salesInvoice}/payments/{salesInvoicePayment}/void', [SalesInvoicePaymentsController::class, 'void'])
        ->middleware('company.ability:sales.write')
        ->name('sales-invoices.payments.void');
    Route::delete('sales-invoices/{salesInvoice}/payments/{salesInvoicePayment}', [SalesInvoicePaymentsController::class, 'destroy'])
        ->middleware('company.ability:sales.delete')
        ->name('sales-invoices.payments.destroy');

    // Top-level company-scoped payments listing for the standalone Payments
    // tab. Nested endpoints above remain the canonical write APIs.
    Route::get('sales-payments', [SalesPaymentsController::class, 'index'])
        ->middleware('company.ability:sales.read')
        ->name('sales-payments.index');
});
