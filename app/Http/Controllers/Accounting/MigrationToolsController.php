<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\ClientRaBill;
use App\Models\Party;
use App\Models\Project;
use App\Models\PurchaseBill;
use App\Services\Accounting\PartyAccountService;
use App\Services\Accounting\VoucherNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * DEV-14: Opening Balance & Migration Tools
 *
 * Per Development Plan v1.2 (Phase 1.5):
 * - Import Opening Balances per ledger
 * - Import Outstanding AR/AP per party (bill-wise)
 *
 * Notes:
 * - We use CSV (Excel can “Save As CSV”).
 * - For outstanding AR/AP we create:
 *   1) A bill record (Purchase Bill / Client RA Bill)
 *   2) A Journal Voucher dated cut-over date to set party ledger balance
 *      (offset to “Opening Balance Adjustment” account)
 */
class MigrationToolsController extends Controller
{
    public function __construct(
        protected PartyAccountService $partyAccountService,
        protected VoucherNumberService $voucherNumberService,
    ) {
        // View screens
        $this->middleware('permission:accounting.accounts.view')->only([
            'index',
            'openingBalancesForm',
            'outstandingApForm',
            'outstandingArForm',
        ]);

        // Import opening balances
        $this->middleware('permission:accounting.accounts.update')->only([
            'importOpeningBalances',
        ]);

        // Import outstanding AR/AP creates vouchers
        $this->middleware('permission:accounting.vouchers.create')->only([
            'importOutstandingAp',
            'importOutstandingAr',
        ]);
    }

    protected function companyId(): int
    {
        return (int) config('accounting.default_company_id', 1);
    }

    public function index()
    {
        return view('accounting.migration_tools.index');
    }

    /*
    |--------------------------------------------------------------------------
    | Opening Balances Import (Ledger-wise)
    |--------------------------------------------------------------------------
    */

    public function openingBalancesForm()
    {
        return view('accounting.migration_tools.opening_balances');
    }

    public function downloadTemplateOpeningBalances()
    {
        $csv = implode("\n", [
            'account_code,opening_balance,dr_cr,opening_date',
            'CASH,15000,dr,2025-04-01',
            'BANK-HDFC,250000,dr,2025-04-01',
            'CAPITAL,265000,cr,2025-04-01',
        ]) . "\n";

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'template_opening_balances.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importOpeningBalances(Request $request)
    {
        $companyId = $this->companyId();

        $validated = $request->validate([
            'as_on_date' => ['required', 'date'],
            'csv_file'   => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $defaultDate = Carbon::parse($validated['as_on_date'])->toDateString();
        $rows = $this->parseCsvFile($request->file('csv_file')->getRealPath());

        $result = [
            'type'     => 'opening_balances',
            'total'    => count($rows),
            'updated'  => 0,
            'skipped'  => 0,
            'errors'   => [],
            'warnings' => [],
        ];

        foreach ($rows as $idx => $row) {
            $rowNo = $idx + 2; // header is row 1

            $accountCode = trim((string) ($row['account_code'] ?? ''));
            if ($accountCode === '') {
                $result['errors'][] = "Row {$rowNo}: account_code is required.";
                $result['skipped']++;
                continue;
            }

            $amountRaw = $row['opening_balance'] ?? null;
            $amount = $this->parseMoney($amountRaw);
            if ($amount === null) {
                $result['errors'][] = "Row {$rowNo}: opening_balance is required and must be numeric.";
                $result['skipped']++;
                continue;
            }

            $drCr = strtolower(trim((string) ($row['dr_cr'] ?? ($row['opening_balance_type'] ?? ''))));
            if ($drCr !== '' && !in_array($drCr, ['dr', 'cr'], true)) {
                $result['errors'][] = "Row {$rowNo}: dr_cr must be 'dr' or 'cr'.";
                $result['skipped']++;
                continue;
            }

            // Support signed balances when dr_cr is not provided.
            if ($drCr === '') {
                if ($amount < 0) {
                    $drCr = 'cr';
                    $amount = abs($amount);
                } else {
                    $drCr = 'dr';
                }
            } else {
                $amount = abs($amount);
            }

            $date = $this->parseDate($row['opening_date'] ?? null) ?? $defaultDate;
            if (! $date) {
                $result['errors'][] = "Row {$rowNo}: opening_date is invalid (use YYYY-MM-DD).";
                $result['skipped']++;
                continue;
            }

            $account = Account::where('company_id', $companyId)
                ->where('code', $accountCode)
                ->first();

            if (! $account) {
                $result['errors'][] = "Row {$rowNo}: account not found for code '{$accountCode}'.";
                $result['skipped']++;
                continue;
            }

            // Guardrail: once vouchers exist, do not alter OB fields (matches AccountController).
            $hasVouchers = VoucherLine::where('account_id', $account->id)->exists();
            if ($hasVouchers) {
                $result['errors'][] = "Row {$rowNo}: account '{$accountCode}' has voucher activity; do opening adjustment via Journal Voucher instead.";
                $result['skipped']++;
                continue;
            }

            $account->opening_balance = $amount;
            $account->opening_balance_type = $drCr;
            $account->opening_balance_date = $date;
            $account->save();

            $result['updated']++;
        }

        return redirect()
            ->route('accounting.migration-tools.opening-balances')
            ->with('import_result', $result);
    }

    /*
    |--------------------------------------------------------------------------
    | Outstanding AP Import (Supplier Bills)
    |--------------------------------------------------------------------------
    */

    public function outstandingApForm()
    {
        return view('accounting.migration_tools.outstanding_ap');
    }

    public function downloadTemplateOutstandingAp()
    {
        $csv = implode("\n", [
            'supplier_code,bill_number,bill_date,due_date,amount,remarks',
            'SUP-0001,INV-123,2025-03-15,2025-04-14,45000,Opening AP as on cut-over',
        ]) . "\n";

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'template_outstanding_ap.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importOutstandingAp(Request $request)
    {
        $companyId = $this->companyId();

        $validated = $request->validate([
            'cutover_date' => ['required', 'date'],
            'csv_file'     => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $cutoverDate = Carbon::parse($validated['cutover_date'])->toDateString();
        $rows = $this->parseCsvFile($request->file('csv_file')->getRealPath());

        $result = [
            'type'      => 'outstanding_ap',
            'total'     => count($rows),
            'created'   => 0,
            'skipped'   => 0,
            'errors'    => [],
            'warnings'  => [],
        ];

        $offsetAccount = $this->getOrCreateOpeningAdjustmentAccount($companyId);

        foreach ($rows as $idx => $row) {
            $rowNo = $idx + 2;

            $supplierCode = trim((string) ($row['supplier_code'] ?? ($row['party_code'] ?? '')));
            if ($supplierCode === '') {
                $result['errors'][] = "Row {$rowNo}: supplier_code is required.";
                $result['skipped']++;
                continue;
            }

            $party = Party::where('code', $supplierCode)->first();
            if (! $party) {
                $result['errors'][] = "Row {$rowNo}: supplier party not found for code '{$supplierCode}'.";
                $result['skipped']++;
                continue;
            }

            if (!($party->is_supplier || $party->is_contractor)) {
                $result['errors'][] = "Row {$rowNo}: party '{$supplierCode}' is not marked as Supplier/Contractor.";
                $result['skipped']++;
                continue;
            }

            $partyAccount = $this->partyAccountService->syncAccountForParty($party);

            if ((float) $partyAccount->opening_balance !== 0.0) {
                $result['warnings'][] = "Row {$rowNo}: supplier ledger '{$partyAccount->code}' has opening_balance set. Ensure you are not double-counting payables.";
            }

            $billNumber = trim((string) ($row['bill_number'] ?? ''));
            if ($billNumber === '') {
                $result['errors'][] = "Row {$rowNo}: bill_number is required.";
                $result['skipped']++;
                continue;
            }

            $billDate = $this->parseDate($row['bill_date'] ?? null);
            if (! $billDate) {
                $result['errors'][] = "Row {$rowNo}: bill_date is required (YYYY-MM-DD).";
                $result['skipped']++;
                continue;
            }

            $dueDate = $this->parseDate($row['due_date'] ?? null);

            $amount = $this->parseMoney($row['amount'] ?? null);
            if ($amount === null || $amount <= 0) {
                $result['errors'][] = "Row {$rowNo}: amount must be a positive number.";
                $result['skipped']++;
                continue;
            }

            // Duplicate guard: same supplier + bill number + bill date
            $existing = PurchaseBill::where('company_id', $companyId)
                ->where('supplier_id', $party->id)
                ->where('bill_number', $billNumber)
                ->whereDate('bill_date', $billDate)
                ->first();

            if ($existing) {
                $result['warnings'][] = "Row {$rowNo}: Purchase Bill already exists (ID {$existing->id}) for '{$supplierCode}' / '{$billNumber}' / {$billDate}. Skipped.";
                $result['skipped']++;
                continue;
            }

            $remarks = trim((string) ($row['remarks'] ?? ''));

            try {
                DB::transaction(function () use (
                    $companyId,
                    $cutoverDate,
                    $offsetAccount,
                    $partyAccount,
                    $party,
                    $billNumber,
                    $billDate,
                    $dueDate,
                    $amount,
                    $remarks
                ) {
                    $narration = 'Opening AP - ' . $party->name . ' - Bill ' . $billNumber;
                    $reference = 'OB-AP/' . $party->code . '/' . Str::limit($billNumber, 50, '');

                    $voucher = $this->createPostedJournalVoucher(
                        companyId: $companyId,
                        voucherDate: $cutoverDate,
                        debitAccountId: $offsetAccount->id,
                        creditAccountId: $partyAccount->id,
                        amount: $amount,
                        narration: $narration,
                        reference: $reference,
                    );

                    PurchaseBill::create([
                        'company_id'      => $companyId,
                        'supplier_id'     => $party->id,
                        'bill_number'     => $billNumber,
                        'bill_date'       => $billDate,
                        'due_date'        => $dueDate,
                        'currency'        => 'INR',
                        'exchange_rate'   => 1,
                        'total_basic'     => $amount,
                        'total_discount'  => 0,
                        'total_tax'       => 0,
                        'total_cgst'      => 0,
                        'total_sgst'      => 0,
                        'total_igst'      => 0,
                        'total_amount'    => $amount,
                        'tds_rate'        => 0,
                        'tds_amount'      => 0,
                        'tcs_rate'        => 0,
                        'tcs_amount'      => 0,
                        'voucher_id'      => $voucher->id,
                        'status'          => 'posted',
                        'remarks'         => $remarks !== '' ? $remarks : 'Opening AP imported via DEV-14',
                        'created_by'      => Auth::id(),
                        'updated_by'      => Auth::id(),
                    ]);
                });

                $result['created']++;
            } catch (\Throwable $e) {
                $result['errors'][] = "Row {$rowNo}: Failed to import AP bill '{$billNumber}' for '{$supplierCode}'. Error: {$e->getMessage()}";
                $result['skipped']++;
            }
        }

        return redirect()
            ->route('accounting.migration-tools.outstanding-ap')
            ->with('import_result', $result);
    }

    /*
    |--------------------------------------------------------------------------
    | Outstanding AR Import (Client Bills)
    |--------------------------------------------------------------------------
    */

    public function outstandingArForm()
    {
        return view('accounting.migration_tools.outstanding_ar');
    }

    public function downloadTemplateOutstandingAr()
    {
        $csv = implode("\n", [
            'client_code,project_code,invoice_number,bill_date,due_date,amount,remarks',
            'CLI-0001,PRJ-2025-0001,INV/2024-25/00012,2025-03-10,2025-04-09,120000,Opening AR as on cut-over',
        ]) . "\n";

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'template_outstanding_ar.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importOutstandingAr(Request $request)
    {
        $companyId = $this->companyId();

        $validated = $request->validate([
            'cutover_date' => ['required', 'date'],
            'csv_file'     => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $cutoverDate = Carbon::parse($validated['cutover_date'])->toDateString();
        $rows = $this->parseCsvFile($request->file('csv_file')->getRealPath());

        $result = [
            'type'      => 'outstanding_ar',
            'total'     => count($rows),
            'created'   => 0,
            'skipped'   => 0,
            'errors'    => [],
            'warnings'  => [],
        ];

        $offsetAccount = $this->getOrCreateOpeningAdjustmentAccount($companyId);

        foreach ($rows as $idx => $row) {
            $rowNo = $idx + 2;

            $clientCode = trim((string) ($row['client_code'] ?? ($row['party_code'] ?? '')));
            if ($clientCode === '') {
                $result['errors'][] = "Row {$rowNo}: client_code is required.";
                $result['skipped']++;
                continue;
            }

            $party = Party::where('code', $clientCode)->first();
            if (! $party) {
                $result['errors'][] = "Row {$rowNo}: client party not found for code '{$clientCode}'.";
                $result['skipped']++;
                continue;
            }

            if (! $party->is_client) {
                $result['errors'][] = "Row {$rowNo}: party '{$clientCode}' is not marked as Client.";
                $result['skipped']++;
                continue;
            }

            $partyAccount = $this->partyAccountService->syncAccountForParty($party);

            if ((float) $partyAccount->opening_balance !== 0.0) {
                $result['warnings'][] = "Row {$rowNo}: client ledger '{$partyAccount->code}' has opening_balance set. Ensure you are not double-counting receivables.";
            }

            $projectCode = trim((string) ($row['project_code'] ?? ''));
            if ($projectCode === '') {
                $result['errors'][] = "Row {$rowNo}: project_code is required for Client RA Bills.";
                $result['skipped']++;
                continue;
            }

            $project = Project::where('code', $projectCode)->first();
            if (! $project) {
                $result['errors'][] = "Row {$rowNo}: project not found for code '{$projectCode}'.";
                $result['skipped']++;
                continue;
            }

            $invoiceNumber = trim((string) ($row['invoice_number'] ?? ''));
            if ($invoiceNumber !== '') {
                $existing = ClientRaBill::where('company_id', $companyId)
                    ->where('invoice_number', $invoiceNumber)
                    ->first();
                if ($existing) {
                    $result['warnings'][] = "Row {$rowNo}: Client bill already exists (ID {$existing->id}) for invoice_number '{$invoiceNumber}'. Skipped.";
                    $result['skipped']++;
                    continue;
                }
            }

            $billDate = $this->parseDate($row['bill_date'] ?? null);
            if (! $billDate) {
                $result['errors'][] = "Row {$rowNo}: bill_date is required (YYYY-MM-DD).";
                $result['skipped']++;
                continue;
            }

            $dueDate = $this->parseDate($row['due_date'] ?? null);

            $amount = $this->parseMoney($row['amount'] ?? null);
            if ($amount === null || $amount <= 0) {
                $result['errors'][] = "Row {$rowNo}: amount must be a positive number.";
                $result['skipped']++;
                continue;
            }

            $remarks = trim((string) ($row['remarks'] ?? ''));

            try {
                DB::transaction(function () use (
                    $companyId,
                    $cutoverDate,
                    $offsetAccount,
                    $partyAccount,
                    $party,
                    $project,
                    $invoiceNumber,
                    $billDate,
                    $dueDate,
                    $amount,
                    $remarks
                ) {
                    $narration = 'Opening AR - ' . $party->name . ($invoiceNumber !== '' ? (' - Inv ' . $invoiceNumber) : '');
                    $reference = 'OB-AR/' . $party->code . '/' . ($invoiceNumber !== '' ? Str::limit($invoiceNumber, 50, '') : 'NA');

                    $voucher = $this->createPostedJournalVoucher(
                        companyId: $companyId,
                        voucherDate: $cutoverDate,
                        debitAccountId: $partyAccount->id,
                        creditAccountId: $offsetAccount->id,
                        amount: $amount,
                        narration: $narration,
                        reference: $reference,
                    );

                    $ra = ClientRaBill::create([
                        'company_id'         => $companyId,
                        'client_id'          => $party->id,
                        'project_id'         => $project->id,
                        'ra_number'          => ClientRaBill::generateNextRaNumber($companyId),
                        'invoice_number'     => $invoiceNumber !== '' ? $invoiceNumber : null,
                        'bill_date'          => $billDate,
                        'due_date'           => $dueDate,
                        'ra_sequence'        => ClientRaBill::getNextRaSequence($party->id, $project->id),
                        'revenue_type'       => 'other',
                        'gross_amount'       => $amount,
                        'current_amount'     => $amount,
                        'net_amount'         => $amount,
                        'total_amount'       => $amount,
                        'receivable_amount'  => $amount,
                        'voucher_id'         => $voucher->id,
                        'status'             => 'posted',
                        'remarks'            => $remarks !== '' ? $remarks : 'Opening AR imported via DEV-14',
                        'created_by'         => Auth::id(),
                        'updated_by'         => Auth::id(),
                    ]);

                    // Optional: create a single summary line (helps printing / display)
                    if (class_exists(\App\Models\ClientRaBillLine::class)) {
                        \App\Models\ClientRaBillLine::create([
                            'client_ra_bill_id' => $ra->id,
                            'line_no'           => 1,
                            'description'       => 'Opening balance invoice',
                            'contracted_qty'    => 1,
                            'previous_qty'      => 0,
                            'current_qty'       => 1,
                            'cumulative_qty'    => 1,
                            'rate'              => $amount,
                            'previous_amount'   => 0,
                            'current_amount'    => $amount,
                            'cumulative_amount' => $amount,
                        ]);
                    }
                });

                $result['created']++;
            } catch (\Throwable $e) {
                $result['errors'][] = "Row {$rowNo}: Failed to import AR bill for '{$clientCode}'. Error: {$e->getMessage()}";
                $result['skipped']++;
            }
        }

        return redirect()
            ->route('accounting.migration-tools.outstanding-ar')
            ->with('import_result', $result);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value); // strip UTF-8 BOM
        $value = trim($value);
        $value = strtolower($value);
        $value = str_replace([' ', '-', '.', '/'], '_', $value);
        $value = preg_replace('/[^a-z0-9_]/', '', $value);
        $value = preg_replace('/_+/', '_', $value);
        return trim($value, '_');
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    protected function parseCsvFile(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw ValidationException::withMessages([
                'csv_file' => 'Unable to read uploaded CSV file.',
            ]);
        }

        $header = null;
        $rows = [];

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            // Skip completely empty rows
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }

            if ($header === null) {
                $header = array_map(function ($h) {
                    return $this->normalizeHeader((string) $h);
                }, $data);

                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = array_key_exists($i, $data) ? trim((string) $data[$i]) : null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    protected function parseMoney(null|string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        // Parentheses → negative
        $negative = false;
        if (preg_match('/^\((.*)\)$/', $v, $m)) {
            $negative = true;
            $v = $m[1];
        }

        $v = str_replace([',', ' '], '', $v);

        if (!is_numeric($v)) {
            return null;
        }

        $num = (float) $v;
        if ($negative) {
            $num = -abs($num);
        }

        return $num;
    }

    protected function parseDate(null|string $value): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') {
            return null;
        }

        // Prefer strict YYYY-MM-DD (our template)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $v)->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        }

        // Fallback to Carbon parse (best-effort)
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function getOrCreateOpeningAdjustmentAccount(int $companyId): Account
    {
        $code = (string) data_get(config('accounting.default_accounts', []), 'opening_balance_adjustment_code', 'OPENING-ADJ');

        $existing = Account::where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        if ($existing) {
            return $existing;
        }

        $group = AccountGroup::where('company_id', $companyId)
            ->where('code', 'EQUITY')
            ->first();

        if (! $group) {
            $group = AccountGroup::where('company_id', $companyId)
                ->where('nature', 'equity')
                ->orderBy('id')
                ->first();
        }

        if (! $group) {
            $group = AccountGroup::where('company_id', $companyId)->orderBy('id')->firstOrFail();
        }

        return Account::create([
            'company_id'            => $companyId,
            'account_group_id'      => $group->id,
            'name'                  => 'Opening Balance Adjustment',
            'code'                  => $code,
            'type'                  => 'ledger',
            'opening_balance'       => 0,
            'opening_balance_type'  => 'dr',
            'opening_balance_date'  => null,
            'is_active'             => true,
            'is_system'             => true,
            'system_key'            => 'opening_balance_adjustment',
        ]);
    }

    protected function createPostedJournalVoucher(
        int $companyId,
        string $voucherDate,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        ?string $narration = null,
        ?string $reference = null,
    ): Voucher {
        $voucherNo = $this->voucherNumberService->next('journal', $companyId, Carbon::parse($voucherDate));

        /** @var \App\Models\Accounting\Voucher $voucher */
        $voucher = Voucher::create([
            'company_id'    => $companyId,
            'voucher_no'    => $voucherNo,
            'voucher_type'  => 'journal',
            'voucher_date'  => $voucherDate,
            'reference'     => $reference,
            'narration'     => $narration,
            'status'        => 'draft',
            'created_by'    => Auth::id(),
        ]);

        VoucherLine::create([
            'voucher_id'   => $voucher->id,
            'line_no'      => 1,
            'account_id'   => $debitAccountId,
            'description'  => $narration,
            'debit'        => $amount,
            'credit'       => 0,
        ]);

        VoucherLine::create([
            'voucher_id'   => $voucher->id,
            'line_no'      => 2,
            'account_id'   => $creditAccountId,
            'description'  => $narration,
            'debit'        => 0,
            'credit'       => $amount,
        ]);

        $voucher->status = 'posted';
        $voucher->posted_by = Auth::id();
        $voucher->posted_at = now();
        $voucher->save();

        return $voucher->fresh(['lines']);
    }
}
