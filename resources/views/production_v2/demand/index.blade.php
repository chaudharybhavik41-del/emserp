@extends('layouts.erp')

@section('title', 'Production V2 Demand Balance')

@section('page_header')
    <div>
        <h1 class="h4 mb-1">Production V2 Demand Balance</h1>
        <div class="small text-body-secondary">{{ $project->code }} - {{ $project->name }}</div>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Part Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th class="text-end">Required</th>
                            <th class="text-end">Planned Cut</th>
                            <th class="text-end">Cut</th>
                            <th class="text-end">Available WIP</th>
                            <th class="text-end">Fit-up Consumed</th>
                            <th class="text-end">Scrap</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>{{ $row->part_code }}</strong></td>
                            <td>{{ $row->part_name }}</td>
                            <td>{{ $row->part_type }}</td>
                            <td class="text-end">{{ number_format((float) $row->required_qty, 3) }} {{ $row->uom_code }}</td>
                            <td class="text-end">{{ number_format((float) $row->planned_cut_qty, 3) }}</td>
                            <td class="text-end">{{ number_format((float) $row->cut_qty, 3) }}</td>
                            <td class="text-end">{{ number_format((float) $row->available_wip_qty, 3) }}</td>
                            <td class="text-end">{{ number_format((float) $row->fitup_consumed_qty, 3) }}</td>
                            <td class="text-end">{{ number_format((float) $row->scrap_qty, 3) }}</td>
                            <td class="text-end {{ (float) $row->balance_qty > 0 ? 'text-danger' : 'text-success' }}">
                                {{ number_format((float) $row->balance_qty, 3) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">No demand rows available. Start by creating part definitions.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
