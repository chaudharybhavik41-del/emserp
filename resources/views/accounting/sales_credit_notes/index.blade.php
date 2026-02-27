@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Sales Credit Notes</h4>
        <a href="{{ route('accounting.sales-credit-notes.create') }}" class="btn btn-primary btn-sm">Create Credit Note</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th>Note No</th>
                    <th>Date</th>
                    <th>Client</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($notes as $n)
                    <tr>
                        <td>{{ $n->note_number }}</td>
                        <td>{{ optional($n->note_date)->format('Y-m-d') }}</td>
                        <td>{{ $n->client?->name }}</td>
                        <td class="text-end">{{ number_format((float)($n->total_amount ?? 0),2) }}</td>
                        <td>
                            <span class="badge bg-{{ $n->status==='posted'?'success':($n->status==='cancelled'?'secondary':'warning') }}">{{ strtoupper($n->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('accounting.sales-credit-notes.show', $n) }}" class="btn btn-outline-primary btn-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No credit notes found.</td></tr>
                @endforelse
                </tbody>
            </table>

            {{ $notes->links() }}
        </div>
    </div>
</div>
@endsection
