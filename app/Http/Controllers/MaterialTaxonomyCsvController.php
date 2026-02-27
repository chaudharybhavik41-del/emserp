<?php

namespace App\Http\Controllers;

use App\Models\MaterialType;
use App\Models\MaterialCategory;
use App\Models\MaterialSubcategory;
use App\Models\Item;
use App\Models\Uom;
use App\Models\Accounting\Account;
use Illuminate\Http\Request;

class MaterialTaxonomyCsvController extends Controller
{
    /**
     * Simple caches to avoid hammering the DB for every CSV row.
     */
    protected array $typeCache = [];
    protected array $categoryCache = [];
    protected array $subcategoryCache = [];
    protected array $accountCache = [];
    protected array $uomCache = [];

    public function __construct()
    {
        // View & export: needs view permission
        $this->middleware('permission:core.material_type.view')
            ->only(['index', 'exportTypes', 'exportCategories', 'exportSubcategories']);

        // Import (including universal import): needs create permission
        $this->middleware('permission:core.material_type.create')
            ->only([
                'importTypes',
                'importCategories',
                'importSubcategories',
                'importAllWithItems',
            ]);
    }

    /**
     * Dashboard page with all CSV import/export actions.
     */
    public function index()
    {
        return view('material_taxonomy.csv');
    }

    // =========================================================================
    // MATERIAL TYPES
    // =========================================================================

    public function exportTypes()
    {
        $fileName = 'material_types_' . now()->format('Ymd_His') . '.csv';

        $callback = function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'code',
                'name',
                'description',
                'accounting_usage',
                'sort_order',
                'is_active',
            ]);

            MaterialType::orderBy('sort_order')
                ->orderBy('code')
                ->chunk(500, function ($types) use ($handle) {
                    foreach ($types as $type) {
                        fputcsv($handle, [
                            $type->code,
                            $type->name,
                            $type->description,
                            $type->accounting_usage,
                            $type->sort_order,
                            $type->is_active ? 1 : 0,
                        ]);
                    }
                });

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function importTypes(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return back()->with('error', 'Unable to open uploaded file.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return back()->with('error', 'CSV file is empty.');
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $index  = array_flip($header);

        $rowNumber = 1;
        $created   = 0;
        $updated   = 0;
        $errors    = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $code = $this->csvValue($row, $index, 'code');
            if ($code === null) {
                $errors[] = "Row {$rowNumber}: 'code' is required.";
                continue;
            }

            $code = strtoupper(trim($code));

            try {
                $type = MaterialType::firstOrNew(['code' => $code]);

                $name       = $this->csvValue($row, $index, 'name');
                $type->name = $name !== null ? $name : ($type->name ?? $code);

                $type->description      = $this->csvValue($row, $index, 'description');
                $type->accounting_usage = $this->csvValue($row, $index, 'accounting_usage') ?? $type->accounting_usage;
                $type->sort_order       = (int) ($this->csvValue($row, $index, 'sort_order') ?? $type->sort_order ?? 0);

                $isActiveCsv = $this->csvValue($row, $index, 'is_active');
                if ($isActiveCsv !== null) {
                    $type->is_active = $this->parseBoolean($isActiveCsv, true);
                }

                $wasNew = ! $type->exists;
                $type->save();

                if ($wasNew) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $message = "Material types import completed. Created: {$created}, Updated: {$updated}.";
        if ($errors) {
            $message .= ' Some rows could not be imported.';
        }

        return back()
            ->with('success', $message)
            ->with('import_errors', $errors);
    }

    // =========================================================================
    // MATERIAL CATEGORIES
    // =========================================================================

    public function exportCategories()
    {
        $fileName = 'material_categories_' . now()->format('Ymd_His') . '.csv';

        $callback = function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'type_code',
                'code',
                'name',
                'description',
                'sort_order',
                'is_active',
            ]);

            MaterialCategory::with('type')
                ->orderBy('material_type_id')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->chunk(500, function ($categories) use ($handle) {
                    foreach ($categories as $category) {
                        fputcsv($handle, [
                            optional($category->type)->code,
                            $category->code,
                            $category->name,
                            $category->description,
                            $category->sort_order,
                            $category->is_active ? 1 : 0,
                        ]);
                    }
                });

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function importCategories(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return back()->with('error', 'Unable to open uploaded file.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return back()->with('error', 'CSV file is empty.');
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $index  = array_flip($header);

        $rowNumber = 1;
        $created   = 0;
        $updated   = 0;
        $errors    = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $typeCode = $this->csvValue($row, $index, 'type_code');
            $code     = $this->csvValue($row, $index, 'code');

            if ($typeCode === null || $code === null) {
                $errors[] = "Row {$rowNumber}: 'type_code' and 'code' are required.";
                continue;
            }

            $type = $this->findTypeByCode($typeCode);
            if (! $type) {
                $errors[] = "Row {$rowNumber}: Material type '{$typeCode}' not found.";
                continue;
            }

            $code = strtoupper(trim($code));

            try {
                $category = MaterialCategory::firstOrNew([
                    'material_type_id' => $type->id,
                    'code'             => $code,
                ]);

                $name            = $this->csvValue($row, $index, 'name');
                $category->name  = $name !== null ? $name : ($category->name ?? $code);
                $category->description = $this->csvValue($row, $index, 'description');
                $category->sort_order  = (int) ($this->csvValue($row, $index, 'sort_order') ?? $category->sort_order ?? 0);

                $isActiveCsv = $this->csvValue($row, $index, 'is_active');
                if ($isActiveCsv !== null) {
                    $category->is_active = $this->parseBoolean($isActiveCsv, true);
                }

                $wasNew = ! $category->exists;
                $category->save();

                if ($wasNew) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $message = "Material categories import completed. Created: {$created}, Updated: {$updated}.";
        if ($errors) {
            $message .= ' Some rows could not be imported.';
        }

        return back()
            ->with('success', $message)
            ->with('import_errors', $errors);
    }

    // =========================================================================
    // MATERIAL SUBCATEGORIES
    // =========================================================================

    public function exportSubcategories()
    {
        $fileName = 'material_subcategories_' . now()->format('Ymd_His') . '.csv';

        $callback = function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'type_code',
                'category_code',
                'code',
                'name',
                'description',
                'sort_order',
                'is_active',
            ]);

            MaterialSubcategory::with(['category.type'])
                ->orderBy('material_category_id')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->chunk(500, function ($subcategories) use ($handle) {
                    foreach ($subcategories as $subcat) {
                        $category = $subcat->category;
                        $type     = $category?->type;

                        fputcsv($handle, [
                            $type?->code,
                            $category?->code,
                            $subcat->code,
                            $subcat->name,
                            $subcat->description,
                            $subcat->sort_order,
                            $subcat->is_active ? 1 : 0,
                        ]);
                    }
                });

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function importSubcategories(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return back()->with('error', 'Unable to open uploaded file.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return back()->with('error', 'CSV file is empty.');
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $index  = array_flip($header);

        $rowNumber = 1;
        $created   = 0;
        $updated   = 0;
        $errors    = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $typeCode     = $this->csvValue($row, $index, 'type_code');
            $categoryCode = $this->csvValue($row, $index, 'category_code');
            $code         = $this->csvValue($row, $index, 'code');

            if ($typeCode === null || $categoryCode === null || $code === null) {
                $errors[] = "Row {$rowNumber}: 'type_code', 'category_code' and 'code' are required.";
                continue;
            }

            $type = $this->findTypeByCode($typeCode);
            if (! $type) {
                $errors[] = "Row {$rowNumber}: Material type '{$typeCode}' not found.";
                continue;
            }

            $category = $this->findCategoryByTypeAndCode($type, $categoryCode);
            if (! $category) {
                $errors[] = "Row {$rowNumber}: Category '{$categoryCode}' for type '{$typeCode}' not found.";
                continue;
            }

            $code = strtoupper(trim($code));

            try {
                $subcat = MaterialSubcategory::firstOrNew([
                    'material_category_id' => $category->id,
                    'code'                 => $code,
                ]);

                $name           = $this->csvValue($row, $index, 'name');
                $subcat->name   = $name !== null ? $name : ($subcat->name ?? $code);
                $subcat->description = $this->csvValue($row, $index, 'description');
                $subcat->sort_order  = (int) ($this->csvValue($row, $index, 'sort_order') ?? $subcat->sort_order ?? 0);

                $isActiveCsv = $this->csvValue($row, $index, 'is_active');
                if ($isActiveCsv !== null) {
                    $subcat->is_active = $this->parseBoolean($isActiveCsv, true);
                }

                $wasNew = ! $subcat->exists;
                $subcat->save();

                if ($wasNew) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $message = "Material subcategories import completed. Created: {$created}, Updated: {$updated}.";
        if ($errors) {
            $message .= ' Some rows could not be imported.';
        }

        return back()
            ->with('success', $message)
            ->with('import_errors', $errors);
    }

    // =========================================================================
    // UNIVERSAL IMPORT: TYPE + CATEGORY + SUBCATEGORY + ITEM
    // =========================================================================

    /**
     * Download a simple template for the universal hierarchy + items CSV.
     *
     * Columns (case-insensitive):
     *   type_code, type_name,
     *   category_code, category_name,
     *   subcategory_code, subcategory_name, subcategory_item_prefix,
     *   item_name, item_short_name, item_grade, item_spec,
     *   item_thickness, item_size, item_description,
     *   uom_code, hsn_code, gst_rate_percent, item_is_active
     */
    public function downloadAllTemplate()
    {
        $fileName = 'material_hierarchy_with_items_template.csv';

        $callback = function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'type_code',
                'type_name',
                'category_code',
                'category_name',
                'subcategory_code',
                'subcategory_name',
                'subcategory_item_prefix',
                'item_name',
                'item_short_name',
                'item_grade',
                'item_spec',
                'item_thickness',
                'item_size',
                'item_description',
                'uom_code',
                'hsn_code',
                'gst_rate_percent',
                'item_is_active',
            ]);

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Import one CSV where each row contains:
     *   - type_code
     *   - category_code
     *   - subcategory_code
     *   - item details (+ uom_code + gst etc.)
     *
     * For each row we:
     *   - find/create type
     *   - find/create category
     *   - find/create subcategory
     *   - create item with auto-generated code (Item::generateCodeForTaxonomy)
     */
    public function importAllWithItems(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return back()->with('error', 'Unable to open uploaded file.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return back()->with('error', 'CSV file is empty.');
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $index  = array_flip($header);

        $rowNumber    = 1;
        $itemsCreated = 0;
        $errors       = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $typeCode        = $this->csvValue($row, $index, 'type_code');
            $typeName        = $this->csvValue($row, $index, 'type_name');
            $categoryCode    = $this->csvValue($row, $index, 'category_code');
            $categoryName    = $this->csvValue($row, $index, 'category_name');
            $subcategoryCode = $this->csvValue($row, $index, 'subcategory_code');
            $subcategoryName = $this->csvValue($row, $index, 'subcategory_name');
            $subcategoryPrefix = $this->csvValue($row, $index, 'subcategory_item_prefix');

            $itemName        = $this->csvValue($row, $index, 'item_name');
            $itemShortName   = $this->csvValue($row, $index, 'item_short_name');
            $itemGrade       = $this->csvValue($row, $index, 'item_grade');
            $itemSpec        = $this->csvValue($row, $index, 'item_spec');
            $itemThickness   = $this->csvValue($row, $index, 'item_thickness');
            $itemSize        = $this->csvValue($row, $index, 'item_size');
            $itemDescription = $this->csvValue($row, $index, 'item_description');

            $uomCode         = $this->csvValue($row, $index, 'uom_code');
            $hsnCode         = $this->csvValue($row, $index, 'hsn_code');
            $gstRateCsv      = $this->csvValue($row, $index, 'gst_rate_percent');
            $itemIsActiveCsv = $this->csvValue($row, $index, 'item_is_active');

            // Simple required checks
            if (! $typeCode || ! $categoryCode || ! $subcategoryCode || ! $itemName || ! $uomCode) {
                $errors[] = "Row {$rowNumber}: type_code, category_code, subcategory_code, item_name and uom_code are required.";
                continue;
            }

            try {
                // 1) Type
                $type = $this->getOrCreateTypeByCode($typeCode, $typeName);

                // 2) Category
                $category = $this->getOrCreateCategoryByCode($type, $categoryCode, $categoryName);

                // 3) Subcategory
                $subcategory = $this->getOrCreateSubcategoryByCode(
                    $category,
                    $subcategoryCode,
                    $subcategoryName,
                    $subcategoryPrefix
                );

                // 4) UOM
                $uom = $this->findUomByCode($uomCode);
                if (! $uom) {
                    $errors[] = "Row {$rowNumber}: UOM '{$uomCode}' not found.";
                    continue;
                }

                // 5) Build item data
                $itemData = [
                    'material_type_id'        => $type->id,
                    'material_category_id'    => $category->id,
                    'material_subcategory_id' => $subcategory->id,
                    'uom_id'                  => $uom->id,

                    'name'        => $itemName,
                    'short_name'  => $itemShortName,
                    'grade'       => $itemGrade,
                    'spec'        => $itemSpec,
                    'thickness'   => $itemThickness !== null ? (float) $itemThickness : null,
                    'size'        => $itemSize,
                    'description' => $itemDescription,

                    'hsn_code'         => $hsnCode,
                    'gst_rate_percent' => $gstRateCsv !== null ? (float) $gstRateCsv : null,
                    'is_active'        => $itemIsActiveCsv !== null
                        ? $this->parseBoolean($itemIsActiveCsv, true)
                        : true,
                ];

                // We ignore any item_code given in CSV and always auto-generate
                $itemData['code'] = Item::generateCodeForTaxonomy(
                    $category->id,
                    $subcategory->id
                );

                Item::create($itemData);
                $itemsCreated++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $message = "Universal hierarchy + items import completed. Items created: {$itemsCreated}.";
        if ($errors) {
            $message .= ' Some rows could not be imported.';
        }

        return back()
            ->with('success', $message)
            ->with('import_errors', $errors);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    protected function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    protected function csvValue(array $row, array $index, string $column): ?string
    {
        if (! isset($index[$column])) {
            return null;
        }

        $value = $row[$index[$column]] ?? null;
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function parseBoolean(string $value, ?bool $default = null): ?bool
    {
        $v = strtolower(trim($value));

        if ($v === '') {
            return $default;
        }

        if (in_array($v, ['1', 'true', 'yes', 'y', 'active'], true)) {
            return true;
        }

        if (in_array($v, ['0', 'false', 'no', 'n', 'inactive'], true)) {
            return false;
        }

        return $default;
    }

    protected function normalisePrefix(string $prefix): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($prefix));

        return mb_substr($normalized, 0, 5);
    }

    protected function findTypeByCode(string $code): ?MaterialType
    {
        $key = strtoupper(trim($code));
        if ($key === '') {
            return null;
        }

        if (! array_key_exists($key, $this->typeCache)) {
            $this->typeCache[$key] = MaterialType::where('code', $key)->first();
        }

        return $this->typeCache[$key];
    }

    /**
     * Find or create type for universal import.
     */
    protected function getOrCreateTypeByCode(string $code, ?string $name = null): MaterialType
    {
        $key = strtoupper(trim($code));
        if ($key === '') {
            throw new \InvalidArgumentException('Empty type code.');
        }

        if (isset($this->typeCache[$key]) && $this->typeCache[$key]) {
            $type = $this->typeCache[$key];
        } else {
            $type = MaterialType::firstOrNew(['code' => $key]);
            $this->typeCache[$key] = $type;
        }

        if ($name !== null && $name !== '') {
            $type->name = $name;
        } elseif (! $type->name) {
            $type->name = $key;
        }

        if (! $type->exists || $type->isDirty()) {
            $type->save();
        }

        return $this->typeCache[$key] = $type;
    }

    protected function findCategoryByTypeAndCode(MaterialType $type, string $code): ?MaterialCategory
    {
        $codeKey = strtoupper(trim($code));
        if ($codeKey === '') {
            return null;
        }

        $cacheKey = $type->id . '|' . $codeKey;

        if (! array_key_exists($cacheKey, $this->categoryCache)) {
            $this->categoryCache[$cacheKey] = MaterialCategory::where('material_type_id', $type->id)
                ->where('code', $codeKey)
                ->first();
        }

        return $this->categoryCache[$cacheKey];
    }

    protected function getOrCreateCategoryByCode(MaterialType $type, string $code, ?string $name = null): MaterialCategory
    {
        $codeKey  = strtoupper(trim($code));
        $cacheKey = $type->id . '|' . $codeKey;

        if (isset($this->categoryCache[$cacheKey]) && $this->categoryCache[$cacheKey]) {
            $category = $this->categoryCache[$cacheKey];
        } else {
            $category = MaterialCategory::firstOrNew([
                'material_type_id' => $type->id,
                'code'             => $codeKey,
            ]);
            $this->categoryCache[$cacheKey] = $category;
        }

        if ($name !== null && $name !== '') {
            $category->name = $name;
        } elseif (! $category->name) {
            $category->name = $codeKey;
        }

        if (! $category->exists || $category->isDirty()) {
            $category->save();
        }

        return $this->categoryCache[$cacheKey] = $category;
    }

    protected function getOrCreateSubcategoryByCode(
        MaterialCategory $category,
        string $code,
        ?string $name = null,
        ?string $itemPrefix = null
    ): MaterialSubcategory {
        $codeKey  = strtoupper(trim($code));
        $cacheKey = $category->id . '|' . $codeKey;

        if (isset($this->subcategoryCache[$cacheKey]) && $this->subcategoryCache[$cacheKey]) {
            $subcat = $this->subcategoryCache[$cacheKey];
        } else {
            $subcat = MaterialSubcategory::firstOrNew([
                'material_category_id' => $category->id,
                'code'                 => $codeKey,
            ]);
            $this->subcategoryCache[$cacheKey] = $subcat;
        }

        if ($name !== null && $name !== '') {
            $subcat->name = $name;
        } elseif (! $subcat->name) {
            $subcat->name = $codeKey;
        }

        // Item prefix is optional; only set if column + migration exist
        if ($itemPrefix !== null && $itemPrefix !== '') {
            // If your table has item_code_prefix, uncomment this line:
            // $subcat->item_code_prefix = $this->normalisePrefix($itemPrefix);
        }

        if (! $subcat->exists || $subcat->isDirty()) {
            $subcat->save();
        }

        return $this->subcategoryCache[$cacheKey] = $subcat;
    }

    protected function findAccountByCode(string $code): ?Account
    {
        $key = strtoupper(trim($code));
        if ($key === '') {
            return null;
        }

        if (! array_key_exists($key, $this->accountCache)) {
            $this->accountCache[$key] = Account::where('code', $key)->first();
        }

        return $this->accountCache[$key];
    }

    protected function findUomByCode(string $code): ?Uom
    {
        $key = strtoupper(trim($code));
        if ($key === '') {
            return null;
        }

        if (! array_key_exists($key, $this->uomCache)) {
            $this->uomCache[$key] = Uom::where('code', $key)->first();
        }

        return $this->uomCache[$key];
    }
}
