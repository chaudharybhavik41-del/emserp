<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\VoucherLine;
use App\Services\Accounting\BillAllocationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class OnAccountAdjustmentController extends Controller
{
    public function __construct(
        protected BillAllocationService $billAllocationService
    ) {
        // Applying On-Account changes allocations, so keep under voucher create permission.
        $this->middleware('permission:accounting.vouchers.create')
            ->only(['index', 'create', 'store']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    /**
     * List debtor ledgers and their On-Account receipt lines (as-of date).
     */
    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $asOfInput = (string) $request->input('as_of_date', '');
        $asOfDate  = $asOfInput !== '' ? Carbon::parse($asOfInput)->startOfDay() : now()->startOfDay();

        $selectedAccountId = $request->integer('party_account_id') ?: null;

        $debtorAccounts = Account::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', 'debtor')
            ->orderBy('name')
            ->get();

        $selectedAccount = null;
        $onAccountLines  = collect();
        $totalOnAccount  = 0.0;

        if ($selectedAccountId) {
            $selectedAccount = $debtorAccounts->firstWhere('id', $selectedAccountId);

            if ($selectedAccount) {
                $onAccountLines = $this->billAllocationService->listOnAccountReceiptLines($selectedAccount, $asOfDate)
                    ->filter(fn (array $r) => (float) $r['on_account'] > 0.009)
                    ->values();

                foreach ($onAccountLines as $r) {
                    $totalOnAccount += (float) ($r['on_account'] ?? 0.0);
                }
            }
        }

        return view('accounting.receipts.on_account_index', [
            'companyId'        => $companyId,
            'asOfDate'         => $asOfDate,
            'debtorAccounts'   => $debtorAccounts,
            'selectedAccount'  => $selectedAccount,
            'onAccountLines'   => $onAccountLines,
            'totalOnAccount'   => $totalOnAccount,
        ]);
    }

    /**
     * Apply on-account from a specific receipt voucher line.
     */
    public function create(Request $request, VoucherLine $voucherLine)
    {
        $companyId = $this->defaultCompanyId();

        $voucherLine->loadMissing(['voucher', 'account']);

        if (! $voucherLine->voucher || (int) $voucherLine->voucher->company_id !== $companyId) {
            abort(404);
        }

        $allocInput = (string) $request->input('allocation_date', '');
        $allocationDate = $allocInput !== '' ? Carbon::parse($allocInput)->startOfDay() : now()->startOfDay();

        $status = (string) $request->input('status', 'posted');
        if ($status === '') {
            $status = 'posted';
        }

        $account = $voucherLine->account;

        // Compute available On-Account as-of allocation date
        $available = (float) $this->billAllocationService->getOnAccountAvailableForVoucherLine($voucherLine, $allocationDate);

        // Show open client bills as-of allocation date for allocations
        $openBills = collect();
        if ($account) {
            $openBills = $this->billAllocationService->getOpenClientBillsForAccount($account, $allocationDate, $status);
        }

        // Last allocation date for this receipt line
        $maxExisting = DB::table('account_bill_allocations')
            ->where('voucher_line_id', (int) $voucherLine->id)
            ->max('allocation_date');

        $lastAllocDate = $maxExisting ? Carbon::parse($maxExisting)->toDateString() : null;

        return view('accounting.receipts.on_account_apply', [
            'companyId'       => $companyId,
            'voucherLine'     => $voucherLine,
            'allocationDate'  => $allocationDate,
            'billStatus'      => $status,
            'available'       => $available,
            'openBills'       => $openBills,
            'lastAllocDate'   => $lastAllocDate,
        ]);
    }

    /**
     * Persist On-Account application.
     */
    public function store(Request $request, VoucherLine $voucherLine)
    {
        $voucherLine->loadMissing(['voucher', 'account']);

        $data = $request->validate([
            'allocation_date' => ['required', 'date'],
            'status'          => ['nullable', 'string'],
            'apply'           => ['nullable', 'array'],
            'apply.*.bill_id' => ['nullable'],
            'apply.*.amount'  => ['nullable'],
        ]);

        $allocationDate = Carbon::parse((string) $data['allocation_date'])->startOfDay();
        $status = (string) ($data['status'] ?? 'posted');
        if ($status === '') {
            $status = 'posted';
        }

        $this->billAllocationService->applyOnAccountToClientBills(
            $voucherLine,
            $allocationDate,
            $data['apply'] ?? [],
            $status
        );

        return redirect()
            ->route('accounting.receipts.on-account.index', [
                'party_account_id' => $voucherLine->account_id,
                'as_of_date'       => $allocationDate->toDateString(),
            ])
            ->with('success', 'On-Account applied successfully.');
    }
}
