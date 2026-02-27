@extends('layouts.erp')

@section('title', 'Store Stock Items')

@section('content')

<div class="card mb-3">
    <div class="card-body">
        <form id="filter-form" class="row g-2">

            <div class="col-md-3">
                <label class="form-label">Project</label>
                <select name="project_id" class="form-select form-select-sm">
                    <option value="">-- All --</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">
                            {{ $project->code }} - {{ $project->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Item</label>
                <select name="item_id" class="form-select form-select-sm">
                    <option value="">-- All --</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}">
                            {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select name="material_category" class="form-select form-select-sm">
                    <option value="">-- All --</option>
                    @foreach($materialCategories as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- All --</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="is_client_material" class="form-select form-select-sm">
                    <option value="">-- All --</option>
                    <option value="0">Own</option>
                    <option value="1">Client</option>
                </select>
            </div>

            <div class="col-md-2 mt-2">
                <label class="form-label">Grade</label>
                <input type="text" name="grade" class="form-control form-control-sm">
            </div>

        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div id="stock-table">
            @include('store.store_stock_items.partials.table')
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('filter-form');
    const tableDiv = document.getElementById('stock-table');
    let timer = null;

    function loadData() {
        const params = new URLSearchParams(new FormData(form)).toString();

        fetch("{{ route('store-stock-items.index') }}?" + params, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.text())
        .then(html => {
            tableDiv.innerHTML = html;
        });
    }

    // Select change
    form.querySelectorAll('select').forEach(el => {
        el.addEventListener('change', loadData);
    });

    // Typing delay
    form.querySelectorAll('input').forEach(el => {
        el.addEventListener('keyup', function () {
            clearTimeout(timer);
            timer = setTimeout(loadData, 400);
        });
    });

});
</script>
@endpush