@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>{{ $asset->asset_code }} - {{ $asset->name }}</h4>
        <div>
            <a href="{{ route('fixed-assets.machinery.index') }}" class="btn btn-light">Back</a>
            @can('fixed_assets.edit')
                <a href="{{ route('fixed-assets.machinery.edit', $asset) }}" class="btn btn-primary">Edit</a>
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Basic Profile</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th>Machine Type</th><td>{{ $asset->machine_type ? ucfirst(str_replace('_', ' ', $asset->machine_type)) : '—' }}</td></tr>
                        <tr><th>Serial No</th><td>{{ $asset->serial_no ?: '—' }}</td></tr>
                        <tr><th>Make/Model</th><td>{{ trim(($asset->make ?? '') . ' ' . ($asset->model ?? '')) ?: '—' }}</td></tr>
                        <tr><th>Project</th><td>{{ $asset->project->name ?? '—' }}</td></tr>
                        <tr><th>Vendor</th><td>{{ $asset->vendor->name ?? '—' }}</td></tr>
                        <tr><th>Status</th><td>{{ ucfirst(str_replace('_', ' ', $asset->status)) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Opening Values</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th>Opening WDV</th><td>{{ number_format((float) $asset->opening_wdv, 2) }}</td></tr>
                        <tr><th>Opening As Of</th><td>{{ optional($asset->opening_as_of)->format('d-m-Y') ?: '—' }}</td></tr>
                        <tr><th>Original Cost</th><td>{{ $asset->original_cost !== null ? number_format((float) $asset->original_cost, 2) : '—' }}</td></tr>
                        <tr><th>Accum. Dep Opening</th><td>{{ $asset->accum_dep_opening !== null ? number_format((float) $asset->accum_dep_opening, 2) : '—' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header">Linked Vouchers</div>
                <div class="card-body table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Type</th><th>Voucher</th><th>Voucher Line</th><th>Linked At</th></tr></thead>
                        <tbody>
                            @forelse($asset->links as $link)
                                <tr>
                                    <td>{{ ucfirst(str_replace('_', ' ', $link->link_type)) }}</td>
                                    <td>{{ $link->voucher->voucher_no ?? '—' }}</td>
                                    <td>{{ $link->voucherLine?->line_no ?? '—' }}</td>
                                    <td>{{ $link->created_at?->format('d-m-Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No voucher links yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
