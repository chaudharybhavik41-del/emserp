<?php

namespace App\ReportsHub;

use App\ReportsHub\Contracts\Report;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

abstract class BaseTabularReport implements Report
{
    /**
     * Multi-company helper used by many reports.
     */
    protected function companyId(): int
    {
        return auth()->user()->company_id ?? 1;
    }

    /**
     * Wrap an existing listing query for totals aggregation.
     *
     * Why: most totals() implementations aggregate (COUNT/SUM) over the *same*
     * filtered dataset. If we run aggregates directly on the same builder that
     * already has a select list, MySQL with ONLY_FULL_GROUP_BY can throw errors.
     *
     * Solution: treat the listing query as a derived table (subquery) and
     * aggregate on top of it.
     */
    protected function wrapForTotals(EloquentBuilder|QueryBuilder $query): QueryBuilder
    {
        $base = $query instanceof EloquentBuilder ? $query->toBase() : $query;

        // Remove ordering/limits so totals always represent full filtered dataset.
        if (method_exists($base, 'cloneWithout')) {
            $base = $base->cloneWithout(['orders', 'limit', 'offset']);
        } else {
            $base = clone $base;
        }
        if (method_exists($base, 'cloneWithoutBindings')) {
            $base = $base->cloneWithoutBindings(['order']);
        }

        return DB::query()->fromSub($base, 't');
    }

    public function description(): ?string
    {
        return null;
    }

    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array
    {
        return [];
    }

    public function defaultSort(): array
    {
        return ['column' => 'id', 'direction' => 'desc'];
    }

    public function filename(array $filters, string $ext): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($this->key()));
        $date = now()->format('Ymd_His');
        return $safe . '_' . $date . '.' . $ext;
    }

    public function resolveValue(array $column, mixed $row, bool $forExport = false): mixed
    {
        $val = $column['value'] ?? null;

        if ($val instanceof \Closure) {
            $val = $val($row, $forExport);
        } elseif (is_string($val) && $val !== '') {
            $val = data_get($row, $val);
        }

        // Normalize common objects
        if ($val instanceof CarbonInterface) {
            $val = $val->toDateString();
        }

        // Prevent arrays/objects from breaking CSV
        if (is_array($val) || is_object($val)) {
            $val = json_encode($val);
        }

        // CSV likes plain strings
        if ($forExport && $val === null) {
            return '';
        }

        return $val;
    }
}
