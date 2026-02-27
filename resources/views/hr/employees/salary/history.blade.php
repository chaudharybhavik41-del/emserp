@extends('layouts.erp')

@section('title', 'Salary History')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">Salary History - {{ $employee->full_name }}</h4>
    <div class="card"><div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Effective From</th><th>Effective To</th><th>Monthly Basic</th><th>Monthly Gross</th><th>Monthly Net</th><th>Annual CTC</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($salaryHistory as $salary)
                    <tr>
                        <td>{{ $salary->effective_from?->format('d M Y') }}</td>
                        <td>{{ $salary->effective_to?->format('d M Y') ?: '-' }}</td>
                        <td>₹{{ number_format($salary->monthly_basic, 2) }}</td>
                        <td>₹{{ number_format($salary->monthly_gross, 2) }}</td>
                        <td>₹{{ number_format($salary->monthly_net, 2) }}</td>
                        <td>₹{{ number_format($salary->annual_ctc, 2) }}</td>
                        <td>{!! $salary->is_current ? '<span class="badge bg-success">Current</span>' : '<span class="badge bg-secondary">Old</span>' !!}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">No salary history found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
        @if($salaryHistory->hasPages())<div class="card-footer">{{ $salaryHistory->links() }}</div>@endif
    </div>
</div>
@endsection
