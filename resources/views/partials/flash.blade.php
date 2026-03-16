@php
    $flashMap = [
        'success' => ['class' => 'text-bg-success', 'icon' => 'bi-check2-circle', 'title' => 'Saved'],
        'info' => ['class' => 'text-bg-primary', 'icon' => 'bi-info-circle', 'title' => 'Info'],
        'warning' => ['class' => 'text-bg-warning text-dark', 'icon' => 'bi-exclamation-triangle', 'title' => 'Notice'],
        'error' => ['class' => 'text-bg-danger', 'icon' => 'bi-x-octagon', 'title' => 'Error'],
    ];
@endphp

@if(session()->hasAny(array_keys($flashMap)))
    <div class="toast-container position-fixed top-0 end-0 p-3 erp-toast-stack">
        @foreach ($flashMap as $flashKey => $meta)
            @if (session($flashKey))
                <div
                    class="toast erp-toast {{ $meta['class'] }}"
                    role="status"
                    aria-live="polite"
                    aria-atomic="true"
                    data-bs-autohide="{{ $flashKey === 'error' ? 'false' : 'true' }}"
                    data-bs-delay="{{ $flashKey === 'warning' ? '6500' : '4200' }}"
                    data-erp-toast
                >
                    <div class="toast-header border-0 {{ $meta['class'] }}">
                        <i class="bi {{ $meta['icon'] }} me-2"></i>
                        <strong class="me-auto">{{ $meta['title'] }}</strong>
                        <small class="opacity-75">{{ now()->format('H:i') }}</small>
                        <button type="button" class="btn-close ms-2 mb-1" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        {{ session($flashKey) }}
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@endif

@if (isset($errors) && $errors->any())
    <div class="alert alert-danger erp-inline-alert mb-3" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-octagon-fill mt-1"></i>
            <div>
                <div class="fw-semibold mb-1">There were some problems with your input.</div>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
