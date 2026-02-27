<?php

namespace App\ReportsHub\Contracts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

interface Report
{
    /**
     * Unique key used in URL: /reports-hub/{key}
     */
    public function key(): string;

    /**
     * Human readable name shown in hub + headers.
     */
    public function name(): string;

    /**
     * Module group (Purchase, Store, HR, Accounting...)
     */
    public function module(): string;

    /**
     * Optional short description.
     */
    public function description(): ?string;

    /**
     * Filter UI schema for the report screen.
     * The hub renders the filters automatically.
     *
     * Each filter:
     * - name (string)    request key
     * - label (string)
     * - type (string)    date|text|select|boolean|number
     * - options (array)  for select only: [['value'=>..., 'label'=>...], ...]
     * - placeholder (string|null)
     * - col (int)        bootstrap column width (1..12)
     * - default (mixed)  optional
     */
    public function filters(Request $request): array;

    /**
     * Laravel validation rules for GET filters.
     */
    public function rules(): array;

    /**
     * Build a query for the report.
     * IMPORTANT: apply filters inside this method.
     */
    public function query(array $filters): EloquentBuilder|QueryBuilder;

    /**
     * Table columns definition.
     *
     * Each column:
     * - label (string)
     * - value (string|callable)  dot-path for data_get OR fn($row) => ...
     * - align (string|null)      left|right|center (optional)
     * - class (string|null)      extra classes (optional)
     * - width (string|null)      e.g. '90px' or '12%' (optional)
     */
    public function columns(): array;

    /**
     * Optional totals for summary cards / footer.
     */
    public function totals(EloquentBuilder|QueryBuilder $query, array $filters): array;

    /**
     * Default ordering: ['column' => 'created_at', 'direction' => 'desc']
     */
    public function defaultSort(): array;

    /**
     * Export filename without path.
     */
    public function filename(array $filters, string $ext): string;

    /**
     * Resolve a cell value for display/export.
     */
    public function resolveValue(array $column, mixed $row, bool $forExport = false): mixed;
}
