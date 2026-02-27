<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProjectWipToCogsDraftService
{
    public function __construct(
        protected VoucherNumberService $voucherNumberService
    ) {
    }

    /**
     * Create a *DRAFT* Journal Voucher that transfers project WIP balances to COGS.
     *
     * - Keeps operational workflow clean (project can be completed)
     * - Still gives Accounts team control (draft voucher must be reviewed + posted)
     *
     * @param  bool  $force  If true, create a new draft even if an existing draft is found.
     * @return Voucher|null  Draft voucher, or null when there is nothing to transfer.
     */
    public function createDraftForProject(Project $project, ?Carbon $asOfDate = null, bool $force = false): ?Voucher
    {
        $enabled = (bool) Config::get('accounting.project_close.auto_generate_wip_to_cogs_on_completion', false);
        if (! $enabled) {
            return null;
        }

        $companyId   = (int) Config::get('accounting.default_company_id', 1);
        $seriesKey   = (string) Config::get('accounting.project_close.voucher_series_key', 'journal');
        $asOfDate    = $asOfDate ?: ($project->end_date ? Carbon::parse($project->end_date) : now());
        $businessDay = $asOfDate->copy();

        $projectCode = $project->code ?: ('PROJECT#' . $project->id);
        $reference   = 'WIP2COGS:' . $projectCode;

        // Idempotency: if a draft already exists (and force=false), reuse it.
        if (! $force) {
            $existing = Voucher::query()
                ->where('company_id', $companyId)
                ->where('voucher_type', 'journal')
                ->where('status', 'draft')
                ->where('project_id', $project->id)
                ->where('reference', $reference)
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $map = $this->resolveWipToCogsMap();
        if (empty($map)) {
            throw new RuntimeException('accounting.project_close.wip_to_cogs_map is empty. Please configure WIP → COGS mapping.');
        }

        // Build grouped debit/credit totals
        $debits  = []; // [account_id => amount]
        $credits = []; // [account_id => amount]
        $meta    = []; // For log/debug

        $total = 0.0;

        foreach ($map as $wipCode => $cogsCode) {
            $wipAccount = Account::query()
                ->where('company_id', $companyId)
                ->where('code', $wipCode)
                ->first();

            if (! $wipAccount) {
                throw new RuntimeException('WIP account not found for code: ' . $wipCode);
            }

            $cogsAccount = Account::query()
                ->where('company_id', $companyId)
                ->where('code', $cogsCode)
                ->first();

            if (! $cogsAccount) {
                $autoCreate = (bool) Config::get('accounting.project_close.auto_create_missing_cogs_accounts', false);
                if (! $autoCreate) {
                    throw new RuntimeException('COGS account not found for code: ' . $cogsCode);
                }

                $cogsAccount = $this->autoCreateCogsAccount($companyId, $cogsCode);
            }

            $balance = $this->getProjectAccountBalance($companyId, (int) $project->id, (int) $wipAccount->id, $businessDay);

            // Expect WIP to be DR (positive). If it is CR (negative), better to stop and review.
            if ($balance < -0.0001) {
                throw new RuntimeException('Project has CREDIT WIP balance for ' . $wipCode . ' (' . round($balance, 2) . '). Please review postings/adjustments.');
            }

            if ($balance <= 0.0001) {
                continue;
            }

            $amount = round($balance, 2);

            $debits[$cogsAccount->id]  = ($debits[$cogsAccount->id] ?? 0) + $amount;
            $credits[$wipAccount->id]  = ($credits[$wipAccount->id] ?? 0) + $amount;

            $total += $amount;

            $meta[] = [
                'wip_code'  => $wipCode,
                'cogs_code' => $cogsCode,
                'amount'    => $amount,
            ];
        }

        if ($total <= 0.0001) {
            ActivityLog::logCustom(
                'project_wip_to_cogs_skipped',
                'No WIP balance found to transfer for project ' . $projectCode . ' (no draft created).',
                $project,
                ['as_of' => $businessDay->toDateString()]
            );

            return null;
        }

        return DB::transaction(function () use ($companyId, $seriesKey, $businessDay, $project, $projectCode, $reference, $debits, $credits, $total, $meta) {
            // Resolve project cost center (Project = Cost Center)
            $costCenterId = ProjectCostCenterResolver::resolveId($companyId, (int) $project->id);

            // Create draft voucher (JV)
            $voucher = new Voucher();
            $voucher->company_id   = $companyId;
            $voucher->voucher_no   = $this->voucherNumberService->next($seriesKey, $companyId, $businessDay);
            $voucher->voucher_type = 'journal';
            $voucher->voucher_date = $businessDay->toDateString();
            $voucher->reference    = $reference;
            $voucher->narration    = 'Auto draft WIP → COGS on project completion - ' . $projectCode;
            $voucher->status       = 'draft';
            $voucher->project_id   = $project->id;
            $voucher->cost_center_id = $costCenterId;
            $voucher->amount_base  = round($total, 2);
            $voucher->created_by   = Auth::id();
            $voucher->save();

            $lineNo = 1;

            // Debit lines (COGS)
            foreach ($debits as $accountId => $amount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $accountId,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'WIP → COGS (Debit)',
                    'debit'          => round($amount, 2),
                    'credit'         => 0,
                    'reference_type' => Project::class,
                    'reference_id'   => $project->id,
                ]);
            }

            // Credit lines (WIP)
            foreach ($credits as $accountId => $amount) {
                VoucherLine::create([
                    'voucher_id'     => $voucher->id,
                    'line_no'        => $lineNo++,
                    'account_id'     => $accountId,
                    'cost_center_id' => $costCenterId,
                    'description'    => 'WIP → COGS (Credit)',
                    'debit'          => 0,
                    'credit'         => round($amount, 2),
                    'reference_type' => Project::class,
                    'reference_id'   => $project->id,
                ]);
            }

            ActivityLog::logCustom(
                'project_wip_to_cogs_draft_created',
                'Draft WIP→COGS voucher ' . $voucher->voucher_no . ' created for project ' . $projectCode . '.',
                $voucher,
                [
                    'project_id' => $project->id,
                    'as_of'      => $businessDay->toDateString(),
                    'amount'     => round($total, 2),
                    'lines'      => $meta,
                ]
            );

            return $voucher;
        });
    }

    /**
     * Resolve WIP → COGS map from config and ensure common WIP codes are covered.
     *
     * @return array<string,string>
     */
    protected function resolveWipToCogsMap(): array
    {
        $map = (array) Config::get('accounting.project_close.wip_to_cogs_map', []);

        // Also ensure the configured Store/Subcontractor WIP codes are included (even if user changed codes).
        $storeWipCode = (string) Config::get('accounting.store.project_wip_material_account_code', 'WIP-MATERIAL');
        if (! array_key_exists($storeWipCode, $map) && array_key_exists('WIP-MATERIAL', $map)) {
            $map[$storeWipCode] = $map['WIP-MATERIAL'];
        }

        $subconWipCode = (string) Config::get('accounting.subcontractor.project_wip_account_code', 'WIP-SUBCON');
        if (! array_key_exists($subconWipCode, $map) && array_key_exists('WIP-SUBCON', $map)) {
            $map[$subconWipCode] = $map['WIP-SUBCON'];
        }

        return $map;
    }

    /**
     * Get net movement (DR - CR) for a specific project + account, up to a date.
     */
    protected function getProjectAccountBalance(int $companyId, int $projectId, int $accountId, Carbon $asOfDate): float
    {
        $row = VoucherLine::query()
            ->join('vouchers as v', 'v.id', '=', 'voucher_lines.voucher_id')
            ->where('v.company_id', $companyId)
            ->where('v.status', 'posted')
            ->where('v.project_id', $projectId)
            ->where('voucher_lines.account_id', $accountId)
            ->whereDate('v.voucher_date', '<=', $asOfDate->toDateString())
            ->selectRaw('COALESCE(SUM(voucher_lines.debit),0) as debit_total, COALESCE(SUM(voucher_lines.credit),0) as credit_total')
            ->first();

        $debit  = (float) ($row->debit_total ?? 0);
        $credit = (float) ($row->credit_total ?? 0);

        return $debit - $credit;
    }

    /**
     * Auto-create a missing COGS account (safe, idempotent).
     */
    protected function autoCreateCogsAccount(int $companyId, string $code): Account
    {
        // Find target group
        $groupCode = (string) Config::get('accounting.project_close.cogs_account_group_code', 'DIRECT_EXPENSES');

        $group = AccountGroup::query()
            ->where('company_id', $companyId)
            ->where('code', $groupCode)
            ->first();

        if (! $group) {
            // Fallbacks (in case chart differs)
            $group = AccountGroup::query()
                ->where('company_id', $companyId)
                ->whereIn('code', ['DIRECT_EXPENSES', 'EXPENSES'])
                ->first();
        }

        if (! $group) {
            throw new RuntimeException('AccountGroup not found for creating COGS accounts. Please create DIRECT_EXPENSES group (or configure accounting.project_close.cogs_account_group_code).');
        }

        $name = match ($code) {
            'COGS-MATERIAL' => 'COGS - Material & Consumables',
            'COGS-SUBCON'   => 'COGS - Subcontractor',
            default         => 'COGS - ' . $code,
        };

        return Account::firstOrCreate(
            [
                'company_id' => $companyId,
                'code'       => $code,
            ],
            [
                'account_group_id'     => $group->id,
                'name'                 => $name,
                'type'                 => 'ledger',
                'is_active'            => true,
                'opening_balance'      => 0,
                'opening_balance_type' => 'dr',
            ]
        );
    }
}
