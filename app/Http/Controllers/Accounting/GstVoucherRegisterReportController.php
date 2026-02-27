<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Party;
use App\Models\Project;
use App\Support\MoneyHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 5e
 * Voucher-based GST register (fallback)
 *
 * Shows GST ledger movements even for manual vouchers (not linked to Purchase Bills / Client RA Bills).
 */
class GstVoucherRegisterReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.reports.view')
            ->only(['index', 'export']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    public function index(Request $request)
    {
        $companyId = (int) ($request->integer('company_id') ?: $this->defaultCompanyId());

        $fromDate = $request->date('from_date') ?: now()->startOfMonth();
        $toDate   = $request->date('to_date') ?: now();

        $fromDate = Carbon::parse($fromDate)->startOfDay();
        $toDate   = Carbon::parse($toDate)->endOfDay();

        $projectId = $request->integer('project_id') ?: null;

        $status = trim((string) $request->string('status', 'posted'));
        $mode   = strtolower(trim((string) $request->string('mode', 'all')));

        if (! in_array($mode, ['all', 'input', 'output'], true)) {
            $mode = 'all';
        }

        [$gstAccounts, $missing] = $this->resolveGstAccounts($companyId);

        $projects = Project::query()
            ->orderBy('name')
            ->get();

        $rows = [];
        $partyAccounts = collect();

        // If all GST accounts are missing, show an empty report with warnings
        if (empty($gstAccounts['all_ids'])) {
            return view('accounting.reports.gst_voucher_register', [
                'companyId'     => $companyId,
                'fromDate'      => $fromDate,
                'toDate'        => $toDate,
                'projectId'     => $projectId,
                'status'        => $status,
                'mode'          => $mode,
                'projects'      => $projects,
                'missing'       => $missing,
                'rows'          => collect(),
                'partyAccounts' => collect(),
                'totals'        => $this->emptyTotals(),
            ]);
        }

        $rows = $this->buildRows($companyId, $fromDate, $toDate, $projectId, $status, $mode, $gstAccounts);

        $partyAccountIds = $rows
            ->pluck('party_account_id')
            ->filter()
            ->unique()
            ->values();

        if ($partyAccountIds->isNotEmpty()) {
            $partyAccounts = Account::query()
                ->with('relatedModel')
                ->whereIn('id', $partyAccountIds)
                ->get()
                ->keyBy('id');
        }

        $totals = $this->computeTotals($rows);

        return view('accounting.reports.gst_voucher_register', [
            'companyId'     => $companyId,
            'fromDate'      => $fromDate,
            'toDate'        => $toDate,
            'projectId'     => $projectId,
            'status'        => $status,
            'mode'          => $mode,
            'projects'      => $projects,
            'missing'       => $missing,
            'rows'          => $rows,
            'partyAccounts' => $partyAccounts,
            'totals'        => $totals,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $companyId = (int) ($request->integer('company_id') ?: $this->defaultCompanyId());

        $fromDate = $request->date('from_date') ?: now()->startOfMonth();
        $toDate   = $request->date('to_date') ?: now();

        $fromDate = Carbon::parse($fromDate)->startOfDay();
        $toDate   = Carbon::parse($toDate)->endOfDay();

        $projectId = $request->integer('project_id') ?: null;

        $status = trim((string) $request->string('status', 'posted'));
        $mode   = strtolower(trim((string) $request->string('mode', 'all')));

        if (! in_array($mode, ['all', 'input', 'output'], true)) {
            $mode = 'all';
        }

        [$gstAccounts, $missing] = $this->resolveGstAccounts($companyId);

        $rows = collect();

        if (! empty($gstAccounts['all_ids'])) {
            $rows = $this->buildRows($companyId, $fromDate, $toDate, $projectId, $status, $mode, $gstAccounts);
        }

        $partyAccountIds = $rows
            ->pluck('party_account_id')
            ->filter()
            ->unique()
            ->values();

        $partyAccounts = collect();

        if ($partyAccountIds->isNotEmpty()) {
            $partyAccounts = Account::query()
                ->with('relatedModel')
                ->whereIn('id', $partyAccountIds)
                ->get()
                ->keyBy('id');
        }

        $fileName = 'gst_voucher_register_' . $mode . '_' . $fromDate->format('Ymd') . '_' . $toDate->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows, $partyAccounts, $missing) {
            $out = fopen('php://output', 'w');

            // If there are missing GST ledgers, we add them as a header row for visibility
            if (! empty($missing)) {
                fputcsv($out, ['NOTE: Missing GST ledgers / config codes: ' . implode(' | ', $missing)]);
            }

            fputcsv($out, [
                'Voucher Date',
                'Voucher No',
                'Voucher Type',
                'Project',
                'Party',
                'GSTIN',
                'Reference',
                'Narration',
                'Input CGST',
                'Input SGST',
                'Input IGST',
                'Output CGST',
                'Output SGST',
                'Output IGST',
                'Total Input GST',
                'Total Output GST',
                'Net (Output - Input)',
                'Voucher Amount',
                'Status',
            ]);

            foreach ($rows as $r) {
                $partyAcc = $r->party_account_id ? ($partyAccounts[$r->party_account_id] ?? null) : null;
                $party    = $partyAcc?->relatedModel;

                $inputCgst  = MoneyHelper::round2($r->input_cgst ?? 0);
                $inputSgst  = MoneyHelper::round2($r->input_sgst ?? 0);
                $inputIgst  = MoneyHelper::round2($r->input_igst ?? 0);
                $outputCgst = MoneyHelper::round2($r->output_cgst ?? 0);
                $outputSgst = MoneyHelper::round2($r->output_sgst ?? 0);
                $outputIgst = MoneyHelper::round2($r->output_igst ?? 0);

                $inputTotalPaise  = MoneyHelper::toPaise($inputCgst) + MoneyHelper::toPaise($inputSgst) + MoneyHelper::toPaise($inputIgst);
                $outputTotalPaise = MoneyHelper::toPaise($outputCgst) + MoneyHelper::toPaise($outputSgst) + MoneyHelper::toPaise($outputIgst);

                fputcsv($out, [
                    $r->voucher_date,
                    $r->voucher_no,
                    $r->voucher_type,
                    $r->project_name,
                    $party?->name ?? $partyAcc?->name,
                    $party?->gstin ?? $partyAcc?->gstin,
                    $r->reference,
                    $r->narration,
                    $inputCgst,
                    $inputSgst,
                    $inputIgst,
                    $outputCgst,
                    $outputSgst,
                    $outputIgst,
                    MoneyHelper::fromPaise($inputTotalPaise),
                    MoneyHelper::fromPaise($outputTotalPaise),
                    MoneyHelper::fromPaise($outputTotalPaise - $inputTotalPaise),
                    MoneyHelper::round2($r->amount_base ?? 0),
                    $r->status,
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: array<string,mixed>, 1: array<int,string>}
     */
    protected function resolveGstAccounts(int $companyId): array
    {
        $codes = [
            // Input
            'input_cgst' => (string) Config::get('accounting.gst.input_cgst_account_code'),
            'input_sgst' => (string) Config::get('accounting.gst.input_sgst_account_code'),
            'input_igst' => (string) Config::get('accounting.gst.input_igst_account_code'),

            // Output
            'output_cgst' => (string) Config::get('accounting.gst.cgst_output_account_code'),
            'output_sgst' => (string) Config::get('accounting.gst.sgst_output_account_code'),
            'output_igst' => (string) Config::get('accounting.gst.igst_output_account_code'),
        ];

        $missing = [];
        $out = [
            'input'  => ['cgst' => null, 'sgst' => null, 'igst' => null],
            'output' => ['cgst' => null, 'sgst' => null, 'igst' => null],
            'all_ids'=> [],
        ];

        foreach ($codes as $key => $code) {
            $code = trim($code);
            if ($code === '') {
                $missing[] = $key . ' (empty config)';
                continue;
            }

            $acc = Account::query()
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->first();

            if (! $acc) {
                $missing[] = $code;
                continue;
            }

            if (str_starts_with($key, 'input_')) {
                $k = substr($key, 6); // cgst/sgst/igst
                $out['input'][$k] = (int) $acc->id;
            } else {
                $k = substr($key, 7); // cgst/sgst/igst
                $out['output'][$k] = (int) $acc->id;
            }

            $out['all_ids'][] = (int) $acc->id;
        }

        $out['all_ids'] = array_values(array_unique(array_filter($out['all_ids'])));

        return [$out, $missing];
    }

    /**
     * Build voucher-wise rows with GST totals.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    protected function buildRows(
        int $companyId,
        Carbon $fromDate,
        Carbon $toDate,
        ?int $projectId,
        string $status,
        string $mode,
        array $gstAccounts
    ) {
        $partyTypeSql = "'" . addslashes(Party::class) . "'";

        $inIds  = array_values(array_filter($gstAccounts['input']));
        $outIds = array_values(array_filter($gstAccounts['output']));

        $inputExpr = ! empty($inIds)
            ? 'SUM(CASE WHEN voucher_lines.account_id IN (' . implode(',', $inIds) . ') THEN (voucher_lines.debit - voucher_lines.credit) ELSE 0 END)'
            : '0';

        $outputExpr = ! empty($outIds)
            ? 'SUM(CASE WHEN voucher_lines.account_id IN (' . implode(',', $outIds) . ') THEN (voucher_lines.credit - voucher_lines.debit) ELSE 0 END)'
            : '0';

        $query = DB::table('voucher_lines')
            ->join('vouchers', 'vouchers.id', '=', 'voucher_lines.voucher_id')
            ->join('accounts', 'accounts.id', '=', 'voucher_lines.account_id')
            ->leftJoin('projects', 'projects.id', '=', 'vouchers.project_id')
            ->where('vouchers.company_id', $companyId)
            ->whereDate('vouchers.voucher_date', '>=', $fromDate->toDateString())
            ->whereDate('vouchers.voucher_date', '<=', $toDate->toDateString());

        if ($projectId) {
            $query->where('vouchers.project_id', $projectId);
        }

        if (in_array($status, ['posted', 'draft'], true)) {
            $query->where('vouchers.status', $status);
        }

        // Only vouchers that have GST movement based on mode
        if ($mode === 'input') {
            $query->havingRaw($inputExpr . ' <> 0');
        } elseif ($mode === 'output') {
            $query->havingRaw($outputExpr . ' <> 0');
        } else {
            $query->havingRaw('(' . $inputExpr . ' <> 0 OR ' . $outputExpr . ' <> 0)');
        }

        // Build safe expressions per GST ledger
        $expr = function (?int $accountId, string $mode) {
            if (! $accountId) {
                return '0';
            }

            if ($mode === 'input') {
                return 'SUM(CASE WHEN voucher_lines.account_id = ' . (int) $accountId . ' THEN (voucher_lines.debit - voucher_lines.credit) ELSE 0 END)';
            }

            return 'SUM(CASE WHEN voucher_lines.account_id = ' . (int) $accountId . ' THEN (voucher_lines.credit - voucher_lines.debit) ELSE 0 END)';
        };

        return $query
            ->select([
                'voucher_lines.voucher_id as voucher_id',
                DB::raw('MAX(vouchers.voucher_date) as voucher_date'),
                DB::raw('MAX(vouchers.voucher_no) as voucher_no'),
                DB::raw('MAX(vouchers.voucher_type) as voucher_type'),
                DB::raw('MAX(vouchers.reference) as reference'),
                DB::raw('MAX(vouchers.narration) as narration'),
                DB::raw('MAX(vouchers.status) as status'),
                DB::raw('MAX(vouchers.amount_base) as amount_base'),
                DB::raw('MAX(vouchers.project_id) as project_id'),
                DB::raw('MAX(projects.name) as project_name'),

                DB::raw('SUM(voucher_lines.debit) as total_debit'),
                DB::raw('SUM(voucher_lines.credit) as total_credit'),

                DB::raw($expr($gstAccounts['input']['cgst'] ?? null, 'input') . ' as input_cgst'),
                DB::raw($expr($gstAccounts['input']['sgst'] ?? null, 'input') . ' as input_sgst'),
                DB::raw($expr($gstAccounts['input']['igst'] ?? null, 'input') . ' as input_igst'),

                DB::raw($expr($gstAccounts['output']['cgst'] ?? null, 'output') . ' as output_cgst'),
                DB::raw($expr($gstAccounts['output']['sgst'] ?? null, 'output') . ' as output_sgst'),
                DB::raw($expr($gstAccounts['output']['igst'] ?? null, 'output') . ' as output_igst'),

                DB::raw("MAX(CASE WHEN accounts.type IN ('debtor','creditor') AND accounts.related_model_type = {$partyTypeSql} THEN accounts.id ELSE NULL END) as party_account_id"),
            ])
            ->groupBy('voucher_lines.voucher_id')
            ->orderByRaw('MAX(vouchers.voucher_date) asc')
            ->orderBy('voucher_lines.voucher_id')
            ->get();
    }

    protected function emptyTotals(): array
    {
        return [
            'input_cgst' => 0,
            'input_sgst' => 0,
            'input_igst' => 0,
            'output_cgst'=> 0,
            'output_sgst'=> 0,
            'output_igst'=> 0,
            'input_total'=> 0,
            'output_total'=> 0,
            'net'        => 0,
            'rows'       => 0,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int,object> $rows
     */
    protected function computeTotals($rows): array
    {
        $totals = $this->emptyTotals();

        foreach ($rows as $r) {
            $totals['input_cgst']  += MoneyHelper::toPaise($r->input_cgst ?? 0);
            $totals['input_sgst']  += MoneyHelper::toPaise($r->input_sgst ?? 0);
            $totals['input_igst']  += MoneyHelper::toPaise($r->input_igst ?? 0);
            $totals['output_cgst'] += MoneyHelper::toPaise($r->output_cgst ?? 0);
            $totals['output_sgst'] += MoneyHelper::toPaise($r->output_sgst ?? 0);
            $totals['output_igst'] += MoneyHelper::toPaise($r->output_igst ?? 0);
        }

        $totals['input_total']  = $totals['input_cgst'] + $totals['input_sgst'] + $totals['input_igst'];
        $totals['output_total'] = $totals['output_cgst'] + $totals['output_sgst'] + $totals['output_igst'];
        $totals['net']          = $totals['output_total'] - $totals['input_total'];
        $totals['rows']         = $rows->count();

        return $totals;
    }
}
