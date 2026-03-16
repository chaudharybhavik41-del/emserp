<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingYearEndCloseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingYearEndCloseController extends Controller
{
    public function __construct(
        protected AccountingYearEndCloseService $yearEndCloseService
    ) {
        $this->middleware('permission:accounting.reports.view')->only(['index']);
    }

    public function index()
    {
        $companyId = (int) Config::get('accounting.default_company_id', 1);
        $data = $this->yearEndCloseService->build($companyId);

        return view('accounting.reports.year_end_close', $data);
    }

    public function exportCarryForward(Request $request): StreamedResponse
    {
        $companyId = (int) Config::get('accounting.default_company_id', 1);
        $includePartyLedgers = $request->boolean('include_party_ledgers');
        $includeZeroBalances = $request->boolean('include_zero_balances');
        $rows = $this->yearEndCloseService->buildCarryForwardRows($companyId, $includePartyLedgers, $includeZeroBalances);
        $previousFy = app(\App\Services\Accounting\AccountingFinancialYearService::class)->previousRange();

        $fileName = 'opening_balance_carry_forward_' . $previousFy['label']
            . ($includePartyLedgers ? '_with_parties' : '_non_party')
            . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['account_code', 'opening_balance', 'dr_cr', 'opening_date']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['account_code'],
                    $row['opening_balance'],
                    $row['dr_cr'],
                    $row['opening_date'],
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
