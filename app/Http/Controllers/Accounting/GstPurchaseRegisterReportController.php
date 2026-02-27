<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\PurchaseBill;
use App\Models\Accounting\PurchaseDebitNote;
use App\Support\MoneyHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GstPurchaseRegisterReportController extends Controller
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

        $supplierId = $request->integer('supplier_id') ?: null;
        $status     = trim((string) $request->string('status', 'posted'));

        // Suppliers for dropdown
        $suppliers = Party::query()
            ->where('is_supplier', true)
            ->orderBy('name')
            ->get();

        $bills  = $this->loadRows($companyId, $fromDate, $toDate, $supplierId, $status);
        $totals = $this->computeTotals($bills);

        return view('accounting.reports.gst_purchase_register', [
            'companyId'  => $companyId,
            'fromDate'   => $fromDate,
            'toDate'     => $toDate,
            'supplierId' => $supplierId,
            'status'     => $status,
            'suppliers'  => $suppliers,
            'bills'      => $bills,
            'totals'     => $totals,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $companyId = (int) ($request->integer('company_id') ?: $this->defaultCompanyId());

        $fromDate = $request->date('from_date') ?: now()->startOfMonth();
        $toDate   = $request->date('to_date') ?: now();

        $fromDate = Carbon::parse($fromDate)->startOfDay();
        $toDate   = Carbon::parse($toDate)->endOfDay();

        $supplierId = $request->integer('supplier_id') ?: null;
        $status     = trim((string) $request->string('status', 'posted'));

        $bills = $this->loadRows($companyId, $fromDate, $toDate, $supplierId, $status);

        $fileName = 'gst_purchase_register_' . $fromDate->format('Ymd') . '_' . $toDate->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($bills) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Invoice Date',
                'Posting Date',
                'Bill No',
                'Voucher No',
                'Supplier Name',
                'Supplier GSTIN',
                'Taxable Value',
                'CGST',
                'SGST',
                'IGST',
                'Total GST',
                'RCM Total GST',
                'Invoice Total',
                'TCS Amount',
                'TDS Amount',
                'Net Payable',
                'Status',
            ]);

            foreach ($bills as $bill) {
                $supplier = $bill->supplier;

                $taxable   = (string) ($bill->getRawOriginal('total_basic') ?? $bill->total_basic ?? '0');
                $cgst      = (string) ($bill->getRawOriginal('total_cgst') ?? $bill->total_cgst ?? '0');
                $sgst      = (string) ($bill->getRawOriginal('total_sgst') ?? $bill->total_sgst ?? '0');
                $igst      = (string) ($bill->getRawOriginal('total_igst') ?? $bill->total_igst ?? '0');
                $rcmCgst   = (string) ($bill->getRawOriginal('total_rcm_cgst') ?? $bill->total_rcm_cgst ?? '0');
                $rcmSgst   = (string) ($bill->getRawOriginal('total_rcm_sgst') ?? $bill->total_rcm_sgst ?? '0');
                $rcmIgst   = (string) ($bill->getRawOriginal('total_rcm_igst') ?? $bill->total_rcm_igst ?? '0');
                $invoice   = (string) ($bill->getRawOriginal('total_amount') ?? $bill->total_amount ?? '0');
                $tcsAmount = (string) ($bill->getRawOriginal('tcs_amount') ?? $bill->tcs_amount ?? '0');
                $tdsAmount = (string) ($bill->getRawOriginal('tds_amount') ?? $bill->tds_amount ?? '0');

                $taxablePaise = MoneyHelper::toPaise($taxable);
                $cgstPaise    = MoneyHelper::toPaise($cgst);
                $sgstPaise    = MoneyHelper::toPaise($sgst);
                $igstPaise    = MoneyHelper::toPaise($igst);
                $rcmCgstPaise = MoneyHelper::toPaise($rcmCgst);
                $rcmSgstPaise = MoneyHelper::toPaise($rcmSgst);
                $rcmIgstPaise = MoneyHelper::toPaise($rcmIgst);
                $invoicePaise = MoneyHelper::toPaise($invoice);
                $tcsPaise     = MoneyHelper::toPaise($tcsAmount);
                $tdsPaise     = MoneyHelper::toPaise($tdsAmount);
                $netPaise     = ($invoicePaise + $tcsPaise) - $tdsPaise;

                fputcsv($out, [
                    optional($bill->bill_date)->toDateString(),
                    // Posting Date: prefer explicit purchase_bills.posting_date, fallback to voucher_date (for debit notes / legacy)
                    optional($bill->getAttribute('posting_date') ?: optional($bill->voucher)->voucher_date ?: $bill->bill_date)->toDateString(),
                    $bill->bill_number,
                    optional($bill->voucher)->voucher_no,
                    $supplier?->name,
                    $supplier?->gstin,
                    MoneyHelper::fromPaise($taxablePaise),
                    MoneyHelper::fromPaise($cgstPaise),
                    MoneyHelper::fromPaise($sgstPaise),
                    MoneyHelper::fromPaise($igstPaise),
                    MoneyHelper::fromPaise($cgstPaise + $sgstPaise + $igstPaise),
                    MoneyHelper::fromPaise($rcmCgstPaise + $rcmSgstPaise + $rcmIgstPaise),
                    MoneyHelper::fromPaise($invoicePaise),
                    MoneyHelper::fromPaise($tcsPaise),
                    MoneyHelper::fromPaise($tdsPaise),
                    MoneyHelper::fromPaise($netPaise),
                    $bill->status,
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Load purchase bills + purchase debit notes as a single collection of
     * PurchaseBill-like models so the existing Blade + totals logic work unchanged.
     */
    protected function loadRows(int $companyId, Carbon $fromDate, Carbon $toDate, ?int $supplierId, string $status)
    {
        $dateColumn = $this->purchaseBillFilterDateColumn();

        $bills = $this->buildQuery($companyId, $fromDate, $toDate, $supplierId, $status)
            ->orderBy($dateColumn)
            ->orderBy('id')
            ->get();

        // If purchase_debit_notes table is not present (legacy DB), just return invoices.
        if (! Schema::hasTable('purchase_debit_notes')) {
            return $bills;
        }

        // Filter by Posting Date:
        // - If voucher exists (posted notes), use voucher.voucher_date
        // - Otherwise (draft notes without voucher), fallback to note_date
        $debitNotesQuery = PurchaseDebitNote::query()
            ->with(['supplier', 'voucher'])
            ->where('company_id', $companyId)
            ->where(function ($q) use ($fromDate, $toDate) {
                $q->whereHas('voucher', function ($vq) use ($fromDate, $toDate) {
                    $vq->whereDate('voucher_date', '>=', $fromDate->toDateString())
                        ->whereDate('voucher_date', '<=', $toDate->toDateString());
                })
                ->orWhere(function ($q2) use ($fromDate, $toDate) {
                    $q2->whereNull('voucher_id')
                        ->whereDate('note_date', '>=', $fromDate->toDateString())
                        ->whereDate('note_date', '<=', $toDate->toDateString());
                });
            });

        if ($supplierId) {
            $debitNotesQuery->where('supplier_id', $supplierId);
        }

        if (in_array($status, ['draft', 'posted', 'cancelled'], true)) {
            $debitNotesQuery->where('status', $status);
        }

        $debitNotes = $debitNotesQuery->get();

        if ($debitNotes->isEmpty()) {
            return $bills;
        }

        $synthetic = $debitNotes->map(function (PurchaseDebitNote $note) {
            // Create an in-memory PurchaseBill instance with negative values,
            // so that totals + report behave like Tally (notes reduce purchases/GST).
            $bill = new PurchaseBill();

            $bill->setRawAttributes([
                'id'              => null,
                'company_id'      => $note->company_id,
                'supplier_id'     => $note->supplier_id,
                'bill_number'     => $note->note_number,
                'total_basic'     => -1 * (float) $note->total_basic,
                'total_cgst'      => -1 * (float) $note->total_cgst,
                'total_sgst'      => -1 * (float) $note->total_sgst,
                'total_igst'      => -1 * (float) $note->total_igst,
                'total_rcm_cgst'  => 0,
                'total_rcm_sgst'  => 0,
                'total_rcm_igst'  => 0,
                'total_amount'    => -1 * (float) $note->total_amount,
                'tcs_amount'      => 0,
                'tds_amount'      => 0,
                'status'          => $note->status,
            ], true);

            // bill_date should behave like the Eloquent date cast used on PurchaseBill
            $bill->setAttribute('bill_date', $note->note_date);

            // Copy relations
            $bill->setRelation('supplier', $note->supplier);
            $bill->setRelation('voucher', $note->voucher);

            // Meta (not used by existing views, but available for future badges/links)
            $bill->setAttribute('source_type', 'purchase_debit_note');
            $bill->setAttribute('source_note_id', $note->id);

            return $bill;
        });

        // Mark original bills for completeness
        $bills->each(function (PurchaseBill $bill) {
            if (! $bill->getAttribute('source_type')) {
                $bill->setAttribute('source_type', 'purchase_bill');
            }
        });

        // Sort by Posting Date (same basis as the filter), then by id/bill_no for stable ordering.
        return $bills
            ->concat($synthetic)
            ->sortBy(function ($bill) {
                $postingDate = $bill->getAttribute('posting_date')
                    ?: optional($bill->voucher)->voucher_date
                    ?: $bill->bill_date;

                if ($postingDate instanceof \DateTimeInterface) {
                    $keyDate = $postingDate->format('Y-m-d');
                } else {
                    $keyDate = (string) $postingDate;
                }

                $id     = (int) ($bill->id ?? 0);
                $billNo = (string) ($bill->bill_number ?? '');

                return $keyDate
                    . '_' . str_pad((string) $id, 10, '0', STR_PAD_LEFT)
                    . '_' . $billNo;
            })
            ->values();
    }

    /**
     * We filter GST Purchase Register by Posting Date (books date).
     *
     * On newer DBs, purchase_bills has `posting_date`. On legacy DBs,
     * fallback to `bill_date` so the report keeps working.
     */
    protected function purchaseBillFilterDateColumn(): string
    {
        return Schema::hasColumn('purchase_bills', 'posting_date') ? 'posting_date' : 'bill_date';
    }

    protected function buildQuery(int $companyId, Carbon $fromDate, Carbon $toDate, ?int $supplierId, string $status)
    {
        $dateColumn = $this->purchaseBillFilterDateColumn();

        $query = PurchaseBill::query()
            ->with(['supplier', 'voucher'])
            ->where('company_id', $companyId)
            ->whereDate($dateColumn, '>=', $fromDate->toDateString())
            ->whereDate($dateColumn, '<=', $toDate->toDateString());

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        // Default is posted. If "all" or empty is passed, do not filter.
        if (in_array($status, ['draft', 'posted', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        return $query;
    }

    protected function computeTotals($bills): array
    {
        $totals = [
            'taxable'    => 0,
            'cgst'       => 0,
            'sgst'       => 0,
            'igst'       => 0,
            'rcm_cgst'   => 0,
            'rcm_sgst'   => 0,
            'rcm_igst'   => 0,
            'invoice'    => 0,
            'tcs_amount' => 0,
            'tds_amount' => 0,
            'net_payable'=> 0,
        ];

        foreach ($bills as $bill) {
            $taxable   = (string) ($bill->getRawOriginal('total_basic') ?? $bill->total_basic ?? '0');
            $cgst      = (string) ($bill->getRawOriginal('total_cgst') ?? $bill->total_cgst ?? '0');
            $sgst      = (string) ($bill->getRawOriginal('total_sgst') ?? $bill->total_sgst ?? '0');
            $igst      = (string) ($bill->getRawOriginal('total_igst') ?? $bill->total_igst ?? '0');
            $rcmCgst   = (string) ($bill->getRawOriginal('total_rcm_cgst') ?? $bill->total_rcm_cgst ?? '0');
            $rcmSgst   = (string) ($bill->getRawOriginal('total_rcm_sgst') ?? $bill->total_rcm_sgst ?? '0');
            $rcmIgst   = (string) ($bill->getRawOriginal('total_rcm_igst') ?? $bill->total_rcm_igst ?? '0');
            $invoice   = (string) ($bill->getRawOriginal('total_amount') ?? $bill->total_amount ?? '0');
            $tcsAmount = (string) ($bill->getRawOriginal('tcs_amount') ?? $bill->tcs_amount ?? '0');
            $tdsAmount = (string) ($bill->getRawOriginal('tds_amount') ?? $bill->tds_amount ?? '0');

            $totals['taxable']    += MoneyHelper::toPaise($taxable);
            $totals['cgst']       += MoneyHelper::toPaise($cgst);
            $totals['sgst']       += MoneyHelper::toPaise($sgst);
            $totals['igst']       += MoneyHelper::toPaise($igst);
            $totals['rcm_cgst']   += MoneyHelper::toPaise($rcmCgst);
            $totals['rcm_sgst']   += MoneyHelper::toPaise($rcmSgst);
            $totals['rcm_igst']   += MoneyHelper::toPaise($rcmIgst);
            $totals['invoice']    += MoneyHelper::toPaise($invoice);
            $totals['tcs_amount'] += MoneyHelper::toPaise($tcsAmount);
            $totals['tds_amount'] += MoneyHelper::toPaise($tdsAmount);
        }

        $totals['net_payable'] = ($totals['invoice'] + ($totals['tcs_amount'] ?? 0)) - $totals['tds_amount'];

        return $totals;
    }
}
