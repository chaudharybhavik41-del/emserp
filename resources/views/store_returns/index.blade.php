@extends('layouts.erp')

@section('title', 'Store Returns')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Store Returns</h1>
        @can('store.return.create')
            <a href="{{ route('store-returns.create') }}" class="btn btn-sm btn-primary">
                New Store Return
            </a>
        @endcan
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 12%">Return No</th>
                        <th style="width: 10%">Date</th>
                        <th style="width: 22%">Project</th>
                        <th style="width: 22%">Contractor / Person</th>
                        <th style="width: 12%">Issue Ref</th>
                        <th style="width: 10%">Status</th>
                        <th style="width: 8%"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($returns as $ret)
                        <tr>
                            <td>{{ $ret->return_number }}</td>
                            <td>{{ optional($ret->return_date)->format('d-m-Y') }}</td>
                            <td>
                                @if($ret->project)
                                    {{ $ret->project->code }} - {{ $ret->project->name }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($ret->contractor)
                                    {{ $ret->contractor->name }}
                                    @if($ret->contractor_person_name)
                                        ({{ $ret->contractor_person_name }})
                                    @endif
                                @elseif($ret->contractor_person_name)
                                    {{ $ret->contractor_person_name }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($ret->issue)
                                    {{ $ret->issue->issue_number }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ strtoupper($ret->status) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('store-returns.show', $ret) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">
                                No store returns yet.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($returns->hasPages())
            <div class="card-footer pb-0">
                {{ $returns->links() }}
            </div>
        @endif
    </div>
@endsection
