<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
// Core & Profile
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\UomController;

// Settings & Configuration
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SecuritySettingsController;
use App\Http\Controllers\MailProfileController;
use App\Http\Controllers\MailTemplateController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SessionController;

// Audit & Logs
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\LoginLogController;

// Access Control
use App\Http\Controllers\AccessRoleController;
use App\Http\Controllers\AccessUserController;
use App\Http\Controllers\Storage\StorageAccessController;

// CRM Module
use App\Http\Controllers\CrmLeadController;
use App\Http\Controllers\CrmLeadAttachmentController;
use App\Http\Controllers\CrmQuotationController;
use App\Http\Controllers\CrmQuotationBreakupTemplateController;

// Project & BOM Module
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\BomItemController;
use App\Http\Controllers\BomTemplateController;
use App\Http\Controllers\BomTemplateItemController;
use App\Http\Controllers\BomPurchaseController;

// Material & Inventory Module
use App\Http\Controllers\MaterialTypeController;
use App\Http\Controllers\MaterialCategoryController;
use App\Http\Controllers\MaterialSubcategoryController;
use App\Http\Controllers\MaterialTaxonomyCsvController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemLookupController;
use App\Http\Controllers\MaterialStockPieceController;
use App\Http\Controllers\MaterialPlanningController;
use App\Http\Controllers\CuttingPlanController;
use App\Http\Controllers\SectionPlanController;
use App\Http\Controllers\RemnantLibraryController;

// Store Module
use App\Http\Controllers\StoreDashboardController;
use App\Http\Controllers\StoreStockController;
use App\Http\Controllers\StoreStockSummaryController;
use App\Http\Controllers\StoreStockItemController;
use App\Http\Controllers\StoreStockAdjustmentController;
use App\Http\Controllers\StoreStockRegisterController;
use App\Http\Controllers\StoreRequisitionController;
use App\Http\Controllers\StoreIssueController;
use App\Http\Controllers\StoreReturnController;
use App\Http\Controllers\MaterialReceiptController;
use App\Http\Controllers\StoreReorderLevelController;

// Purchase Module
use App\Http\Controllers\PurchaseIndentController;
use App\Http\Controllers\PurchaseRfqController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\Purchase\PurchaseBillController;
use App\Http\Controllers\StandardTermController;
use App\Http\Controllers\Vendor\PurchaseRfqVendorPortalController;

// Party Management
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PartyContactController;
use App\Http\Controllers\PartyBankController;
use App\Http\Controllers\PartyBranchController;
use App\Http\Controllers\PartyAttachmentController;

// Machine & Asset Module
use App\Http\Controllers\MachineController;
use App\Http\Controllers\MachineryBillController;
use App\Http\Controllers\MachineAssignmentController;
use App\Http\Controllers\MachineCalibrationController;
use App\Http\Controllers\GatePassController;

// Accounting Module
use App\Http\Controllers\Accounting\PaymentReceiptController;

// Production Module
use App\Http\Controllers\Production\ProductionActivityController;
use App\Http\Controllers\Production\ProductionPlanController;
use App\Http\Controllers\Production\ProductionPlanRouteController;
use App\Http\Controllers\Production\ProductionDprController;
use App\Http\Controllers\Production\ProductionQcController;
use App\Http\Controllers\Production\ProductionTraceabilityController;
use App\Http\Controllers\Production\ProductionBillingController;
use App\Http\Controllers\Production\ProductionDispatchController;
use App\Http\Controllers\Production\ProductionDashboardController;
use App\Http\Controllers\Production\ProductionPlanFromBomController;
use App\Http\Controllers\Production\ProductionTraceabilitySearchController;
use App\Http\Controllers\Production\ProductionPlanRouteMatrixController;


// Approval Module
use App\Http\Controllers\MyApprovalsController;
use App\Http\Controllers\ApprovalActionsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::prefix('vendor')
    ->name('vendor.')
    ->middleware(['signed', 'throttle:30,1'])
    ->group(function () {
        Route::get('/rfq/{purchase_rfq_vendor}/quote', [PurchaseRfqVendorPortalController::class, 'show'])
            ->name('rfq.quote');

        Route::post('/rfq/{purchase_rfq_vendor}/quote', [PurchaseRfqVendorPortalController::class, 'store'])
            ->name('rfq.quote.store');
    });




Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});




Route::middleware('auth')->group(function () {

    /*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/


     Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Dashboard APIs (charts + KPIs)
    Route::prefix('dashboard/api')->name('dashboard.api.')->group(function () {
    Route::get('summary', [DashboardController::class, 'apiSummary'])->name('summary');

    // Charts
    Route::get('charts/cashflow', [DashboardController::class, 'chartCashflow'])->name('charts.cashflow');
    Route::get('charts/store-grn-issue', [DashboardController::class, 'chartStoreGrnVsIssue'])->name('charts.store_grn_issue');
    Route::get('charts/production-dpr', [DashboardController::class, 'chartProductionDpr'])->name('charts.production_dpr');
   	Route::get('charts/gst-summary', [DashboardController::class, 'chartGstSummary'])->name('charts.gst_summary');
	Route::get('charts/top-expenses', [DashboardController::class, 'chartTopExpenses'])->name('charts.top_expenses');
	Route::get('charts/store-stock-mix', [DashboardController::class, 'chartStockMixByCategory'])->name('charts.store_stock_mix');

    });

    /*
    |--------------------------------------------------------------------------
    | Profile & User Management
    |--------------------------------------------------------------------------
    */
    // Breeze profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // User management routes
    Route::resource('users', UserController::class);
    Route::prefix('users')->name('users.')->group(function () {
        Route::post('{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
        Route::post('{id}/restore', [UserController::class, 'restore'])->name('restore');
        Route::delete('{id}/force-delete', [UserController::class, 'forceDelete'])->name('force-delete');
    });

    // Session management
    Route::prefix('profile/sessions')->name('sessions.')->group(function () {
        Route::get('/', [SessionController::class, 'index'])->name('index');
        Route::delete('{session}', [SessionController::class, 'destroy'])->name('destroy');
        Route::delete('/', [SessionController::class, 'destroyOthers'])->name('destroy-others');
    });

    /*
    |--------------------------------------------------------------------------
    | Organization Setup (Companies, Departments, UOM)
    |--------------------------------------------------------------------------
    */
    Route::resource('companies', CompanyController::class)->except(['show', 'destroy']);
    Route::resource('departments', DepartmentController::class)->except(['show']);
    Route::prefix('departments')->name('departments.')->group(function () {
        Route::post('reorder', [DepartmentController::class, 'reorder'])->name('reorder');
        Route::get('tree/json', [DepartmentController::class, 'tree'])->name('tree');
    });
    Route::resource('uoms', UomController::class)->except(['show']);

    /*
    |--------------------------------------------------------------------------
    | Settings & Configuration
    |--------------------------------------------------------------------------
    */
    Route::prefix('settings')->name('settings.')->group(function () {
        // General settings
        Route::get('general', [SettingsController::class, 'general'])->name('general');
        Route::post('general', [SettingsController::class, 'updateGeneral'])->name('general.update');

        // Security settings
        Route::get('security', [SecuritySettingsController::class, 'index'])->name('security');
        Route::post('security', [SecuritySettingsController::class, 'update'])->name('security.update');
    });

    // Mail profiles
    Route::resource('mail-profiles', MailProfileController::class)->except(['show']);
    Route::post('mail-profiles/{mail_profile}/test', [MailProfileController::class, 'sendTest'])
        ->name('mail-profiles.test');

    // Mail templates
    Route::resource('mail-templates', MailTemplateController::class)->except(['show']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read_all');
    Route::post('notifications/test', [NotificationController::class, 'sendTest'])->name('notifications.test');

    /*
    |--------------------------------------------------------------------------
    | Audit & Logs
    |--------------------------------------------------------------------------
    */
    // Activity logs
    Route::prefix('activity-logs')->name('activity-logs.')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->name('index');
        Route::get('export/csv', [ActivityLogController::class, 'export'])->name('export');
        Route::post('clear', [ActivityLogController::class, 'clear'])->name('clear');
        Route::get('{activityLog}', [ActivityLogController::class, 'show'])->name('show');
    });

    // Login logs
    Route::prefix('login-logs')->name('login-logs.')->group(function () {
        Route::get('/', [LoginLogController::class, 'index'])->name('index');
        Route::get('export/csv', [LoginLogController::class, 'export'])->name('export');
        Route::post('unlock', [LoginLogController::class, 'unlockAccount'])->name('unlock');
        Route::post('clear', [LoginLogController::class, 'clear'])->name('clear');
        Route::get('user/{user}', [LoginLogController::class, 'userHistory'])->name('user-history');
    });

    /*
    |--------------------------------------------------------------------------
    | Access Control (Roles & Permissions)
    |--------------------------------------------------------------------------
    */
    Route::prefix('access-control')->name('access.')->group(function () {
        // Roles
        Route::resource('roles', AccessRoleController::class);
        Route::post('roles/{role}/duplicate', [AccessRoleController::class, 'duplicate'])->name('roles.duplicate');

        // Users
        Route::get('users', [AccessUserController::class, 'index'])->name('users.index');
        Route::get('users/{user}/edit', [AccessUserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [AccessUserController::class, 'update'])->name('users.update');
      
      	Route::get('storage-access', [StorageAccessController::class, 'index'])->name('storage-access.index');
		Route::put('storage-access/{user}', [StorageAccessController::class, 'update'])->name('storage-access.update');
    });
	
  	 /*
    |--------------------------------------------------------------------------
    | Party Management (Vendors/Customers)
    |--------------------------------------------------------------------------
    */
    Route::resource('parties', PartyController::class);

    // Party contacts
    Route::post('parties/{party}/contacts', [PartyContactController::class, 'store'])->name('parties.contacts.store');
    Route::put('party-contacts/{contact}', [PartyContactController::class, 'update'])->name('party-contacts.update');
    Route::delete('party-contacts/{contact}', [PartyContactController::class, 'destroy'])->name('party-contacts.destroy');

    // Party banks
    Route::post('parties/{party}/banks', [PartyBankController::class, 'store'])->name('parties.banks.store');
    Route::put('party-banks/{bank}', [PartyBankController::class, 'update'])->name('party-banks.update');
    Route::delete('party-banks/{bank}', [PartyBankController::class, 'destroy'])->name('party-banks.destroy');

    // Party branches (additional GSTINs)
    // Route::post('parties/{party}/branches', [PartyBranchController::class, 'store'])->name('parties.branches.store');


    Route::post('/parties/{party}/branches', [PartyBranchController::class, 'store'])
    ->name('parties.branches.store');

    Route::get('api/parties/{party}/branches', [PartyBranchController::class, 'apiIndex'])->name('api.parties.branches');
    Route::delete('party-branches/{branch}', [PartyBranchController::class, 'destroy'])->name('party-branches.destroy');

    // Party attachments
    Route::post('parties/{party}/attachments', [PartyAttachmentController::class, 'store'])->name('parties.attachments.store');
    Route::delete('party-attachments/{attachment}', [PartyAttachmentController::class, 'destroy'])->name('party-attachments.destroy');
   
  
  	/*
    |--------------------------------------------------------------------------
    | Material Master & Taxonomy
    |--------------------------------------------------------------------------
    */
    Route::resource('material-types', MaterialTypeController::class)->except(['show']);
    Route::resource('material-categories', MaterialCategoryController::class)->except(['show']);
    Route::resource('material-subcategories', MaterialSubcategoryController::class)->except(['show']);
    Route::resource('items', ItemController::class)->except(['show']);

    // Material taxonomy CSV import/export
	Route::prefix('material-taxonomy')->name('material-taxonomy.')->group(function () {
    Route::get('csv', [MaterialTaxonomyCsvController::class, 'index'])->name('csv.index');

    /**
     * GET fallbacks (avoid 405 if someone opens import URLs in browser).
     * These do NOT change import logic — imports remain POST only.
     */
    Route::get('import/types', [MaterialTaxonomyCsvController::class, 'index'])->name('import.types.form');
    Route::get('import/categories', [MaterialTaxonomyCsvController::class, 'index'])->name('import.categories.form');
    Route::get('import/subcategories', [MaterialTaxonomyCsvController::class, 'index'])->name('import.subcategories.form');
    Route::get('import/all', [MaterialTaxonomyCsvController::class, 'index'])->name('import.all.form');

    Route::get('export/types', [MaterialTaxonomyCsvController::class, 'exportTypes'])->name('export.types');
    Route::post('import/types', [MaterialTaxonomyCsvController::class, 'importTypes'])->name('import.types');

    Route::get('export/categories', [MaterialTaxonomyCsvController::class, 'exportCategories'])->name('export.categories');
    Route::post('import/categories', [MaterialTaxonomyCsvController::class, 'importCategories'])->name('import.categories');

    Route::get('export/subcategories', [MaterialTaxonomyCsvController::class, 'exportSubcategories'])->name('export.subcategories');
    Route::post('import/subcategories', [MaterialTaxonomyCsvController::class, 'importSubcategories'])->name('import.subcategories');

    // Universal template
    Route::get('template/all', [MaterialTaxonomyCsvController::class, 'downloadAllTemplate'])->name('template.all');
    Route::post('import/all', [MaterialTaxonomyCsvController::class, 'importAllWithItems'])->name('import.all');
	});


    // Material stock pieces (global)
    Route::get('material-stock-pieces', [MaterialStockPieceController::class, 'index'])->name('material-stock-pieces.index');
    Route::get('material-stock-pieces/{materialStockPiece}', [MaterialStockPieceController::class, 'show'])->name('material-stock-pieces.show');

    // AJAX item search
    Route::get('/ajax/items/search', [ItemLookupController::class, 'index'])->name('ajax.items.search');

    /*
    |--------------------------------------------------------------------------
    | Store Module
    |--------------------------------------------------------------------------
    */
    // Store Dashboard
    Route::get('store/dashboard', [StoreDashboardController::class, 'index'])
        ->name('store.dashboard')
        ->middleware('permission:store.stock.view');

    // Store Stock views
    Route::get('store-stock', [StoreStockController::class, 'index'])
        ->name('store-stock.index')
        ->middleware('permission:store.stock.view');

    Route::get('store-stock-summary', [StoreStockSummaryController::class, 'index'])
        ->name('store-stock-summary.index')
        ->middleware('permission:store.stock.view');

    Route::get('store-stock-register', [StoreStockRegisterController::class, 'index'])
        ->name('store-stock-register.index')
        ->middleware('permission:store.stock.view');

    Route::get('store-remnants', [RemnantLibraryController::class, 'index'])
        ->name('store-remnants.index')
        ->middleware('permission:store.stock.view');

    // Store Stock Items
    Route::resource('store-stock-items', StoreStockItemController::class)->only(['index', 'show', 'edit', 'update']);

    // Store Stock Adjustments
    Route::resource('store-stock-adjustments', StoreStockAdjustmentController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);

    // Store Reorder Levels (Min/Target)
	Route::resource('store-reorder-levels', StoreReorderLevelController::class)
    ->except(['show']);

	// Low Stock report + Create Purchase Indent
	Route::get('store-low-stock', [StoreReorderLevelController::class, 'lowStock'])
    ->name('store-low-stock.index');

	Route::post('store-low-stock/create-indent', [StoreReorderLevelController::class, 'createIndent'])
    ->name('store-low-stock.create-indent');

  
  	// Material Receipts (GRN)
    Route::resource('material-receipts', MaterialReceiptController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('material-receipts/{materialReceipt}/attachments', [MaterialReceiptController::class, 'uploadHeaderAttachment'])
        ->name('material-receipts.attachments.store');
    Route::post('material-receipt-lines/{materialReceiptLine}/attachments', [MaterialReceiptController::class, 'uploadLineAttachment'])
        ->name('material-receipt-lines.attachments.store');
    Route::post('material-receipts/{materialReceipt}/status', [MaterialReceiptController::class, 'updateStatus'])
        ->name('material-receipts.update-status');
  
  	Route::get('material-receipts/{materialReceipt}/return', [MaterialReceiptController::class, 'createReturn'])
    ->name('material-receipts.return.create');

	Route::post('material-receipts/{materialReceipt}/return', [MaterialReceiptController::class, 'storeReturn'])
    ->name('material-receipts.return.store');

    // Attachments (shared)
    Route::get('attachments/{attachment}/download', [MaterialReceiptController::class, 'downloadAttachment'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [MaterialReceiptController::class, 'deleteAttachment'])->name('attachments.destroy');

    // Store Requisitions
    Route::resource('store-requisitions', StoreRequisitionController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
	Route::get('ajax/store-requisitions/available-brands', [StoreRequisitionController::class, 'ajaxAvailableBrands'])
    ->name('ajax.store-requisitions.available-brands');

   
 	 // Store Issues
    Route::resource('store-issues', StoreIssueController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('store-issues/{store_issue}/post-to-accounts', [StoreIssueController::class, 'postToAccounts'])
        ->name('store-issues.post-to-accounts')
        ->middleware('permission:store.issue.post_to_accounts');
    Route::get('ajax/store-issues/{store_issue}/lines', [StoreIssueController::class, 'ajaxLines'])
        ->name('ajax.store-issues.lines');

    // Store Returns
    Route::resource('store-returns', StoreReturnController::class)->only(['index', 'create', 'store', 'show']);
	Route::post('store-returns/{storeReturn}/post-to-accounts', [StoreReturnController::class, 'postToAccounts'])
    ->name('store-returns.post-to-accounts');

	Route::post('store-stock-adjustments/{storeStockAdjustment}/post-to-accounts', [StoreStockAdjustmentController::class, 'postToAccounts'])
    ->name('store-stock-adjustments.post-to-accounts');
Route::get('/get-item-brands/{id}', [StoreStockAdjustmentController::class, 'getBrands']);
  
    /*
    |--------------------------------------------------------------------------
    | Purchase Module
    |--------------------------------------------------------------------------
    */
    // Standard Terms
    Route::resource('standard-terms', StandardTermController::class);

    // Purchase Indents
    Route::resource('purchase-indents', PurchaseIndentController::class)
        ->parameters(['purchase-indents' => 'indent'])
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('purchase-indents/{indent}/approve', [PurchaseIndentController::class, 'approve'])->name('purchase-indents.approve');
    Route::post('purchase-indents/{indent}/reject', [PurchaseIndentController::class, 'reject'])->name('purchase-indents.reject');
    Route::post('purchase-indents/{indent}/cancel', [PurchaseIndentController::class, 'cancel'])->name('purchase-indents.cancel');

    // Purchase RFQs
    Route::resource('purchase-rfqs', PurchaseRfqController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('purchase-rfqs/{purchaseRfq}/send', [PurchaseRfqController::class, 'sendEmails'])->name('purchase-rfqs.send');
    Route::get('purchase-rfqs/{purchaseRfq}/quotes', [PurchaseRfqController::class, 'editQuotes'])->name('purchase-rfqs.quotes.edit');
    Route::match(['POST', 'PUT'], 'purchase-rfqs/{purchaseRfq}/quotes', [PurchaseRfqController::class, 'updateQuotes'])->name('purchase-rfqs.quotes.update');
    Route::post('purchase-rfqs/{purchaseRfq}/cancel', [PurchaseRfqController::class, 'cancel'])->name('purchase-rfqs.cancel');
	Route::post('purchase-rfqs/{purchaseRfq}/revision/send', [PurchaseRfqController::class, 'sendRevisionEmails'])
    ->name('purchase-rfqs.revision.send');

      
  	// Purchase Orders
    Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'show', 'edit', 'update']);
    Route::post('purchase-orders/from-rfq/{purchase_rfq}', [PurchaseOrderController::class, 'storeFromRfq'])->name('purchase-orders.store-from-rfq');
    Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
    Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::post('purchase-orders/{purchase_order}/send-email', [PurchaseOrderController::class, 'sendEmail'])->name('purchase-orders.send-email');
    Route::get('purchase-orders/{purchase_order}/print', [PurchaseOrderController::class, 'print'])->name('purchase-orders.print');
    Route::get('purchase-orders/{purchaseOrder}/items-for-grn', [PurchaseOrderController::class, 'itemsForGrn'])
        ->name('purchase-orders.items-for-grn')
        ->middleware('permission:store.material_receipt.create');

    // Purchase Bills (Supplier Invoices)
    Route::prefix('purchase')->name('purchase.')->group(function () {
        Route::post('bills/{bill}/post', [PurchaseBillController::class, 'post'])->name('bills.post');
        Route::resource('bills', PurchaseBillController::class);
    Route::post('bills/{bill}/reverse', [PurchaseBillController::class, 'reverse'])
        ->name('bills.reverse');
    Route::post('bills/{bill}/unallocate', [PurchaseBillController::class, 'unallocate'])
    	->name('bills.unallocate');
        
    });
  
  	

    // AJAX helpers for purchase bills
    Route::get('ajax/purchase-orders', [PurchaseBillController::class, 'ajaxPurchaseOrdersForSupplier'])
        ->name('purchase.bills.ajax.purchase-orders');
    Route::get('ajax/grn-lines', [PurchaseBillController::class, 'ajaxGrnLinesForPurchaseOrder'])
        ->name('purchase.bills.ajax.grn-lines');

    /*
    |--------------------------------------------------------------------------
    | CRM Module
    |--------------------------------------------------------------------------
    */
    Route::prefix('crm')->as('crm.')->group(function () {
        // Leads
        Route::resource('leads', CrmLeadController::class);
        Route::post('leads/{lead}/mark-won', [CrmLeadController::class, 'markWon'])->name('leads.mark-won');
        Route::post('leads/{lead}/mark-lost', [CrmLeadController::class, 'markLost'])->name('leads.mark-lost');
		
      	// Lead attachments
        Route::post('leads/{lead}/attachments', [CrmLeadAttachmentController::class, 'store'])->name('leads.attachments.store');
        Route::get('leads/{lead}/attachments/{attachment}/download', [CrmLeadAttachmentController::class, 'download'])->name('leads.attachments.download');
        Route::delete('leads/{lead}/attachments/{attachment}', [CrmLeadAttachmentController::class, 'destroy'])->name('leads.attachments.destroy');

        
      	// Quotations
        Route::resource('quotations', CrmQuotationController::class);

        // Quotation Breakup Templates (internal costing templates)
        Route::resource('quotation-breakup-templates', CrmQuotationBreakupTemplateController::class)
            ->parameters(['quotation-breakup-templates' => 'template']);

        Route::get('leads/{lead}/quotations/create', [CrmQuotationController::class, 'createForLead'])->name('leads.quotations.create');
        Route::post('leads/{lead}/quotations', [CrmQuotationController::class, 'storeForLead'])->name('leads.quotations.store');
        Route::post('quotations/{quotation}/accept', [CrmQuotationController::class, 'accept'])->name('quotations.accept');
        Route::post('quotations/{quotation}/revise', [CrmQuotationController::class, 'revise'])->name('quotations.revise');
        Route::get('quotations/{quotation}/pdf', [CrmQuotationController::class, 'pdf'])->name('quotations.pdf');
        Route::get('quotations/{quotation}/email', [CrmQuotationController::class, 'emailForm'])->name('quotations.email-form');
        Route::post('quotations/{quotation}/email', [CrmQuotationController::class, 'sendEmail'])->name('quotations.email-send');
    });

    /*
    |--------------------------------------------------------------------------
    | Project Module
    |--------------------------------------------------------------------------
    */
    Route::resource('projects', ProjectController::class);

    /*
    |--------------------------------------------------------------------------
    | BOM Module (Project-scoped)
    |--------------------------------------------------------------------------
    */
    Route::prefix('projects/{project}')->name('projects.')->group(function () {
        // BOM headers
        Route::resource('boms', BomController::class);
        Route::post('boms/{bom}/finalize', [BomController::class, 'finalize'])->name('boms.finalize');
        Route::get('boms/{bom}/export', [BomController::class, 'export'])->name('boms.export');
        Route::post('boms/{bom}/clone-version', [BomController::class, 'cloneVersion'])->name('boms.clone-version');
        Route::get('boms/{bom}/copy', [BomController::class, 'copyForm'])->name('boms.copy-form');
        Route::post('boms/{bom}/copy', [BomController::class, 'copyStore'])->name('boms.copy-store');
        Route::get('boms/{bom}/assemblies/{item}/export', [BomController::class, 'exportAssembly'])->name('boms.export-assembly');
        Route::post('boms/{bom}/save-template', [BomTemplateController::class, 'storeFromBom'])->name('boms.save-template');
        Route::get('boms/{bom}/requirements', [BomController::class, 'requirements'])->name('boms.requirements');

        // Material planning wizard
        Route::get('boms/{bom}/material-planning', [MaterialPlanningController::class, 'index'])->name('boms.material-planning.index');
        Route::get('boms/{bom}/material-planning/select-stock', [MaterialPlanningController::class, 'selectStock'])->name('boms.material-planning.select-stock');
        Route::post('boms/{bom}/material-planning/allocate', [MaterialPlanningController::class, 'allocate'])->name('boms.material-planning.allocate');
        Route::post('boms/{bom}/material-planning/add-planned-piece', [MaterialPlanningController::class, 'addPlannedPiece'])->name('boms.material-planning.add-planned-piece');
        Route::get('boms/{bom}/material-planning/debug', [MaterialPlanningController::class, 'debugStock'])->name('boms.material-planning.debug');

        // BOM items
        Route::prefix('boms/{bom}')->name('boms.')->group(function () {
            Route::resource('items', BomItemController::class)->except(['show', 'index']);
        });

        // Cutting plans
        Route::get('boms/{bom}/cutting-plans', [CuttingPlanController::class, 'index'])->name('boms.cutting-plans.index');
        Route::get('boms/{bom}/cutting-plans/create', [CuttingPlanController::class, 'create'])->name('boms.cutting-plans.create');
        Route::post('boms/{bom}/cutting-plans', [CuttingPlanController::class, 'store'])->name('boms.cutting-plans.store');
        Route::get('boms/{bom}/cutting-plans/{cuttingPlan}/edit', [CuttingPlanController::class, 'edit'])->name('boms.cutting-plans.edit');
        Route::post('boms/{bom}/cutting-plans/{cuttingPlan}/plates', [CuttingPlanController::class, 'addPlate'])->name('boms.cutting-plans.add-plate');
        Route::post('boms/{bom}/cutting-plans/{cuttingPlan}/plates/{plate}/allocations', [CuttingPlanController::class, 'addAllocation'])->name('boms.cutting-plans.add-allocation');

        // Section plans
        Route::get('boms/{bom}/section-plans', [SectionPlanController::class, 'index'])->name('boms.section-plans.index');
        Route::get('boms/{bom}/section-plans/edit', [SectionPlanController::class, 'edit'])->name('boms.section-plans.edit');
        Route::post('boms/{bom}/section-plans/{sectionPlan}/bars', [SectionPlanController::class, 'storeBar'])->name('boms.section-plans.bars.store');
        Route::delete('boms/{bom}/section-plans/{sectionPlan}/bars/{bar}', [SectionPlanController::class, 'destroyBar'])->name('boms.section-plans.bars.destroy');

        // Purchase plates
        Route::get('boms/{bom}/purchase-plates', [BomPurchaseController::class, 'plates'])->name('boms.purchase-plates.index');

       /*
        |--------------------------------------------------------------------------
        | Production Module (Project-scoped)
        |--------------------------------------------------------------------------
        */
		        // Production Plans
        // NOTE: Keep static routes BEFORE the resource route, otherwise
        // `/production-plans/from-bom` is captured by the resource `show` route
        // as `{production_plan}` and returns 404.
        // WP-03: Create Production Plan from BOM
        Route::get('production-plans/from-bom', [ProductionPlanFromBomController::class, 'form'])
            ->name('production-plans.from-bom');
        Route::post('production-plans/from-bom', [ProductionPlanFromBomController::class, 'store'])
            ->name('production-plans.from-bom.store');

        Route::resource('production-plans', ProductionPlanController::class)
            ->parameters(['production-plans' => 'production_plan'])
            ->whereNumber('production_plan');

        Route::post('production-plans/{production_plan}/approve', [ProductionPlanController::class, 'approve'])
            ->name('production-plans.approve');

		// Production Plan Routes
        Route::get('production-plans/{production_plan}/items/{item}/route', [ProductionPlanRouteController::class, 'edit'])
            ->name('production-plans.route.edit');
        Route::put('production-plans/{production_plan}/items/{item}/route', [ProductionPlanRouteController::class, 'update'])
            ->name('production-plans.route.update');
      
      // Route Matrix (bulk route enable/disable + assignments)
	Route::get('production-plans/{production_plan}/route-matrix', [ProductionPlanRouteMatrixController::class, 'edit'])
    ->name('production-plans.route-matrix.edit');
	Route::post('production-plans/{production_plan}/route-matrix', [ProductionPlanRouteMatrixController::class, 'update'])
    ->name('production-plans.route-matrix.update');
	Route::post('production-plans/{production_plan}/route-matrix/assign', [ProductionPlanRouteMatrixController::class, 'bulkAssign'])
    ->name('production-plans.route-matrix.assign');


        // Production DPRs (Project-scoped)
        Route::resource('production-dprs', ProductionDprController::class)
            ->parameters(['production-dprs' => 'production_dpr'])
            ->only(['index', 'create', 'store', 'show']);
        Route::post('production-dprs/{production_dpr}/submit', [ProductionDprController::class, 'submit'])
            ->name('production-dprs.submit');
        Route::post('production-dprs/{production_dpr}/approve', [ProductionDprController::class, 'approve'])
            ->name('production-dprs.approve');

        // Production QC (Project-scoped)
        Route::get('production-qc', [ProductionQcController::class, 'index'])->name('production-qc.index');
        Route::put('production-qc/{qc}', [ProductionQcController::class, 'update'])->name('production-qc.update');

        // Production Traceability
        Route::get('production-dprs/{production_dpr}/traceability', [ProductionTraceabilityController::class, 'edit'])
            ->name('production-dprs.traceability.edit');
        Route::post('production-dprs/{production_dpr}/traceability', [ProductionTraceabilityController::class, 'update'])
            ->name('production-dprs.traceability.update');

        // Production Billing
        Route::get('production-billing', [ProductionBillingController::class, 'index'])->name('production-billing.index');
        Route::get('production-billing/create', [ProductionBillingController::class, 'create'])->name('production-billing.create');
        Route::post('production-billing', [ProductionBillingController::class, 'store'])->name('production-billing.store');
        Route::get('production-billing/{production_bill}', [ProductionBillingController::class, 'show'])->name('production-billing.show');
        Route::post('production-billing/{production_bill}/finalize', [ProductionBillingController::class, 'finalize'])->name('production-billing.finalize');
        Route::post('production-billing/{production_bill}/cancel', [ProductionBillingController::class, 'cancel'])->name('production-billing.cancel');

        // Production Dispatch (Client)
        Route::get('production-dispatches', [ProductionDispatchController::class, 'index'])->name('production-dispatches.index');
        Route::get('production-dispatches/create', [ProductionDispatchController::class, 'create'])->name('production-dispatches.create');
        Route::post('production-dispatches', [ProductionDispatchController::class, 'store'])->name('production-dispatches.store');
        Route::get('production-dispatches/{production_dispatch}', [ProductionDispatchController::class, 'show'])->name('production-dispatches.show');
        Route::post('production-dispatches/{production_dispatch}/finalize', [ProductionDispatchController::class, 'finalize'])->name('production-dispatches.finalize');
        Route::post('production-dispatches/{production_dispatch}/cancel', [ProductionDispatchController::class, 'cancel'])->name('production-dispatches.cancel');

        // Production Dashboard
        Route::get('production-dashboard', [ProductionDashboardController::class, 'index'])->name('production-dashboard.index');
        Route::get('production-dashboard/wip-activity', [ProductionDashboardController::class, 'wipByActivity'])->name('production-dashboard.wip-activity');
        Route::get('production-dashboard/remnants', [ProductionDashboardController::class, 'remnants'])->name('production-dashboard.remnants');

        // Production Traceability Search (WP-12)
        Route::get('production-traceability', [ProductionTraceabilitySearchController::class, 'index'])->name('production-traceability.index');
    });

    /*
    |--------------------------------------------------------------------------
    | Production Module (Global Routes)
    |--------------------------------------------------------------------------
    */
    Route::prefix('production')->name('production.')->group(function () {
        // Production Activities Master
        Route::resource('activities', ProductionActivityController::class)
            ->parameters(['activities' => 'activity'])
            ->except(['show']);

        // Global Production DPRs
        Route::resource('production-dprs', ProductionDprController::class)
            ->parameters(['production-dprs' => 'production_dpr'])
            ->only(['index', 'create', 'store', 'show']);
        Route::post('production-dprs/{production_dpr}/submit', [ProductionDprController::class, 'submit'])
            ->name('production-dprs.submit');
        Route::post('production-dprs/{production_dpr}/approve', [ProductionDprController::class, 'approve'])
            ->name('production-dprs.approve');

        // Global Production QC
        Route::get('production-qc', [ProductionQcController::class, 'index'])->name('production-qc.index');
        Route::put('production-qc/{qc}', [ProductionQcController::class, 'update'])->name('production-qc.update');
    });


    /*
    |--------------------------------------------------------------------------
    | BOM Template Library
    |--------------------------------------------------------------------------
    */
    Route::resource('bom-templates', BomTemplateController::class);
    Route::get('bom-templates/{bomTemplate}/create-bom', [BomTemplateController::class, 'createBomForm'])
        ->name('bom-templates.create-bom-form');
    Route::post('bom-templates/{bomTemplate}/create-bom', [BomTemplateController::class, 'createBomStore'])
        ->name('bom-templates.create-bom-store');
    Route::prefix('bom-templates/{bomTemplate}')->name('bom-templates.')->group(function () {
        Route::resource('items', BomTemplateItemController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->names('items');
    });

  		  /*
		|--------------------------------------------------------------------------
		| Machinery & Assets Module
		|--------------------------------------------------------------------------
		*/

  // Machines (base URL: /machinery, route names: machines.*)
	Route::prefix('machinery')->name('machines.')->group(function () {
    Route::get('/', [MachineController::class, 'index'])->name('index');
    Route::get('/create', [MachineController::class, 'create'])->name('create');
    Route::post('/', [MachineController::class, 'store'])->name('store');
    Route::get('/{machine}', [MachineController::class, 'show'])->name('show');
    Route::get('/{machine}/edit', [MachineController::class, 'edit'])->name('edit');
    Route::put('/{machine}', [MachineController::class, 'update'])->name('update');
    Route::delete('/{machine}', [MachineController::class, 'destroy'])->name('destroy');
	});

	
	// Machinery Bills (Purchase Bills containing machinery items)
	Route::prefix('machinery-bills')->name('machinery-bills.')->group(function () {
        Route::get('/', [MachineryBillController::class, 'index'])
            ->middleware('permission:machinery.machine.view')
            ->name('index');

        Route::get('/{bill}', [MachineryBillController::class, 'show'])
            ->middleware('permission:machinery.machine.view')
            ->name('show');

        Route::post('/{bill}/generate-machines', [MachineryBillController::class, 'generateMachines'])
            ->middleware('permission:machinery.machine.create')
            ->name('generate');
	});


	// Machine Assignments (base URL: /machine-assignments, route names: machine-assignments.*)
	Route::prefix('machine-assignments')->name('machine-assignments.')->group(function () {
    Route::get('/', [MachineAssignmentController::class, 'index'])->name('index');
    Route::get('/create', [MachineAssignmentController::class, 'create'])->name('create');
    Route::post('/', [MachineAssignmentController::class, 'store'])->name('store');
    Route::get('/{machineAssignment}', [MachineAssignmentController::class, 'show'])->name('show');

    // IMPORTANT: these must match MachineAssignmentController method names
    Route::get('/{machineAssignment}/return', [MachineAssignmentController::class, 'returnForm'])->name('return-form');
    Route::post('/{machineAssignment}/return', [MachineAssignmentController::class, 'processReturn'])->name('process-return');

    Route::get('/{machineAssignment}/extend', [MachineAssignmentController::class, 'extendForm'])->name('extend-form');
    Route::post('/{machineAssignment}/extend', [MachineAssignmentController::class, 'processExtend'])->name('process-extend');
	});

	// Machine Calibrations (base URL: /machine-calibrations, route names: machine-calibrations.*)
	Route::prefix('machine-calibrations')->name('machine-calibrations.')->group(function () {
    Route::get('/dashboard', [MachineCalibrationController::class, 'dashboard'])->name('dashboard');

    Route::get('/', [MachineCalibrationController::class, 'index'])->name('index');
    Route::get('/create', [MachineCalibrationController::class, 'create'])->name('create');
    Route::post('/', [MachineCalibrationController::class, 'store'])->name('store');

    Route::get('/{machineCalibration}', [MachineCalibrationController::class, 'show'])->name('show');
    Route::get('/{machineCalibration}/edit', [MachineCalibrationController::class, 'edit'])->name('edit');
    Route::put('/{machineCalibration}', [MachineCalibrationController::class, 'update'])->name('update');
    Route::delete('/{machineCalibration}', [MachineCalibrationController::class, 'destroy'])->name('destroy');
	});

    // Gate Passes
    Route::resource('gate-passes', GatePassController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('gate-passes/{gate_pass}/return', [GatePassController::class, 'returnForm'])->name('gate-passes.return');
    Route::post('gate-passes/{gate_pass}/return', [GatePassController::class, 'registerReturn'])->name('gate-passes.register-return');
    Route::post('gate-passes/{gate_pass}/close', [GatePassController::class, 'closeWithoutFullReturn'])->name('gate-passes.close-without-full-return');
    Route::get('gate-passes/{gate_pass}/pdf', [GatePassController::class, 'pdf'])->name('gate-passes.pdf');

     /*
    |--------------------------------------------------------------------------
    | Accounting Module
    |--------------------------------------------------------------------------
    */

    
    // NOTE: These are legacy payment/receipt routes kept for reference.
    // The active payment/receipt voucher flow is implemented in routes/accounting.php (BankCashVoucherController).
    Route::prefix('accounting/legacy')->name('accounting.legacy.')->group(function () {
        // Payments (legacy)
        Route::get('payments/create', [PaymentReceiptController::class, 'createPayment'])->name('payments.create');
        Route::post('payments', [PaymentReceiptController::class, 'storePayment'])->name('payments.store');

        // Receipts (legacy)
        Route::get('receipts/create', [PaymentReceiptController::class, 'createReceipt'])->name('receipts.create');
        Route::post('receipts', [PaymentReceiptController::class, 'storeReceipt'])->name('receipts.store');
    });


    /*
    |--------------------------------------------------------------------------
    | Approval Workflow
    |--------------------------------------------------------------------------
    */
    Route::get('/my-approvals', [MyApprovalsController::class, 'index'])->name('my-approvals.index');
    Route::get('/my-approvals/{approvalRequest}', [MyApprovalsController::class, 'show'])->name('my-approvals.show');
    Route::post('/approvals/steps/{approvalStep}/approve', [ApprovalActionsController::class, 'approve'])->name('approvals.steps.approve');
    Route::post('/approvals/steps/{approvalStep}/reject', [ApprovalActionsController::class, 'reject'])->name('approvals.steps.reject');

}); // End of auth middleware group

require __DIR__ . '/auth.php';
require __DIR__ . '/accounting.php';
require __DIR__ . '/hr.php';
require __DIR__ . '/tasks.php';
require __DIR__ . '/support.php';
// Machinery Maintenance module routes
require __DIR__.'/machine_maintenance_routes.php';
require __DIR__.'/reports_hub.php';
require __DIR__ . '/storage.php';
