<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\VoucherLine;
use App\Support\MoneyHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class GstSummaryReportController extends Controller
{
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

        // Resolve configured GST accounts (by code)
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

        $accounts = [];
        $missing  = [];

        foreach ($codes as $key => $code) {
            $code = trim($code);
            if ($code === '') {
                $missing[] = $key . ' (empty config)';
                $accounts[$key] = null;
                continue;
            }

            $acc = Account::query()
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->first();

            if (! $acc) {
                $missing[] = $code;
                $accounts[$key] = null;
                continue;
            }

            $accounts[$key] = $acc;
        }

        // Netting logic:
        // - Input GST ledgers normally increase by DEBIT, and decrease by CREDIT (returns / reversals)
        //   => Net Input = Debit - Credit
        // - Output GST ledgers normally increase by CREDIT, and decrease by DEBIT (credit notes / reversals)
        //   => Net Output = Credit - Debit

        // Input GST (net)
        $inCgstDr = $this->sumForAccount($companyId, $accounts['input_cgst']?->id,  $fromDate, $toDate, 'debit');
        $inCgstCr = $this->sumForAccount($companyId, $accounts['input_cgst']?->id,  $fromDate, $toDate, 'credit');
        $inputCgst = $inCgstDr - $inCgstCr;

        $inSgstDr = $this->sumForAccount($companyId, $accounts['input_sgst']?->id,  $fromDate, $toDate, 'debit');
        $inSgstCr = $this->sumForAccount($companyId, $accounts['input_sgst']?->id,  $fromDate, $toDate, 'credit');
        $inputSgst = $inSgstDr - $inSgstCr;

        $inIgstDr = $this->sumForAccount($companyId, $accounts['input_igst']?->id,  $fromDate, $toDate, 'debit');
        $inIgstCr = $this->sumForAccount($companyId, $accounts['input_igst']?->id,  $fromDate, $toDate, 'credit');
        $inputIgst = $inIgstDr - $inIgstCr;

        // Output GST (net)
        $outCgstCr = $this->sumForAccount($companyId, $accounts['output_cgst']?->id, $fromDate, $toDate, 'credit');
        $outCgstDr = $this->sumForAccount($companyId, $accounts['output_cgst']?->id, $fromDate, $toDate, 'debit');
        $outputCgst = $outCgstCr - $outCgstDr;

        $outSgstCr = $this->sumForAccount($companyId, $accounts['output_sgst']?->id, $fromDate, $toDate, 'credit');
        $outSgstDr = $this->sumForAccount($companyId, $accounts['output_sgst']?->id, $fromDate, $toDate, 'debit');
        $outputSgst = $outSgstCr - $outSgstDr;

        $outIgstCr = $this->sumForAccount($companyId, $accounts['output_igst']?->id, $fromDate, $toDate, 'credit');
        $outIgstDr = $this->sumForAccount($companyId, $accounts['output_igst']?->id, $fromDate, $toDate, 'debit');
        $outputIgst = $outIgstCr - $outIgstDr;

        $rows = [
            [
                'tax'   => 'CGST',
                'input' => $inputCgst,
                'output'=> $outputCgst,
                'net'   => $outputCgst - $inputCgst,
            ],
            [
                'tax'   => 'SGST',
                'input' => $inputSgst,
                'output'=> $outputSgst,
                'net'   => $outputSgst - $inputSgst,
            ],
            [
                'tax'   => 'IGST',
                'input' => $inputIgst,
                'output'=> $outputIgst,
                'net'   => $outputIgst - $inputIgst,
            ],
        ];

        $totInput  = $inputCgst + $inputSgst + $inputIgst;
        $totOutput = $outputCgst + $outputSgst + $outputIgst;
        $totNet    = $totOutput - $totInput;

        $export = strtolower((string) $request->get('export', ''));

        if ($export === 'csv') {
            $filename = 'gst-summary_' . $fromDate->format('Ymd') . '_to_' . $toDate->format('Ymd') . '.csv';

            $csv = "Tax Type,Input GST (Net Dr-Cr),Output GST (Net Cr-Dr),Net Payable (Output-Input)\n";
            foreach ($rows as $r) {
                $csv .= $r['tax'] . ',' . number_format($r['input'], 2, '.', '') . ',' . number_format($r['output'], 2, '.', '') . ',' . number_format($r['net'], 2, '.', '') . "\n";
            }
            $csv .= 'TOTAL,' . number_format($totInput, 2, '.', '') . ',' . number_format($totOutput, 2, '.', '') . ',' . number_format($totNet, 2, '.', '') . "\n";

            return response($csv)
                ->header('Content-Type', 'text/csv; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        // DEV-11: Excel export (simple HTML table .xls; opens in Excel without extra packages)
        if (in_array($export, ['xls', 'excel'], true)) {
            $filename = 'gst-summary_' . $fromDate->format('Ymd') . '_to_' . $toDate->format('Ymd') . '.xls';

            $html = '<table border="1">'
                . '<thead>'
                . '<tr>'
                . '<th>Tax Type</th>'
                . '<th>Input GST (Net Dr-Cr)</th>'
                . '<th>Output GST (Net Cr-Dr)</th>'
                . '<th>Net Payable (Output-Input)</th>'
                . '</tr>'
                . '</thead>'
                . '<tbody>';

            foreach ($rows as $r) {
                $html .= '<tr>'
                    . '<td>' . e($r['tax']) . '</td>'
                    . '<td style="mso-number-format:\"0.00\";">' . number_format((float) $r['input'], 2, '.', '') . '</td>'
                    . '<td style="mso-number-format:\"0.00\";">' . number_format((float) $r['output'], 2, '.', '') . '</td>'
                    . '<td style="mso-number-format:\"0.00\";">' . number_format((float) $r['net'], 2, '.', '') . '</td>'
                    . '</tr>';
            }

            $html .= '<tr>'
                . '<th>TOTAL</th>'
                . '<th style="mso-number-format:\"0.00\";">' . number_format((float) $totInput, 2, '.', '') . '</th>'
                . '<th style="mso-number-format:\"0.00\";">' . number_format((float) $totOutput, 2, '.', '') . '</th>'
                . '<th style="mso-number-format:\"0.00\";">' . number_format((float) $totNet, 2, '.', '') . '</th>'
                . '</tr>';

            $html .= '</tbody></table>';

            return response($html)
                ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        return view('accounting.reports.gst_summary', [
            'companyId' => $companyId,
            'fromDate'  => $fromDate,
            'toDate'    => $toDate,
            'codes'     => $codes,
            'accounts'  => $accounts,
            'missing'   => $missing,
            'rows'      => $rows,
            'totInput'  => $totInput,
            'totOutput' => $totOutput,
            'totNet'    => $totNet,
        ]);
    }

    protected function sumForAccount(int $companyId, ?int $accountId, Carbon $from, Carbon $to, string $field): float
    {
        if (! $accountId) {
            return 0.0;
        }

        // Note: We sum only posted vouchers.
        // We join vouchers to filter by voucher_date and status.
        $sum = VoucherLine::query()
            ->join('vouchers as v', 'voucher_lines.voucher_id', '=', 'v.id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->whereBetween('v.voucher_date', [$from->toDateString(), $to->toDateString()])
            ->where('voucher_lines.account_id', $accountId)
            ->sum('voucher_lines.' . $field);

        // Normalize using MoneyHelper to avoid float drift.
        $paise = MoneyHelper::toPaise($sum);
        return (float) MoneyHelper::fromPaise($paise);
    }
}
