<?php

use App\Http\Controllers\ReportsHubController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reports Hub Routes
|--------------------------------------------------------------------------
| Add to routes/web.php: require __DIR__.'/reports_hub.php';
|
| Centralised Reports Hub where all module reports can live under one URL.
| Each report supports:
| - Filters (GET query string)
| - PDF export (A4)
| - Print view (A4)
| - CSV export (streamed)
|
*/

Route::middleware(['auth'])
    ->prefix('reports-hub')
    ->name('reports-hub.')
    ->group(function () {
        Route::get('/', [ReportsHubController::class, 'index'])->name('index');

        Route::get('{key}', [ReportsHubController::class, 'show'])
            ->where('key', '[A-Za-z0-9\-_]+')
            ->name('show');

        Route::get('{key}/print', [ReportsHubController::class, 'print'])
            ->where('key', '[A-Za-z0-9\-_]+')
            ->name('print');

        Route::get('{key}/pdf', [ReportsHubController::class, 'pdf'])
            ->where('key', '[A-Za-z0-9\-_]+')
            ->name('pdf');

        Route::get('{key}/csv', [ReportsHubController::class, 'csv'])
            ->where('key', '[A-Za-z0-9\-_]+')
            ->name('csv');
    });
