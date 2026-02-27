<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\TdsCertificate;
use App\Models\Accounting\TdsSection;
use App\Models\PurchaseBill;
use App\Models\SubcontractorRaBill;
use App\Services\Accounting\PartyAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class TdsCertificateReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:accounting.reports.view')->only([
            'index',
            'edit',
            'update',
            'syncPayable',
        ]);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    public function index(Request $request)
    {
        $companyId = (int) $request->input('company_id', $this->defaultCompanyId());

        $direction = (string) $request->input('direction', 'receivable');
        if (! in_array($direction, ['receivable', 'payable'], true)) {
            $direction = 'receivable';
        }

        $fromDate = $request->input('from_date');
        $toDate   = $request->input('to_date');
        $partyId  = $request->input('party_account_id');
        $status   = (string) $request->input('status', 'all'); // all|pending|received

        $query = TdsCertificate::query()
            ->with(['voucher', 'partyAccount'])
            ->where('company_id', $companyId)
            ->where('direction', $direction);

        if ($fromDate || $toDate) {
            $query->whereHas('voucher', function ($q) use ($fromDate, $toDate) {
                if ($fromDate) {
                    $q->whereDate('voucher_date', '>=', $fromDate);
                }
                if ($toDate) {
                    $q->whereDate('voucher_date', '<=', $toDate);
                }
            });
        }

        if ($partyId) {
            $query->where('party_account_id', (int) $partyId);
        }

        if ($status === 'pending') {
            $query->where(function ($q) {
                $q->whereNull('certificate_no')->orWhere('certificate_no', '');
            });
        } elseif ($status === 'received') {
            $query->whereNotNull('certificate_no')->where('certificate_no', '!=', '');
        }

        $totalTds = (clone $query)->sum('tds_amount');

        $rows = $query
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $parties = Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('accounting.reports.tds_certificates', [
            'companyId' => $companyId,
            'direction' => $direction,
            'fromDate'  => $fromDate,
            'toDate'    => $toDate,
            'partyId'   => $partyId,
            'status'    => $status,
            'totalTds'  => $totalTds,
            'rows'      => $rows,
            'parties'   => $parties,
        ]);
    }

    public function edit(TdsCertificate $certificate)
    {
        $companyId = $this->defaultCompanyId();
        if ((int) $certificate->company_id !== (int) $companyId) {
            abort(404);
        }

        $tdsSections = TdsSection::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('accounting.reports.tds_certificate_edit', [
            'companyId'    => $companyId,
            'certificate'  => $certificate->load(['voucher', 'partyAccount']),
            'tdsSections'  => $tdsSections,
        ]);
    }

    public function update(Request $request, TdsCertificate $certificate)
    {
        $companyId = $this->defaultCompanyId();
        if ((int) $certificate->company_id !== (int) $companyId) {
            abort(404);
        }

        $data = $request->validate([
            'tds_section'      => ['nullable', 'string', 'max:20', Rule::exists('tds_sections', 'code')->where(fn ($q) => $q->where('company_id', $companyId)->where('is_active', true))],
            'tds_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tds_amount'       => ['required', 'numeric', 'min:0'],
            'certificate_no'   => ['nullable', 'string', 'max:100'],
            'certificate_date' => ['nullable', 'date'],
            'remarks'          => ['nullable', 'string', 'max:2000'],
        ]);

        // If section selected but rate missing, auto-fill from master.
        if ((! isset($data['tds_rate']) || (float) $data['tds_rate'] <= 0) && ! empty($data['tds_section'])) {
            $sec = TdsSection::where('company_id', $companyId)->where('code', $data['tds_section'])->first();
            if ($sec) {
                $data['tds_rate'] = (float) $sec->default_rate;
            }
        }

        $certificate->fill($data);
        $certificate->updated_by = auth()->id();
        $certificate->save();

        return redirect()
            ->route('accounting.reports.tds-certificates')
            ->with('success', 'TDS certificate updated.');
    }

    /**
     * DEV18: Backfill payable-side certificate rows for already posted documents.
     *
     * Creates/updates rows for:
     * - Purchase Bills (voucher_type = purchase)
     * - Subcontractor RA Bills (voucher_type = subcontractor_ra)
     */
    public function syncPayable(Request $request, PartyAccountService $partyAccountService)
    {
        $companyId = $this->defaultCompanyId();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $purchaseBills = PurchaseBill::query()
            ->with('supplier')
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereNotNull('voucher_id')
            ->where('tds_amount', '>', 0)
            ->get();

        foreach ($purchaseBills as $bill) {
            if (! $bill->voucher_id || ! $bill->supplier) {
                $skipped++;
                continue;
            }

            $partyAccount = $partyAccountService->syncAccountForParty($bill->supplier, $companyId);
            if (! $partyAccount) {
                $skipped++;
                continue;
            }

            $cert = TdsCertificate::firstOrNew([
                'company_id' => $companyId,
                'direction'  => 'payable',
                'voucher_id' => (int) $bill->voucher_id,
            ]);

            $isNew = ! $cert->exists;
            if ($isNew) {
                $cert->created_by = auth()->id();
            }

            $rate = (float) ($bill->tds_rate ?? 0);

            $cert->party_account_id = (int) $partyAccount->id;
            $cert->tds_section      = $bill->tds_section ?: null;
            $cert->tds_rate         = $rate > 0 ? $rate : null;
            $cert->tds_amount       = round((float) ($bill->tds_amount ?? 0), 2);
            $cert->updated_by       = auth()->id();
            $cert->save();

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        $raBills = SubcontractorRaBill::query()
            ->with('subcontractor')
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereNotNull('voucher_id')
            ->where('tds_amount', '>', 0)
            ->get();

        foreach ($raBills as $ra) {
            if (! $ra->voucher_id || ! $ra->subcontractor) {
                $skipped++;
                continue;
            }

            $partyAccount = $partyAccountService->syncAccountForParty($ra->subcontractor, $companyId);
            if (! $partyAccount) {
                $skipped++;
                continue;
            }

            $cert = TdsCertificate::firstOrNew([
                'company_id' => $companyId,
                'direction'  => 'payable',
                'voucher_id' => (int) $ra->voucher_id,
            ]);

            $isNew = ! $cert->exists;
            if ($isNew) {
                $cert->created_by = auth()->id();
            }

            $rate = (float) ($ra->tds_rate ?? 0);

            $cert->party_account_id = (int) $partyAccount->id;
            $cert->tds_section      = $ra->tds_section ?: null;
            $cert->tds_rate         = $rate > 0 ? $rate : null;
            $cert->tds_amount       = round((float) ($ra->tds_amount ?? 0), 2);
            $cert->updated_by       = auth()->id();
            $cert->save();

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        return redirect()
            ->route('accounting.reports.tds-certificates', [
                'company_id' => $companyId,
                'direction'  => 'payable',
            ])
            ->with('success', 'Payable TDS certificates synced. Created: ' . $created . ', Updated: ' . $updated . ', Skipped: ' . $skipped);
    }
}
