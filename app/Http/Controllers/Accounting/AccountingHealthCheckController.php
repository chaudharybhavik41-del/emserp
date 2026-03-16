<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingHealthCheckService;
use Illuminate\Support\Facades\Config;

class AccountingHealthCheckController extends Controller
{
    public function __construct(
        protected AccountingHealthCheckService $healthCheckService
    ) {
        $this->middleware('permission:accounting.reports.view')->only(['index']);
    }

    public function index()
    {
        $companyId = (int) Config::get('accounting.default_company_id', 1);
        $data = $this->healthCheckService->build($companyId);

        return view('accounting.reports.health_check', $data);
    }
}
