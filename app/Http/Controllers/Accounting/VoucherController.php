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
use App\Services\Accounting\VoucherNumberService;
use App\Services\Accounting\VoucherReversalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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


    public function index(Request $request)
    {
        $query = Voucher::with(['project', 'costCenter'])
            ->orderByDesc('voucher_date')
            ->orderByDesc('id');

        if ($type = $request->get('type')) {
            $query->where('voucher_type', $type);
        }

        if ($projectId = $request->get('project_id')) {
            $query->where('project_id', $projectId);
        }

        $vouchers = $query->paginate(25);

        $docLinks = $this->buildDocLinksForVoucherIds($vouchers->pluck('id')->all());

        return view('accounting.vouchers.index', compact('vouchers', 'docLinks'));
    }

    public function create()
    {

        $accounts    = Account::orderBy('name')->get();
        
        $costCenters = CostCenter::orderBy('name')->get();
        $projects    = Project::orderBy('code')->orderBy('name')->get(['id','code','name']);

        // Keep the type set small & controlled for now.
        $voucherTypes = [
            'journal' => 'Journal',
            'contra'  => 'Contra',
        ];

        return view('accounting.vouchers.create', compact('accounts', 'costCenters', 'projects', 'voucherTypes'));
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->input('company_id');

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
            'lines.*.description'   => ['nullable', 'string', 'max:255'],
            'lines.*.debit'         => ['nullable', 'numeric'],
            'lines.*.credit'        => ['nullable', 'numeric'],

            // Optional UI action
            'post_now'              => ['nullable'],
        ]);

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

        $voucher = DB::transaction(function () use ($request, $companyId, $voucherNoInput, $voucherType, $voucherDate, $data, $lines) {
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

                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $line['account_id'],
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'description'    => $line['description'] ?? null,
                    'debit'          => $debit,
                    'credit'         => $credit,
                    'reference_type' => null,
                    'reference_id'   => null,
                ]);
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

        $voucher->load(['lines.account', 'lines.costCenter']);

        $accounts     = Account::orderBy('name')->get();
        $costCenters  = CostCenter::orderBy('name')->get();
        $projects    = Project::orderBy('code')->orderBy('name')->get(['id','code','name']);
        $voucherTypes = [
            'journal' => 'Journal',
            'contra'  => 'Contra',
        ];

        return view('accounting.vouchers.edit', compact('voucher', 'accounts', 'costCenters', 'projects', 'voucherTypes'));
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
            'lines.*.description'    => ['nullable', 'string', 'max:255'],
            'lines.*.debit'          => ['nullable', 'numeric'],
            'lines.*.credit'         => ['nullable', 'numeric'],

            'post_now'               => ['nullable'],
        ]);

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

        DB::transaction(function () use ($request, $voucher, $data, $lines) {
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

                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $line['account_id'],
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'description'    => $line['description'] ?? null,
                    'debit'          => $debit,
                    'credit'         => $credit,
                    'reference_type' => null,
                    'reference_id'   => null,
                ]);
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
            ->route('accounting.vouchers.show', $voucher)
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