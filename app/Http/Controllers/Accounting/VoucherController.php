<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use Illuminate\Support\Facades\Schema;
use App\Models\Accounting\SalesCreditNote;
use App\Models\Accounting\PurchaseDebitNote;
use App\Models\Project;
use App\Models\Machine;
use App\Services\Accounting\VoucherNumberService;
use App\Services\Accounting\VoucherReversalService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class VoucherController extends Controller
{
    /**
     * NOTE: This controller is for manual vouchers (Journal / Contra).
     * Payment/Receipt vouchers have their own screens (BankCashVoucherController).
     */
    public function __construct(
        protected VoucherNumberService $voucherNumberService,
        protected VoucherReversalService $voucherReversalService
    ) {
        $this->middleware('permission:accounting.vouchers.view')->only(['index', 'show']);
        $this->middleware('permission:accounting.vouchers.create')->only(['create', 'store']);
        $this->middleware('permission:accounting.vouchers.update')->only(['edit', 'update', 'post', 'reverse']);
        $this->middleware('permission:accounting.vouchers.delete')->only(['destroy']);
    }


    /**
     * Build voucher_id => ['badge' => 'DN'|'CN', 'label' => note_number, 'url' => document url]
     * Safe: if note tables are not migrated, returns empty map.
     */
    protected function buildDocLinksForVoucherIds(array $voucherIds): array
    {
        $out = [];
        $voucherIds = array_values(array_unique(array_filter(array_map('intval', $voucherIds))));
        if (empty($voucherIds)) {
            return $out;
        }

        if (Schema::hasTable('purchase_debit_notes')) {
            $dns = PurchaseDebitNote::query()
                ->whereIn('voucher_id', $voucherIds)
                ->get(['id', 'voucher_id', 'note_number']);

            foreach ($dns as $dn) {
                $out[(int) $dn->voucher_id] = [
                    'badge' => 'DN',
                    'label' => (string) $dn->note_number,
                    'url'   => url('/accounting/purchase-debit-notes/' . (int) $dn->id),
                ];
            }
        }

        if (Schema::hasTable('sales_credit_notes')) {
            $cns = SalesCreditNote::query()
                ->whereIn('voucher_id', $voucherIds)
                ->get(['id', 'voucher_id', 'note_number']);

            foreach ($cns as $cn) {
                $out[(int) $cn->voucher_id] = [
                    'badge' => 'CN',
                    'label' => (string) $cn->note_number,
                    'url'   => url('/accounting/sales-credit-notes/' . (int) $cn->id),
                ];
            }
        }

        return $out;
    }

    /**
     * Guard manual vouchers against malformed lines that can still arithmetically balance.
     *
     * @param  array<int, array<string, mixed>>  $lines
     *
     * @throws ValidationException
     */
    protected function validateManualVoucherLines(array $lines): void
    {
        $errors = [];

        foreach ($lines as $index => $line) {
            $debit  = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($debit < 0) {
                $errors["lines.$index.debit"] = 'Debit cannot be negative.';
            }

            if ($credit < 0) {
                $errors["lines.$index.credit"] = 'Credit cannot be negative.';
            }

            if ($debit > 0 && $credit > 0) {
                $errors["lines.$index.debit"] = 'Enter either debit or credit on a line, not both.';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Cash/bank ledgers allowed in contra vouchers.
     */
    protected function resolveContraAccountIdsFromCollection(Collection $accounts): array
    {
        $cashAccountTypes = Config::get('accounting.cashflow_cash_account_types', ['bank', 'cash']);
        $cashGroupCodes = Config::get('accounting.cashflow_cash_group_codes', []);

        $contraAccounts = $accounts;

        if (! empty($cashAccountTypes)) {
            $contraAccounts = $contraAccounts->whereIn('type', $cashAccountTypes);
        }

        if ($contraAccounts->isEmpty() && ! empty($cashGroupCodes)) {
            $contraAccounts = $accounts->filter(function (Account $account) use ($cashGroupCodes) {
                return in_array($account->group?->code, $cashGroupCodes, true);
            });
        }

        return $contraAccounts
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function resolveContraAccountIdsForCompany(int $companyId): array
    {
        $accounts = Account::with('group')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return $this->resolveContraAccountIdsFromCollection($accounts);
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     *
     * @throws ValidationException
     */
    protected function validateContraVoucherAccounts(int $companyId, array $lines): void
    {
        $allowedAccountIds = $this->resolveContraAccountIdsForCompany($companyId);

        if (empty($allowedAccountIds)) {
            throw ValidationException::withMessages([
                'voucher_type' => 'No cash or bank ledgers are configured for contra vouchers.',
            ]);
        }

        $errors = [];

        foreach ($lines as $index => $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($debit == 0.0 && $credit == 0.0) {
                continue;
            }

            $accountId = (int) ($line['account_id'] ?? 0);

            if ($accountId <= 0 || ! in_array($accountId, $allowedAccountIds, true)) {
                $errors["lines.$index.account_id"] = 'Contra voucher allows only cash or bank ledgers.';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }


    public function index(Request $request)
    {
        $query = Voucher::with(['project', 'costCenter'])
            ->orderByDesc('voucher_date')
            ->orderByDesc('id');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->get('type')) {
            $query->where('voucher_type', $type);
        }

        if ($projectId = $request->get('project_id')) {
            $query->where('project_id', $projectId);
        }

        if ($costCenterId = $request->get('cost_center_id')) {
            $query->where('cost_center_id', $costCenterId);
        }

        if ($search = $request->get('search')) {
            $query->where('voucher_no', 'like', "%{$search}%");
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('voucher_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('voucher_date', '<=', $dateTo);
        }

        $vouchers = $query->paginate($request->get('per_page', 25))->withQueryString();

        $docLinks = $this->buildDocLinksForVoucherIds($vouchers->pluck('id')->all());

        $projects    = Project::orderBy('code')->get(['id', 'code', 'name']);
        $costCenters = CostCenter::orderBy('name')->get(['id', 'name']);
        $voucherTypes = [
            'journal' => 'Journal',
            'contra'  => 'Contra',
        ];

        return view('accounting.vouchers.index', compact(
            'vouchers',
            'docLinks',
            'projects',
            'costCenters',
            'voucherTypes'
        ));
    }

    public function create()
    {

        $accounts    = Account::with('group')->orderBy('name')->get();
        $contraAccountIds = $this->resolveContraAccountIdsFromCollection($accounts);

        $costCenters = CostCenter::orderBy('name')->get();
        $projects    = Project::orderBy('code')->orderBy('name')->get(['id','code','name']);
        $hasMachineLineDimension = Schema::hasTable('voucher_lines')
            && Schema::hasColumn('voucher_lines', 'machine_id')
            && Schema::hasTable('machines');
        $machines = $hasMachineLineDimension
            ? Machine::orderBy('name')->get(['id', 'code', 'name'])
            : collect();
        $projectCostCenterMap = $costCenters
            ->filter(fn (CostCenter $costCenter) => ! empty($costCenter->project_id))
            ->mapWithKeys(fn (CostCenter $costCenter) => [(int) $costCenter->project_id => (int) $costCenter->id]);

        // Keep the type set small & controlled for now.
        $voucherTypes = [
            'journal' => 'Journal',
            'contra'  => 'Contra',
        ];

        return view('accounting.vouchers.create', compact(
            'accounts',
            'costCenters',
            'projects',
            'voucherTypes',
            'projectCostCenterMap',
            'machines',
            'hasMachineLineDimension'
        ));
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->input('company_id');
        $hasMachineLineDimension = Schema::hasTable('voucher_lines')
            && Schema::hasColumn('voucher_lines', 'machine_id')
            && Schema::hasTable('machines');
        $lineMachineRule = $hasMachineLineDimension
            ? ['nullable', 'integer', 'exists:machines,id']
            : ['nullable'];

        $data = $request->validate([
            'company_id'    => ['required', 'integer'],
            'voucher_type'  => ['required', 'string', Rule::in(['journal', 'contra'])],
            'voucher_date'  => ['required', 'date'],
            'voucher_no'    => ['nullable', 'string', 'max:50'],
            'reference'     => ['nullable', 'string', 'max:100'],
            'narration'     => ['nullable', 'string'],
            'project_id'    => ['nullable', 'integer', 'exists:projects,id'],
            'cost_center_id'=> ['nullable', 'integer', 'exists:cost_centers,id'],
            'currency_id'   => ['nullable', 'integer'],
            'exchange_rate' => ['nullable', 'numeric'],

            'lines'                 => ['required', 'array', 'min:1'],
            'lines.*.account_id'    => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.cost_center_id'=> ['nullable', 'integer', 'exists:cost_centers,id'],
            'lines.*.machine_id'    => $lineMachineRule,
            'lines.*.description'   => ['nullable', 'string', 'max:255'],
            'lines.*.debit'         => ['nullable', 'numeric'],
            'lines.*.credit'        => ['nullable', 'numeric'],

            // Optional UI action
            'post_now'              => ['nullable'],
        ]);

        $this->validateManualVoucherLines($data['lines']);

        if (($data['voucher_type'] ?? null) === 'contra') {
            $this->validateContraVoucherAccounts($companyId, $data['lines']);
        }

        $voucherNoInput = trim((string) ($data['voucher_no'] ?? ''));
        $voucherType    = (string) $data['voucher_type'];
        $voucherDate    = $data['voucher_date'];

        // If user typed a voucher_no, enforce company-wide uniqueness.
        if ($voucherNoInput !== '') {
            $request->validate([
                'voucher_no' => [
                    Rule::unique('vouchers', 'voucher_no')
                        ->where(fn ($q) => $q->where('company_id', $companyId)),
                ],
            ]);
        }

        $lines = $data['lines'];
        unset($data['lines']);

        $data['exchange_rate'] = $data['exchange_rate'] ?? 1;
        $data['created_by']    = $request->user()?->id;
        $data['status']        = 'draft';

        $voucher = DB::transaction(function () use ($request, $companyId, $voucherNoInput, $voucherType, $voucherDate, $data, $lines, $hasMachineLineDimension) {
            $voucherNo = $voucherNoInput !== ''
                ? $voucherNoInput
                : $this->voucherNumberService->next($voucherType, $companyId, $voucherDate);

            /** @var Voucher $voucher */
            $voucher = Voucher::create(array_merge($data, [
                'voucher_no'   => $voucherNo,
                'voucher_type' => $voucherType,
                'voucher_date' => $voucherDate,
            ]));

            $totalDebit  = 0.0;
            $totalCredit = 0.0;
            $lineNo      = 1;

            foreach ($lines as $line) {
                $debit  = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                if ($debit == 0 && $credit == 0) {
                    continue;
                }

                $totalDebit  += $debit;
                $totalCredit += $credit;

                $lineData = [
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $line['account_id'],
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'description'    => $line['description'] ?? null,
                    'debit'          => $debit,
                    'credit'         => $credit,
                    'reference_type' => null,
                    'reference_id'   => null,
                ];

                if ($hasMachineLineDimension) {
                    $lineData['machine_id'] = ! empty($line['machine_id']) ? (int) $line['machine_id'] : null;
                }

                VoucherLine::create($lineData);
            }

            if ($lineNo === 1) {
                throw new RuntimeException('Please enter at least one debit/credit line.');
            }

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                throw new RuntimeException('Voucher is not balanced (total debit != total credit).');
            }

            $voucher->amount_base = max($totalDebit, $totalCredit);
            $voucher->save();

            ActivityLog::logCreated($voucher, 'Created voucher ' . $voucher->voucher_no . ' (Draft)');

            // Optional: Save & Post
            if ($request->boolean('post_now')) {
                if (! $request->user() || ! $request->user()->can('accounting.vouchers.update')) {
                    throw new RuntimeException('You do not have permission to post vouchers.');
                }

                $voucher->posted_by = $request->user()?->id;
                $voucher->posted_at = now();
                $voucher->status    = 'posted';
                $voucher->save();

                ActivityLog::logCustom(
                    'voucher_posted',
                    'Posted voucher ' . $voucher->voucher_no,
                    $voucher
                );
            }

            return $voucher;
        });

        return redirect()
            ->route('accounting.vouchers.show', $voucher)
            ->with('success', 'Voucher saved successfully.');
    }

    public function show(Voucher $voucher)
    {
        $voucher->load([
            'project',
            'costCenter',
            'currency',
            'createdBy',
            'postedBy',
            'reversedBy',
            'lines.account',
            'lines.costCenter',
            'lines.machine',
            'lines.fixedAssetLinks.machine',
        ]);

        $reversalVoucher = null;
        if ($voucher->reversal_voucher_id) {
            $reversalVoucher = Voucher::select(['id', 'voucher_no', 'voucher_type', 'voucher_date', 'status'])
                ->find($voucher->reversal_voucher_id);
        }

        $reversedFrom = null;
        if ($voucher->reversal_of_voucher_id) {
            $reversedFrom = Voucher::select(['id', 'voucher_no', 'voucher_type', 'voucher_date', 'status'])
                ->find($voucher->reversal_of_voucher_id);
        }

        $activityLogs = ActivityLog::with('user')
            ->forSubject($voucher)
            ->latest()
            ->limit(50)
            ->get();

        $totalDebit  = (float) $voucher->lines->sum('debit');
        $totalCredit = (float) $voucher->lines->sum('credit');

                $docLinks = $this->buildDocLinksForVoucherIds([(int) $voucher->id]);

        return view('accounting.vouchers.show', compact(
            'voucher',
            'reversalVoucher',
            'reversedFrom',
            'activityLogs',
            'totalDebit',
            'totalCredit',
            'docLinks'
        ));
    }

    public function edit(Voucher $voucher)
    {
        if ($voucher->isPosted()) {
            return redirect()
                ->route('accounting.vouchers.show', $voucher)
                ->with('error', 'Posted vouchers cannot be edited. Please reverse and create a new voucher instead.');
        }

        if ($voucher->isReversed()) {
            return redirect()
                ->route('accounting.vouchers.show', $voucher)
                ->with('error', 'Reversed vouchers cannot be edited.');
        }

        $voucher->load(['lines.account', 'lines.costCenter', 'lines.machine']);

        $accounts     = Account::with('group')->orderBy('name')->get();
        $contraAccountIds = $this->resolveContraAccountIdsFromCollection($accounts);
        $costCenters  = CostCenter::orderBy('name')->get();
        $projects    = Project::orderBy('code')->orderBy('name')->get(['id','code','name']);
        $hasMachineLineDimension = Schema::hasTable('voucher_lines')
            && Schema::hasColumn('voucher_lines', 'machine_id')
            && Schema::hasTable('machines');
        $machines = $hasMachineLineDimension
            ? Machine::orderBy('name')->get(['id', 'code', 'name'])
            : collect();
        $voucherTypes = [
            'journal' => 'Journal',
            'contra'  => 'Contra',
        ];

        return view('accounting.vouchers.edit', compact(
            'voucher',
            'accounts',
            'costCenters',
            'projects',
            'voucherTypes',
            'machines',
            'hasMachineLineDimension'
        ));
    }

    public function update(Request $request, Voucher $voucher)
    {
        if ($voucher->isPosted()) {
            return redirect()
                ->route('accounting.vouchers.show', $voucher)
                ->with('error', 'Posted vouchers cannot be edited.');
        }

        if ($voucher->isReversed()) {
            return redirect()
                ->route('accounting.vouchers.show', $voucher)
                ->with('error', 'Reversed vouchers cannot be edited.');
        }

        $companyId = (int) $voucher->company_id;
        $hasMachineLineDimension = Schema::hasTable('voucher_lines')
            && Schema::hasColumn('voucher_lines', 'machine_id')
            && Schema::hasTable('machines');
        $lineMachineRule = $hasMachineLineDimension
            ? ['nullable', 'integer', 'exists:machines,id']
            : ['nullable'];

        $data = $request->validate([
            'voucher_no'    => ['required', 'string', 'max:50'],
            'voucher_type'  => ['required', 'string', Rule::in(['journal', 'contra'])],
            'voucher_date'  => ['required', 'date'],
            'reference'     => ['nullable', 'string', 'max:100'],
            'narration'     => ['nullable', 'string'],
            'project_id'    => ['nullable', 'integer', 'exists:projects,id'],
            'cost_center_id'=> ['nullable', 'integer', 'exists:cost_centers,id'],
            'currency_id'   => ['nullable', 'integer'],
            'exchange_rate' => ['nullable', 'numeric'],

            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.account_id'     => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'lines.*.machine_id'     => $lineMachineRule,
            'lines.*.description'    => ['nullable', 'string', 'max:255'],
            'lines.*.debit'          => ['nullable', 'numeric'],
            'lines.*.credit'         => ['nullable', 'numeric'],

            'post_now'               => ['nullable'],
        ]);

        $this->validateManualVoucherLines($data['lines']);

        if (($data['voucher_type'] ?? null) === 'contra') {
            $this->validateContraVoucherAccounts($companyId, $data['lines']);
        }

        // Enforce company-wide uniqueness of voucher_no
        $request->validate([
            'voucher_no' => [
                Rule::unique('vouchers', 'voucher_no')
                    ->ignore($voucher->id)
                    ->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $lines = $data['lines'];
        unset($data['lines']);

        $data['exchange_rate'] = $data['exchange_rate'] ?? 1;

        DB::transaction(function () use ($request, $voucher, $data, $lines, $hasMachineLineDimension) {
            $old = $voucher->getOriginal();

            $voucher->fill($data);
            $voucher->save();

            // Rebuild lines (manual vouchers only)
            $voucher->lines()->delete();

            $totalDebit  = 0.0;
            $totalCredit = 0.0;
            $lineNo      = 1;

            foreach ($lines as $line) {
                $debit  = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                if ($debit == 0 && $credit == 0) {
                    continue;
                }

                $totalDebit  += $debit;
                $totalCredit += $credit;

                $lineData = [
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $line['account_id'],
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'description'    => $line['description'] ?? null,
                    'debit'          => $debit,
                    'credit'         => $credit,
                    'reference_type' => null,
                    'reference_id'   => null,
                ];

                if ($hasMachineLineDimension) {
                    $lineData['machine_id'] = ! empty($line['machine_id']) ? (int) $line['machine_id'] : null;
                }

                VoucherLine::create($lineData);
            }

            if ($lineNo === 1) {
                throw new RuntimeException('Please enter at least one debit/credit line.');
            }

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                throw new RuntimeException('Voucher is not balanced (total debit != total credit).');
            }

            $voucher->amount_base = max($totalDebit, $totalCredit);
            $voucher->save();

            ActivityLog::logUpdated($voucher, $old, 'Updated voucher ' . $voucher->voucher_no . ' (Draft)');

            if ($request->boolean('post_now')) {
                $voucher->posted_by = $request->user()?->id;
                $voucher->posted_at = now();
                $voucher->status    = 'posted';
                $voucher->save();

                ActivityLog::logCustom(
                    'voucher_posted',
                    'Posted voucher ' . $voucher->voucher_no,
                    $voucher
                );
            }
        });

        return redirect()
            ->route('accounting.vouchers.show', $voucher)
            ->with('success', 'Voucher updated successfully.');
    }

    public function post(Request $request, Voucher $voucher)
    {
        if ($voucher->isPosted()) {
            return redirect()
                ->route('accounting.vouchers.show', $voucher)
                ->with('info', 'Voucher is already posted.');
        }

        if ($voucher->isReversed()) {
            return redirect()
                ->route('accounting.vouchers.show', $voucher)
                ->with('error', 'Reversed vouchers cannot be posted again.');
        }

        DB::transaction(function () use ($request, $voucher) {
            $voucher->posted_by = $request->user()?->id;
            $voucher->posted_at = now();
            $voucher->status    = 'posted';
            $voucher->save();

            ActivityLog::logCustom(
                'voucher_posted',
                'Posted voucher ' . $voucher->voucher_no,
                $voucher
            );
        });

        return redirect()
            ->route('accounting.vouchers.index', $voucher)
            ->with('success', 'Voucher posted successfully.');
    }

    public function reverse(Request $request, Voucher $voucher)
    {
        $data = $request->validate([
            'reversal_date' => ['required', 'date'],
            'reason'        => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $reversalVoucher = $this->voucherReversalService->reverse(
                $voucher,
                $data['reversal_date'],
                $data['reason'] ?? null,
                $request->user()?->id
            );
        } catch (\Throwable $e) {
            report($e);
            return redirect()
                ->route('accounting.vouchers.show', $voucher)
                ->with('error', 'Failed to reverse voucher: ' . $e->getMessage());
        }

        return redirect()
            ->route('accounting.vouchers.show', $voucher)
            ->with('success', 'Voucher reversed successfully. Reversal voucher: ' . $reversalVoucher->voucher_no);
    }

    /**
     * Update only the voucher date.
     * Allowed even for posted vouchers for corrections.
     */
    public function changeDate(Request $request, Voucher $voucher)
    {
        $data = $request->validate([
            'voucher_date' => ['required', 'date'],
        ]);

        $oldDate = $voucher->voucher_date ? $voucher->voucher_date->toDateString() : 'N/A';
        $newDate = $data['voucher_date'];

        if ($oldDate === $newDate) {
            return redirect()->back()->with('info', 'No change in date.');
        }

        $old = $voucher->getAttributes();
        $voucher->voucher_date = $newDate;
        $voucher->save();

        ActivityLog::logUpdated(
            $voucher,
            $old,
            "Updated voucher date from $oldDate to $newDate"
        );

        return redirect()
            ->route('accounting.vouchers.show', $voucher)
            ->with('success', 'Voucher date updated successfully.');
    }

    public function destroy(Voucher $voucher)
    {
        if ($voucher->isPosted()) {
            return redirect()
                ->route('accounting.vouchers.show', $voucher)
                ->with('error', 'Posted vouchers cannot be deleted. Please reverse instead.');
        }

        if ($voucher->isReversed()) {
            return redirect()
                ->route('accounting.vouchers.show', $voucher)
                ->with('error', 'Reversed vouchers cannot be deleted.');
        }

        DB::transaction(function () use ($voucher) {
            ActivityLog::logDeleted($voucher, 'Deleted draft voucher ' . $voucher->voucher_no);
            $voucher->delete();
        });

        return redirect()
            ->route('accounting.vouchers.index')
            ->with('success', 'Voucher deleted successfully.');
    }
}
