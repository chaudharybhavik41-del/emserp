@php
    $voucherId = (int) ($voucher->id ?? 0);
    $doc = $voucherId && !empty($docLinks) && isset($docLinks[$voucherId]) ? $docLinks[$voucherId] : null;
@endphp

@if($doc)
    <a href="{{ $doc['url'] }}" class="text-decoration-none ms-2" target="_blank" rel="noopener">
        <span class="badge bg-info text-dark" title="Open linked document">
            {{ $doc['badge'] }}
        </span>
        <span class="text-muted small">{{ $doc['label'] }}</span>
    </a>
@endif
