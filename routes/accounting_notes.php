<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Accounting\PurchaseDebitNoteController;
use App\Http\Controllers\Accounting\SalesCreditNoteController;

Route::middleware('auth')->prefix('accounting')->name('accounting.')->group(function () {
    // Purchase Debit Notes
    Route::get('purchase-debit-notes', [PurchaseDebitNoteController::class, 'index'])->name('purchase-debit-notes.index');
    Route::get('purchase-debit-notes/create', [PurchaseDebitNoteController::class, 'create'])->name('purchase-debit-notes.create');
    Route::post('purchase-debit-notes', [PurchaseDebitNoteController::class, 'store'])->name('purchase-debit-notes.store');
    Route::get('purchase-debit-notes/{purchaseDebitNote}', [PurchaseDebitNoteController::class, 'show'])->name('purchase-debit-notes.show');
    Route::get('purchase-debit-notes/{purchaseDebitNote}/edit', [PurchaseDebitNoteController::class, 'edit'])->name('purchase-debit-notes.edit');
    Route::put('purchase-debit-notes/{purchaseDebitNote}', [PurchaseDebitNoteController::class, 'update'])->name('purchase-debit-notes.update');
    Route::post('purchase-debit-notes/{purchaseDebitNote}/post', [PurchaseDebitNoteController::class, 'post'])->name('purchase-debit-notes.post');
    Route::post('purchase-debit-notes/{purchaseDebitNote}/cancel', [PurchaseDebitNoteController::class, 'cancel'])->name('purchase-debit-notes.cancel');

    // Sales Credit Notes
    Route::get('sales-credit-notes', [SalesCreditNoteController::class, 'index'])->name('sales-credit-notes.index');
    Route::get('sales-credit-notes/create', [SalesCreditNoteController::class, 'create'])->name('sales-credit-notes.create');
    Route::post('sales-credit-notes', [SalesCreditNoteController::class, 'store'])->name('sales-credit-notes.store');
    Route::get('sales-credit-notes/{salesCreditNote}', [SalesCreditNoteController::class, 'show'])->name('sales-credit-notes.show');
    Route::get('sales-credit-notes/{salesCreditNote}/edit', [SalesCreditNoteController::class, 'edit'])->name('sales-credit-notes.edit');
    Route::put('sales-credit-notes/{salesCreditNote}', [SalesCreditNoteController::class, 'update'])->name('sales-credit-notes.update');
    Route::post('sales-credit-notes/{salesCreditNote}/post', [SalesCreditNoteController::class, 'post'])->name('sales-credit-notes.post');
    Route::post('sales-credit-notes/{salesCreditNote}/cancel', [SalesCreditNoteController::class, 'cancel'])->name('sales-credit-notes.cancel');
});
