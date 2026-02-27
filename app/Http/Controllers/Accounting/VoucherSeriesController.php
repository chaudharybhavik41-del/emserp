<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherSeries;
use App\Models\Accounting\VoucherSeriesCounter;
use App\Services\Accounting\VoucherNumberService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class VoucherSeriesController extends Controller
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService
    ) {
        // Using existing permissions to avoid "permission does not exist" errors.
        $this->middleware('permission:accounting.accounts.view')->only(['index']);
        $this->middleware('permission:accounting.accounts.update')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    protected function defaultCompanyId(): int
    {
        return (int) (Config::get('accounting.default_company_id', 1));
    }

    protected function financialYearCode(Carbon $date): string
    {
        $startMonth = (int) Config::get('accounting.financial_year.start_month', 4);
        $fyStartYear = $date->month >= $startMonth ? $date->year : $date->year - 1;
        $fyEndYear = $fyStartYear + 1;

        return sprintf('%d-%02d', $fyStartYear, $fyEndYear % 100);
    }
    public function index(Request $request)
    {
        $companyId = $this->defaultCompanyId();
        $today = now();

        $query = VoucherSeries::where('company_id', $companyId);

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($qq) use ($like) {
                $qq->where('key', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('prefix', 'like', $like);
            });
        }

        $status = $request->get('status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $sort = (string) $request->get('sort', '');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = ['key', 'name', 'prefix', 'use_financial_year', 'is_active'];

        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            // Preserve existing behaviour
            $query->orderBy('key');
        }

        $series = $query->get();

        $rows = $series->map(function (VoucherSeries $s) use ($companyId, $today) {
            $preview = $this->voucherNumberService->preview($s->key, $companyId, $today);

            $fyCode = $s->use_financial_year ? $this->financialYearCode($today) : 'NA';
            $counter = VoucherSeriesCounter::where('voucher_series_id', $s->id)
                ->where('fy_code', $fyCode)
                ->first();

            return [
                'series'      => $s,
                'preview'     => $preview,
                'fy_code'     => $fyCode,
                'next_number' => $counter ? (int) $counter->next_number : null,
            ];
        });

        return view('accounting.voucher_series.index', compact('companyId', 'rows', 'today'));
    }

    public function create()
    {
        $companyId = $this->defaultCompanyId();
        $knownSeries = (array) Config::get('accounting.voucher_series', []);

        return view('accounting.voucher_series.create', compact('companyId', 'knownSeries'));
    }

    public function store(Request $request)
    {
        $companyId = $this->defaultCompanyId();

        $data = $request->validate([
            'key' => [
                'required',
                'string',
                'max:50',
                Rule::unique('voucher_series', 'key')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => ['nullable', 'string', 'max:100'],
            'prefix' => [
                'required',
                'string',
                'max:20',
                Rule::unique('voucher_series', 'prefix')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'use_financial_year' => ['nullable', 'boolean'],
            'separator' => ['required', 'string', 'max:5'],
            'pad_length' => ['required', 'integer', 'min:2', 'max:12'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $series = new VoucherSeries();
        $series->company_id = $companyId;
        $series->key = trim($data['key']);
        $series->name = $data['name'] ?? ucwords(str_replace('_', ' ', $series->key));
        $series->prefix = trim($data['prefix']);
        $series->use_financial_year = (bool) ($data['use_financial_year'] ?? false);
        $series->separator = $data['separator'];
        $series->pad_length = (int) $data['pad_length'];
        $series->is_active = (bool) ($data['is_active'] ?? true);
        $series->save();

        return redirect()
            ->route('accounting.voucher-series.index')
            ->with('success', 'Voucher series created successfully.');
    }

    public function edit(VoucherSeries $voucher_series)
    {
        $companyId = $this->defaultCompanyId();
        abort_unless($voucher_series->company_id === $companyId, 404);

        $knownSeries = (array) Config::get('accounting.voucher_series', []);

        $today = now();
        $fyCodeDefault = $voucher_series->use_financial_year ? $this->financialYearCode($today) : 'NA';

        $counter = VoucherSeriesCounter::where('voucher_series_id', $voucher_series->id)
            ->where('fy_code', $fyCodeDefault)
            ->first();

        $preview = $this->voucherNumberService->preview($voucher_series->key, $companyId, $today);
        $maxUsed = $this->voucherNumberService->maxUsedSequence($companyId, $voucher_series, $fyCodeDefault);

        return view('accounting.voucher_series.edit', [
            'companyId'     => $companyId,
            'voucherSeries' => $voucher_series,
            'knownSeries'   => $knownSeries,
            'fyCodeDefault' => $fyCodeDefault,
            'counter'       => $counter,
            'preview'       => $preview,
            'maxUsed'       => $maxUsed,
        ]);
    }

    public function update(Request $request, VoucherSeries $voucher_series)
    {
        $companyId = $this->defaultCompanyId();
        abort_unless($voucher_series->company_id === $companyId, 404);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'prefix' => [
                'required',
                'string',
                'max:20',
                Rule::unique('voucher_series', 'prefix')
                    ->ignore($voucher_series->id)
                    ->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'use_financial_year' => ['nullable', 'boolean'],
            'separator' => ['required', 'string', 'max:5'],
            'pad_length' => ['required', 'integer', 'min:2', 'max:12'],
            'is_active' => ['nullable', 'boolean'],

            // Optional: manage counter
            'counter_fy_code' => ['nullable', 'string', 'max:20'],
            'next_number' => ['nullable', 'integer', 'min:1'],
        ]);

        // Prevent deleting/renaming series key by UI to avoid breaking services.
        $voucher_series->name = $data['name'] ?? $voucher_series->name;
        $voucher_series->prefix = trim($data['prefix']);
        $voucher_series->use_financial_year = (bool) ($data['use_financial_year'] ?? false);
        $voucher_series->separator = $data['separator'];
        $voucher_series->pad_length = (int) $data['pad_length'];
        $voucher_series->is_active = (bool) ($data['is_active'] ?? false);
        $voucher_series->save();

        // Counter update (optional)
        $fyCode = $voucher_series->use_financial_year
            ? (trim((string) ($data['counter_fy_code'] ?? '')) ?: $this->financialYearCode(now()))
            : 'NA';

        if (! $voucher_series->use_financial_year) {
            $fyCode = 'NA';
        }

        if (array_key_exists('next_number', $data) && $data['next_number'] !== null && $data['next_number'] !== '') {
            $nextNumber = (int) $data['next_number'];

            $maxUsed = $this->voucherNumberService->maxUsedSequence($companyId, $voucher_series, $fyCode);
            if ($nextNumber <= $maxUsed) {
                return back()
                    ->withErrors(['next_number' => 'Next number must be greater than the maximum used sequence (' . $maxUsed . ') for FY ' . $fyCode . '.'])
                    ->withInput();
            }

            VoucherSeriesCounter::updateOrCreate(
                ['voucher_series_id' => $voucher_series->id, 'fy_code' => $fyCode],
                ['next_number' => $nextNumber]
            );
        }

        return redirect()
            ->route('accounting.voucher-series.index')
            ->with('success', 'Voucher series updated successfully.');
    }

    public function destroy(VoucherSeries $voucher_series)
    {
        $companyId = $this->defaultCompanyId();
        abort_unless($voucher_series->company_id === $companyId, 404);

        // Safety: don't allow deletion if vouchers exist for this prefix
        $prefixLike = $voucher_series->prefix . $voucher_series->separator . '%';
        if ($voucher_series->use_financial_year) {
            $prefixLike = $voucher_series->prefix . $voucher_series->separator . '%';
        }

        $hasVouchers = Voucher::where('company_id', $companyId)
            ->where('voucher_no', 'like', $prefixLike)
            ->exists();

        if ($hasVouchers) {
            return back()->with('error', 'Cannot delete this voucher series because vouchers already exist for this prefix.');
        }

        $voucher_series->delete();

        return redirect()
            ->route('accounting.voucher-series.index')
            ->with('success', 'Voucher series deleted successfully.');
    }
}


