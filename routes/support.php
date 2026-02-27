<?php

use App\Http\Controllers\Support\SupportDigestController;
use App\Http\Controllers\Support\SupportDocumentAttachmentController;
use App\Http\Controllers\Support\SupportDocumentController;
use App\Http\Controllers\Support\SupportFolderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('support')->name('support.')->group(function () {

    // Document Library
    Route::get('documents', [SupportDocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/create', [SupportDocumentController::class, 'create'])->name('documents.create');
    Route::post('documents', [SupportDocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}', [SupportDocumentController::class, 'show'])->name('documents.show');
    Route::get('documents/{document}/edit', [SupportDocumentController::class, 'edit'])->name('documents.edit');
    Route::put('documents/{document}', [SupportDocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{document}', [SupportDocumentController::class, 'destroy'])->name('documents.destroy');

    // Document attachments
    Route::post('documents/{document}/attachments', [SupportDocumentAttachmentController::class, 'store'])->name('documents.attachments.store');
    Route::get('documents/{document}/attachments/{attachment}/download', [SupportDocumentAttachmentController::class, 'download'])->name('documents.attachments.download');
    Route::delete('documents/{document}/attachments/{attachment}', [SupportDocumentAttachmentController::class, 'destroy'])->name('documents.attachments.destroy');

    // Folder management
    Route::get('folders', [SupportFolderController::class, 'index'])->name('folders.index');
    Route::get('folders/create', [SupportFolderController::class, 'create'])->name('folders.create');
    Route::post('folders', [SupportFolderController::class, 'store'])->name('folders.store');
    Route::get('folders/{folder}/edit', [SupportFolderController::class, 'edit'])->name('folders.edit');
    Route::put('folders/{folder}', [SupportFolderController::class, 'update'])->name('folders.update');
    Route::delete('folders/{folder}', [SupportFolderController::class, 'destroy'])->name('folders.destroy');

    // Daily Digest
    Route::get('digest/preview', [SupportDigestController::class, 'preview'])->name('digest.preview');
    Route::post('digest/send', [SupportDigestController::class, 'send'])->name('digest.send');
    Route::post('digest/send-test', [SupportDigestController::class, 'sendTest'])->name('digest.send_test');
    Route::get('digest/recipients', [SupportDigestController::class, 'recipients'])->name('digest.recipients');
    Route::post('digest/recipients', [SupportDigestController::class, 'updateRecipients'])->name('digest.recipients.update');
});
