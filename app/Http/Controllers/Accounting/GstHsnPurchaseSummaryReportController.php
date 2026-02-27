<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Support\MoneyHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 5e
 * GST Purchase HSN Summary (Item + Expense lines)
 */
class GstHsnPurchaseSummaryReportController extends Controller
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

        $status = trim((string) $request->string('status', 'posted'));

        $includeExpenses = $request->boolean('include_expenses', true);

        $rows = $this->buildRows($companyId, $fromDate, $toDate, $status, $includeExpenses);

        $totals = $this->computeTotals($rows);

        return view('accounting.reports.gst_hsn_purchase_summary', [
            'companyId'        => $companyId,
            'fromDate'         => $fromDate,
            'toDate'           => $toDate,
            'status'           => $status,
            'includeExpenses'  => $includeExpenses,
            'rows'             => $rows,
            'totals'           => $totals,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $companyId = (int) ($request->integer('company_id') ?: $this->defaultCompanyId());

        $fromDate = $request->date('from_date') ?: now()->startOfMonth();
        $toDate   = $request->date('to_date') ?: now();

        $fromDate = Carbon::parse($fromDate)->startOfDay();
        $toDate   = Carbon::parse($toDate)->endOfDay();

        $status = trim((string) $request->string('status', 'posted'));
        $includeExpenses = $request->boolean('include_expenses', true);

        $rows = $this->buildRows($companyId, $fromDate, $toDate, $status, $includeExpenses);

        $fileName = 'gst_purchase_hsn_summary_' . $fromDate->format('Ymd') . '_' . $toDate->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Source',
                'HSN/SAC',
                'GST Rate (%)',
                'Taxable Value',
                'CGST',
                'SGST',
                'IGST',
                'Total GST',
                'Gross Total',
            ]);

            foreach ($rows as $r) {
                $taxablePaise = MoneyHelper::toPaise($r['taxable'] ?? 0);
                $cgstPaise    = MoneyHelper::toPaise($r['cgst'] ?? 0);
                $sgstPaise    = MoneyHelper::toPaise($r['sgst'] ?? 0);
                $igstPaise    = MoneyHelper::toPaise($r['igst'] ?? 0);

                fputcsv($out, [
                    $r['source'],
                    $r['hsn_sac'],
                    $r['gst_rate'],
                    MoneyHelper::fromPaise($taxablePaise),
                    MoneyHelper::fromPaise($cgstPaise),
                    MoneyHelper::fromPaise($sgstPaise),
                    MoneyHelper::fromPaise($igstPaise),
                    MoneyHelper::fromPaise($cgstPaise + $sgstPaise + $igstPaise),
                    MoneyHelper::fromPaise($taxablePaise + $cgstPaise + $sgstPaise + $igstPaise),
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    protected function buildRows(int $companyId, Carbon $fromDate, Carbon $toDate, string $status, bool $includeExpenses)
    {
        // 1) Item lines (purchase_bill_lines + items.hsn_code)
        $itemRows = DB::table('purchase_bill_lines')
            ->join('purchase_bills', 'purchase_bills.id', '=', 'purchase_bill_lines.purchase_bill_id')
            ->leftJoin('items', 'items.id', '=', 'purchase_bill_lines.item_id')
            ->where('purchase_bills.company_id', $companyId)
            ->whereDate('purchase_bills.bill_date', '>=', $fromDate->toDateString())
            ->whereDate('purchase_bills.bill_date', '<=', $toDate->toDateString())
            ->when(in_array($status, ['draft', 'posted', 'cancelled'], true), function ($q) use ($status) {
                $q->where('purchase_bills.status', $status);
            })
            ->selectRaw("COALESCE(NULLIF(items.hsn_code,''), 'NA') as hsn_sac")
            ->selectRaw('ROUND(purchase_bill_lines.tax_rate, 2) as gst_rate')
            ->selectRaw('SUM(purchase_bill_lines.basic_amount) as taxable')
            ->selectRaw('SUM(purchase_bill_lines.cgst_amount) as cgst')
            ->selectRaw('SUM(purchase_bill_lines.sgst_amount) as sgst')
            ->selectRaw('SUM(purchase_bill_lines.igst_amount) as igst')
            ->groupBy(DB::raw("COALESCE(NULLIF(items.hsn_code,''), 'NA')"), DB::raw('ROUND(purchase_bill_lines.tax_rate, 2)'))
            ->orderBy('hsn_sac')
            ->orderBy('gst_rate')
            ->get();

        $rows = collect();

        foreach ($itemRows as $r) {
            $rows->push([
                'source'  => 'Items',
                'hsn_sac' => (string) $r->hsn_sac,
                'gst_rate'=> (string) $r->gst_rate,
                'taxable' => (string) $r->taxable,
                'cgst'    => (string) $r->cgst,
                'sgst'    => (string) $r->sgst,
                'igst'    => (string) $r->igst,
            ]);
        }

        if (! $includeExpenses) {
            return $rows;
        }

        // 2) Expense lines (purchase_bill_expense_lines + gst_account_rates.hsn_sac_code)
        // We join gst_account_rates with an effective date slice.
        $expenseRows = DB::table('purchase_bill_expense_lines as el')
            ->join('purchase_bills', 'purchase_bills.id', '=', 'el.purchase_bill_id')
            ->leftJoin('accounts as a', 'a.id', '=', 'el.account_id')
            ->leftJoin('gst_account_rates as gar', function ($join) {
                $join->on('gar.account_id', '=', 'el.account_id')
                    ->whereColumn('gar.effective_from', '<=', 'purchase_bills.bill_date')
                    ->where(function ($q) {
                        $q->whereNull('gar.effective_to')
                          ->orWhereColumn('gar.effective_to', '>=', 'purchase_bills.bill_date');
                    });
            })
            ->where('purchase_bills.company_id', $companyId)
            ->whereDate('purchase_bills.bill_date', '>=', $fromDate->toDateString())
            ->whereDate('purchase_bills.bill_date', '<=', $toDate->toDateString())
            ->when(in_array($status, ['draft', 'posted', 'cancelled'], true), function ($q) use ($status) {
                $q->where('purchase_bills.status', $status);
            })
            ->selectRaw("COALESCE(NULLIF(gar.hsn_sac_code,''), 'NA') as hsn_sac")
            ->selectRaw('ROUND(COALESCE(el.tax_rate, gar.igst_rate, 0), 2) as gst_rate')
            ->selectRaw('SUM(el.basic_amount) as taxable')
            ->selectRaw('SUM(el.cgst_amount) as cgst')
            ->selectRaw('SUM(el.sgst_amount) as sgst')
            ->selectRaw('SUM(el.igst_amount) as igst')
            ->groupBy(DB::raw("COALESCE(NULLIF(gar.hsn_sac_code,''), 'NA')"), DB::raw('ROUND(COALESCE(el.tax_rate, gar.igst_rate, 0), 2)'))
            ->orderBy('hsn_sac')
            ->orderBy('gst_rate')
            ->get();

        foreach ($expenseRows as $r) {
            $rows->push([
                'source'  => 'Expenses',
                'hsn_sac' => (string) $r->hsn_sac,
                'gst_rate'=> (string) $r->gst_rate,
                'taxable' => (string) $r->taxable,
                'cgst'    => (string) $r->cgst,
                'sgst'    => (string) $r->sgst,
                'igst'    => (string) $r->igst,
            ]);
        }

        // Sort final rows
        return $rows->sortBy(function ($r) {
            return sprintf('%s|%s|%s', $r['hsn_sac'], str_pad((string) $r['gst_rate'], 8, '0', STR_PAD_LEFT), $r['source']);
        })->values();
    }

    /**
     * @param \Illuminate\Support\Collection<int,array<string,mixed>> $rows
     */
    protected function computeTotals($rows): array
    {
        $totals = [
            'taxable' => 0,
            'cgst'    => 0,
            'sgst'    => 0,
            'igst'    => 0,
            'rows'    => $rows->count(),
        ];

        foreach ($rows as $r) {
            $totals['taxable'] += MoneyHelper::toPaise($r['taxable'] ?? 0);
            $totals['cgst']    += MoneyHelper::toPaise($r['cgst'] ?? 0);
            $totals['sgst']    += MoneyHelper::toPaise($r['sgst'] ?? 0);
            $totals['igst']    += MoneyHelper::toPaise($r['igst'] ?? 0);
        }

        $totals['gst_total']   = $totals['cgst'] + $totals['sgst'] + $totals['igst'];
        $totals['gross_total'] = $totals['taxable'] + $totals['gst_total'];

        return $totals;
    }
}
