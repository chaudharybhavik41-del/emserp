@php
    // Helper: current value for filter, using validated $filters first
    $valOf = function(string $name, $default = null) use ($filters) {
        if (array_key_exists($name, $filters) && $filters[$name] !== null) return $filters[$name];
        return request()->query($name, $default);
    };
@endphp

<form method="GET" action="{{ route('reports-hub.show', $report->key()) }}" class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            @foreach($filterDefs as $f)
                @php
                    $name = $f['name'];
                    $type = $f['type'] ?? 'text';
                    $col  = (int) ($f['col'] ?? 3);
                    $label = $f['label'] ?? ucfirst($name);
                    $placeholder = $f['placeholder'] ?? null;
                    $default = $f['default'] ?? null;
                    $value = old($name, $valOf($name, $default));
                @endphp

                <div class="col-12 col-md-{{ $col }}">
                    <label class="form-label small text-muted mb-1" for="f_{{ $name }}">{{ $label }}</label>

                    @if($type === 'select')
                        <select id="f_{{ $name }}"
                                name="{{ $name }}"
                                class="form-select form-select-sm select2">
                            <option value="">{{ $placeholder ?? 'All' }}</option>
                            @foreach(($f['options'] ?? []) as $opt)
                                @php
                                    $ov = (string) ($opt['value'] ?? '');
                                    $ol = (string) ($opt['label'] ?? $ov);
                                @endphp
                                <option value="{{ $ov }}" @selected((string)$value === $ov)>{{ $ol }}</option>
                            @endforeach
                        </select>
                    @elseif($type === 'boolean')
                        <select id="f_{{ $name }}"
                                name="{{ $name }}"
                                class="form-select form-select-sm">
                            <option value="">{{ $placeholder ?? 'Any' }}</option>
                            <option value="1" @selected((string)$value === '1')>Yes</option>
                            <option value="0" @selected((string)$value === '0')>No</option>
                        </select>
                    @elseif($type === 'date')
                        <input id="f_{{ $name }}"
                               type="date"
                               name="{{ $name }}"
                               value="{{ $value }}"
                               class="form-control form-control-sm">
                    @elseif($type === 'number')
                        <input id="f_{{ $name }}"
                               type="number"
                               step="any"
                               name="{{ $name }}"
                               value="{{ $value }}"
                               class="form-control form-control-sm"
                               placeholder="{{ $placeholder }}">
                    @else
                        <input id="f_{{ $name }}"
                               type="text"
                               name="{{ $name }}"
                               value="{{ $value }}"
                               class="form-control form-control-sm"
                               placeholder="{{ $placeholder }}">
                    @endif
                </div>
            @endforeach

            <div class="col-12 col-md-2">
                <button class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-funnel"></i> Apply
                </button>
            </div>
            <div class="col-12 col-md-2">
                <a class="btn btn-sm btn-outline-secondary w-100"
                   href="{{ route('reports-hub.show', $report->key()) }}">
                    Reset
                </a>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && jQuery().select2) {
        $('.select2').select2({ width: '100%' });
    }
});
</script>
@endpush
