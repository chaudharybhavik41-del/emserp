<?php

use App\Http\Controllers\Storage\StorageBrowserController;
use App\Http\Controllers\Storage\StorageFileController;
use App\Http\Controllers\Storage\StorageFolderAccessController;
use App\Http\Controllers\Storage\StorageFolderController;
use App\Http\Middleware\EnsureHasStorageAccess;
use App\Http\Controllers\Storage\StorageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureHasStorageAccess::class])
    ->prefix('file-storage')
    ->name('storage.')
    ->group(function () {

        Route::get('/', [StorageBrowserController::class, 'index'])->name('index');
        Route::get('folders/{folder}', [StorageBrowserController::class, 'show'])->name('folders.show');

        Route::post('folders', [StorageFolderController::class, 'store'])->name('folders.store');
        Route::put('folders/{folder}', [StorageFolderController::class, 'update'])->name('folders.update');
        Route::delete('folders/{folder}', [StorageFolderController::class, 'destroy'])->name('folders.destroy');

        // Access management
        Route::get('folders/{folder}/access', [StorageFolderAccessController::class, 'index'])->name('folders.access.index');
        Route::post('folders/{folder}/access', [StorageFolderAccessController::class, 'store'])->name('folders.access.store');
        Route::delete('folders/{folder}/access/{user}', [StorageFolderAccessController::class, 'destroy'])->name('folders.access.destroy');

        // Files
        Route::post('files/bulk-download', [StorageFileController::class, 'bulkDownload'])->name('files.bulk-download');
        Route::post('files/bulk-delete', [StorageFileController::class, 'bulkDestroy'])->name('files.bulk-destroy');
        Route::post('files/bulk-move', [StorageFileController::class, 'bulkMove'])->name('files.bulk-move');
        Route::post('folders/{folder}/files', [StorageFileController::class, 'store'])->name('files.store');
        Route::get('files/{file}/preview', [StorageFileController::class, 'preview'])->name('files.preview');
        Route::get('files/{file}/download', [StorageFileController::class, 'download'])->name('files.download');
        Route::put('files/{file}', [StorageFileController::class, 'update'])->name('files.update');
        Route::delete('files/{file}', [StorageFileController::class, 'destroy'])->name('files.destroy');
    });
