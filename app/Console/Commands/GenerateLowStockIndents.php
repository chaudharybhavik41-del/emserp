<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\PurchaseIndent;
use App\Models\PurchaseIndentItem;
use App\Models\StoreReorderLevel;
use App\Services\Store\LowStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateLowStockIndents extends Command
{
    /**
     * Usage:
     *  php artisan purchase:generate-low-stock-indent --department=2 --required_by=2026-01-15
     */
    protected $signature = 'purchase:generate-low-stock-indent
        {--department= : Department ID (required, or config("purchase.low_stock_department_id"))}
        {--required_by= : Required-by date (Y-m-d). Default = today + 7 days}
        {--dry-run : Show what would be created without saving}';

    protected $description = 'Generate draft Purchase Indents from Store Reorder Levels (min/target)';

    public function handle(LowStockService $svc): int
    {
        $departmentId = $this->option('department');
        $departmentId = $departmentId !== null && $departmentId !== '' ? (int) $departmentId : (int) config('purchase.low_stock_department_id', 0);

        if ($departmentId <= 0) {
            $departmentId = (int) (Department::query()->orderBy('id')->value('id') ?? 0);
        }

        if ($departmentId <= 0) {
            $this->error('No department found. Provide --department=<id> or set purchase.low_stock_department_id');
            return self::FAILURE;
        }

        $requiredBy = (string) ($this->option('required_by') ?: '');
        if ($requiredBy === '') {
            $requiredBy = now()->addDays(7)->toDateString();
        }

        $levels = StoreReorderLevel::with('item')
            ->where('is_active', true)
            ->get();

        if ($levels->isEmpty()) {
            $this->info('No active reorder levels found.');
            return self::SUCCESS;
        }

        $availability = $svc->availabilityByLevel($levels);

        // Filter low items and skip those that already have an open MINMAX indent line.
        $candidates = collect();

        foreach ($levels as $level) {
            $avail = (float) ($availability[(int) $level->id] ?? 0);
            $min   = (float) ($level->min_qty ?? 0);
            $target= (float) ($level->target_qty ?? 0);

            if ($avail + 0.0001 >= $min) {
                continue;
            }

            $suggested = max(0.0, $target - $avail);
            if ($suggested <= 0.0001) {
                continue;
            }

            $alreadyOpen = PurchaseIndentItem::query()
                ->where('origin_type', 'MINMAX')
                ->where('origin_id', (int) $level->id)
                ->whereHas('indent', function ($q) {
                    $q->whereIn('status', ['draft', 'submitted', 'approved']);
                })
                ->exists();

            if ($alreadyOpen) {
                continue;
            }

            $candidates->push([
                'level' => $level,
                'available' => $avail,
                'suggested' => $suggested,
            ]);
        }

        if ($candidates->isEmpty()) {
            $this->info('No low-stock candidates found (or already covered by open indents).');
            return self::SUCCESS;
        }

        // Group by project_id (NULL / project)
        $groups = $candidates->groupBy(function ($row) {
            $level = $row['level'];
            return $level->project_id ? (string) ((int) $level->project_id) : 'NULL';
        });

        $this->info('Low-stock groups to create: ' . $groups->count());

        foreach ($groups as $projectKey => $rows) {
            $projectId = $projectKey === 'NULL' ? null : (int) $projectKey;
            $this->line('• Project: ' . ($projectId ?: 'GENERAL') . ' lines=' . count($rows));
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: no indents created.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($groups, $departmentId, $requiredBy) {
            foreach ($groups as $projectKey => $rows) {
                $projectId = $projectKey === 'NULL' ? null : (int) $projectKey;

                $indent = new PurchaseIndent();
                $indent->code             = $this->generateIndentCode();
                $indent->project_id       = $projectId;
                $indent->department_id    = $departmentId;
                $indent->created_by       = null;
                $indent->approved_by      = null;
                $indent->required_by_date = $requiredBy;
                $indent->status           = 'draft';
                $indent->remarks          = 'Auto Low Stock indent (scheduled).';
                $indent->save();

                $lineNo = 1;

                foreach ($rows as $row) {
                    /** @var StoreReorderLevel $level */
                    $level = $row['level'];
                    $item  = $level->item;

                    $piItem = new PurchaseIndentItem();
                    $piItem->purchase_indent_id  = $indent->id;
                    $piItem->line_no             = $lineNo++;
                    $piItem->origin_type         = 'MINMAX';
                    $piItem->origin_id           = (int) $level->id;
                    $piItem->item_id             = (int) $item->id;
                    $piItem->brand               = $level->brand ? trim((string) $level->brand) : null;
                    $piItem->order_qty           = (float) $row['suggested'];
                    $piItem->uom_id              = !empty($item->uom_id) ? (int) $item->uom_id : null;
                    $piItem->grade               = $item->grade ?? null;
                    $piItem->description         = $item->name;
                    $piItem->remarks             = 'Auto low stock';
                    $piItem->save();
                }

                $this->info('Created indent: ' . ($indent->code ?? ('#' . $indent->id)));
            }
        });

        return self::SUCCESS;
    }

    private function generateIndentCode(): string
    {
        $year = date('y');
        $prefix = "IND-{$year}-";

        $lastIndent = PurchaseIndent::where('code', 'LIKE', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();

        $newNumber = $lastIndent ? ((int) substr((string) $lastIndent->code, -4) + 1) : 1;

        return $prefix . str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
    }
}
