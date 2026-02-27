<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\AccountGroupController;
use App\Http\Controllers\Accounting\AccountTypeController;
use App\Http\Controllers\Accounting\BalanceSheetReportController;
use App\Http\Controllers\Accounting\BankCashVoucherController;
use App\Http\Controllers\Accounting\CashFlowReportController;
use App\Http\Controllers\Accounting\ClientAgeingReportController;
use App\Http\Controllers\Accounting\ClientOutstandingReportController;
use App\Http\Controllers\Accounting\DayBookReportController;
use App\Http\Controllers\Accounting\FundFlowReportController;
use App\Http\Controllers\Accounting\GstHsnPurchaseSummaryReportController;
use App\Http\Controllers\Accounting\GstHsnSalesSummaryReportController;
use App\Http\Controllers\Accounting\GstPurchaseRegisterReportController;
use App\Http\Controllers\Accounting\GstSalesRegisterReportController;
use App\Http\Controllers\Accounting\GstSummaryReportController;
use App\Http\Controllers\Accounting\GstVoucherRegisterReportController;
use App\Http\Controllers\Accounting\InventoryValuationReportController;
use App\Http\Controllers\Accounting\LedgerReportController;
use App\Http\Controllers\Accounting\MigrationToolsController;
use App\Http\Controllers\Accounting\OnAccountAdjustmentController;
use App\Http\Controllers\Accounting\ProfitLossReportController;
use App\Http\Controllers\Accounting\ProjectCostSheetController;
use App\Http\Controllers\Accounting\SupplierAgeingReportController;
use App\Http\Controllers\Accounting\SupplierOutstandingReportController;
use App\Http\Controllers\Accounting\TrialBalanceReportController;
use App\Http\Controllers\Accounting\UnbalancedVouchersReportController;
use App\Http\Controllers\Accounting\VoucherController;
use App\Http\Controllers\Accounting\VoucherSeriesController;
use App\Http\Controllers\Accounting\TdsSectionController;
use App\Http\Controllers\Accounting\TdsCertificateReportController;
use App\Http\Controllers\ClientRaBillController;
use App\Http\Controllers\SubcontractorRaBillController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('accounting')->name('accounting.')->group(function () {
    // Accounts
    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    // COA Masters: Account Groups
    Route::get('account-groups', [AccountGroupController::class, 'index'])->name('account-groups.index');
    Route::get('account-groups/create', [AccountGroupController::class, 'create'])->name('account-groups.create');
    Route::post('account-groups', [AccountGroupController::class, 'store'])->name('account-groups.store');
    Route::get('account-groups/{accountGroup}/edit', [AccountGroupController::class, 'edit'])->name('account-groups.edit');
    Route::put('account-groups/{accountGroup}', [AccountGroupController::class, 'update'])->name('account-groups.update');
    Route::delete('account-groups/{accountGroup}', [AccountGroupController::class, 'destroy'])->name('account-groups.destroy');

    // COA Masters: Account Types
    Route::get('account-types', [AccountTypeController::class, 'index'])->name('account-types.index');
    Route::get('account-types/create', [AccountTypeController::class, 'create'])->name('account-types.create');
    Route::post('account-types', [AccountTypeController::class, 'store'])->name('account-types.store');
    Route::get('account-types/{accountType}/edit', [AccountTypeController::class, 'edit'])->name('account-types.edit');
    Route::put('account-types/{accountType}', [AccountTypeController::class, 'update'])->name('account-types.update');
    Route::delete('account-types/{accountType}', [AccountTypeController::class, 'destroy'])->name('account-types.destroy');


    // Vouchers
    Route::get('vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
    Route::get('vouchers/create', [VoucherController::class, 'create'])->name('vouchers.create');
    Route::post('vouchers', [VoucherController::class, 'store'])->name('vouchers.store');

    // Phase 6: Manual voucher drilldown + workflow (draft → post, and reversal)
    Route::get('vouchers/{voucher}', [VoucherController::class, 'show'])->name('vouchers.show');
    Route::get('vouchers/{voucher}/edit', [VoucherController::class, 'edit'])->name('vouchers.edit');
    Route::put('vouchers/{voucher}', [VoucherController::class, 'update'])->name('vouchers.update');
    Route::delete('vouchers/{voucher}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');
    Route::post('vouchers/{voucher}/post', [VoucherController::class, 'post'])->name('vouchers.post');
    Route::post('vouchers/{voucher}/reverse', [VoucherController::class, 'reverse'])->name('vouchers.reverse');

    // Voucher Series (centralised numbering)
    Route::resource('voucher-series', VoucherSeriesController::class)->except(['show']);

    // TDS Sections (Master)
    Route::resource('tds-sections', TdsSectionController::class)->except(['show']);

    // Payments / Receipts (Bank & Cash)
    Route::get('payments/create', [BankCashVoucherController::class, 'createPayment'])->name('payments.create');
    Route::post('payments', [BankCashVoucherController::class, 'storePayment'])->name('payments.store');

    Route::get('receipts/create', [BankCashVoucherController::class, 'createReceipt'])->name('receipts.create');
    Route::post('receipts', [BankCashVoucherController::class, 'storeReceipt'])->name('receipts.store');

    // Phase 8: Apply On-Account (Advance) to client bills
    Route::get('receipts/on-account', [OnAccountAdjustmentController::class, 'index'])->name('receipts.on-account.index');
    Route::get('receipts/on-account/{voucherLine}', [OnAccountAdjustmentController::class, 'create'])->name('receipts.on-account.create');
    Route::post('receipts/on-account/{voucherLine}', [OnAccountAdjustmentController::class, 'store'])->name('receipts.on-account.store');

    // API helpers
    Route::get('api/open-purchase-bills', [BankCashVoucherController::class, 'openPurchaseBills'])->name('api.open-purchase-bills');
    Route::get('api/open-client-bills', [BankCashVoucherController::class, 'openClientBills'])->name('api.open-client-bills');

    /*
    |--------------------------------------------------------------------------
    | DEV-14: Migration Tools (Opening Balances & Outstanding AR/AP)
    |--------------------------------------------------------------------------
    */
    Route::prefix('migration-tools')->name('migration-tools.')->group(function () {
        Route::get('/', [MigrationToolsController::class, 'index'])->name('index');

        // Opening balances (ledger-wise)
        Route::get('opening-balances', [MigrationToolsController::class, 'openingBalancesForm'])->name('opening-balances');
        Route::get('opening-balances/template', [MigrationToolsController::class, 'downloadTemplateOpeningBalances'])->name('opening-balances.template');
        Route::post('opening-balances/import', [MigrationToolsController::class, 'importOpeningBalances'])->name('opening-balances.import');

        // Outstanding AP (Supplier Bills)
        Route::get('outstanding-ap', [MigrationToolsController::class, 'outstandingApForm'])->name('outstanding-ap');
        Route::get('outstanding-ap/template', [MigrationToolsController::class, 'downloadTemplateOutstandingAp'])->name('outstanding-ap.template');
        Route::post('outstanding-ap/import', [MigrationToolsController::class, 'importOutstandingAp'])->name('outstanding-ap.import');

        // Outstanding AR (Client Bills)
        Route::get('outstanding-ar', [MigrationToolsController::class, 'outstandingArForm'])->name('outstanding-ar');
        Route::get('outstanding-ar/template', [MigrationToolsController::class, 'downloadTemplateOutstandingAr'])->name('outstanding-ar.template');
        Route::post('outstanding-ar/import', [MigrationToolsController::class, 'importOutstandingAr'])->name('outstanding-ar.import');
    });

    // Core Reports
    Route::get('reports/supplier-outstanding', [SupplierOutstandingReportController::class, 'index'])->name('reports.supplier-outstanding');
    Route::get('reports/client-outstanding', [ClientOutstandingReportController::class, 'index'])->name('reports.client-outstanding');
    Route::get('reports/trial-balance', [TrialBalanceReportController::class, 'index'])->name('reports.trial-balance');
    Route::get('reports/ledger', [LedgerReportController::class, 'index'])->name('reports.ledger');
    Route::get('reports/day-book', [DayBookReportController::class, 'index'])->name('reports.day-book');
    Route::get('reports/profit-loss', [ProfitLossReportController::class, 'index'])->name('reports.profit-loss');
    Route::get('reports/balance-sheet', [BalanceSheetReportController::class, 'index'])->name('reports.balance-sheet');
    Route::get('reports/inventory-valuation', [InventoryValuationReportController::class, 'index'])->name('reports.inventory-valuation');

    // GST Reports
    Route::get('reports/gst-summary', [GstSummaryReportController::class, 'index'])->name('reports.gst-summary');

    Route::get('reports/gst-purchase-register', [GstPurchaseRegisterReportController::class, 'index'])->name('reports.gst-purchase-register');
    Route::get('reports/gst-purchase-register/export', [GstPurchaseRegisterReportController::class, 'export'])->name('reports.gst-purchase-register.export');

    Route::get('reports/gst-sales-register', [GstSalesRegisterReportController::class, 'index'])->name('reports.gst-sales-register');
    Route::get('reports/gst-sales-register/export', [GstSalesRegisterReportController::class, 'export'])->name('reports.gst-sales-register.export');

    // Phase 5e: Voucher-based GST register (fallback)
    Route::get('reports/gst-voucher-register', [GstVoucherRegisterReportController::class, 'index'])->name('reports.gst-voucher-register');
    Route::get('reports/gst-voucher-register/export', [GstVoucherRegisterReportController::class, 'export'])->name('reports.gst-voucher-register.export');

    // Phase 5e: HSN/SAC Summaries
    Route::get('reports/gst-hsn-purchase-summary', [GstHsnPurchaseSummaryReportController::class, 'index'])->name('reports.gst-hsn-purchase-summary');
    Route::get('reports/gst-hsn-purchase-summary/export', [GstHsnPurchaseSummaryReportController::class, 'export'])->name('reports.gst-hsn-purchase-summary.export');

    Route::get('reports/gst-hsn-sales-summary', [GstHsnSalesSummaryReportController::class, 'index'])->name('reports.gst-hsn-sales-summary');
    Route::get('reports/gst-hsn-sales-summary/export', [GstHsnSalesSummaryReportController::class, 'export'])->name('reports.gst-hsn-sales-summary.export');

    // Ageing Reports
    Route::get('reports/supplier-ageing', [SupplierAgeingReportController::class, 'index'])->name('reports.supplier-ageing');
    Route::get('reports/client-ageing', [ClientAgeingReportController::class, 'index'])->name('reports.client-ageing');

    Route::get('reports/supplier-ageing/{account}/bills', [SupplierAgeingReportController::class, 'bills'])->name('reports.supplier-ageing.bills');
    Route::get('reports/client-ageing/{account}/bills', [ClientAgeingReportController::class, 'bills'])->name('reports.client-ageing.bills');

    // Cash/Fund Flow
    Route::get('reports/cash-flow', [CashFlowReportController::class, 'index'])->name('reports.cash-flow');
    Route::get('reports/fund-flow', [FundFlowReportController::class, 'index'])->name('reports.fund-flow');

    // Unbalanced vouchers
    Route::get('reports/unbalanced-vouchers', [UnbalancedVouchersReportController::class, 'index'])->name('reports.unbalanced-vouchers');

    // TDS Certificates (tracking)
    Route::get('reports/tds-certificates', [TdsCertificateReportController::class, 'index'])->name('reports.tds-certificates');
    Route::get('reports/tds-certificates/{certificate}/edit', [TdsCertificateReportController::class, 'edit'])->name('reports.tds-certificates.edit');
    Route::post('reports/tds-certificates/sync-payable', [TdsCertificateReportController::class, 'syncPayable'])->name('reports.tds-certificates.sync-payable');
    Route::put('reports/tds-certificates/{certificate}', [TdsCertificateReportController::class, 'update'])->name('reports.tds-certificates.update');

    /*
    |--------------------------------------------------------------------------
    | DEV-3: Subcontractor RA Bills
    |--------------------------------------------------------------------------
    */
    Route::prefix('subcontractor-ra')->name('subcontractor-ra.')->group(function () {
        Route::get('/', [SubcontractorRaBillController::class, 'index'])->name('index');
        Route::get('/create', [SubcontractorRaBillController::class, 'create'])->name('create');
        Route::post('/', [SubcontractorRaBillController::class, 'store'])->name('store');
        Route::get('/party-summary', [SubcontractorRaBillController::class, 'partySummary'])->name('party-summary');
        Route::get('/{subcontractorRa}', [SubcontractorRaBillController::class, 'show'])->name('show');
        Route::get('/{subcontractorRa}/edit', [SubcontractorRaBillController::class, 'edit'])->name('edit');
        Route::put('/{subcontractorRa}', [SubcontractorRaBillController::class, 'update'])->name('update');
        Route::delete('/{subcontractorRa}', [SubcontractorRaBillController::class, 'destroy'])->name('destroy');

        // Workflow actions
        Route::post('/{subcontractorRa}/submit', [SubcontractorRaBillController::class, 'submit'])->name('submit');
        Route::post('/{subcontractorRa}/approve', [SubcontractorRaBillController::class, 'approve'])->name('approve');
        Route::post('/{subcontractorRa}/reject', [SubcontractorRaBillController::class, 'reject'])->name('reject');
        Route::post('/{subcontractorRa}/post', [SubcontractorRaBillController::class, 'post'])->name('post');
        Route::post('/{subcontractorRa}/reverse', [SubcontractorRaBillController::class, 'reverse'])->name('reverse');
    });

    /*
    |--------------------------------------------------------------------------
    | DEV-4: Client RA Bills / Sales Invoices
    |--------------------------------------------------------------------------
    */
    Route::prefix('client-ra')->name('client-ra.')->group(function () {
        Route::get('/', [ClientRaBillController::class, 'index'])->name('index');
        Route::get('/create', [ClientRaBillController::class, 'create'])->name('create');
        Route::post('/', [ClientRaBillController::class, 'store'])->name('store');
        Route::get('/{clientRa}', [ClientRaBillController::class, 'show'])->name('show');
        Route::get('/{clientRa}/edit', [ClientRaBillController::class, 'edit'])->name('edit');
        Route::put('/{clientRa}', [ClientRaBillController::class, 'update'])->name('update');
        Route::delete('/{clientRa}', [ClientRaBillController::class, 'destroy'])->name('destroy');

        // Workflow actions
        Route::post('/{clientRa}/submit', [ClientRaBillController::class, 'submit'])->name('submit');
        Route::post('/{clientRa}/approve', [ClientRaBillController::class, 'approve'])->name('approve');
        Route::post('/{clientRa}/reject', [ClientRaBillController::class, 'reject'])->name('reject');
        Route::post('/{clientRa}/post', [ClientRaBillController::class, 'post'])->name('post');
        Route::post('/{clientRa}/reverse', [ClientRaBillController::class, 'reverse'])->name('reverse');

        // Print/Export
        Route::get('/{clientRa}/print', [ClientRaBillController::class, 'print'])->name('print');
    });

    /*
    |--------------------------------------------------------------------------
    | DEV-9: Project Cost Sheet Reports
    |--------------------------------------------------------------------------
    */
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('project-cost-sheet', [ProjectCostSheetController::class, 'index'])->name('project-cost-sheet');
        Route::get('project-cost-sheet/{project}', [ProjectCostSheetController::class, 'show'])->name('project-cost-sheet.show');
        Route::get('project-cost-sheet/{project}/export', [ProjectCostSheetController::class, 'export'])->name('project-cost-sheet.export');
    });
});
require __DIR__ . '/accounting_notes.php';


