<?php

namespace App\Services\Machinery;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\Machine;
use App\Services\Accounting\VoucherNumberService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MachineOpeningFaPostingService
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService
    ) {
    }

    /**
     * Preview opening machinery posting totals for the cutover date.
     *
     * @return array{
     *   company_id:int,
     *   cutover_date:string,
     *   asset_count:int,
     *   total_opening_wdv:float,
     *   existing_voucher:\App\Models\Accounting\Voucher|null
     * }
     */
    public function preview(string $cutoverDate, ?int $companyId = null): array
    {
        $companyId = $companyId ?: (int) Config::get('accounting.default_company_id', 1);
        $cutoverDate = Carbon::parse($cutoverDate)->toDateString();

        $base = $this->eligibleOpeningMachinesQuery($cutoverDate);
        $assetCount = (int) (clone $base)->count();
        $totalOpeningWdv = round((float) (clone $base)->sum('opening_wdv'), 2);

        $existingVoucher = $this->findExistingOpeningVoucher($companyId, $cutoverDate);

        return [
            'company_id' => $companyId,
            'cutover_date' => $cutoverDate,
            'asset_count' => $assetCount,
            'total_opening_wdv' => $totalOpeningWdv,
            'existing_voucher' => $existingVoucher,
        ];
    }

    /**
     * Create and post opening machinery JV once (idempotent).
     *
     * @return array{
     *   created:bool,
     *   voucher:\App\Models\Accounting\Voucher,
     *   asset_count:int,
     *   total_opening_wdv:float,
     *   cutover_date:string
     * }
     */
    public function post(string $cutoverDate, ?int $companyId = null): array
    {
        $companyId = $companyId ?: (int) Config::get('accounting.default_company_id', 1);
        $cutoverDate = Carbon::parse($cutoverDate)->toDateString();

        return DB::transaction(function () use ($companyId, $cutoverDate) {
            $settingKey = 'machinery.opening_fa_voucher_id';

            $exists = DB::table('system_settings')->where('key', $settingKey)->exists();
            if (! $exists) {
                DB::table('system_settings')->insert([
                    'key' => $settingKey,
                    'group' => 'machinery',
                    'value' => null,
                    'type' => 'integer',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $settingRow = DB::table('system_settings')
                ->where('key', $settingKey)
                ->lockForUpdate()
                ->first();

            $existingVoucherId = (int) ($settingRow?->value ?? 0);
            if ($existingVoucherId > 0) {
                $existing = Voucher::find($existingVoucherId);
                if ($existing) {
                    $preview = $this->preview($cutoverDate, $companyId);
                    return [
                        'created' => false,
                        'voucher' => $existing,
                        'asset_count' => (int) $preview['asset_count'],
                        'total_opening_wdv' => (float) $preview['total_opening_wdv'],
                        'cutover_date' => $cutoverDate,
                    ];
                }
            }

            $byReference = Voucher::query()
                ->where('company_id', $companyId)
                ->where('voucher_type', 'journal')
                ->where('reference', $this->openingReference($cutoverDate))
                ->orderByDesc('id')
                ->first();

            if ($byReference) {
                set_setting('machinery.opening_fa_voucher_id', (int) $byReference->id, 'integer', 'machinery');
                set_setting('machinery.opening_fa_cutover_date', $cutoverDate, 'string', 'machinery');

                $preview = $this->preview($cutoverDate, $companyId);
                return [
                    'created' => false,
                    'voucher' => $byReference,
                    'asset_count' => (int) $preview['asset_count'],
                    'total_opening_wdv' => (float) $preview['total_opening_wdv'],
                    'cutover_date' => $cutoverDate,
                ];
            }

            $preview = $this->preview($cutoverDate, $companyId);
            $assetCount = (int) $preview['asset_count'];
            $total = (float) $preview['total_opening_wdv'];

            if ($assetCount <= 0 || $total <= 0) {
                throw new RuntimeException('No opening machinery WDV found for posting.');
            }

            $faAccount = $this->resolveFixedAssetMachineryAccount($companyId);
            $openingAdjAccount = $this->getOrCreateOpeningAdjustmentAccount($companyId);

            $voucherDate = Carbon::parse($cutoverDate);
            $voucherNo = $this->voucherNumberService->next('journal', $companyId, $voucherDate);
            $narration = 'Opening machinery WDV as on ' . $cutoverDate;

            $voucher = Voucher::create([
                'company_id' => $companyId,
                'voucher_no' => $voucherNo,
                'voucher_type' => 'journal',
                'voucher_date' => $cutoverDate,
                'reference' => $this->openingReference($cutoverDate),
                'narration' => $narration,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            VoucherLine::create([
                'voucher_id' => $voucher->id,
                'line_no' => 1,
                'account_id' => $faAccount->id,
                'description' => 'Opening machinery WDV',
                'debit' => round($total, 2),
                'credit' => 0,
            ]);

            VoucherLine::create([
                'voucher_id' => $voucher->id,
                'line_no' => 2,
                'account_id' => $openingAdjAccount->id,
                'description' => 'Opening balance adjustment',
                'debit' => 0,
                'credit' => round($total, 2),
            ]);

            $voucher->status = 'posted';
            $voucher->posted_by = Auth::id();
            $voucher->posted_at = now();
            $voucher->save();

            set_setting('machinery.opening_fa_voucher_id', (int) $voucher->id, 'integer', 'machinery');
            set_setting('machinery.opening_fa_cutover_date', $cutoverDate, 'string', 'machinery');
            set_setting('machinery.opening_fa_posted_total', round($total, 2), 'float', 'machinery');

            return [
                'created' => true,
                'voucher' => $voucher,
                'asset_count' => $assetCount,
                'total_opening_wdv' => round($total, 2),
                'cutover_date' => $cutoverDate,
            ];
        });
    }

    protected function eligibleOpeningMachinesQuery(string $cutoverDate)
    {
        if (! Schema::hasTable('machines') || ! Schema::hasColumn('machines', 'opening_wdv')) {
            return Machine::query()->whereRaw('1 = 0');
        }

        $query = Machine::query()
            ->where(function ($q) {
                $q->whereNull('accounting_treatment')
                    ->orWhere('accounting_treatment', 'fixed_asset');
            })
            ->where('opening_wdv', '>', 0);

        if (Schema::hasColumn('machines', 'opening_date')) {
            $query->where(function ($q) use ($cutoverDate) {
                $q->whereNull('opening_date')
                    ->orWhereDate('opening_date', '<=', $cutoverDate);
            });
        }

        return $query;
    }

    protected function findExistingOpeningVoucher(int $companyId, string $cutoverDate): ?Voucher
    {
        $voucherId = (int) setting('machinery.opening_fa_voucher_id', 0);
        if ($voucherId > 0) {
            $voucher = Voucher::find($voucherId);
            if ($voucher) {
                return $voucher;
            }
        }

        return Voucher::query()
            ->where('company_id', $companyId)
            ->where('voucher_type', 'journal')
            ->where('reference', $this->openingReference($cutoverDate))
            ->orderByDesc('id')
            ->first();
    }

    protected function openingReference(string $cutoverDate): string
    {
        return 'MACH-OPENING-' . $cutoverDate;
    }

    protected function resolveFixedAssetMachineryAccount(int $companyId): Account
    {
        $code = (string) (
            Config::get('accounting.default_accounts.fixed_asset_machinery_code')
            ?: Config::get('accounting.default_accounts.fixed_asset_default_code')
        );

        if (trim($code) === '') {
            throw new RuntimeException('Fixed asset machinery account code is not configured.');
        }

        $account = Account::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        if (! $account) {
            throw new RuntimeException('Fixed asset machinery account not found for code: ' . $code);
        }

        return $account;
    }

    protected function getOrCreateOpeningAdjustmentAccount(int $companyId): Account
    {
        $code = (string) data_get(config('accounting.default_accounts', []), 'opening_balance_adjustment_code', 'OPENING-ADJ');
        $code = trim($code) !== '' ? trim($code) : 'OPENING-ADJ';

        $existing = Account::query()
            ->where('company_id', $companyId)
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
            'company_id' => $companyId,
            'account_group_id' => $group->id,
            'name' => 'Opening Balance Adjustment',
            'code' => $code,
            'type' => 'ledger',
            'opening_balance' => 0,
            'opening_balance_type' => 'dr',
            'opening_balance_date' => null,
            'is_active' => true,
            'is_system' => true,
            'system_key' => 'opening_balance_adjustment',
        ]);
    }
}
