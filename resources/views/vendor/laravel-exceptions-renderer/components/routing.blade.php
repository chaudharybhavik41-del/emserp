{{-- 
    Hotfix: Avoid Blade @forelse compilation bug on this server (produces $__empty_-1).
    Keep this renderer minimal so exceptions never crash the exception page itself.
--}}

@php
    $routing = isset($routing) ? $routing : [];
@endphp

<div class="mb-3">
    <h2 class="h5 mb-2">Routing</h2>

    @if(empty($routing))
        <p class="text-muted small mb-0">No routing data available.</p>
    @else
        <div class="small">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width: 25%;">Key</th>
                    <th>Value</th>
                </tr>
                </thead>
                <tbody>
                @foreach($routing as $key => $value)
                    <tr>
                        <td class="text-muted">{{ $key }}</td>
                        <td>
                            <span class="font-monospace">{{ is_scalar($value) ? $value : json_encode($value) }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
