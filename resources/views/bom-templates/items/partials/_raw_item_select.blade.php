@php
    /**
     * Raw material master items grouped as:
     *   Category › Subcategory
     *
     * This creates a clear hierarchy inside the Select2 dropdown.
     *
     * @var \Illuminate\Support\Collection|\App\Models\Item[] $rawItems
     * @var \App\Models\BomTemplateItem $item
     */
    $selectedItemId = old('item_id', $item->item_id);

    $grouped = $rawItems
        ->groupBy(function ($r) {
            $cat = $r->category?->name ?? 'Uncategorized';
            $sub = $r->subcategory?->name ?? 'Other';
            return $cat . ' › ' . $sub;
        })
        ->sortKeys();
@endphp

<select name="item_id" id="item_id" class="form-select raw-item-select">
    <option value="">-- Select Item --</option>

    @foreach($grouped as $label => $items)
        <optgroup label="{{ $label }}">
            @foreach($items->sortBy('name') as $raw)
                <option value="{{ $raw->id }}" @selected((string)$selectedItemId === (string)$raw->id)>
                    {{ $raw->code }} - {{ $raw->name }}
                </option>
            @endforeach
        </optgroup>
    @endforeach
</select>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Select2 is loaded globally in layouts.erp
        if (window.jQuery && typeof jQuery.fn.select2 !== 'undefined') {
            jQuery('.raw-item-select').select2({
                width: '100%',
                placeholder: '-- Select Item --',
                allowClear: true
            });
        }
    });
</script>
@endpush

