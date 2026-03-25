<?php

use App\Http\Controllers\Api\Mobile\MobileApprovalController;
use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileMaintenanceController;
use App\Http\Controllers\Api\Mobile\MobileProductionController;
use App\Http\Controllers\Api\Mobile\MobileStoreRequisitionController;
use App\Http\Controllers\Api\Mobile\CompanionMetaController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')->name('api.mobile.v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [MobileAuthController::class, 'login'])->name('login');
    });

    Route::get('health', [CompanionMetaController::class, 'health'])->name('health');
    Route::get('meta', [CompanionMetaController::class, 'meta'])->name('meta');

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::get('me', [MobileAuthController::class, 'me'])->name('me');
            Route::post('logout', [MobileAuthController::class, 'logout'])->name('logout');
        });

        Route::get('me', [CompanionMetaController::class, 'me'])->name('me');

        Route::get('approvals', [MobileApprovalController::class, 'index'])->name('approvals.index');
        Route::get('approvals/{approvalRequest}', [MobileApprovalController::class, 'show'])->name('approvals.show');
        Route::post('approvals/steps/{approvalStep}/approve', [MobileApprovalController::class, 'approve'])->name('approvals.steps.approve');
        Route::post('approvals/steps/{approvalStep}/reject', [MobileApprovalController::class, 'reject'])->name('approvals.steps.reject');

        Route::prefix('store/requisitions')->name('store.requisitions.')->group(function () {
            Route::get('form-data', [MobileStoreRequisitionController::class, 'formData'])->name('form-data');
            Route::get('items', [MobileStoreRequisitionController::class, 'itemLookup'])->name('items');
            Route::get('available-brands', [MobileStoreRequisitionController::class, 'availableBrands'])->name('available-brands');
            Route::get('/', [MobileStoreRequisitionController::class, 'index'])->name('index');
            Route::post('/', [MobileStoreRequisitionController::class, 'store'])->name('store');
            Route::get('{storeRequisition}', [MobileStoreRequisitionController::class, 'show'])->name('show');
        });

        Route::prefix('maintenance')->name('maintenance.')->group(function () {
            Route::get('form-data', [MobileMaintenanceController::class, 'formData'])->name('form-data');

            Route::get('plans', [MobileMaintenanceController::class, 'plans'])->name('plans.index');
            Route::get('plans/{maintenancePlan}', [MobileMaintenanceController::class, 'planShow'])->name('plans.show');

            Route::get('logs', [MobileMaintenanceController::class, 'logs'])->name('logs.index');
            Route::post('logs', [MobileMaintenanceController::class, 'logStore'])->name('logs.store');
            Route::get('logs/{maintenanceLog}', [MobileMaintenanceController::class, 'logShow'])->name('logs.show');
            Route::post('logs/{maintenanceLog}/complete', [MobileMaintenanceController::class, 'logComplete'])->name('logs.complete');

            Route::get('breakdowns', [MobileMaintenanceController::class, 'breakdowns'])->name('breakdowns.index');
            Route::post('breakdowns', [MobileMaintenanceController::class, 'breakdownStore'])->name('breakdowns.store');
            Route::get('breakdowns/{breakdown}', [MobileMaintenanceController::class, 'breakdownShow'])->name('breakdowns.show');
            Route::post('breakdowns/{breakdown}/acknowledge', [MobileMaintenanceController::class, 'breakdownAcknowledge'])->name('breakdowns.acknowledge');
            Route::post('breakdowns/{breakdown}/assign-team', [MobileMaintenanceController::class, 'breakdownAssignTeam'])->name('breakdowns.assign-team');
            Route::post('breakdowns/{breakdown}/start-repair', [MobileMaintenanceController::class, 'breakdownStartRepair'])->name('breakdowns.start-repair');
            Route::post('breakdowns/{breakdown}/resolve', [MobileMaintenanceController::class, 'breakdownResolve'])->name('breakdowns.resolve');
        });

        Route::prefix('production-v2')->name('production-v2.')->group(function () {
            Route::get('projects', [MobileProductionController::class, 'projects'])->name('projects.index');
            Route::get('projects/{project}/form-data', [MobileProductionController::class, 'formData'])->name('projects.form-data');

            Route::get('projects/{project}/dprs', [MobileProductionController::class, 'dprs'])->name('projects.dprs.index');
            Route::post('projects/{project}/dprs', [MobileProductionController::class, 'dprStore'])->name('projects.dprs.store');
            Route::get('projects/{project}/dprs/{productionDpr}', [MobileProductionController::class, 'dprShow'])->name('projects.dprs.show');

            Route::get('projects/{project}/operation-events/form-data', [MobileProductionController::class, 'operationFormData'])->name('projects.operation-events.form-data');
            Route::get('projects/{project}/operation-events', [MobileProductionController::class, 'operationEvents'])->name('projects.operation-events.index');
            Route::post('projects/{project}/operation-events', [MobileProductionController::class, 'operationEventStore'])->name('projects.operation-events.store');
            Route::get('projects/{project}/operation-events/{operationEvent}', [MobileProductionController::class, 'operationEventShow'])->name('projects.operation-events.show');
        });
    });
});
