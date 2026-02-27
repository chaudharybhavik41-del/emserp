<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MachineMaintenancePlanController;
use App\Http\Controllers\MachineMaintenanceLogController;
use App\Http\Controllers\MachineBreakdownController;
use App\Http\Controllers\MachineMaintenanceReportController;

Route::middleware(['auth'])->prefix('machinery/maintenance')->name('maintenance.')->group(function () {

    // Maintenance Plans
    Route::prefix('plans')->name('plans.')->group(function () {
        Route::get('/', [MachineMaintenancePlanController::class, 'index'])->name('index');
        Route::get('/create', [MachineMaintenancePlanController::class, 'create'])->name('create');
        Route::post('/', [MachineMaintenancePlanController::class, 'store'])->name('store');

        // IMPORTANT: param name must match controller signature ($maintenance_plan)
        Route::get('/{maintenance_plan}', [MachineMaintenancePlanController::class, 'show'])->name('show');
        Route::get('/{maintenance_plan}/edit', [MachineMaintenancePlanController::class, 'edit'])->name('edit');
        Route::put('/{maintenance_plan}', [MachineMaintenancePlanController::class, 'update'])->name('update');
        Route::delete('/{maintenance_plan}', [MachineMaintenancePlanController::class, 'destroy'])->name('destroy');

        Route::post('/{maintenance_plan}/toggle', [MachineMaintenancePlanController::class, 'toggle'])->name('toggle');
    });

    // Maintenance Logs
    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('/', [MachineMaintenanceLogController::class, 'index'])->name('index');
        Route::get('/create', [MachineMaintenanceLogController::class, 'create'])->name('create');
        Route::post('/', [MachineMaintenanceLogController::class, 'store'])->name('store');

        Route::get('/calendar', [MachineMaintenanceLogController::class, 'calendar'])->name('calendar');

        // IMPORTANT: param name must match controller signature ($maintenance_log)
        Route::get('/{maintenance_log}', [MachineMaintenanceLogController::class, 'show'])->name('show');
        Route::get('/{maintenance_log}/edit', [MachineMaintenanceLogController::class, 'edit'])->name('edit');
        Route::put('/{maintenance_log}', [MachineMaintenanceLogController::class, 'update'])->name('update');
        Route::delete('/{maintenance_log}', [MachineMaintenanceLogController::class, 'destroy'])->name('destroy');

        Route::post('/{maintenance_log}/complete', [MachineMaintenanceLogController::class, 'complete'])->name('complete');
        Route::post('/{maintenance_log}/add-spare', [MachineMaintenanceLogController::class, 'addSpare'])->name('add-spare');
    });

    // Breakdown Register
    Route::prefix('breakdowns')->name('breakdowns.')->group(function () {
        Route::get('/', [MachineBreakdownController::class, 'index'])->name('index');
        Route::get('/create', [MachineBreakdownController::class, 'create'])->name('create');
        Route::post('/', [MachineBreakdownController::class, 'store'])->name('store');
        Route::get('/{breakdown}', [MachineBreakdownController::class, 'show'])->name('show');

        Route::post('/{breakdown}/acknowledge', [MachineBreakdownController::class, 'acknowledge'])->name('acknowledge');
        Route::post('/{breakdown}/assign-team', [MachineBreakdownController::class, 'assignTeam'])->name('assign-team');
        Route::post('/{breakdown}/start-repair', [MachineBreakdownController::class, 'startRepair'])->name('start-repair');
        Route::post('/{breakdown}/resolve', [MachineBreakdownController::class, 'resolve'])->name('resolve');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->middleware('permission:machinery.maintenance.reports')->group(function () {
        Route::get('/issued-register', [MachineMaintenanceReportController::class, 'issuedRegister'])->name('issued-register');
        Route::get('/cost-analysis', [MachineMaintenanceReportController::class, 'costAnalysis'])->name('cost-analysis');
    });
});
