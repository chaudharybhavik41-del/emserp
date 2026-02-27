<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentVoucherRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Project;
use App\Services\Accounting\PaymentReceiptPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

    public function createPayment(): View
    {
        return view('accounting.payments.create', [
            'mode'         => 'payment',
            'bankAccounts' => $this->bankAccounts(),
            'accounts'     => $this->lineAccounts(),
            'costCenters'  => $this->costCenters(),
            'projects'     => $this->projects(),
        ]);
    }

    public function createReceipt(): View
    {
        return view('accounting.receipts.create', [
            'mode'         => 'receipt',
            'bankAccounts' => $this->bankAccounts(),
            'accounts'     => $this->lineAccounts(),
            'costCenters'  => $this->costCenters(),
            'projects'     => $this->projects(),
        ]);
    }

    public function storePayment(StorePaymentVoucherRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $voucher = $this->postingService->createPayment($data);

        return redirect()
            ->route('accounting.vouchers.index', ['type' => 'payment'])
            ->with('success', 'Payment voucher ' . $voucher->voucher_no . ' created successfully.');
    }

    public function storeReceipt(StorePaymentVoucherRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $voucher = $this->postingService->createReceipt($data);

        return redirect()
            ->route('accounting.vouchers.index', ['type' => 'receipt'])
            ->with('success', 'Receipt voucher ' . $voucher->voucher_no . ' created successfully.');
    }
}
