<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\SalesCreditNote;
use App\Models\Accounting\SalesCreditNoteLine;
use App\Models\Party;
use App\Services\Accounting\SalesCreditNotePostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalesCreditNoteController extends Controller
{
    public function __construct(
        protected SalesCreditNotePostingService $postingService
    ) {
        $this->middleware('auth');
        $this->middleware('permission:accounting.vouchers.view')->only(['index','show']);
        $this->middleware('permission:accounting.vouchers.create')->only(['create','store','post','cancel']);
        $this->middleware('permission:accounting.vouchers.update')->only(['edit','update']);
    }

    protected function companyId(): int
    {
        return (int) Config::get('accounting.default_company_id', 1);
    }

    protected function nextNoteNumber(int $companyId): string
    {
        $year = now()->format('Y');
        $prefix = 'SCN-' . $year . '-';

        $last = SalesCreditNote::query()
            ->where('company_id', $companyId)
            ->where('note_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('note_number');

        $next = 1;
        if ($last && preg_match('/^(?:SCN-\d{4}-)(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function index(Request $request)
    {
        $companyId = $this->companyId();
        $notes = SalesCreditNote::query()
            ->with('client')
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('accounting.sales_credit_notes.index', compact('companyId','notes'));
    }

    public function create()
    {
        $companyId = $this->companyId();

        $clients = Party::query()
            ->where('is_client', true)
            ->orderBy('name')
            ->get();

        // For credit note lines, user selects DEBIT account (sales return/discount/etc.)
        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $note = new SalesCreditNote([
            'company_id'   => $companyId,
            'note_number'  => $this->nextNoteNumber($companyId),
            'note_date'    => now()->toDateString(),
            'status'       => 'draft',
        ]);

        return view('accounting.sales_credit_notes.form', compact('companyId','note','clients','accounts'));
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();

        $data = $request->validate([
            'client_id'   => ['required','integer', Rule::exists('parties','id')],
            'note_date'   => ['required','date'],
            'note_number' => ['required','string','max:50'],
            'reference'   => ['nullable','string','max:100'],
            'remarks'     => ['nullable','string'],

            'lines'                 => ['required','array','min:1'],
            'lines.*.account_id'    => ['required','integer', Rule::exists('accounts','id')],
            'lines.*.description'   => ['nullable','string','max:255'],
            'lines.*.basic_amount'  => ['required','numeric','gt:0'],
            'lines.*.cgst_rate'     => ['nullable','numeric','min:0','max:100'],
            'lines.*.sgst_rate'     => ['nullable','numeric','min:0','max:100'],
            'lines.*.igst_rate'     => ['nullable','numeric','min:0','max:100'],
        ]);

        $note = DB::transaction(function () use ($data, $companyId) {
            $note = SalesCreditNote::create([
                'company_id'  => $companyId,
                'client_id'   => (int) $data['client_id'],
                'note_number' => trim($data['note_number']),
                'note_date'   => $data['note_date'],
                'reference'   => $data['reference'] ?? null,
                'remarks'     => $data['remarks'] ?? null,
                'status'      => 'draft',
                'created_by'  => auth()->id(),
                'updated_by'  => auth()->id(),
            ]);

            $ln = 1;
            foreach ($data['lines'] as $row) {
                $basic = (float) ($row['basic_amount'] ?? 0);
                if ($basic <= 0) continue;

                $cgstRate = (float) ($row['cgst_rate'] ?? 0);
                $sgstRate = (float) ($row['sgst_rate'] ?? 0);
                $igstRate = (float) ($row['igst_rate'] ?? 0);

                $cgstAmt = round($basic * $cgstRate / 100, 2);
                $sgstAmt = round($basic * $sgstRate / 100, 2);
                $igstAmt = round($basic * $igstRate / 100, 2);

                SalesCreditNoteLine::create([
                    'sales_credit_note_id' => $note->id,
                    'line_no'      => $ln++,
                    'account_id'   => (int) $row['account_id'],
                    'description'  => $row['description'] ?? null,
                    'basic_amount' => round($basic, 2),
                    'cgst_rate'    => $cgstRate,
                    'sgst_rate'    => $sgstRate,
                    'igst_rate'    => $igstRate,
                    'cgst_amount'  => $cgstAmt,
                    'sgst_amount'  => $sgstAmt,
                    'igst_amount'  => $igstAmt,
                    'total_amount' => round($basic + $cgstAmt + $sgstAmt + $igstAmt, 2),
                ]);
            }

            return $note;
        });

        return redirect()->route('accounting.sales-credit-notes.show', $note)
            ->with('success','Sales Credit Note saved.');
    }

    public function show(SalesCreditNote $salesCreditNote)
    {
        $companyId = $this->companyId();
        if ((int) $salesCreditNote->company_id !== $companyId) abort(404);

        $note = $salesCreditNote->load(['client','lines.account','voucher']);
        return view('accounting.sales_credit_notes.show', compact('companyId','note'));
    }

    public function edit(SalesCreditNote $salesCreditNote)
    {
        $companyId = $this->companyId();
        if ((int) $salesCreditNote->company_id !== $companyId) abort(404);

        if (($salesCreditNote->status ?? null) !== 'draft') {
            return redirect()->route('accounting.sales-credit-notes.show', $salesCreditNote)
                ->with('error','Only draft notes can be edited.');
        }

        $clients = Party::query()
            ->where('is_client', true)
            ->orderBy('name')
            ->get();

        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $note = $salesCreditNote->load('lines');

        return view('accounting.sales_credit_notes.form', compact('companyId','note','clients','accounts'));
    }

    public function update(Request $request, SalesCreditNote $salesCreditNote)
    {
        $companyId = $this->companyId();
        if ((int) $salesCreditNote->company_id !== $companyId) abort(404);

        if (($salesCreditNote->status ?? null) !== 'draft') {
            return redirect()->route('accounting.sales-credit-notes.show', $salesCreditNote)
                ->with('error','Only draft notes can be updated.');
        }

        $data = $request->validate([
            'client_id'   => ['required','integer', Rule::exists('parties','id')],
            'note_date'   => ['required','date'],
            'reference'   => ['nullable','string','max:100'],
            'remarks'     => ['nullable','string'],

            'lines'                 => ['required','array','min:1'],
            'lines.*.account_id'    => ['required','integer', Rule::exists('accounts','id')],
            'lines.*.description'   => ['nullable','string','max:255'],
            'lines.*.basic_amount'  => ['required','numeric','gt:0'],
            'lines.*.cgst_rate'     => ['nullable','numeric','min:0','max:100'],
            'lines.*.sgst_rate'     => ['nullable','numeric','min:0','max:100'],
            'lines.*.igst_rate'     => ['nullable','numeric','min:0','max:100'],
        ]);

        DB::transaction(function () use ($salesCreditNote, $data) {
            $salesCreditNote->client_id  = (int) $data['client_id'];
            $salesCreditNote->note_date  = $data['note_date'];
            $salesCreditNote->reference  = $data['reference'] ?? null;
            $salesCreditNote->remarks    = $data['remarks'] ?? null;
            $salesCreditNote->updated_by = auth()->id();
            $salesCreditNote->save();

            $salesCreditNote->lines()->delete();

            $ln = 1;
            foreach ($data['lines'] as $row) {
                $basic = (float) ($row['basic_amount'] ?? 0);
                if ($basic <= 0) continue;

                $cgstRate = (float) ($row['cgst_rate'] ?? 0);
                $sgstRate = (float) ($row['sgst_rate'] ?? 0);
                $igstRate = (float) ($row['igst_rate'] ?? 0);

                $cgstAmt = round($basic * $cgstRate / 100, 2);
                $sgstAmt = round($basic * $sgstRate / 100, 2);
                $igstAmt = round($basic * $igstRate / 100, 2);

                SalesCreditNoteLine::create([
                    'sales_credit_note_id' => $salesCreditNote->id,
                    'line_no'      => $ln++,
                    'account_id'   => (int) $row['account_id'],
                    'description'  => $row['description'] ?? null,
                    'basic_amount' => round($basic, 2),
                    'cgst_rate'    => $cgstRate,
                    'sgst_rate'    => $sgstRate,
                    'igst_rate'    => $igstRate,
                    'cgst_amount'  => $cgstAmt,
                    'sgst_amount'  => $sgstAmt,
                    'igst_amount'  => $igstAmt,
                    'total_amount' => round($basic + $cgstAmt + $sgstAmt + $igstAmt, 2),
                ]);
            }
        });

        return redirect()->route('accounting.sales-credit-notes.show', $salesCreditNote)
            ->with('success','Sales Credit Note updated.');
    }

    public function post(SalesCreditNote $salesCreditNote)
    {
        $companyId = $this->companyId();
        if ((int) $salesCreditNote->company_id !== $companyId) abort(404);

        try {
            $this->postingService->post($salesCreditNote);
        } catch (\Throwable $e) {
            return redirect()->route('accounting.sales-credit-notes.show', $salesCreditNote)
                ->with('error','Failed to post: ' . $e->getMessage());
        }

        return redirect()->route('accounting.sales-credit-notes.show', $salesCreditNote)
            ->with('success','Credit Note posted to accounts.');
    }

    public function cancel(Request $request, SalesCreditNote $salesCreditNote)
    {
        $companyId = $this->companyId();
        if ((int) $salesCreditNote->company_id !== $companyId) abort(404);

        $data = $request->validate([
            'reason' => ['nullable','string','max:500'],
        ]);

        try {
            $this->postingService->cancel($salesCreditNote, (string)($data['reason'] ?? ''));
        } catch (\Throwable $e) {
            return redirect()->route('accounting.sales-credit-notes.show', $salesCreditNote)
                ->with('error','Failed to cancel: ' . $e->getMessage());
        }

        return redirect()->route('accounting.sales-credit-notes.show', $salesCreditNote)
            ->with('success','Credit Note cancelled (reversal voucher created).');
    }
}
