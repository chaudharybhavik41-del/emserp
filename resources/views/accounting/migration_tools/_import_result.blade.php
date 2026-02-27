@if(session('import_result'))
    @php($r = session('import_result'))

    <div class="alert alert-info">
        <div class="d-flex flex-wrap gap-3">
            <div><strong>Total Rows:</strong> {{ $r['total'] ?? 0 }}</div>
            @if(isset($r['updated']))
                <div><strong>Updated:</strong> {{ $r['updated'] }}</div>
            @endif
            @if(isset($r['created']))
                <div><strong>Created:</strong> {{ $r['created'] }}</div>
            @endif
            <div><strong>Skipped:</strong> {{ $r['skipped'] ?? 0 }}</div>
        </div>
        <div class="small text-muted mt-2">
            Import type: <code>{{ $r['type'] ?? 'N/A' }}</code>
        </div>
    </div>

    @if(!empty($r['warnings']))
        <div class="alert alert-warning">
            <strong>Warnings</strong>
            <ul class="mb-0 mt-2">
                @foreach($r['warnings'] as $w)
                    <li>{{ $w }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($r['errors']))
        <div class="alert alert-danger">
            <strong>Errors</strong>
            <ul class="mb-0 mt-2">
                @foreach($r['errors'] as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif
@endif
