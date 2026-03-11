<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Project;
use App\Services\Accounting\PaymentReceiptPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentReceiptController extends Controller
{
    public function __construct(
        protected PaymentReceiptPostingService $postingService
    ) {
        $this->middleware('permission:accounting.vouchers.view')->only(['createPayment', 'createReceipt']);
        $this->middleware('permission:accounting.vouchers.create')->only(['storePayment', 'storeReceipt']);
    }

    protected function bankAccounts()
    {
        return Account::whereHas('group', function ($q) {
                $q->whereIn('code', ['BANK_ACCOUNTS', 'CASH_IN_HAND']);
            })
            ->orderBy('name')
            ->get();
    }

    protected function lineAccounts()
    {
        // For now, allow all active accounts; later we can restrict to relevant groups.
        return Account::where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    protected function costCenters()
    {
        return CostCenter::orderBy('name')->get();
    }

    protected function projects()
    {
        return Project::orderBy('code')->get();
    }

    public function createPayment(): RedirectResponse
    {
        return redirect()
            ->route('accounting.payments.index')
            ->with('info', 'Legacy payment route retired. Use the active payment voucher list.');
    }

    public function createReceipt(): RedirectResponse
    {
        return redirect()
            ->route('accounting.receipts.index')
            ->with('info', 'Legacy receipt route retired. Use the active receipt voucher list.');
    }

    public function storePayment(Request $request): RedirectResponse
    {
        return redirect()
            ->route('accounting.payments.index')
            ->with('error', 'Legacy payment posting route retired. Re-enter the voucher from the active payment voucher list.');
    }

    public function storeReceipt(Request $request): RedirectResponse
    {
        return redirect()
            ->route('accounting.receipts.index')
            ->with('error', 'Legacy receipt posting route retired. Re-enter the voucher from the active receipt voucher list.');
    }
}
