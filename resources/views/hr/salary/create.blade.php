@extends('layouts.erp')

@section('title', 'Create Salary Structure')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">Create Salary Structure</h4>
    <form method="POST" action="{{ route('hr.salary.store') }}">
        @csrf
        <div class="card"><div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Code</label><input name="code" class="form-control" required></div>
                <div class="col-md-8"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="col-md-4"><label class="form-label">Effective From</label><input type="date" name="effective_from" class="form-control" value="{{ now()->toDateString() }}" required></div>
            </div>
            <hr>
            <h6>Components</h6>
            <div class="row g-2">
                @foreach($components as $component)
                    <div class="col-md-6">
                        <div class="border rounded p-2">
                            <div class="form-check mb-2"><input class="form-check-input component-check" type="checkbox" value="{{ $component->id }}" id="cmp_{{ $component->id }}"><label class="form-check-label" for="cmp_{{ $component->id }}">{{ $component->name }}</label></div>
                            <div class="row g-2">
                                <div class="col-6"><select class="form-select form-select-sm calc-type" data-id="{{ $component->id }}"><option value="fixed">Fixed</option><option value="percent_of_basic">% of Basic</option><option value="percent_of_gross">% of Gross</option></select></div>
                                <div class="col-6"><input type="number" class="form-control form-control-sm calc-value" data-id="{{ $component->id }}" min="0" step="0.01" value="0"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div id="componentInputs"></div>
            <div class="mt-3"><button class="btn btn-primary">Create</button> <a href="{{ route('hr.salary.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </div></div>
    </form>
</div>

@push('scripts')
<script>
document.querySelector('form').addEventListener('submit', function () {
    const container = document.getElementById('componentInputs');
    container.innerHTML = '';
    let i = 0;
    document.querySelectorAll('.component-check:checked').forEach(function (check) {
        const id = check.value;
        const type = document.querySelector('.calc-type[data-id="' + id + '"]').value;
        const val = document.querySelector('.calc-value[data-id="' + id + '"]').value;
        container.insertAdjacentHTML('beforeend', '<input type="hidden" name="components[' + i + '][id]" value="' + id + '">');
        container.insertAdjacentHTML('beforeend', '<input type="hidden" name="components[' + i + '][calculation_type]" value="' + type + '">');
        container.insertAdjacentHTML('beforeend', '<input type="hidden" name="components[' + i + '][calculation_value]" value="' + val + '">');
        container.insertAdjacentHTML('beforeend', '<input type="hidden" name="components[' + i + '][is_active]" value="1">');
        i++;
    });
});
</script>
@endpush
@endsection
