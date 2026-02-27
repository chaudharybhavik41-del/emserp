<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ClientRaBill;
use App\Models\Party;
use App\Models\Accounting\SalesCreditNote;
use App\Support\MoneyHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GstSalesRegisterReportController extends Controller
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

        $clientId = $request->integer('client_id') ?: null;

        $status = (string) $request->string('status', 'posted');
        $status = trim($status);

        // Clients for dropdown
        $clients = Party::query()
            ->where('is_client', true)
            ->orderBy('name')
            ->get();

        // If the Sales/RA tables are not migrated, show a friendly message.
        if (! Schema::hasTable('client_ra_bills')) {
            return view('accounting.reports.gst_sales_register', [
                'companyId' => $companyId,
                'fromDate'  => $fromDate,
                'toDate'    => $toDate,
                'clientId'  => $clientId,
                'status'    => $status,
                'clients'   => $clients,
                'bills'     => collect(),
                'totals'    => [
                    'taxable'     => 0,
                    'cgst'        => 0,
                    'sgst'        => 0,
                    'igst'        => 0,
                    'total_gst'   => 0,
                    'invoice'     => 0,
                    'tds_amount'  => 0,
                    'receivable'  => 0,
                ],
                'missingTable' => true,
            ]);
        }

        $bills  = $this->loadRows($companyId, $fromDate, $toDate, $clientId, $status);
        $totals = $this->computeTotals($bills);

        return view('accounting.reports.gst_sales_register', [
            'companyId'    => $companyId,
            'fromDate'     => $fromDate,
            'toDate'       => $toDate,
            'clientId'     => $clientId,
            'status'       => $status,
            'clients'      => $clients,
            'bills'        => $bills,
            'totals'       => $totals,
            'missingTable' => false,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $companyId = (int) ($request->integer('company_id') ?: $this->defaultCompanyId());

        $fromDate = $request->date('from_date') ?: now()->startOfMonth();
        $toDate   = $request->date('to_date') ?: now();

        $fromDate = Carbon::parse($fromDate)->startOfDay();
        $toDate   = Carbon::parse($toDate)->endOfDay();

        $clientId = $request->integer('client_id') ?: null;
        $status   = trim((string) $request->string('status', 'posted'));

        if (! Schema::hasTable('client_ra_bills')) {
            abort(404, 'client_ra_bills table not found. Run migrations for Sales/RA module first.');
        }

        $bills = $this->loadRows($companyId, $fromDate, $toDate, $clientId, $status);

        $fileName = 'gst_sales_register_' . $fromDate->format('Ymd') . '_' . $toDate->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($bills) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Bill Date',
                'Bill No',
                'Voucher No',
                'Client Name',
                'Client GSTIN',
                'Taxable Value',
                'CGST',
                'SGST',
                'IGST',
                'Total GST',
                'Invoice Total',
                'TDS Amount',
                'Receivable',
                'Status',
            ]);

            foreach ($bills as $bill) {
                $client = $bill->client;

                $taxable    = (string) ($bill->getRawOriginal('net_amount') ?? $bill->net_amount ?? '0');
                $cgst       = (string) ($bill->getRawOriginal('cgst_amount') ?? $bill->cgst_amount ?? '0');
                $sgst       = (string) ($bill->getRawOriginal('sgst_amount') ?? $bill->sgst_amount ?? '0');
                $igst       = (string) ($bill->getRawOriginal('igst_amount') ?? $bill->igst_amount ?? '0');
                $totalGst   = (string) ($bill->getRawOriginal('total_gst') ?? $bill->total_gst ?? '0');
                $invoice    = (string) ($bill->getRawOriginal('total_amount') ?? $bill->total_amount ?? '0');
                $tdsAmount  = (string) ($bill->getRawOriginal('tds_amount') ?? $bill->tds_amount ?? '0');
                $receivable = (string) ($bill->getRawOriginal('receivable_amount') ?? $bill->receivable_amount ?? '0');

                $taxablePaise   = MoneyHelper::toPaise($taxable);
                $cgstPaise      = MoneyHelper::toPaise($cgst);
                $sgstPaise      = MoneyHelper::toPaise($sgst);
                $igstPaise      = MoneyHelper::toPaise($igst);
                $totalGstPaise  = MoneyHelper::toPaise($totalGst);
                $invoicePaise   = MoneyHelper::toPaise($invoice);
                $tdsPaise       = MoneyHelper::toPaise($tdsAmount);
                $receivablePaise= MoneyHelper::toPaise($receivable);

                fputcsv($out, [
                    optional($bill->bill_date)->toDateString(),
                    $bill->bill_number,
                    optional($bill->voucher)->voucher_no,
                    $client?->name,
                    $client?->gstin,
                    MoneyHelper::fromPaise($taxablePaise),
                    MoneyHelper::fromPaise($cgstPaise),
                    MoneyHelper::fromPaise($sgstPaise),
                    MoneyHelper::fromPaise($igstPaise),
                    MoneyHelper::fromPaise($totalGstPaise),
                    MoneyHelper::fromPaise($invoicePaise),
                    MoneyHelper::fromPaise($tdsPaise),
                    MoneyHelper::fromPaise($receivablePaise),
                    $bill->status,
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Load Client RA Bills + Sales Credit Notes as a single collection of
     * ClientRaBill-like models so the existing Blade + totals logic work unchanged.
     */
    protected function loadRows(int $companyId, Carbon $fromDate, Carbon $toDate, ?int $clientId, string $status)
    {
        $bills = $this->buildQuery($companyId, $fromDate, $toDate, $clientId, $status)
            ->orderBy('bill_date')
            ->orderBy('id')
            ->get();

        // If sales credit notes table is missing (feature not deployed), just return RA bills.
        if (! Schema::hasTable('sales_credit_notes')) {
            return $bills;
        }

        $notesQuery = SalesCreditNote::query()
            ->with(['client', 'voucher'])
            ->where('company_id', $companyId)
            ->whereDate('note_date', '>=', $fromDate->toDateString())
            ->whereDate('note_date', '<=', $toDate->toDateString());

        if ($clientId) {
            $notesQuery->where('client_id', $clientId);
        }

        if (in_array($status, ['draft', 'posted', 'cancelled'], true)) {
            $notesQuery->where('status', $status);
        }

        $notes = $notesQuery->get();

        if ($notes->isEmpty()) {
            return $bills;
        }

        $synthetic = $notes->map(function (SalesCreditNote $note) {
            // Create an in-memory ClientRaBill instance with negative values
            // so that notes reduce output GST and revenue.
            $bill = new ClientRaBill();

            $bill->setRawAttributes([
                'id'                => null,
                'company_id'        => $note->company_id,
                'client_id'         => $note->client_id,
                'project_id'        => null,
                'bill_number'       => $note->note_number,
                'net_amount'        => -1 * (float) $note->total_basic,
                'cgst_amount'       => -1 * (float) $note->total_cgst,
                'sgst_amount'       => -1 * (float) $note->total_sgst,
                'igst_amount'       => -1 * (float) $note->total_igst,
                'total_gst'         => -1 * (float) $note->total_tax,
                'tds_amount'        => 0,
                'total_amount'      => -1 * (float) $note->total_amount,
                'receivable_amount' => -1 * (float) $note->total_amount,
                'status'            => $note->status,
            ], true);

            $bill->setAttribute('bill_date', $note->note_date);

            $bill->setRelation('client', $note->client);
            // Project is not available on credit note; keep null so Blade can handle it gracefully.
            $bill->setRelation('project', null);
            $bill->setRelation('voucher', $note->voucher);

            $bill->setAttribute('source_type', 'sales_credit_note');
            $bill->setAttribute('source_note_id', $note->id);

            return $bill;
        });

        $bills->each(function (ClientRaBill $bill) {
            if (! $bill->getAttribute('source_type')) {
                $bill->setAttribute('source_type', 'client_ra_bill');
            }
        });

        return $bills
            ->concat($synthetic)
            ->sortBy(function ($bill) {
                $date = $bill->bill_date;

                if ($date instanceof \DateTimeInterface) {
                    $keyDate = $date->format('Y-m-d');
                } else {
                    $keyDate = (string) $date;
                }

                $id = (int) ($bill->id ?? 0);

                return $keyDate . '_' . str_pad((string) $id, 10, '0', STR_PAD_LEFT);
            })
            ->values();
    }

    protected function buildQuery(int $companyId, Carbon $fromDate, Carbon $toDate, ?int $clientId, string $status)
    {
        $query = ClientRaBill::query()
            ->with(['client', 'project', 'voucher'])
            ->where('company_id', $companyId)
            ->whereDate('bill_date', '>=', $fromDate->toDateString())
            ->whereDate('bill_date', '<=', $toDate->toDateString());

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        if (in_array($status, ['draft', 'posted', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        return $query;
    }

    protected function computeTotals($bills): array
    {
        $totals = [
            'taxable'     => 0,
            'cgst'        => 0,
            'sgst'        => 0,
            'igst'        => 0,
            'total_gst'   => 0,
            'invoice'     => 0,
            'tds_amount'  => 0,
            'receivable'  => 0,
        ];

        foreach ($bills as $bill) {
            $taxable    = (string) ($bill->getRawOriginal('net_amount') ?? $bill->net_amount ?? '0');
            $cgst       = (string) ($bill->getRawOriginal('cgst_amount') ?? $bill->cgst_amount ?? '0');
            $sgst       = (string) ($bill->getRawOriginal('sgst_amount') ?? $bill->sgst_amount ?? '0');
            $igst       = (string) ($bill->getRawOriginal('igst_amount') ?? $bill->igst_amount ?? '0');
            $totalGst   = (string) ($bill->getRawOriginal('total_gst') ?? $bill->total_gst ?? '0');
            $invoice    = (string) ($bill->getRawOriginal('total_amount') ?? $bill->total_amount ?? '0');
            $tdsAmount  = (string) ($bill->getRawOriginal('tds_amount') ?? $bill->tds_amount ?? '0');
            $receivable = (string) ($bill->getRawOriginal('receivable_amount') ?? $bill->receivable_amount ?? '0');

            $totals['taxable']    += MoneyHelper::toPaise($taxable);
            $totals['cgst']       += MoneyHelper::toPaise($cgst);
            $totals['sgst']       += MoneyHelper::toPaise($sgst);
            $totals['igst']       += MoneyHelper::toPaise($igst);
            $totals['total_gst']  += MoneyHelper::toPaise($totalGst);
            $totals['invoice']    += MoneyHelper::toPaise($invoice);
            $totals['tds_amount'] += MoneyHelper::toPaise($tdsAmount);
            $totals['receivable'] += MoneyHelper::toPaise($receivable);
        }

        return $totals;
    }
}
